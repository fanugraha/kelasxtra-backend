<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptTopicScore;
use App\Models\Program;
use App\Models\Topic;
use App\Models\TopicMasterySnapshot;
use App\Services\AccessControlService;
use App\Services\RankingService;
use App\Services\StreakService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    private const LEVEL_LABELS = [
        'weak' => 'Perlu Fokus',
        'medium' => 'Sedang Berkembang',
        'strong' => 'Sudah Kuat',
        'insufficient_data' => 'Belum Cukup Data',
    ];

    public function performanceSummary(Request $request)
    {
        $user = $request->user();
        $programId = $request->query('program_id', $user->preferred_program_id);
        $attemptsLimit = (int) $request->query('attempts_limit', 5);

        // context=tryout saja -- attempt dari exam Latihan Topik (context=
        // topic_practice) TIDAK dihitung di sini. Kalau ikut kehitung, user
        // yang cuma pernah latihan topik (belum pernah tryout resmi TWK/
        // TIU/TKP) akan salah dianggap state='ready' padahal belum ada
        // section resmi yang benar-benar dikerjakan. Lihat juga catatan
        // yang sama di buildSections() di bawah -- root cause bug section
        // duplikat yang ditemukan lewat audit mobile app.
        $totalAttempts = ExamAttempt::where('user_id', $user->id)
            ->whereHas('exam', fn ($q) => $q->where('program_id', $programId)->tryout())
            ->count();

        $streak = app(StreakService::class)->currentStreak($user, $programId);
        $ranking = app(RankingService::class)->latestRanking($user, $programId);

        if ($totalAttempts === 0) {
            return response()->json([
                'program' => Program::find($programId)?->only(['id', 'name']),
                'state' => 'no_attempts',
                'sections' => [],
                'top_recommendations' => [],
                'streak' => $streak,
                'ranking' => $ranking,
                'cta' => [
                    'message' => 'Kerjakan tryout pertamamu untuk melihat peta kekuatan & kelemahanmu',
                    'action_link' => "/app/packages?program_id={$programId}",
                ],
            ]);
        }

        $hasFullAccess = app(AccessControlService::class)
            ->hasFullPerformanceAccess($user, $programId);

        $sections = $this->buildSections($user, $programId, $attemptsLimit);
        $recommendations = $this->buildTopRecommendations($sections, $programId);

        $allTopics = collect($sections)->pluck('topics')->flatten(1);
        $totalTopics = $allTopics->count();
        $insufficientTopics = $allTopics->where('level', 'insufficient_data')->count();

        $state = ($totalTopics > 0 && $insufficientTopics / $totalTopics >= 0.6)
            ? 'insufficient_attempts'
            : 'ready';

        if (! $hasFullAccess) {
            $sections = collect($sections)->map(function ($section) {
                $section['topics'] = ['locked' => true];
                return $section;
            })->all();

            $recommendations = [];
        }

        return response()->json([
            'program' => Program::find($programId)?->only(['id', 'name']),
            'state' => $state,
            'sections' => $sections,
            'top_recommendations' => $recommendations,
            'streak' => $streak,
            'ranking' => $ranking,
            'access' => [
                'full' => $hasFullAccess,
                'upgrade_cta' => $hasFullAccess ? null : [
                    'message' => 'Buka analisis lengkap per topik dengan memiliki paket program ini',
                    'action_link' => "/app/packages?program_id={$programId}",
                ],
            ],
        ]);
    }

    /**
     * GET /api/me/topic-mastery-history
     *
     * P2.7 (read path): riwayat mastery MINGGUAN per topik, dari rollup
     * topic_mastery_snapshots (write path sudah ada sejak PR sebelumnya).
     * SENGAJA endpoint terpisah dari topicPerformance()/performanceSummary()
     * di atas, bukan nambahin field ke keduanya -- dua endpoint itu
     * ngasih ranking topik (banyak topik x 1 angka), sementara ini ngasih
     * time-series 1 topik (buat chart "naik dari 40% ke 65% dalam
     * sebulan") -- bentuk datanya beda kelas, gabung bakal bikin payload
     * ranking bengkak buat kasus yang cuma kepake pas user buka drill-down
     * 1 topik.
     *
     * CATATAN SCOPE: cuma consumer siswa (dashboard sendiri). Dashboard
     * orang tua & recommendation engine SENGAJA belum dibuatkan endpoint
     * -- keduanya masih P3.8 (relasi ortu-anak, skema rekomendasi) yang
     * granularitasnya belum diputuskan produk. TopicMasteryService sendiri
     * (dipanggil method ini di bawah lewat query langsung ke model, bukan
     * lewat service) sudah bisa dipanggil kode lain kapan saja tanpa
     * perubahan apapun begitu consumer itu jelas kebutuhannya.
     */
    public function topicMasteryHistory(Request $request)
    {
        $request->validate([
            'topic_id' => ['required', 'integer', 'exists:topics,id'],
            'periods' => ['sometimes', 'integer', 'min:1', 'max:52'],
        ]);

        $user = $request->user();
        $topic = Topic::with('taxonomy')->find($request->query('topic_id'));
        $periodsLimit = (int) $request->query('periods', 12);

        $programId = $topic->taxonomy?->program_id;

        // Sama seperti performanceSummary(): akses penuh (Enrollment atau
        // Subscription yang cover Program ini, lihat AccessControlService)
        // wajib buat lihat riwayat, bukan cuma ringkasan terkini -- kalau
        // tidak, hasil GRATIS di preview exam bisa dipakai buat intip
        // riwayat lengkap tanpa pernah beli apapun.
        $hasFullAccess = $programId
            ? app(AccessControlService::class)->hasFullPerformanceAccess($user, $programId)
            : false;

        if (! $hasFullAccess) {
            return response()->json([
                'topic' => ['id' => $topic->id, 'code' => $topic->code, 'name' => $topic->name],
                'periods' => [],
                'access' => [
                    'full' => false,
                    'upgrade_cta' => [
                        'message' => 'Buka riwayat performa lengkap dengan memiliki paket program ini',
                        'action_link' => $programId ? "/app/packages?program_id={$programId}" : '/app/packages',
                    ],
                ],
            ]);
        }

        $snapshots = TopicMasterySnapshot::where('user_id', $user->id)
            ->where('topic_id', $topic->id)
            ->orderByDesc('period')
            ->limit($periodsLimit)
            ->get()
            ->sortBy('period') // urutan tampil: lama -> baru, pas buat sumbu-x chart
            ->values()
            ->map(fn (TopicMasterySnapshot $snapshot) => [
                'period' => $snapshot->period,
                'correct_count' => $snapshot->correct_count,
                'total_count' => $snapshot->total_count,
                'percentage' => $snapshot->percentage,
                'trend' => $snapshot->trend,
                'computed_at' => $snapshot->computed_at?->toIso8601String(),
            ]);

        return response()->json([
            'topic' => ['id' => $topic->id, 'code' => $topic->code, 'name' => $topic->name],
            'periods' => $snapshots,
            'access' => ['full' => true, 'upgrade_cta' => null],
        ]);
    }

    protected function buildSections($user, int $programId, int $attemptsLimit): array
    {
        // context=tryout saja -- lihat catatan di performanceSummary() soal
        // $totalAttempts. Exam Latihan Topik (context=topic_practice) punya
        // ExamSection sendiri yang di-generate TopicPartGenerator dengan
        // taxonomy_id = topic.taxonomy_id (SAMA dengan taxonomy_id section
        // resmi, mis. TWK) -- kalau attempt-nya ikut ditarik ke sini, jadi
        // muncul "section" tambahan yang topics[]-nya identik/duplikat
        // dengan section resmi (karena sumber topik query-nya sama:
        // Topic::where('taxonomy_id', ...)). Ini juga bikin topik yang
        // sama muncul 2x di top_recommendations (lihat buildTopRecommendations
        // di bawah), kebuang slot rekomendasi buat duplikat topik yang sama.
        $recentAttemptIds = ExamAttempt::where('user_id', $user->id)
            ->whereHas('exam', fn ($q) => $q->where('program_id', $programId)->tryout())
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->limit($attemptsLimit)
            ->pluck('id')
            ->values();

        if ($recentAttemptIds->isEmpty()) {
            return [];
        }

        // Skor section diambil dari GABUNGAN semua attempt dalam window, bukan
        // cuma attempt paling baru -- siswa sering latihan per-section terpisah
        // (mis. TIU saja hari ini, padahal TWK dikerjakan beberapa hari lalu),
        // jadi section yang tidak ada di attempt terbaru tetap perlu tampil
        // kalau masih di dalam window attemptsLimit. Skor tiap section diambil
        // dari attempt TERBARU untuk section itu spesifik (bukan rata-rata).
        $sectionRows = DB::table('exam_attempt_section_scores')
            ->join('exam_attempts', 'exam_attempts.id', '=', 'exam_attempt_section_scores.exam_attempt_id')
            ->join('exam_sections', 'exam_sections.id', '=', 'exam_attempt_section_scores.exam_section_id')
            ->whereIn('exam_attempt_section_scores.exam_attempt_id', $recentAttemptIds)
            ->orderByDesc('exam_attempts.finished_at')
            ->get([
                'exam_sections.id as section_id',
                'exam_sections.code',
                'exam_sections.name',
                'exam_sections.taxonomy_id',
                'exam_sections.min_passing_score',
                'exam_attempt_section_scores.raw_score',
                'exam_attempt_section_scores.passed_threshold',
            ])
            ->unique('code')
            ->values();

        if ($sectionRows->isEmpty()) {
            return [];
        }

        $topicScoresByTopic = ExamAttemptTopicScore::whereIn('exam_attempt_id', $recentAttemptIds)
            ->get()
            ->groupBy('topic_id');

        $latestAttemptId = $recentAttemptIds->first();
        $previousAttemptId = $recentAttemptIds->get(1);

        $sections = [];

        foreach ($sectionRows as $sectionRow) {
            $topicsInSection = Topic::where('taxonomy_id', $sectionRow->taxonomy_id)->get();

            $topics = [];

            foreach ($topicsInSection as $topic) {
                $scores = $topicScoresByTopic->get($topic->id, collect());

                $totalCount = $scores->sum('total_count');
                $correctCount = $scores->sum('correct_count');
                $sampleSize = $totalCount;

                if ($sampleSize < 5) {
                    $topics[] = [
                        'topic_id' => $topic->id,
                        'name' => $topic->name,
                        'level' => 'insufficient_data',
                        'label' => self::LEVEL_LABELS['insufficient_data'],
                        'percentage' => null,
                        'sample_size' => $sampleSize,
                        'priority_score' => null,
                        'trend' => null,
                        'trend_message' => null,
                    ];
                    continue;
                }

                $percentage = round(($correctCount / $totalCount) * 100);

                $level = match (true) {
                    $percentage < 60 => 'weak',
                    $percentage < 80 => 'medium',
                    default => 'strong',
                };

                $questionWeight = $totalCount;
                $scoreGap = max(0, 100 - $percentage);
                $priorityScore = round(($questionWeight * $scoreGap) / 100, 1);

                $trend = null;
                $trendMessage = null;

                if ($latestAttemptId && $previousAttemptId) {
                    $latestScore = $scores->firstWhere('exam_attempt_id', $latestAttemptId);
                    $prevScore = $scores->firstWhere('exam_attempt_id', $previousAttemptId);

                    if ($latestScore && $prevScore && $latestScore->total_count > 0 && $prevScore->total_count > 0) {
                        $latestPct = round(($latestScore->correct_count / $latestScore->total_count) * 100);
                        $prevPct = round(($prevScore->correct_count / $prevScore->total_count) * 100);
                        $diff = $latestPct - $prevPct;

                        $trend = $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'stable');
                        $trendMessage = $diff == 0
                            ? 'Stabil dibanding attempt sebelumnya'
                            : ($diff > 0
                                ? "Naik {$diff}% dari attempt sebelumnya"
                                : 'Turun ' . abs($diff) . '% dari attempt sebelumnya');
                    }
                }

                $topics[] = [
                    'topic_id' => $topic->id,
                    'name' => $topic->name,
                    'level' => $level,
                    'label' => self::LEVEL_LABELS[$level],
                    'percentage' => $percentage,
                    'sample_size' => $sampleSize,
                    'priority_score' => $priorityScore,
                    'trend' => $trend,
                    'trend_message' => $trendMessage,
                ];
            }

            $gapToPass = $sectionRow->min_passing_score !== null
                ? max(0, $sectionRow->min_passing_score - $sectionRow->raw_score)
                : null;

            $sections[] = [
                'section_id' => $sectionRow->section_id,
                'code' => $sectionRow->code,
                'name' => $sectionRow->name,
                'status' => (bool) $sectionRow->passed_threshold ? 'passed' : 'not_passed',
                'current_score' => $sectionRow->raw_score,
                'min_passing_score' => $sectionRow->min_passing_score,
                'gap_to_pass' => $gapToPass,
                'topics' => $topics,
            ];
        }

        return $sections;
    }

    protected function buildTopRecommendations(array $sections, int $programId): array
    {
        $allTopics = collect($sections)->flatMap(function ($section) {
            return collect($section['topics'] ?? [])->map(function ($topic) use ($section) {
                $topic['section_code'] = $section['code'];
                return $topic;
            });
        });

        $candidates = $allTopics
            ->where('level', 'weak')
            ->whereNotNull('priority_score')
            ->sortByDesc('priority_score')
            ->take(3)
            ->values();

        return $candidates->map(function ($topic) use ($programId) {
            $suggestedCount = 10;

            return [
                'topic_id' => $topic['topic_id'],
                'topic_name' => $topic['name'],
                'section_code' => $topic['section_code'],
                'reason' => 'high_weight_low_score',
                'message' => "Kerjakan {$suggestedCount} soal {$topic['name']} — targetkan naik ke level Sedang Berkembang.",
                'suggested_question_count' => $suggestedCount,
                'practice_link' => "/app/packages?program_id={$programId}",
            ];
        })->all();
    }
}
