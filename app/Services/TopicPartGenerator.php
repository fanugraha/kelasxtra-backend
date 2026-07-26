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
     * Sebelumnya generator ini mewajibkan ada Package dengan is_focus_topic=true
     * sebelum boleh generate Part -- itu aturan lama dari sebelum Subscription
     * ada dan sudah tidak relevan, jadi semua logika pencarian/attach Package
     * dihapus dari sini.
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
            ->with('bank')
            ->get();

        $nextPart = (Exam::where('topic_id', $topic->id)->max('part_number') ?? 0) + 1;

        return DB::transaction(function () use ($topic, $questions, $nextPart, $questionCount, $programId, $durationMinutes) {
            $exam = Exam::create([
                'program_id' => $programId,
                'title' => "{$topic->name} - Part {$nextPart}",
                'topic_id' => $topic->id,
                'part_number' => $nextPart,
                // Kalau admin isi durasi manual, pakai itu. Kalau kosong,
                // estimasi otomatis: 1 menit per soal, minimal 5 menit.
                'duration_minutes' => $durationMinutes ?? max(5, $questionCount),
                // Part 1 tiap topik otomatis gratis -- ini "sample rasa" funnel:
                // siswa bisa coba kualitas soal tanpa subscribe dulu. Part 2
                // dst butuh Subscription aktif (lihat AccessControlService).
                'is_free_preview' => $nextPart === 1,
            ]);

            $questionsByBank = $questions->groupBy('bank_id');

            foreach ($questionsByBank as $bankId => $bankQuestions) {
                $bank = $bankQuestions->first()->bank;

                $section = $exam->sections()->create([
                    'taxonomy_id' => $topic->taxonomy_id,
                    'question_bank_id' => $bankId,
                    // Kalau soal part ini nyebar di >1 Question Bank, code harus
                    // dibedain per bank biar gak nabrak unique(exam_id, code).
                    // Kalau cuma 1 bank, tetap pakai topic->code polos (kompatibel
                    // dengan behavior lama).
                    'code' => $questionsByBank->count() > 1
                        ? "{$topic->code}-{$bankId}"
                        : $topic->code,
                    'name' => $topic->name,
                    'scoring_type' => $bank->scoring_type,
                ]);

                foreach ($bankQuestions as $question) {
                    $exam->questions()->attach($question->id, [
                        'exam_section_id' => $section->id,
                    ]);

                    TopicUsedQuestion::create([
                        'topic_id' => $topic->id,
                        'question_id' => $question->id,
                        'exam_id' => $exam->id,
                    ]);
                }
            }

            return $exam;
        });
    }
}
