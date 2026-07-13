<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartExamRequest;
use App\Http\Requests\SubmitAnswerRequest;
use App\Http\Resources\ExamAttemptResource;
use App\Models\Exam;
use App\Models\ExamAttempt;
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

        if (!$this->accessControl->canAttemptExam($user, $exam)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses untuk mengerjakan exam ini.',
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

            $existing = ExamAttempt::where('user_id', $user->id)
                ->where('exam_id', $exam->id)
                ->where('exam_batch_id', $batch->id)
                ->first();

            if ($existing) {
                return new ExamAttemptResource($existing->load('exam'));
            }
        }

        $attempt = DB::transaction(function () use ($exam, $batch, $user) {
            $questions = $exam->questions()->with('options')->get();

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

            return ExamAttempt::create([
                'user_id' => $user->id,
                'exam_id' => $exam->id,
                'exam_batch_id' => $batch?->id,
                'question_order' => $questionOrder,
                'started_at' => now(),
                'status' => 'in_progress',
                'tab_switch_count' => 0,
            ]);
        });
        return new ExamAttemptResource($attempt->load('exam.questions.options'));
    }

    /**
     * GET /api/exam-attempts/{attempt}
     * Reload soal + sisa waktu, sekaligus cek auto-submit kalau siswa refresh
     * setelah deadline lewat.
     */

    /**
 * GET /api/my-exams
 * Daftar exam yang boleh diakses siswa — reuse AccessControlService::canAttemptExam()
 * (logic sama persis dengan yang dipakai start(), supaya tidak ada 2 sumber kebenaran
 * soal siapa boleh akses exam apa).
 */
public function myExams(Request $request)
{
    $user = $request->user();

    $exams = Exam::with('bank')->withCount('questions')->get();

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
            'program_id' => $exam->bank->program_id,
        ]);
}

/**
 * GET /api/packages/{package}/exams
 * Daftar exam yang termasuk dalam SATU package spesifik — dipakai halaman
 * "Latihan Soal" per-package (PackageExams.jsx). Sengaja pakai relasi
 * package->questionBanks eksplisit, bukan cocokkan program_id, supaya
 * tidak ikut menampilkan exam dari package lain yang kebetulan program-nya
 * sama.
 */
public function forPackage(Request $request, \App\Models\Package $package)
{
    $user = $request->user();
    $bankIds = $package->questionBanks()->pluck('question_banks.id');

    $exams = Exam::with('bank')
        ->withCount('questions')
        ->whereIn('bank_id', $bankIds)
        ->get();

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
        ]);
}

    /**
     * GET /api/exams/{exam}/summary
     * Ringkasan status pengerjaan siswa untuk satu exam — dipakai halaman
     * detail exam sebelum siswa mulai/lanjut mengerjakan (mirip "Persiapan
     * Tryout" + kartu skor pertama/terbaru).
     * Sengaja hanya melihat attempt mandiri (exam_batch_id null); attempt
     * dari try out batch punya leaderboard sendiri dan tidak dicampur di sini.
     */
    public function summary(Request $request, Exam $exam)
    {
        $user = $request->user();

        if (!$exam->is_free_preview && !$this->accessControl->canAttemptExam($user, $exam)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses untuk melihat exam ini.',
            ], 403);
        }

        $exam->load('sections');

        $attempts = ExamAttempt::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('exam_batch_id')
            ->with('sectionScores')
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

            $passed = $exam->require_all_sections_pass
                ? $sections->isNotEmpty() && $sections->every(fn ($s) => $s['passed_threshold'] === true)
                : $attempt->score >= $exam->passing_score;

            return [
                'attempt_id' => $attempt->id,
                'finished_at' => $attempt->finished_at,
                'score' => $attempt->score,
                'correct_count' => $attempt->correct_count,
                'sections' => $sections->values(),
                'passed' => $passed,
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

        return new ExamAttemptResource($attempt->fresh(['exam.questions.options', 'exam.sections', 'answers', 'sectionScores']));
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

        $attempts = ExamAttempt::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('exam_batch_id')
            ->whereIn('status', ['submitted', 'auto_submitted', 'graded'])
            ->with('sectionScores')
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

            $passed = $exam->require_all_sections_pass
                ? $sections->isNotEmpty() && $sections->every(fn ($s) => $s['passed_threshold'] === true)
                : $attempt->score >= $exam->passing_score;

            return [
                'attempt_id' => $attempt->id,
                'attempt_number' => $index + 1,
                'started_at' => $attempt->started_at,
                'finished_at' => $attempt->finished_at,
                'score' => $attempt->score,
                'correct_count' => $attempt->correct_count,
                'sections' => $sections->values(),
                'passed' => $passed,
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

        $attempt->load(['exam.questions.options', 'answers']);

        $questions = $attempt->exam->questions->map(function ($question) use ($attempt) {
            $answer = $attempt->answers->firstWhere('question_id', $question->id);
            $correctOption = $question->options->firstWhere('is_correct', true);

            return [
                'question_id' => $question->id,
                'question_text' => $question->question_text,
                'media_type' => $question->media_type,
                'media_url' => $question->media_url,
                'type' => $question->type,
                'explanation' => $question->explanation,
                'options' => $question->options->map(fn ($opt) => [
                    'id' => $opt->id,
                    'option_text' => $opt->option_text,
                    'is_correct' => $opt->is_correct,
                ])->values(),
                'selected_option_id' => $answer?->selected_option_id,
                'essay_answer' => $answer?->essay_answer,
                'correct_option_id' => $correctOption?->id,
                'is_correct' => $answer?->is_correct,
                'needs_manual_grading' => $answer?->needs_manual_grading ?? false,
            ];
        });

        return response()->json([
            'attempt_id' => $attempt->id,
            'exam_title' => $attempt->exam->title,
            'score' => $attempt->score,
            'correct_count' => $attempt->correct_count,
            'questions' => $questions->values(),
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
    protected function autoSubmitIfExpired(ExamAttempt $attempt): bool
    {
        if ($attempt->status !== 'in_progress') {
            return in_array($attempt->status, ['auto_submitted', 'graded']);
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
        DB::transaction(function () use ($attempt, $status) {
            $answers = $attempt->answers()->with('question.options')->get();
            $examQuestions = $attempt->exam->questions; // pivot: points, exam_section_id
            $sections = $attempt->exam->sections->keyBy('id');

            $score = 0;
            $correctCount = 0;
            $hasPendingEssay = false;
            $sectionTotals = [];

            foreach ($answers as $answer) {
                $pivot = $examQuestions->firstWhere('id', $answer->question_id)?->pivot;
                $sectionId = $pivot?->exam_section_id;

                if ($answer->question->type === 'essay') {
                    if ($answer->needs_manual_grading) {
                        $hasPendingEssay = true;
                        continue;
                    }

                    if ($answer->is_correct) {
                        $points = $pivot->points ?? 0;
                        $score += $points;
                        $correctCount++;

                        if ($sectionId) {
                            $sectionTotals[$sectionId]['score'] = ($sectionTotals[$sectionId]['score'] ?? 0) + $points;
                            $sectionTotals[$sectionId]['correct'] = ($sectionTotals[$sectionId]['correct'] ?? 0) + 1;
                        }
                    }
                    continue;
                }

                // soal pg: koreksi jawaban siswa dengan kunci jawaban (is_correct
                // pada question_options). Poin diambil dari bobot soal di pivot
                // exam_questions (points per soal), fallback 1 poin kalau bobot
                // belum diisi -- supaya nilai tetap bisa dihitung meski data bobot
                // di exam_questions/question_options belum lengkap.
                $selectedOption = $answer->question->options->firstWhere('id', $answer->selected_option_id);
                $isCorrect = (bool) ($selectedOption->is_correct ?? false);
                $points = $isCorrect ? ($pivot->points ?? 1) : 0;

                $answer->update(['is_correct' => $isCorrect]);

                $score += $points;
                if ($isCorrect) {
                    $correctCount++;
                }

                if ($sectionId) {
                    $sectionTotals[$sectionId]['score'] = ($sectionTotals[$sectionId]['score'] ?? 0) + $points;
                    $sectionTotals[$sectionId]['correct'] = ($sectionTotals[$sectionId]['correct'] ?? 0) + ($isCorrect ? 1 : 0);
                }
            }

            foreach ($sectionTotals as $sectionId => $totals) {
                $section = $sections->get($sectionId);
                $passed = $section?->min_passing_score !== null
                    ? $totals['score'] >= $section->min_passing_score
                    : null;

                ExamAttemptSectionScore::updateOrCreate(
                    ['exam_attempt_id' => $attempt->id, 'exam_section_id' => $sectionId],
                    [
                        'raw_score' => $totals['score'],
                        'correct_count' => $totals['correct'],
                        'passed_threshold' => $passed,
                    ]
                );
            }

            $attempt->update([
                'status' => $hasPendingEssay ? $status : 'graded',
                'score' => $score,
                'correct_count' => $correctCount,
                'finished_at' => now(),
            ]);
        });
    }

    protected function authorizeOwnership(Request $request, ExamAttempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 403, 'Bukan attempt milik Anda.');
    }
}