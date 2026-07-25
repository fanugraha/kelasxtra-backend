<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartExamRequest;
use App\Http\Requests\SubmitAnswerRequest;
use App\Http\Resources\ExamAttemptResource;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptTopicScore;
use App\Models\ExamAttemptSectionScore;
use App\Models\ExamBatch;
use App\Services\AccessControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Nama tabel/kolom mengikuti persis skema di mvp-desain-lms-kelasxtra.md section 4,
 * plus tambahan skema fleksibel multi-section (TWK/TIU/TKP, Reading/Structure/Listening):
 * - exam_attempts: score, correct_count, started_at, finished_at, status,
 *   question_order (json), tab_switch_count
 * - exam_answers: attempt_id, question_id, selected_option_id, essay_answer,
 *   is_correct, needs_manual_grading
 * - question_options: question_id, option_text, points, is_correct
 * - questions.type: 'pg' | 'essay'
 * - exam_questions (pivot): exam_id, question_id, exam_section_id, points
 * - exam_sections: exam_id, code, name, order, scoring_type, points_per_question,
 *   min_passing_score, max_score, duration_minutes, is_locked_after_next
 * - exam_attempt_section_scores: exam_attempt_id, exam_section_id, raw_score,
 *   correct_count, passed_threshold
 *
 * ASUMSI relasi Eloquent (nama method, bukan nama tabel — sesuaikan kalau beda
 * di model asli project):
 * - Exam::questions() -> many-to-many ke Question lewat exam_questions, withPivot(['points', 'exam_section_id'])
 * - Exam::sections() -> hasMany ke ExamSection
 * - Question::options() -> hasMany ke QuestionOption (tabel question_options)
 * - ExamAttempt::answers() -> hasMany ke ExamAnswer (tabel exam_answers, FK attempt_id)
 * - ExamAttempt::sectionScores() -> hasMany ke ExamAttemptSectionScore
 */
class ExamController extends Controller
{
    public function __construct(protected AccessControlService $accessControl)
    {
    }

    /**
     * POST /api/exams/start
     * Body: exam_id (required), exam_batch_id (nullable)
     *
     * Alur (disepakati & tidak berubah dari revisi sebelumnya):
     * 1. AccessControlService::canAttemptExam() -> false berarti tolak
     * 2. Kalau exam_batch_id diisi:
     *    a. Cek now() dalam window batch -> di luar window berarti tolak
     *    b. Cek attempt existing di batch ini -> kalau ada, return attempt itu (resume)
     * 3. Kalau exam_batch_id kosong (latihan soal): selalu boleh bikin attempt baru
     * 4. Randomisasi soal & opsi, simpan ke question_order, buat row exam_attempts baru
     */
    public function start(StartExamRequest $request)
    {
        $user = $request->user();
        $exam = Exam::findOrFail($request->validated('exam_id'));
        $bankId = $request->validated('bank_id');

        if (!$this->accessControl->canAttemptExam($user, $exam)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses untuk mengerjakan exam ini.',
            ], 403);
        }

        // Untuk exam yang merupakan bagian dari rangkaian part (Latihan Fokus),
        // canAttemptExam() di atas cuma mengecek enrollment -- belum mengecek
        // urutan part. canAccessExamPart() menambahkan cek itu; kalau gagal
        // di sini (bukan di atas), artinya penyebabnya SPESIFIK karena belum
        // menyelesaikan part sebelumnya, bukan karena kurang akses paket.
        if (!$this->accessControl->canAccessExamPart($user, $exam)) {
            return response()->json([
                'message' => 'Selesaikan part sebelumnya terlebih dahulu sebelum mengerjakan part ini.',
                'reason' => 'previous_part_incomplete',
            ], 403);
        }

        $batchId = $request->validated('exam_batch_id');
        $batch = null;

        if ($batchId) {
            $batch = ExamBatch::findOrFail($batchId);

            $now = now();
            if ($now->lt($batch->start_at) || $now->gt($batch->end_at)) {
                return response()->json([
                    'message' => 'Try out batch ini belum dibuka atau sudah ditutup.',
                    'batch_start_at' => $batch->start_at,
                    'batch_end_at' => $batch->end_at,
                ], 422);
            }
        }

        // Cek attempt existing untuk kombinasi exam + batch + bank ini. Siswa
        // bisa punya attempt terpisah per bank soal (1 exam bisa jual banyak
        // bank), jadi bank_id ikut jadi kunci pencarian resume.
        // Hanya resume attempt yang BELUM selesai (in_progress). Attempt yang
        // sudah graded/submitted/auto_submitted harus memicu attempt BARU saat
        // siswa klik "Ulangi Ujian" -- sebelumnya query ini tidak filter status,
        // jadi siswa yang mengulang selalu diarahkan ke attempt lama yang sudah
        // selesai (redirect balik oleh frontend karena status != in_progress).
        $existing = ExamAttempt::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->where('exam_batch_id', $batch?->id)
            ->where('bank_id', $bankId)
            ->where('status', 'in_progress')
            ->first();

        if ($existing) {
            return new ExamAttemptResource($existing->load('exam'));
        }

        $attempt = DB::transaction(function () use ($exam, $batch, $user, $bankId) {
            // Soal difilter ke bank yang dipilih siswa saja -- 1 attempt = 1 bank,
            // supaya nilai & jumlah soal (TWK/TIU/TKP) sesuai isi bank itu sendiri,
            // bukan gabungan semua bank yang nempel ke exam ini.
            $questions = $exam->questions()
                ->whereHas('bank', fn ($q) => $q->where('question_banks.id', $bankId))
                ->with('options')
                ->get();

            $questionOrder = [
                'questions' => $questions->pluck('id')->shuffle()->values(),
                // Setiap elemen eksplisit menyertakan question_id, supaya tidak ambigu
                // saat di-encode ke JSON (mapWithKeys sebelumnya rawan tertukar antara
                // JSON object vs array tergantung urutan key).
                'options' => $questions->map(function ($question) {
                    return [
                        'question_id' => $question->id,
                        'option_ids' => $question->options->pluck('id')->shuffle()->values(),
                    ];
                })->values(),
            ];

            $newAttempt = ExamAttempt::create([
                'user_id' => $user->id,
                'exam_id' => $exam->id,
                'exam_batch_id' => $batch?->id,
                'bank_id' => $bankId,
                'question_order' => $questionOrder,
                'started_at' => now(),
                'status' => 'in_progress',
                'tab_switch_count' => 0,
            ]);

            // Mode timer per-section (TOEFL-style): mulai dari section
            // dengan order paling kecil. Mode timer global (CPNS-style)
            // tidak menyentuh kolom ini sama sekali -- tetap null.
            if ($exam->uses_section_timers) {
                $firstSection = $exam->sections()->orderBy('order')->first();
                if ($firstSection) {
                    $newAttempt->update([
                        'current_section_id' => $firstSection->id,
                        'section_started_at' => $newAttempt->started_at,
                    ]);
                }
            }

            return $newAttempt;
        });
        return new ExamAttemptResource($attempt->load('exam.questions.options', 'exam.questions.bank.taxonomy'));
    }

    /**
     * GET /api/exam-attempts/{attempt}
     * Reload soal + sisa waktu, sekaligus cek auto-submit kalau siswa refresh
     * setelah deadline lewat.
     */

    /**
     * Ambil daftar bank soal yang benar-benar dipakai di tiap exam (dari soal
     * yang ter-attach), plus daftar program_id turunannya. Dipakai myExams()
     * dan forPackage() supaya frontend bisa menampilkan pilihan bank sebelum
     * siswa mulai ujian, dan supaya program_id tidak lagi cuma diambil dari
     * kolom bank_id tunggal di tabel exams (yang cuma menunjuk 1 bank,
     * padahal satu exam bisa menjual gabungan banyak bank).
     *
     * @param  \Illuminate\Support\Collection  $exams
     * @return array<int, array{banks: array, program_ids: array}>
     */
    private function resolveAvailableBanks($exams): array
    {
        $bankIdsByExam = [];
        $countsByExam = [];

        foreach ($exams as $exam) {
            $counts = $exam->questions()
                ->select('questions.bank_id')
                ->selectRaw('count(*) as cnt')
                ->groupBy('questions.bank_id')
                ->pluck('cnt', 'bank_id');

            $bankIdsByExam[$exam->id] = $counts->keys()->filter()->values()->all();
            $countsByExam[$exam->id] = $counts;
        }

        $allBankIds = collect($bankIdsByExam)->flatten()->unique()->values();

        $banksById = \App\Models\QuestionBank::whereIn('id', $allBankIds)
            ->get(['id', 'title', 'program_id'])
            ->keyBy('id');

        $result = [];

        foreach ($bankIdsByExam as $examId => $bankIds) {
            $banks = collect($bankIds)->map(fn ($id) => $banksById->get($id))->filter();

            $result[$examId] = [
                'banks' => $banks->map(fn ($bank) => [
                    'id' => $bank->id,
                    'title' => $bank->title,
                    // Jumlah soal khusus bank ini di exam ini -- dipakai forPackage()
                    // untuk memecah 1 exam jadi beberapa card per bank/part.
                    'questions_count' => $countsByExam[$examId][$bank->id] ?? 0,
                ])->values()->all(),
                'program_ids' => $banks->pluck('program_id')->filter()->unique()->values()->all(),
            ];
        }

        return $result;
    }

/**
     * GET /api/my-exams/latest-attempted
     * Exam Latihan Soal (attempt mandiri, exam_batch_id null) yang PALING BARU
     * dikerjakan/dilanjutkan siswa -- dipakai Beranda untuk menentukan exam mana
     * yang ditampilkan di section Leaderboard Mingguan, karena leaderboard
     * mingguan itu basisnya Latihan Soal, bukan Try Out batch.
     * Fallback: kalau siswa belum pernah mengerjakan Latihan Soal sama sekali,
     * balikin exam Latihan Soal pertama yang boleh diakses, supaya section
     * tetap bisa menampilkan leaderboard periode berjalan meski skornya kosong.
     */
    public function latestAttemptedExam(Request $request)
    {
        $user = $request->user();

        $latestAttempt = ExamAttempt::where('user_id', $user->id)
            ->whereNull('exam_batch_id')
            ->orderByDesc('started_at')
            ->first();

        if ($latestAttempt) {
            return response()->json(['exam_id' => $latestAttempt->exam_id]);
        }

        $exams = Exam::get();

        $fallback = $exams
            ->filter(fn (Exam $exam) => $this->accessControl->canAttemptExam($user, $exam))
            ->first();

        return response()->json(['exam_id' => $fallback?->id]);
    }

/**
     * GET /api/my-exams
     * Daftar exam yang boleh diakses siswa — reuse AccessControlService::canAttemptExam()
     * (logic sama persis dengan yang dipakai start(), supaya tidak ada 2 sumber kebenaran
     * soal siapa boleh akses exam apa).
     */
    public function myExams(Request $request)
    {
        $user = $request->user();

        $exams = Exam::withCount('questions')->get();

        $bankInfo = $this->resolveAvailableBanks($exams);

        return $exams
            ->filter(fn (Exam $exam) => $this->accessControl->canAttemptExam($user, $exam))
            ->values()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'duration_minutes' => $exam->duration_minutes,
                'passing_score' => $exam->passing_score,
                'questions_count' => $exam->questions_count,
                'is_free_preview' => $exam->is_free_preview,
                'program_id' => $bankInfo[$exam->id]['program_ids'][0] ?? null,
                'program_ids' => $bankInfo[$exam->id]['program_ids'] ?? [],
                'available_banks' => $bankInfo[$exam->id]['banks'] ?? [],
            ]);
    }

/**
 * GET /api/packages/{package}/exams
 * Daftar exam yang termasuk dalam SATU package spesifik — dipakai halaman
 * "Latihan Soal" per-package (PackageExams.jsx). Sengaja pakai relasi
 * package->exams eksplisit (bukan lewat bank_id), supaya admin bisa
 * memilih exam mana saja yang dijual di package ini, bahkan kalau bank
 * soal itu punya beberapa exam lain yang tidak ikut dijual.
 */
public function forPackage(Request $request, \App\Models\Package $package)
    {
        $user = $request->user();

        $exams = $package->exams()
            ->withCount('questions')
            ->get();

        $bankInfo = $this->resolveAvailableBanks($exams);

        return $exams
            ->filter(fn (Exam $exam) => $this->accessControl->canAttemptExam($user, $exam))
            ->values()
            ->flatMap(function (Exam $exam) use ($bankInfo, $user) {
                $banks = $bankInfo[$exam->id]['banks'] ?? [];

                // Exam lama / data belum rapi yang belum punya bank jelas --
                // tetap tampil sebagai 1 card biasa, jangan hilang dari daftar.
                // Latihan Fokus: exam ini bagian dari rangkaian part per topik.
                // is_locked dihitung terpisah dari filter akses paket di atas --
                // exam TETAP muncul di daftar meski part sebelumnya belum selesai,
                // supaya siswa lihat semua part yang ada (bukan hilang begitu saja),
                // tapi frontend perlu tahu part mana yang masih terkunci.
                $isLocked = !$this->accessControl->canAccessExamPart($user, $exam);

                if (empty($banks)) {
                    return collect([[
                        'exam_id' => $exam->id,
                        'bank_id' => null,
                        'title' => $exam->title,
                        'duration_minutes' => $exam->duration_minutes,
                        'passing_score' => $exam->passing_score,
                        'questions_count' => $exam->questions_count,
                        'is_free_preview' => $exam->is_free_preview,
                        'part_number' => $exam->part_number,
                        'is_locked' => $isLocked,
                    ]]);
                }

                // Satu card per bank/part (mis. Part 10, 11, 12, 13), supaya siswa
                // langsung lihat semua part yang tersedia di package ini -- bukan
                // 1 card gabungan yang menyembunyikan pilihan bank di balik modal.
                return collect($banks)->map(fn ($bank) => [
                    'exam_id' => $exam->id,
                    'bank_id' => $bank['id'],
                    'title' => $bank['title'],
                    'duration_minutes' => $exam->duration_minutes,
                    'passing_score' => $exam->passing_score,
                    'questions_count' => $bank['questions_count'] ?? 0,
                    'is_free_preview' => $exam->is_free_preview,
                    'part_number' => $exam->part_number,
                    'is_locked' => $isLocked,
                ]);
            })
            ->values();
    }

    /**
     * GET /api/exams/{exam}/summary
     * Ringkasan status pengerjaan siswa untuk satu exam — dipakai halaman
     * detail exam sebelum siswa mulai/lanjut mengerjakan (mirip "Persiapan
     * Tryout" + kartu skor pertama/terbaru).
     * Sengaja hanya melihat attempt mandiri (exam_batch_id null); attempt
     * dari try out batch punya leaderboard sendiri dan tidak dicampur di sini.
     */
    /**
     * GET /api/exams/{exam}/banks
     * Daftar bank yang bisa dipilih untuk exam ini -- dipakai halaman
     * pemilihan bank sebelum siswa mulai mengerjakan. StartExamRequest
     * nantinya memvalidasi bank_id yang dikirim siswa terhadap daftar ini.
     */
    public function banks(Request $request, Exam $exam)
    {
        $user = $request->user();

        if (!$exam->is_free_preview && !$this->accessControl->canAttemptExam($user, $exam)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses untuk melihat exam ini.',
            ], 403);
        }

        $bankInfo = $this->resolveAvailableBanks(collect([$exam]));

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
            ],
            'banks' => $bankInfo[$exam->id]['banks'] ?? [],
        ]);
    }

    public function summary(Request $request, Exam $exam)
    {
        $user = $request->user();

        if (!$exam->is_free_preview && !$this->accessControl->canAttemptExam($user, $exam)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses untuk melihat exam ini.',
            ], 403);
        }

        $exam->load('sections');

        $bankId = $request->query('bank_id');

        $attempts = ExamAttempt::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('exam_batch_id')
            ->when($bankId, fn ($q) => $q->where('bank_id', $bankId))
            ->with(['sectionScores', 'bank:id,title'])
            ->orderBy('started_at')
            ->get();

        $inProgress = $attempts->firstWhere('status', 'in_progress');
        $completed = $attempts->reject(fn ($a) => $a->status === 'in_progress')->values();

        $formatAttempt = function (ExamAttempt $attempt) use ($exam) {
            $sections = $exam->sections->map(function ($section) use ($attempt) {
                $result = $attempt->sectionScores->firstWhere('exam_section_id', $section->id);

                return [
                    'code' => $section->code,
                    'name' => $section->name,
                    'raw_score' => $result?->raw_score ?? 0,
                    'min_passing_score' => $section->min_passing_score,
                    'passed_threshold' => $result?->passed_threshold,
                ];
            });

            $passed = $exam->isAttemptPassed($attempt);

            return [
                'attempt_id' => $attempt->id,
                'finished_at' => $attempt->finished_at,
                'score' => $attempt->score,
                'correct_count' => $attempt->correct_count,
                'sections' => $sections->values(),
                'passed' => $passed,
                'bank' => $attempt->bank ? [
                    'id' => $attempt->bank->id,
                    'title' => $attempt->bank->title,
                ] : null,
            ];
        };

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'duration_minutes' => $exam->duration_minutes,
                'passing_score' => $exam->passing_score,
                'is_free_preview' => $exam->is_free_preview,
                'sections' => $exam->sections->map(fn ($s) => [
                    'code' => $s->code,
                    'name' => $s->name,
                    'min_passing_score' => $s->min_passing_score,
                ])->values(),
            ],
            'in_progress_attempt_id' => $inProgress?->id,
            'attempts_count' => $completed->count(),
            'first_attempt' => $completed->isNotEmpty() ? $formatAttempt($completed->first()) : null,
            'latest_attempt' => $completed->isNotEmpty() ? $formatAttempt($completed->last()) : null,
        ]);
    }

    public function show(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeOwnership($request, $attempt);

        $this->autoSubmitIfExpired($attempt);

        return new ExamAttemptResource($attempt->fresh(['exam.questions.options', 'exam.questions.bank.taxonomy', 'exam.sections', 'answers', 'sectionScores']));
    }

    /**
     * GET /api/exams/{exam}/attempts
     * Riwayat SEMUA percobaan siswa untuk exam ini (bukan cuma pertama/terbaru
     * seperti summary()) -- dipakai halaman yang menampilkan daftar "Perolehan
     * Nilai" per percobaan. Setiap attempt disertai breakdown skor per section,
     * supaya tabel Pelajaran/Passing Grade/Nilai bisa dirender langsung tanpa
     * request tambahan.
     */
    public function attempts(Request $request, Exam $exam)
    {
        $user = $request->user();

        if (!$exam->is_free_preview && !$this->accessControl->canAttemptExam($user, $exam)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses untuk melihat exam ini.',
            ], 403);
        }

        $exam->load('sections');

        $bankId = $request->query('bank_id');

        $attempts = ExamAttempt::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('exam_batch_id')
            ->whereIn('status', ['submitted', 'auto_submitted', 'graded'])
            ->when($bankId, fn ($q) => $q->where('bank_id', $bankId))
            ->with(['sectionScores', 'bank:id,title'])
            ->orderBy('started_at')
            ->get();

        $formatAttempt = function (ExamAttempt $attempt, int $index) use ($exam) {
            $sections = $exam->sections->map(function ($section) use ($attempt) {
                $result = $attempt->sectionScores->firstWhere('exam_section_id', $section->id);

                return [
                    'code' => $section->code,
                    'name' => $section->name,
                    'raw_score' => $result?->raw_score ?? 0,
                    'correct_count' => $result?->correct_count ?? 0,
                    'min_passing_score' => $section->min_passing_score,
                    'passed_threshold' => $result?->passed_threshold,
                ];
            });

            $passed = $exam->isAttemptPassed($attempt);

            return [
                'attempt_id' => $attempt->id,
                'attempt_number' => $index + 1,
                'started_at' => $attempt->started_at,
                'finished_at' => $attempt->finished_at,
                'score' => $attempt->score,
                'correct_count' => $attempt->correct_count,
                'sections' => $sections->values(),
                'passed' => $passed,
                'bank' => $attempt->bank ? [
                    'id' => $attempt->bank->id,
                    'title' => $attempt->bank->title,
                ] : null,
            ];
        };

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'passing_score' => $exam->passing_score,
            ],
            'attempts' => $attempts->values()->map($formatAttempt)->values(),
        ]);
    }

    /**
     * GET /api/exam-attempts/{attempt}/review
     * Pembahasan lengkap per soal untuk satu attempt yang sudah selesai:
     * teks soal, semua opsi, opsi yang dipilih siswa, opsi kunci jawaban,
     * status benar/salah, dan teks pembahasan (questions.explanation).
     * Hanya bisa diakses kalau attempt sudah tidak in_progress -- pembahasan
     * tidak boleh bocor selagi siswa masih mengerjakan.
     */
    public function review(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeOwnership($request, $attempt);

        if ($attempt->status === 'in_progress') {
            return response()->json([
                'message' => 'Pembahasan hanya tersedia setelah ujian selesai.',
            ], 422);
        }

        $attempt->load(['exam.questions.options', 'exam.questions.bank.taxonomy', 'exam.questions.topic.taxonomy', 'answers']);

        // Susun ulang soal & opsi sesuai question_order yang tersimpan waktu
        // attempt dimulai -- supaya urutan yang dilihat siswa di halaman review
        // PERSIS SAMA dengan urutan yang dia lihat waktu mengerjakan ujian.
        // Tanpa ini, urutan selalu jatuh ke urutan default relasi (bukan acak),
        // jadi huruf A/B/C/D/E di review bisa merujuk ke jawaban yang beda
        // dari yang sebenarnya dipilih siswa -- walau skor tetap akurat karena
        // pencocokan jawaban pakai selected_option_id, bukan posisi huruf.
        $order = $attempt->question_order ?? [];
        $questionOrderIds = $order['questions'] ?? null;
        $optionOrderByQuestion = collect($order['options'] ?? [])
            ->keyBy('question_id');

        $questionsById = $attempt->exam->questions->keyBy('id');

        // Fallback ke urutan default relasi kalau attempt lama tidak punya
        // question_order tersimpan (mis. dibuat sebelum fitur ini ada).
        $orderedQuestionIds = $questionOrderIds ?? $questionsById->keys()->values();

        $questions = collect($orderedQuestionIds)->map(function ($questionId) use ($questionsById, $attempt, $optionOrderByQuestion) {
            $question = $questionsById->get($questionId);
            if (!$question) {
                return null;
            }

            $answer = $attempt->answers->firstWhere('question_id', $question->id);
            $correctOption = $question->options->firstWhere('is_correct', true);

            $optionsById = $question->options->keyBy('id');
            $optionOrderIds = $optionOrderByQuestion->get($question->id)['option_ids'] ?? null;
            $orderedOptionIds = $optionOrderIds ?? $optionsById->keys()->values();

            $orderedOptions = collect($orderedOptionIds)
                ->map(fn ($optId) => $optionsById->get($optId))
                ->filter()
                ->map(fn ($opt) => [
                    'id' => $opt->id,
                    'option_text' => $opt->option_text,
                    'image_url' => $opt->image_url,
                    'is_correct' => $opt->is_correct,
                ])
                ->values();

            return [
                'question_id' => $question->id,
                'question_text' => $question->question_text,
                'media_type' => $question->media_type,
                'media_url' => $question->media_url,
                'type' => $question->type,
                'topic' => $question->bank->taxonomy?->name,
                'category' => $question->bank->taxonomy ? [
                    'code' => $question->bank->taxonomy->code,
                    'name' => $question->bank->taxonomy->name,
                ] : null,
                'sub_topic' => $question->topic ? [
                    'id' => $question->topic->id,
                    'code' => $question->topic->code,
                    'name' => $question->topic->name,
                ] : null,
                'explanation' => $question->explanation,
                'options' => $orderedOptions,
                'selected_option_id' => $answer?->selected_option_id,
                'essay_answer' => $answer?->essay_answer,
                'correct_option_id' => $correctOption?->id,
                'is_correct' => $answer?->is_correct,
                'needs_manual_grading' => $answer?->needs_manual_grading ?? false,
            ];
        })->filter();

        return response()->json([
            'attempt_id' => $attempt->id,
            'exam_title' => $attempt->exam->title,
            'score' => $attempt->score,
            'correct_count' => $attempt->correct_count,
            'questions' => $questions->values(),
        ]);
    }

    /**
     * GET /api/me/topic-performance?program_id=
     * Rekap performa siswa per sub-topic (Topic model), diagregasi dari
     * SEMUA attempt milik siswa yang login, LINTAS SEMUA EXAM dalam 1
     * program, yang sudah selesai (status != in_progress -- konsisten
     * dengan konvensi di review() di atas). Dipakai buat dashboard
     * "Peta Kekuatan & Kelemahan" di Fase 4.
     *
     * program_id wajib -- endpoint ini scoped ke 1 program (mis. SKD CPNS
     * 2026), bukan lintas program sekaligus, supaya topik dari program
     * yang beda konteks (mis. tryout sekolah vs CPNS) tidak tercampur.
     */
    public function topicPerformance(Request $request)
    {
        $request->validate([
            'program_id' => ['required', 'integer', 'exists:programs,id'],
        ]);

        $user = $request->user();
        $programId = (int) $request->query('program_id');

        $examIds = Exam::where('program_id', $programId)->pluck('id');

        // created_at per attempt dipakai buat nyortir baris skor tiap topik
        // dari yang paling baru, supaya bisa dihitung "performa terkini" (lihat
        // $recentSample di bawah) tanpa query terpisah per topik.
        $attempts = ExamAttempt::where('user_id', $user->id)
            ->whereIn('exam_id', $examIds)
            ->where('status', '!=', 'in_progress')
            ->orderByDesc('created_at')
            ->pluck('created_at', 'id');

        $attemptIds = $attempts->keys();

        // Minimal soal terjawab sebelum sebuah topik dianggap punya data yang
        // cukup untuk ditampilkan persentasenya -- di bawah ini, persentase bisa
        // sangat menyesatkan (mis. 1 dari 2 soal salah = 50%, padahal sample-nya
        // terlalu kecil untuk disimpulkan apa-apa).
        $minSample = 5;

        // Jumlah soal terakhir (dari attempt-attempt paling baru) yang dipakai
        // buat menghitung "performa terkini", dibandingkan sama akumulasi semua
        // attempt, supaya siswa yang sudah membaik/menurun kelihatan trend-nya
        // -- bukan cuma keseret rata-rata dari attempt lama. Disetel sama
        // dengan $minSample (bukan lebih besar) supaya "terkini" murni dari
        // attempt-attempt terbaru tanpa perlu nyomot soal dari attempt lama
        // buat mencapai sample size -- kalau lebih besar dari jumlah soal per
        // attempt, angka "terkini" jadi kecampur data lama dan bedanya
        // terlalu tipis buat kelihatan sebagai trend.
        $recentSample = 5;

        $topicScores = ExamAttemptTopicScore::whereIn('exam_attempt_id', $attemptIds)
            ->with('topic.taxonomy')
            ->get()
            ->filter(fn ($row) => $row->topic !== null)
            ->groupBy('topic_id')
            ->map(function ($rows) use ($minSample, $recentSample, $attempts) {
                $topic = $rows->first()->topic;
                $correct = $rows->sum('correct_count');
                $total = $rows->sum('total_count');
                $hasEnoughData = $total >= $minSample;

                // Urutkan baris topik ini dari attempt paling baru, lalu
                // akumulasi soal sampai mencapai $recentSample (atau habis).
                $sortedRows = $rows->sortByDesc(fn ($row) => $attempts[$row->exam_attempt_id] ?? null);

                $recentCorrect = 0;
                $recentTotal = 0;
                foreach ($sortedRows as $row) {
                    if ($recentTotal >= $recentSample) {
                        break;
                    }
                    $recentCorrect += $row->correct_count;
                    $recentTotal += $row->total_count;
                }

                $recentHasEnoughData = $recentTotal >= $minSample;
                $recentPercentage = $recentHasEnoughData ? round($recentCorrect / $recentTotal * 100, 1) : null;
                $percentage = $hasEnoughData ? round($correct / $total * 100, 1) : null;

                // Trend cuma dihitung kalau dua-duanya (keseluruhan & terkini)
                // punya sample cukup, dan "terkini" bukan cuma seluruh data yang
                // sama persis (attempt-nya sedikit) -- kalau sama, nggak ada
                // perbandingan yang berarti.
                $trend = null;
                if ($hasEnoughData && $recentHasEnoughData && $recentTotal < $total) {
                    $diff = $recentPercentage - $percentage;
                    if ($diff >= 15) {
                        $trend = 'up';
                    } elseif ($diff <= -15) {
                        $trend = 'down';
                    } else {
                        $trend = 'stable';
                    }
                }

                return [
                    'topic_id' => $topic->id,
                    'topic_code' => $topic->code,
                    'topic_name' => $topic->name,
                    'category' => $topic->taxonomy ? [
                        'id' => $topic->taxonomy->id,
                        'code' => $topic->taxonomy->code,
                        'name' => $topic->taxonomy->name,
                    ] : null,
                    'correct_count' => $correct,
                    'total_count' => $total,
                    'has_enough_data' => $hasEnoughData,
                    'percentage' => $percentage,
                    'recent_percentage' => $recentPercentage,
                    'trend' => $trend,
                ];
            })
            // Topik bersample cukup diurutkan dari yang paling lemah dulu; topik
            // yang datanya belum cukup ditaruh di akhir supaya tidak nyempil di
            // antara ranking "terlemah" yang sebenarnya belum valid.
            ->sortBy(fn ($t) => [$t['has_enough_data'] ? 0 : 1, $t['percentage'] ?? 0])
            ->values();

        return response()->json([
            'program_id' => $programId,
            'attempts_included' => $attemptIds->count(),
            'topics' => $topicScores,
        ]);
    }

    /**
     * POST /api/exam-attempts/{attempt}/answer
     * Autosave satu jawaban. Mendukung 2 tipe soal:
     * - pg      -> body: question_id, selected_option_id
     * - essay   -> body: question_id, essay_answer
     * Soal essay otomatis ditandai needs_manual_grading = true, is_correct
     * dibiarkan null sampai tutor menilai.
     */
    public function submitAnswer(SubmitAnswerRequest $request, ExamAttempt $attempt)
    {
        $this->authorizeOwnership($request, $attempt);

        if ($this->autoSubmitIfExpired($attempt)) {
            return response()->json([
                'message' => 'Waktu pengerjaan sudah habis, jawaban tidak bisa diubah lagi.',
            ], 422);
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json(['message' => 'Attempt ini sudah tidak aktif.'], 422);
        }

        $data = $request->validated();

        if ($attempt->exam->uses_section_timers && $attempt->current_section_id) {
            $pivot = $attempt->exam->questions()
                ->where('questions.id', $data['question_id'])
                ->first()?->pivot;

            if ($pivot && (int) $pivot->exam_section_id !== (int) $attempt->current_section_id) {
                return response()->json([
                    'message' => 'Soal ini bukan bagian dari bagian ujian yang sedang aktif.',
                ], 422);
            }
        }

        $isEssay = array_key_exists('essay_answer', $data) && $data['essay_answer'] !== null;

        $attempt->answers()->updateOrCreate(
            ['question_id' => $data['question_id']],
            [
                'selected_option_id' => $isEssay ? null : ($data['selected_option_id'] ?? null),
                'essay_answer' => $isEssay ? $data['essay_answer'] : null,
                'needs_manual_grading' => $isEssay,
                // is_correct sengaja tidak diisi di sini: soal pg dihitung final
                // saat finish()/auto-submit, soal essay diisi tutor via TutorGradingController
            ]
        );

        return response()->json(['message' => 'Jawaban tersimpan.']);
    }

    /**
     * POST /api/exam-attempts/{attempt}/tab-switch
     * Anti-cheat dasar (section 7 & 8 desain MVP): frontend kirim event
     * `visibilitychange`, backend increment counter. Tidak menolak/menghentikan
     * attempt secara otomatis di MVP — cuma dicatat untuk direview admin/tutor.
     */
    public function recordTabSwitch(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeOwnership($request, $attempt);

        if ($attempt->status === 'in_progress') {
            $attempt->increment('tab_switch_count');
        }

        return response()->json(['tab_switch_count' => $attempt->tab_switch_count]);
    }

    /**
     * POST /api/exam-attempts/{attempt}/finish
     * Submit manual oleh siswa.
     */
    public function finish(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeOwnership($request, $attempt);

        if ($this->autoSubmitIfExpired($attempt)) {
            return new ExamAttemptResource($attempt->fresh());
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json(['message' => 'Attempt ini sudah selesai sebelumnya.'], 422);
        }

        $this->gradeAndClose($attempt, 'submitted');

        return new ExamAttemptResource($attempt->fresh());
    }

    /**
     * Cek waktu habis (started_at + exam.duration_minutes), terpisah dari
     * validasi window exam_batch. True kalau baru saja / sudah auto_submitted.
     */
    /**
     * Mode timer per-section (TOEFL-style): cek apakah waktu section yang
     * SEDANG AKTIF sudah habis. Kalau habis, section itu otomatis ditutup
     * (soal yang belum dijawab dianggap kosong/salah -- tidak ada aksi
     * khusus perlu ditulis di sini, karena gradeAndClose() sudah menghitung
     * skor cuma dari jawaban yang tersimpan) dan attempt dimajukan ke
     * section berikutnya sesuai `order`. Di-loop supaya kalau siswa lama
     * tidak membuka halaman, beberapa section yang sudah lewat sekaligus
     * tetap dimajukan satu-satu sampai section aktif ketemu yang valid,
     * atau exam otomatis selesai kalau section terakhir juga sudah habis.
     * Tidak melakukan apa-apa untuk exam bertipe timer global (CPNS-style).
     */
    protected function checkAndAdvanceSection(ExamAttempt $attempt): void
    {
        if (!$attempt->exam->uses_section_timers || $attempt->status !== 'in_progress') {
            return;
        }

        $sections = $attempt->exam->sections()->orderBy('order')->get();
        if ($sections->isEmpty()) {
            return;
        }

        // Attempt lama sebelum fitur ini ada / edge case belum punya section
        // aktif -- set ke section pertama supaya tidak error di bawah.
        if (!$attempt->current_section_id) {
            $attempt->update([
                'current_section_id' => $sections->first()->id,
                'section_started_at' => $attempt->section_started_at ?? now(),
            ]);
        }

        while ($attempt->status === 'in_progress') {
            $currentSection = $sections->firstWhere('id', $attempt->current_section_id);

            if (!$currentSection || !$currentSection->duration_minutes) {
                return; // section tanpa durasi diatur -- anggap tidak dibatasi timer
            }

            $sectionDeadline = $attempt->section_started_at->clone()->addMinutes($currentSection->duration_minutes);

            if (now()->lessThanOrEqualTo($sectionDeadline)) {
                return; // section aktif masih dalam waktu
            }

            $nextSection = $sections->where('order', '>', $currentSection->order)->sortBy('order')->first();

            if ($nextSection) {
                // section_started_at baru dihitung persis dari deadline section
                // sebelumnya (bukan now()), supaya siswa tidak "untung" dapat
                // waktu tambahan gara-gara server sempat delay memprosesnya.
                $attempt->update([
                    'current_section_id' => $nextSection->id,
                    'section_started_at' => $sectionDeadline,
                ]);
            } else {
                // Section terakhir sudah habis waktunya -- seluruh exam selesai.
                $this->gradeAndClose($attempt, 'auto_submitted');

                return;
            }
        }
    }

    protected function autoSubmitIfExpired(ExamAttempt $attempt): bool
    {
        if ($attempt->status !== 'in_progress') {
            return in_array($attempt->status, ['auto_submitted', 'graded']);
        }

        $this->checkAndAdvanceSection($attempt);

        if ($attempt->status !== 'in_progress') {
            return true;
        }

        $deadline = $attempt->started_at->clone()->addMinutes($attempt->exam->duration_minutes);

        if (now()->greaterThan($deadline)) {
            $this->gradeAndClose($attempt, 'auto_submitted');

            return true;
        }

        return false;
    }

    /**
     * Grading otomatis HANYA untuk soal pg. Soal essay dilewati (tetap
     * needs_manual_grading = true) sampai dinilai tutor lewat TutorGradingController.
     * Status attempt jadi 'graded' langsung kalau tidak ada essay yang pending;
     * kalau masih ada essay pending, status tetap 'submitted'/'auto_submitted'
     * sampai semua essay dinilai (lihat TutorGradingController::grade()).
     *
     * Perubahan dari versi sebelumnya: skor soal pg sekarang dihitung dari
     * `points` milik OPSI YANG DIPILIH siswa (question_options.points), bukan
     * dari is_correct + pivot exam_questions.points. Ini mendukung 2 model
     * skor sekaligus tanpa cabang logic terpisah:
     * - single_correct (TWK/TIU): opsi benar points=5, opsi lain 0
     * - weighted_options (TKP): semua opsi punya points sendiri (1-5), tidak
     *   ada "salah" -- selectedOption->points langsung dipakai apa adanya.
     *
     * Hasil juga dikelompokkan per exam_section_id (dari pivot exam_questions)
     * dan disimpan ke exam_attempt_section_scores, supaya bisa dicek per-section
     * pass/fail (lihat Exam::require_all_sections_pass).
     */
    protected function gradeAndClose(ExamAttempt $attempt, string $status): void
    {
        // exam_batch_id null = mode latihan soal (leaderboard mingguan berlaku
        // di sini). Try out batch pakai leaderboard-nya sendiri (LeaderboardController),
        // tidak disentuh oleh trigger ini.
        $isPracticeExam = $attempt->exam_batch_id === null;

        // Logic scoring diekstrak ke ExamScoringService supaya SAMA PERSIS
        // dengan yang dipakai ExamAttempt::recalculateScore() (dipanggil ulang
        // setelah tutor menilai essay) -- sebelumnya dua tempat ini pakai
        // rumus berbeda, lihat komentar di ExamScoringService untuk detail bug-nya.
        $result = app(\App\Services\ExamScoringService::class)->scoreAndPersist($attempt);

        $attempt->update([
            'status' => $result['has_pending_essay'] ? $status : 'graded',
            'score' => $result['score'],
            'correct_count' => $result['correct_count'],
            'finished_at' => now(),
        ]);

        // Leaderboard mingguan latihan soal di-generate ulang INSTAN begitu
        // attempt-nya final (skor sudah pasti, tidak ada essay pending) --
        // bukan nunggu job terjadwal mingguan. Job terjadwal tetap ada sebagai
        // jaring pengaman (menangani exam yang skornya baru final belakangan,
        // misal lewat penilaian essay manual TutorGradingController -- kalau
        // exam latihan soal kamu punya soal essay, tambahkan trigger yang sama
        // di sana supaya leaderboard-nya juga instan, bukan cuma tertutupi job).
        //
        // Kuota voucher per siswa per minggu (lihat PracticeLeaderboardService::
        // assignReward()) otomatis jadi "siapa cepat dia dapat" secara kronologis
        // begitu trigger ini instan -- exam yang diselesaikan siswa lebih dulu
        // yang dapet prioritas voucher, tanpa perlu logic tambahan.
        if ($isPracticeExam && $attempt->fresh()->status === 'graded') {
            app(\App\Services\PracticeLeaderboardService::class)->generateForExam($attempt->exam);
        }
    }

    protected function authorizeOwnership(Request $request, ExamAttempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 403, 'Bukan attempt milik Anda.');
    }
}