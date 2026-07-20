<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditOrphanSections extends Command
{
    protected $signature = 'audit:orphan-sections';
    protected $description = 'Cari soal tanpa exam_section_id dan attempt siswa yang terdampak';

    public function handle(): int
    {
        $this->info('=== 1. Soal tanpa exam_section_id, dikelompokkan per Exam ===');

        $orphans = DB::table('exam_questions')
            ->whereNull('exam_section_id')
            ->select('exam_id', DB::raw('count(*) as jumlah_soal'))
            ->groupBy('exam_id')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('Tidak ada soal yang exam_section_id-nya kosong. Aman.');
        } else {
            foreach ($orphans as $row) {
                $exam = Exam::find($row->exam_id);
                $flag = $exam?->require_all_sections_pass ? 'YA' : 'tidak';
                $this->line("Exam #{$row->exam_id} ({$exam?->title}) -- {$row->jumlah_soal} soal tanpa section, require_all_sections_pass={$flag}");
            }
        }

        $this->newLine();
        $this->info('=== 2. Exam attempt siswa yang terdampak ===');

        $orphanExamIds = $orphans->pluck('exam_id');

        if ($orphanExamIds->isEmpty()) {
            $this->info('Tidak ada, karena tidak ada soal yang orphan.');
            return self::SUCCESS;
        }

        $affectedAttempts = ExamAttempt::whereIn('exam_id', $orphanExamIds)
            ->whereIn('status', ['submitted', 'auto_submitted', 'graded'])
            ->get(['id', 'user_id', 'exam_id', 'bank_id', 'score', 'finished_at']);

        $this->line("Total attempt terdampak: {$affectedAttempts->count()}");
        $this->line("Total siswa unik terdampak: {$affectedAttempts->pluck('user_id')->unique()->count()}");

        $this->newLine();
        $this->info('=== 3. Rincian per Exam ===');
        $byExam = $affectedAttempts->groupBy('exam_id');
        foreach ($byExam as $examId => $attempts) {
            $exam = Exam::find($examId);
            $siswaUnik = $attempts->pluck('user_id')->unique()->count();
            $this->line("Exam #{$examId} ({$exam?->title}): {$attempts->count()} attempt, {$siswaUnik} siswa unik");
        }

        return self::SUCCESS;
    }
}
