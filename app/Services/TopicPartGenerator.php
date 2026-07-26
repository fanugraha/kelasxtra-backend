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
        $nextPart = (Exam::where('topic_id', $topic->id)->max('part_number') ?? 0) + 1;

        // GUARD (26 Jul 2026): taxonomy type='subject' (Mapel) SENGAJA punya
        // program_id null -- mapel bersifat global, dipakai lintas Program
        // lewat question_banks.program_id, bukan lewat taxonomies.program_id.
        // Kalau exam Part ini dibiarkan lanjut dibuat dengan program_id null,
        // AccessControlService::canAccessExamPart() akan SELALU menolak Part
        // 2 dst (butuh program_id buat cek subscription->coversProgram()),
        // dan siswa akan mentok 403 tanpa penjelasan meski subscription-nya
        // aktif. Part 1 aman (free preview, tidak lewat pengecekan itu), jadi
        // guard ini cuma berlaku dari Part 2 dan seterusnya.
        //
        // Ini BUKAN bug logic yang bisa ditambal 1 baris: untuk topic mode
        // subject, satu Topic bisa dipakai lintas beberapa Program sekaligus
        // (lewat bank soal beda-beda program), jadi "program mana yang harus
        // dicek subscription-nya" belum well-defined di level Topic. Kalau
        // fitur Latihan Topik memang mau diaktifkan untuk Program mode
        // subject, desain akses-nya perlu dipikirkan ulang dulu (mis. cek
        // subscription per soal berdasarkan bank asalnya, bukan per Topic) --
        // BUKAN cuma menghapus guard ini.
        if ($nextPart > 1 && $topic->taxonomy->isSubject()) {
            throw new \RuntimeException(
                "Tidak bisa generate Part {$nextPart} untuk topik \"{$topic->name}\": ".
                "topik ini ada di bawah Mapel \"{$topic->taxonomy->name}\" (taxonomy type=subject), ".
                'yang program_id-nya sengaja kosong karena mapel bersifat global lintas Program. '.
                'Latihan Topik Part 2+ untuk mode subject BELUM didukung -- AccessControlService '.
                'akan selalu menolak akses siswa ke part ini walau subscription-nya aktif. '.
                'Part 1 (free preview) tetap aman dan tidak terpengaruh guard ini.'
            );
        }

        $questions = Question::where('topic_id', $topic->id)
            ->whereNotIn('id', $usedQuestionIds)
            ->inRandomOrder()
            ->limit($questionCount)
            ->get();

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
