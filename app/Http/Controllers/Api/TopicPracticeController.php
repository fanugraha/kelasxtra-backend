<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Taxonomy;
use App\Models\Topic;
use App\Services\AccessControlService;
use Illuminate\Http\Request;

class TopicPracticeController extends Controller
{
    public function __construct(protected AccessControlService $accessControl)
    {
    }

    /**
     * GET /api/latihan-soal/categories
     * Layar 1: daftar Kategori/Mapel (Taxonomy) yang punya minimal 1 Topik
     * dengan Part latihan tersedia. Dikelompokkan per Program.
     */
    public function categories(Request $request)
    {
        return Taxonomy::query()
            ->whereHas('topics.exams', fn ($q) => $q->topicPractice())
            ->with('program')
            ->get()
            ->map(fn (Taxonomy $taxonomy) => [
                'id' => $taxonomy->id,
                'code' => $taxonomy->code,
                'name' => $taxonomy->name,
                'program' => [
                    'id' => $taxonomy->program?->id,
                    'name' => $taxonomy->program?->name,
                ],
            ])
            ->values();
    }

    /**
     * GET /api/latihan-soal/categories/{taxonomy}/topics
     * Layar 2: daftar Topik dalam 1 Kategori, dengan progress ringkas
     * (berapa Part sudah selesai dari total Part yang ada).
     */
    public function topics(Request $request, Taxonomy $taxonomy)
    {
        $user = $request->user();

        $topics = Topic::where('taxonomy_id', $taxonomy->id)
            ->whereHas('exams', fn ($q) => $q->topicPractice())
            ->with('exams')
            ->get();

        return $topics->map(function (Topic $topic) use ($user) {
            $parts = $topic->exams()->topicPractice()->orderBy('part_number')->get();
            $examIds = $parts->pluck('id');

            $completedCount = ExamAttempt::where('user_id', $user->id)
                ->whereIn('exam_id', $examIds)
                ->whereIn('status', ['submitted', 'auto_submitted', 'graded'])
                ->distinct('exam_id')
                ->count('exam_id');

            return [
                'topic_id' => $topic->id,
                'name' => $topic->name,
                'code' => $topic->code,
                'total_parts' => $parts->count(),
                'completed_parts' => $completedCount,
            ];
        })->values();
    }

    /**
     * GET /api/latihan-soal/latest-attempted
     * Dipakai Continue Card di Beranda: topik latihan terakhir yang disentuh
     * user (attempt manapun statusnya, termasuk in_progress), lengkap dengan
     * posisi Part-nya (part_number/total_parts) dan status part berikutnya,
     * supaya card bisa nampilkan "Lanjutkan Part X" atau "Part X dari Y
     * selesai -- lanjut ke Part X+1" tanpa Flutter perlu query roadmap
     * lengkap dulu.
     */
    public function latestAttempted(Request $request)
    {
        $user = $request->user();

        $latestAttempt = ExamAttempt::where('user_id', $user->id)
            ->whereNull('exam_batch_id')
            ->whereHas('exam', fn ($q) => $q->topicPractice())
            ->orderByDesc('started_at')
            ->with('exam.topic')
            ->first();

        if (! $latestAttempt || ! $latestAttempt->exam?->topic) {
            return response()->json(['topic_id' => null]);
        }

        $topic = $latestAttempt->exam->topic;

        $parts = $topic->exams()->topicPractice()->orderBy('part_number')->get();
        $examIds = $parts->pluck('id');

        $attemptsByExamId = ExamAttempt::where('user_id', $user->id)
            ->whereIn('exam_id', $examIds)
            ->whereIn('status', ['submitted', 'auto_submitted', 'graded'])
            ->get()
            ->groupBy('exam_id');

        $currentPart = $parts->firstWhere('id', $latestAttempt->exam_id);
        $inProgress = $latestAttempt->status === 'in_progress' ? $latestAttempt : null;

        $completedAttempts = $attemptsByExamId->get($currentPart->id, collect());
        $isCurrentCompleted = $completedAttempts->isNotEmpty();
        $bestScore = $completedAttempts->max('score');

        $nextPart = $isCurrentCompleted
            ? $parts->firstWhere('part_number', '>', $currentPart->part_number)
            : null;

        $nextPartUnlocked = $nextPart
            ? $this->accessControl->canAccessExamPart($user, $nextPart)
            : false;

        return response()->json([
            'topic_id' => $topic->id,
            'topic_name' => $topic->name,
            'current_part' => [
                'exam_id' => $currentPart->id,
                'part_number' => $currentPart->part_number,
                'title' => $currentPart->title,
                'in_progress_attempt_id' => $inProgress?->id,
                'is_completed' => $isCurrentCompleted,
                'best_score' => $bestScore,
            ],
            'total_parts' => $parts->count(),
            'next_part' => $nextPart ? [
                'exam_id' => $nextPart->id,
                'part_number' => $nextPart->part_number,
                'title' => $nextPart->title,
                'unlocked' => $nextPartUnlocked,
            ] : null,
        ]);
    }

    /**
     * GET /api/latihan-soal/topics/{topic}/roadmap
     * Layar 3: daftar Part dalam 1 Topik beserta status akses tiap Part.
     *
     * status:
     *  - 'completed'          : sudah pernah diselesaikan, boleh diulang bebas
     *  - 'unlocked'           : boleh dikerjakan sekarang, belum pernah selesai
     *  - 'locked_subscription': terkunci karena belum Subscription aktif
     *  - 'locked_sequence'    : terkunci karena Part sebelumnya belum selesai
     */
    public function roadmap(Request $request, Topic $topic)
    {
        $user = $request->user();

        $parts = $topic->exams()
            ->topicPractice()
            ->orderBy('part_number')
            ->get();

        $attemptsByExamId = ExamAttempt::where('user_id', $user->id)
            ->whereIn('exam_id', $parts->pluck('id'))
            ->whereIn('status', ['submitted', 'auto_submitted', 'graded'])
            ->get()
            ->groupBy('exam_id');

        return $parts->map(function (Exam $exam) use ($user, $attemptsByExamId) {
            $attempts = $attemptsByExamId->get($exam->id, collect());
            $isCompleted = $attempts->isNotEmpty();
            $bestScore = $attempts->max('score');

            if ($isCompleted) {
                $status = 'completed';
            } elseif ($this->accessControl->canAccessExamPart($user, $exam)) {
                $status = 'unlocked';
            } elseif (! $exam->is_free_preview && ! $this->accessControl->canAttemptExam($user, $exam)) {
                $status = 'locked_subscription';
            } else {
                $status = 'locked_sequence';
            }

            return [
                'exam_id' => $exam->id,
                'part_number' => $exam->part_number,
                'title' => $exam->title,
                'is_free_preview' => $exam->is_free_preview,
                'status' => $status,
                'best_score' => $bestScore,
                'duration_minutes' => $exam->duration_minutes,
            ];
        })->values();
    }
}
