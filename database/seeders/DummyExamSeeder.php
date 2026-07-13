<?php

namespace Database\Seeders;

use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamBatch;
use App\Models\Package;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder KHUSUS untuk testing manual Tahap 5 (bukan seeder produksi).
 * Bikin 1 alur lengkap: program -> package (latihan_soal) -> subject ->
 * question_bank -> 3 soal pg + 1 soal essay -> exam -> exam_batch (untuk
 * testing try out) + 1 user siswa yang sudah enrollment aktif ke package itu.
 *
 * Jalankan: php artisan db:seed --class=Database\\Seeders\\DummyExamSeeder
 */
class DummyExamSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Siswa test
        $siswa = User::firstOrCreate(
            ['email' => 'siswa.test@kelasxtra.test'],
            [
                'name' => 'Siswa Testing',
                'phone' => '081234567890',
                'password' => Hash::make('password123'),
                'role' => 'siswa',
                'level_pendidikan' => 'SMA',
                'is_active' => true,
            ]
        );

        // 2. Program
        $program = Program::firstOrCreate(
            ['slug' => 'snbt-testing'],
            [
                'name' => 'SNBT (Testing)',
                'description' => 'Program dummy untuk testing Tahap 5',
                'icon' => null,
                'is_active' => true,
            ]
        );

        // 3. Package (type latihan_soal, sesuai section 7 desain — bukan exam_batch)
        $package = Package::firstOrCreate(
            ['program_id' => $program->id, 'name' => 'Paket Latihan Soal Testing'],
            [
                'type' => 'latihan_soal',
                'price' => 0,
                'discount_price' => null,
                'duration_days' => 365,
                'description' => 'Dummy package untuk testing exam engine',
            ]
        );

        // 4. Enrollment aktif untuk siswa test (dibutuhkan AccessControlService::canAttemptExam)
        Enrollment::firstOrCreate(
            ['user_id' => $siswa->id, 'package_id' => $package->id],
            [
                'class_id' => null,
                'status' => 'active',
                'start_date' => now()->subDay(),
                'end_date' => now()->addDays(30),
                'transaction_id' => null,
            ]
        );

        // 5. Subject + question bank
        $subject = Subject::firstOrCreate(['name' => 'Penalaran Umum (Testing)']);

        $bank = QuestionBank::firstOrCreate(
            ['subject_id' => $subject->id, 'program_id' => $program->id, 'title' => 'Bank Soal Testing'],
        );

        // 6. Soal pg (3 soal) + soal essay (1 soal)
        $questionsData = [
            [
                'question_text' => 'Berapa hasil dari 12 + 8?',
                'type' => 'pg',
                'difficulty' => 'mudah',
                'options' => [
                    ['option_text' => '18', 'is_correct' => false],
                    ['option_text' => '20', 'is_correct' => true],
                    ['option_text' => '22', 'is_correct' => false],
                    ['option_text' => '24', 'is_correct' => false],
                ],
            ],
            [
                'question_text' => 'Ibu kota Indonesia adalah?',
                'type' => 'pg',
                'difficulty' => 'mudah',
                'options' => [
                    ['option_text' => 'Bandung', 'is_correct' => false],
                    ['option_text' => 'Jakarta', 'is_correct' => true],
                    ['option_text' => 'Surabaya', 'is_correct' => false],
                    ['option_text' => 'Medan', 'is_correct' => false],
                ],
            ],
            [
                'question_text' => 'Kelanjutan pola: 2, 4, 8, 16, ...?',
                'type' => 'pg',
                'difficulty' => 'sedang',
                'options' => [
                    ['option_text' => '24', 'is_correct' => false],
                    ['option_text' => '30', 'is_correct' => false],
                    ['option_text' => '32', 'is_correct' => true],
                    ['option_text' => '36', 'is_correct' => false],
                ],
            ],
            [
                'question_text' => 'Jelaskan menurut pendapatmu, mengapa penalaran logis penting dalam ujian SNBT.',
                'type' => 'essay',
                'difficulty' => 'sedang',
                'options' => [], // essay tidak punya opsi
            ],
        ];

        $questionIds = [];
        foreach ($questionsData as $data) {
            $question = Question::firstOrCreate(
                ['bank_id' => $bank->id, 'question_text' => $data['question_text']],
                ['type' => $data['type'], 'difficulty' => $data['difficulty'], 'image_url' => null]
            );

            foreach ($data['options'] as $opt) {
                QuestionOption::firstOrCreate(
                    ['question_id' => $question->id, 'option_text' => $opt['option_text']],
                    ['is_correct' => $opt['is_correct']]
                );
            }

            $questionIds[] = $question->id;
        }

        // 7. Exam (durasi sengaja pendek — 5 menit — supaya auto-submit gampang ditest)
        $exam = Exam::firstOrCreate(
            ['bank_id' => $bank->id, 'title' => 'Latihan Soal Testing Tahap 5'],
            [
                'duration_minutes' => 5,
                'passing_score' => 60,
                'is_free_preview' => false,
            ]
        );

        // attach ke pivot exam_questions dengan poin (skip kalau sudah ada)
        $points = [25, 25, 25, 25]; // 3 pg + 1 essay, total 100
        foreach ($questionIds as $i => $qid) {
            if (! $exam->questions()->where('questions.id', $qid)->exists()) {
                $exam->questions()->attach($qid, ['points' => $points[$i]]);
            }
        }

        // 8. Exam batch untuk testing try out (window 1 jam dari sekarang)
        $batch = ExamBatch::firstOrCreate(
            ['exam_id' => $exam->id, 'name' => 'Try Out Testing Batch 1'],
            [
                'start_at' => now()->subMinutes(5),
                'end_at' => now()->addHour(),
                'is_national' => false,
                'status' => 'ongoing',
            ]
        );

        $this->command->info('Dummy data selesai dibuat:');
        $this->command->info("- Siswa test: siswa.test@kelasxtra.test / password123 (id={$siswa->id})");
        $this->command->info("- Exam (latihan soal, tanpa batch): id={$exam->id}, title={$exam->title}, durasi {$exam->duration_minutes} menit");
        $this->command->info("- Exam batch (try out): id={$batch->id}, window {$batch->start_at} s/d {$batch->end_at}");
        $this->command->info('- 3 soal pg + 1 soal essay, total poin 100');
    }
}
