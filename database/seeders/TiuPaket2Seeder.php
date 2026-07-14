<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class TiuPaket2Seeder extends Seeder
{
    public function run(): void
    {
        $program = Program::firstOrCreate(
            ['slug' => 'cpns-skd'],
            [
                'name' => 'CPNS (SKD)',
                'description' => 'Persiapan Seleksi Kompetensi Dasar CPNS',
                'icon' => null,
                'is_active' => true,
            ]
        );

        $subject = Subject::firstOrCreate(['name' => 'TIU (Tes Intelegensia Umum)']);

        $bank = QuestionBank::firstOrCreate(
            ['title' => 'Bank Soal TIU - Paket 2'],
            ['subject_id' => $subject->id, 'program_id' => $program->id]
        );

        $exam = Exam::firstOrCreate(
            ['title' => 'TIU - Paket 2'],
            [
                'bank_id' => $bank->id,
                'duration_minutes' => 25,
                'passing_score' => 14,
                'require_all_sections_pass' => false,
                'is_free_preview' => false,
            ]
        );

        $questionData = $this->questionData();

        $section = ExamSection::firstOrCreate(
            ['exam_id' => $exam->id, 'code' => 'tiu'],
            [
                'name' => 'Tes Intelegensia Umum (TIU)',
                'order' => 1,
                'scoring_type' => 'single_correct',
                'points_per_question' => 5,
                'min_passing_score' => 80,
                'max_score' => count($questionData) * 5,
                'duration_minutes' => null,
                'is_locked_after_next' => false,
            ]
        );

        foreach ($questionData as $q) {
            $question = Question::create([
                'bank_id' => $bank->id,
                'question_text' => $q['text'],
                'media_url' => null,
                'media_type' => 'none',
                'type' => 'pg',
                'difficulty' => 'sedang',
            ]);

            foreach ($q['options'] as $letter => $text) {
                $isCorrect = $letter === $q['answer'];

                $question->options()->create([
                    'option_text' => $text,
                    'points' => $isCorrect ? 5 : 0,
                    'is_correct' => $isCorrect,
                ]);
            }

            $exam->questions()->attach($question->id, [
                'points' => 5,
                'exam_section_id' => $section->id,
            ]);
        }

        $this->command->info('Selesai: ' . count($questionData) . ' soal TIU Paket 2 ditambahkan ke exam #' . $exam->id . ', section #' . $section->id);
    }

    protected function questionData(): array
    {
        return [
            // Verbal - Analogi
            [
                'text' => 'LUKA : BENGKAK = PUPUK : ...',
                'options' => ['A' => 'Subur', 'B' => 'Kebun', 'C' => 'Kotoran', 'D' => 'Tumbuhan', 'E' => 'Kompos'],
                'answer' => 'A',
            ],
            [
                'text' => 'HIBURAN : TERAS = UPACARA : ...',
                'options' => ['A' => 'Lelah', 'B' => 'Lapangan', 'C' => 'Berdiri', 'D' => 'Halaman', 'E' => 'Hormat'],
                'answer' => 'B',
            ],
            [
                'text' => 'KUALITAS : HARGA = RESPONSIF : ...',
                'options' => ['A' => 'Komunikatif', 'B' => 'Tanggap', 'C' => 'Cepat', 'D' => 'Hati-hati', 'E' => 'Keterbukaan'],
                'answer' => 'B',
            ],
            [
                'text' => 'PENGEMIS : MISKIN = PEGUNUNGAN : ...',
                'options' => ['A' => 'Sejuk', 'B' => 'Ramai', 'C' => 'Masyarakat', 'D' => 'Sepi', 'E' => 'Indah'],
                'answer' => 'A',
            ],

            // Verbal - Logika Analitik
            [
                'text' => 'Di sebuah ruangan terdapat 5 siswa yang duduk sejajar dengan aturan: (1) Wati diapit oleh Budi dan Caca, (2) Toni duduk di salah satu ujung barisan, (3) Bina ingin duduk di sebelah Caca. Di mana posisi Budi?',
                'options' => [
                    'A' => 'Diantara Toni dan Bina',
                    'B' => 'Disebelah Caca',
                    'C' => 'Di salah satu ujung barisan',
                    'D' => 'Diantara Bina dan Caca',
                    'E' => 'Disebelah Bina',
                ],
                'answer' => 'C',
            ],
            [
                'text' => 'Akan dibuat 3 kelompok dari 9 anak (Adi, Beni, Ciko, Dion, Eca, Frizza, Gia, Hadi, Ida) dengan ketentuan: (1) Adi dan Hadi harus berbeda kelompok, (2) Beni dan Ciko dapat berada dalam satu kelompok dengan Adi atau Hadi, (3) Gia atau Frizza harus sekelompok dengan Ida, (4) Eca berbeda kelompok dengan Dion. Pernyataan mana yang benar?',
                'options' => [
                    'A' => 'Hadi, Ciko, dan Ida berada di satu kelompok',
                    'B' => 'Adi, Beni, dan Ciko berada di satu kelompok',
                    'C' => 'Dion, Eca, dan Frizza berada di satu kelompok',
                    'D' => 'Adi, Eca, dan Ida berada di satu kelompok',
                    'E' => 'Beni, Dion, dan Gia berada di satu kelompok',
                ],
                'answer' => 'B',
            ],
            [
                'text' => 'Lima peserta diklat (Rian, Sinta, Tono, Una, Vera) presentasi berurutan dengan ketentuan: (1) Sinta tidak presentasi pertama maupun terakhir, (2) Tono presentasi tepat setelah Rian, (3) Una presentasi urutan ketiga. Jika Vera urutan pertama, siapa yang presentasi urutan keempat?',
                'options' => ['A' => 'Rian', 'B' => 'Sinta', 'C' => 'Tono', 'D' => 'Una', 'E' => 'Informasi tidak cukup menentukan'],
                'answer' => 'C',
            ],

            // Verbal - Silogisme
            [
                'text' => 'Premis 1: Semua tanaman hias yang disukai memiliki bunga yang indah. Premis 2: Tanaman hias dengan bunga yang indah dianggap berharga oleh para kolektor. Premis 3: Tanaman itu tidak memiliki bunga yang indah. Kesimpulan yang tepat?',
                'options' => [
                    'A' => 'Semua tanaman hias yang memiliki bunga indah disukai',
                    'B' => 'Semua yang disukai dan memiliki bunga yang indah adalah tanaman hias',
                    'C' => 'Tanaman tersebut tidak disukai',
                    'D' => 'Tanaman tersebut memiliki bunga yang indah',
                    'E' => 'Tanaman tersebut tidak memiliki bunga',
                ],
                'answer' => 'C',
            ],
            [
                'text' => 'Premis 1: Tidak ada warga desa X mendapatkan bantuan. Premis 2: Warga yang mendapat bantuan mendapatkan pelatihan. Kesimpulan yang tepat?',
                'options' => [
                    'A' => 'Warga desa X mendapatkan pelatihan',
                    'B' => 'Warga desa X tidak mendapatkan pelatihan',
                    'C' => 'Beberapa warga desa X mendapat bantuan',
                    'D' => 'Semua warga desa X mendapat pelatihan',
                    'E' => 'Tidak ada warga yang mendapatkan pelatihan',
                ],
                'answer' => 'B',
            ],
            [
                'text' => 'Premis 1: Semua warga desa Mangga menanam pohon kurma. Premis 2: Tidak ada pegawai kantor X menanam pohon kurma. Kesimpulan yang tepat?',
                'options' => [
                    'A' => 'Beberapa warga desa Mangga adalah pegawai kantor X',
                    'B' => 'Semua pegawai kantor X adalah warga desa Mangga',
                    'C' => 'Tidak ada warga desa Mangga yang menjadi pegawai kantor X',
                    'D' => 'Beberapa pegawai kantor X menanam pohon kurma',
                    'E' => 'Semua warga desa Mangga adalah pegawai kantor X',
                ],
                'answer' => 'C',
            ],
            [
                'text' => 'Premis 1: Atlet renang menggunakan jas renang impor luar negeri. Premis 2: Jas renang impor lebih baik dari jas renang lokal karena dapat membawa mereka ke babak final. Premis 3: Selly berhasil masuk ke babak final. Kesimpulan yang tepat?',
                'options' => [
                    'A' => 'Semua atlet renang masuk ke babak final karena menggunakan jas renang impor',
                    'B' => 'Beberapa atlet renang tidak masuk ke final karena menggunakan jas renang lokal',
                    'C' => 'Semua atlet renang kecuali Selly menggunakan jas renang lokal',
                    'D' => 'Selly mungkin atlet renang yang menggunakan jas renang impor',
                    'E' => 'Semua atlet renang tidak masuk ke final karena menggunakan jas renang lokal',
                ],
                'answer' => 'D',
            ],

            // Numerik - Deret
            [
                'text' => 'Lanjutkan deret: 64, 63, 65, 64, x, 65, 67, 66. Berapakah x?',
                'options' => ['A' => '64', 'B' => '65', 'C' => '66', 'D' => '68', 'E' => '70'],
                'answer' => 'C',
            ],
            [
                'text' => 'Lanjutkan deret: 7, 13, 25, 49, …, …',
                'options' => ['A' => '93, 183', 'B' => '97, 193', 'C' => '95, 191', 'D' => '94, 190', 'E' => '92, 192'],
                'answer' => 'B',
            ],
            [
                'text' => 'Lanjutkan deret: 31, 32, 36, 37, 46, 47, 66, 67, …, …',
                'options' => ['A' => '100, 109', 'B' => '102, 108', 'C' => '106, 107', 'D' => '104, 109', 'E' => '103, 108'],
                'answer' => 'C',
            ],
            [
                'text' => 'Lengkapi deret: …, 3, 9, 1, 5, -4, -28, -38. Berapakah angka yang hilang di awal?',
                'options' => ['A' => '10', 'B' => '9', 'C' => '6', 'D' => '3', 'E' => '1'],
                'answer' => 'A',
            ],

            // Numerik - Hitung Cepat
            [
                'text' => 'Berapakah hasil dari √((0,6)² − (0,4)²) ÷ 0,2?',
                'options' => ['A' => '0,2', 'B' => '0,5', 'C' => '1,0', 'D' => '2,0', 'E' => '4,0'],
                'answer' => 'C',
            ],
            [
                'text' => 'Jika x = 83,33% dari 24 dan y = ∛8000, maka pernyataan yang benar adalah...',
                'options' => [
                    'A' => 'x > y',
                    'B' => 'x < y',
                    'C' => 'x = y',
                    'D' => 'x = y²',
                    'E' => 'Hubungan x dan y tidak dapat ditentukan',
                ],
                'answer' => 'C',
            ],
            [
                'text' => 'Berapakah hasil dari 999² − 1?',
                'options' => ['A' => '998.000', 'B' => '998.001', 'C' => '999.000', 'D' => '998.002', 'E' => '999.999'],
                'answer' => 'A',
            ],
            [
                'text' => 'Berapakah hasil dari (1/2)⁻² + 2³ × 0,25?',
                'options' => ['A' => '4', 'B' => '5', 'C' => '6', 'D' => '8', 'E' => '10'],
                'answer' => 'C',
            ],

            // Numerik - Perbandingan
            [
                'text' => 'Suatu proyek dikerjakan 12 pekerja selesai dalam 20 hari. Berapa tambahan pekerja yang diperlukan agar proyek selesai dalam 16 hari?',
                'options' => ['A' => '15 orang', 'B' => '12 orang', 'C' => '8 orang', 'D' => '5 orang', 'E' => '3 orang'],
                'answer' => 'E',
            ],
            [
                'text' => 'Jika 2 ekor sapi menghabiskan 1 karung rumput dalam 3 hari, maka 6 ekor sapi menghabiskan 10 karung rumput dalam waktu?',
                'options' => ['A' => '48 hari', 'B' => '24 hari', 'C' => '12 hari', 'D' => '10 hari', 'E' => '8 hari'],
                'answer' => 'D',
            ],

            // Numerik - Soal Cerita
            [
                'text' => 'Andika dapat menyelesaikan laporan dalam 6 jam, Berri dalam 4 jam. Andika mulai pukul 08.00 sendiri, Berri baru ikut membantu pukul 09.00. Pada pukul berapa laporan selesai?',
                'options' => ['A' => '09.40', 'B' => '10.00', 'C' => '11.00', 'D' => '11.20', 'E' => '11.40'],
                'answer' => 'C',
            ],
            [
                'text' => 'Tiga tahun lalu, jumlah umur ayah dan anak laki-lakinya adalah 52 tahun. Jika sekarang perbandingan umur ayah dan anak adalah 4:1, berapa umur ayah 5 tahun yang akan datang?',
                'options' => ['A' => '43', 'B' => '46', 'C' => '48', 'D' => '51', 'E' => '53'],
                'answer' => 'D',
            ],
            [
                'text' => 'Sebuah toko memberikan diskon berturut-turut 20% lalu tambahan 10% bagi member. Jika total bayar member untuk sebuah gaun adalah Rp144.000, berapa harga label asli gaun tersebut?',
                'options' => ['A' => 'Rp180.000', 'B' => 'Rp200.000', 'C' => 'Rp220.000', 'D' => 'Rp240.000', 'E' => 'Rp250.000'],
                'answer' => 'B',
            ],
        ];
    }
}