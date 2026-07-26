<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Topic;
use App\Models\TopicUsedQuestion;
use Illuminate\Support\Facades\DB;

class TopicPartGenerator
{
    /**
     * Generate Exam Part baru untuk sebuah Topic.
     *
     * CATATAN (26 Jul 2026): Part latihan topik TIDAK dijual satuan lewat
     * Package -- aksesnya HANYA lewat Subscription (Subscription->coversProgram()).
     *
     * CATATAN (26 Jul 2026, fix): Part latihan topik SELALU dibungkus jadi
     * SATU section saja, TIDAK di-split per bank_id soal asalnya. bank_id
     * cuma relevan untuk Exam try-out biasa (attach bank soal manual lewat
     * admin); untuk latihan topik, identitas "part ini soal apa" sudah
     * cukup diwakili oleh topic_id.
     */
    public function generateNextPart(Topic $topic, int $questionCount = 10, ?int $durationMinutes = null): Exam
    {
        $usedQuestionIds = TopicUsedQuestion::where('topic_id', $topic->id)
            ->pluck('question_id');

        $availableCount = Question::where('topic_id', $topic->id)
            ->whereNotIn('id', $usedQuestionIds)
            ->count();

        if ($availableCount < $questionCount) {
            throw new \RuntimeException(
                "Stok soal topik \"{$topic->name}\" tinggal {$availableCount}, " .
                "butuh {$questionCount} untuk generate part baru. Tambah soal dulu."
            );
        }

        $programId = $topic->taxonomy->program_id;

        $questions = Question::where('topic_id', $topic->id)
            ->whereNotIn('id', $usedQuestionIds)
            ->inRandomOrder()
            ->limit($questionCount)
            ->get();

        $nextPart = (Exam::where('topic_id', $topic->id)->max('part_number') ?? 0) + 1;

        return DB::transaction(function () use ($topic, $questions, $nextPart, $questionCount, $programId, $durationMinutes) {
            $exam = Exam::create([
                'program_id' => $programId,
                'title' => "{$topic->name} - Part {$nextPart}",
                'topic_id' => $topic->id,
                'part_number' => $nextPart,
                'duration_minutes' => $durationMinutes ?? max(5, $questionCount),
                'is_free_preview' => $nextPart === 1,
            ]);

            $section = $exam->sections()->create([
                'taxonomy_id' => $topic->taxonomy_id,
                'question_bank_id' => null,
                'code' => $topic->code,
                'name' => $topic->name,
                'scoring_type' => 'single_correct',
            ]);

            foreach ($questions as $question) {
                $exam->questions()->attach($question->id, [
                    'exam_section_id' => $section->id,
                ]);

                TopicUsedQuestion::create([
                    'topic_id' => $topic->id,
                    'question_id' => $question->id,
                    'exam_id' => $exam->id,
                ]);
            }

            return $exam;
        });
    }
}
