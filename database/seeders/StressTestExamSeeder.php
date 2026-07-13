<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ExamAttempt;
use App\Models\ExamBatch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StressTestExamSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ambil opsi enum yang valid dari database agar aman dari error truncation
        $validStatuses = $this->getEnumValues('exam_batches', 'status');

        // Pilih status fallback yang didukung (prioritaskan 'active', jika tidak ada pakai opsi pertama)
        $statusTarget = in_array('active', $validStatuses) ? 'active' : ($validStatuses[0] ?? 'active');

        // Pastikan ada Exam Batch target dengan ID 4
        $batch = ExamBatch::find(4);
        if (!$batch) {
            $batch = ExamBatch::create([
                'id' => 4,
                'exam_id' => 4, // Asumsi exam dengan ID 4 sudah ada di database seeder utama
                'name' => 'Try Out Akbar Nasional Premium',
                'start_at' => now()->subDays(1),
                'end_at' => now()->subHours(1), // Sengaja dibuat expired agar langsung memicu scheduler
                'status' => $statusTarget,
            ]);
        } else {
            $batch->update([
                'status' => $statusTarget,
                'end_at' => now()->subHours(1),
            ]);
        }

        $this->command->info("Menggunakan status batch: '{$statusTarget}'");
        $this->command->info('Memulai injeksi 1.000 siswa dummy...');

        // 2. Buat data array 1.000 User Siswa (Bulk Insert agar cepat)
        $usersData = [];
        $password = Hash::make('password123');
        $now = now();

        for ($i = 1; $i <= 1000; $i++) {
            $usersData[] = [
                'name' => 'Siswa TO Massal ' . $i,
                'email' => 'siswa_to_' . Str::random(5) . $i . '@kelasxtra.com',
                'password' => $password,
                'role' => 'siswa',
                'level_pendidikan' => 'SMA',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($usersData, 200) as $chunk) {
            User::insert($chunk);
        }

        // Ambil ID dari user-user baru yang barusan kita buat
        $userIds = User::where('email', 'like', 'siswa_to_%@kelasxtra.com')->pluck('id');

        $this->command->info('User berhasil dibuat. Membuka 1.000 Lembar Ujian...');

        // 3. Ambil opsi enum status yang valid untuk tabel exam_attempts
        $validAttemptStatuses = $this->getEnumValues('exam_attempts', 'status');
        $attemptStatus = in_array('graded', $validAttemptStatuses)
            ? 'graded'
            : (in_array('submitted', $validAttemptStatuses) ? 'submitted' : ($validAttemptStatuses[0] ?? 'graded'));

        // Buat data array 1.000 Exam Attempts terkait Batch 4
        $attemptsData = [];
        foreach ($userIds as $userId) {
            $startedAt = now()->subHours(rand(2, 4));
            $attemptsData[] = [
                'user_id' => $userId,
                'exam_id' => $batch->exam_id,
                'exam_batch_id' => $batch->id,
                'score' => rand(5, 100), // Scoring acak untuk persaingan ranking
                'correct_count' => rand(1, 25),
                'started_at' => $startedAt,
                'finished_at' => (clone $startedAt)->addMinutes(rand(15, 45)),
                'status' => $attemptStatus,
                'question_order' => json_encode(['questions' => [78, 77]]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($attemptsData, 200) as $chunk) {
            ExamAttempt::insert($chunk);
        }

        $this->command->info('Injeksi Berhasil! 1.000 Peserta TO siap dicalculate oleh Scheduler.');
    }

    /**
     * Helper untuk membaca nilai kolom ENUM langsung dari MySQL engine.
     */
    private function getEnumValues(string $table, string $column): array
    {
        try {
            $type = DB::select(
                "SHOW COLUMNS FROM `{$table}` WHERE Field = ?",
                [$column]
            )[0]->Type;

            preg_match('/^enum\((.*)\)$/', $type, $matches);

            $enum = [];
            foreach (explode(',', $matches[1]) as $value) {
                $enum[] = trim($value, "'");
            }
            return $enum;
        } catch (\Exception $e) {
            return [];
        }
    }
}