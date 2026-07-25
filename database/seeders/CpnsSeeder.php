<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Package;
use App\Models\Program;
use App\Models\Promo;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\SubscriptionPlan;
use App\Models\Taxonomy;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder data CPNS 2026 -- reset total lalu bangun ulang:
 * Program -> Taxonomy (TWK/TIU/TKP) -> Topic (kisi-kisi Kemenpan-RB) ->
 * Question Bank (3 paket per kategori, soal DUMMY) -> Exam (2 all-program +
 * 2 fokus topik) -> Package -> Subscription Plan -> Promo.
 *
 * PENTING: semua soal di sini DUMMY untuk keperluan testing alur aplikasi
 * (checkout, pengerjaan exam, scoring, leaderboard, dst) -- bukan bank soal
 * final yang siap dipakai siswa asli. Nilai ambang batas & kisi-kisi
 * mengikuti pola umum SKD CPNS, sesuaikan lagi ke aturan resmi tahun
 * berjalan kalau sudah ada admin yang input data produksi.
 *
 * Jalankan: php artisan db:seed --class="Database\Seeders\CpnsSeeder"
 */
class CpnsSeeder extends Seeder
{
    public function run(): void
    {
        $this->resetDataExceptAdmin();

        $program = $this->createProgram();
        [$twk, $tiu, $tkp] = $this->createTaxonomies($program);

        $twkTopics = $this->createTwkTopics($twk);
        $tiuTopics = $this->createTiuTopics($tiu);
        $tkpTopics = $this->createTkpTopics($tkp);

        $twkBanks = $this->createBanks($program, $twk, 'TWK', 3, 30, $twkTopics, 'single_correct', 5, 0);
        $tiuBanks = $this->createBanks($program, $tiu, 'TIU', 3, 35, $tiuTopics, 'single_correct', 5, 0);
        $tkpBanks = $this->createBanks($program, $tkp, 'TKP', 3, 45, $tkpTopics, 'weighted_options', 5, 1);

        [$exam1, $exam2] = $this->createAllProgramExams($program, $twkBanks, $tiuBanks, $tkpBanks);
        [$focus1, $focus2] = $this->createFocusExams($program, $twkTopics, $tiuTopics, $twkBanks, $tiuBanks);

        [$tryout1, $tryout2, $focusTwkPkg, $focusTiuPkg] = $this->createPackages(
            $program, $exam1, $exam2, $focus1, $focus2, $twk, $tiu
        );

        $plan = $this->createSubscriptionPlan($program);
        $promo = $this->createPromo($tryout1);

        $this->command->info('=== CPNS 2026 seeder selesai ===');
        $this->command->info("Program: {$program->name} (id={$program->id})");
        $this->command->info("Taxonomy: TWK#{$twk->id} TIU#{$tiu->id} TKP#{$tkp->id}");
        $this->command->info('Bank soal: 9 bank, total ' . Question::count() . ' soal dummy');
        $this->command->info("Exam all-program: #{$exam1->id}, #{$exam2->id}");
        $this->command->info("Exam fokus topik: #{$focus1->id}, #{$focus2->id}");
        $this->command->info("Package: #{$tryout1->id}, #{$tryout2->id}, #{$focusTwkPkg->id}, #{$focusTiuPkg->id}");
        $this->command->info("Subscription plan: #{$plan->id} ({$plan->name}, Rp" . number_format((float) $plan->price, 0, ',', '.') . ")");
        $this->command->info("Promo: {$promo->code} (diskon {$promo->discount_value}%)");
    }

    /**
     * Hapus semua data domain (soal, exam, package, subscription, promo,
     * transaksi, dll) dan semua user KECUALI yang role='admin'. Kalau ada
     * tabel lain yang ingin tetap dipertahankan (mis. articles untuk blog
     * publik), hapus nama tabelnya dari daftar di bawah.
     */
    protected function resetDataExceptAdmin(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'transaction_logs', 'transactions',
            'subscriptions', 'subscription_programs',
            'enrollments', 'leaderboard_snapshots',
            'exam_attempt_topic_scores', 'exam_attempt_section_scores',
            'exam_answers', 'exam_attempts', 'exam_batches',
            'topic_used_questions', 'package_exam', 'packages',
            'exam_questions', 'exam_sections', 'exams',
            'question_options', 'question_passages', 'questions', 'question_banks',
            'topics', 'taxonomies',
            'subscription_plans', 'promos',
            'testimonials', 'articles', 'tutors', 'classes',
            'programs', 'brands',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        User::where('role', '!=', 'admin')->delete();

        Schema::enableForeignKeyConstraints();
    }

    protected function createProgram(): Program
    {
        $brand = Brand::firstOrCreate(
            ['slug' => 'xtra-academy'],
            ['name' => 'Xtra Academy', 'domain' => null]
        );

        return Program::create([
            'brand_id' => $brand->id,
            'name' => 'CPNS 2026',
            'slug' => 'cpns-2026',
            'description' => 'Persiapan Seleksi Kompetensi Dasar (SKD) CPNS 2026 -- TWK, TIU, dan TKP.',
            'icon' => null,
            'is_active' => true,
            'question_grouping_mode' => 'category',
        ]);
    }

    /**
     * Nilai ambang batas (passing_grade) mengikuti pola ambang batas umum
     * SKD CPNS (TWK 65, TIU 80, TKP 166 dari skor maksimal 150/175/225).
     * CATATAN: ambang batas resmi ditetapkan ulang tiap tahun lewat
     * Permenpan-RB dan bisa berbeda untuk formasi khusus (cumlaude,
     * disabilitas, putra/putri daerah, dll) -- sesuaikan kalau perlu.
     */
    protected function createTaxonomies(Program $program): array
    {
        $twk = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'category',
            'code' => 'twk',
            'name' => 'Tes Wawasan Kebangsaan (TWK)',
            'passing_grade' => 65,
            'requires_passage' => false,
        ]);

        $tiu = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'category',
            'code' => 'tiu',
            'name' => 'Tes Intelegensia Umum (TIU)',
            'passing_grade' => 80,
            'requires_passage' => false,
        ]);

        $tkp = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'category',
            'code' => 'tkp',
            'name' => 'Tes Karakteristik Pribadi (TKP)',
            'passing_grade' => 166,
            'requires_passage' => false,
        ]);

        return [$twk, $tiu, $tkp];
    }

    // Kisi-kisi TWK mengikuti Permenpan-RB No. 27 Tahun 2021.
    protected function createTwkTopics(Taxonomy $twk): array
    {
        $data = [
            ['code' => 'nasionalisme', 'name' => 'Nasionalisme'],
            ['code' => 'integritas', 'name' => 'Integritas'],
            ['code' => 'bela-negara', 'name' => 'Bela Negara'],
            ['code' => 'pilar-negara', 'name' => 'Pilar Negara (Pancasila, UUD 1945, NKRI, Bhinneka Tunggal Ika)'],
            ['code' => 'bahasa-negara', 'name' => 'Bahasa Negara (Bahasa Indonesia)'],
        ];

        return collect($data)->map(fn ($t) => Topic::create([
            'taxonomy_id' => $twk->id,
            'code' => $t['code'],
            'name' => $t['name'],
        ]))->all();
    }

    protected function createTiuTopics(Taxonomy $tiu): array
    {
        $data = [
            ['code' => 'verbal', 'name' => 'Kemampuan Verbal (Analogi, Silogisme, Analitis)'],
            ['code' => 'numerik', 'name' => 'Kemampuan Numerik (Berhitung, Deret, Perbandingan, Soal Cerita)'],
            ['code' => 'figural', 'name' => 'Kemampuan Figural (Analogi, Ketidaksamaan, Serial Gambar)'],
        ];

        return collect($data)->map(fn ($t) => Topic::create([
            'taxonomy_id' => $tiu->id,
            'code' => $t['code'],
            'name' => $t['name'],
        ]))->all();
    }

    // 6 aspek TKP mengikuti Permenpan-RB No. 27 Tahun 2021.
    protected function createTkpTopics(Taxonomy $tkp): array
    {
        $data = [
            ['code' => 'pelayanan-publik', 'name' => 'Pelayanan Publik'],
            ['code' => 'jejaring-kerja', 'name' => 'Jejaring Kerja'],
            ['code' => 'sosial-budaya', 'name' => 'Sosial Budaya'],
            ['code' => 'tik', 'name' => 'Teknologi Informasi dan Komunikasi'],
            ['code' => 'profesionalisme', 'name' => 'Profesionalisme'],
            ['code' => 'anti-radikalisme', 'name' => 'Anti Radikalisme'],
        ];

        return collect($data)->map(fn ($t) => Topic::create([
            'taxonomy_id' => $tkp->id,
            'code' => $t['code'],
            'name' => $t['name'],
        ]))->all();
    }

    protected function createBanks(
        Program $program,
        Taxonomy $taxonomy,
        string $prefix,
        int $bankCount,
        int $perBank,
        array $topics,
        string $scoringType,
        int $pointCorrect,
        int $pointWrong
    ): array {
        $banks = [];

        for ($i = 1; $i <= $bankCount; $i++) {
            $bank = QuestionBank::create([
                'program_id' => $program->id,
                'taxonomy_id' => $taxonomy->id,
                'title' => "Bank Soal {$prefix} - Paket {$i}",
                'scoring_type' => $scoringType,
                'point_correct' => $pointCorrect,
                'point_wrong' => $pointWrong,
            ]);

            $this->fillBankQuestions($bank, $topics, $perBank, $scoringType, $pointCorrect, $pointWrong);

            $banks[] = $bank;
        }

        return $banks;
    }

    protected function fillBankQuestions(
        QuestionBank $bank,
        array $topics,
        int $total,
        string $scoringType,
        int $pointCorrect,
        int $pointWrong
    ): void {
        $topicCount = count($topics);
        $base = intdiv($total, $topicCount);
        $remainder = $total % $topicCount;

        foreach ($topics as $topicIndex => $topic) {
            $count = $base + ($topicIndex < $remainder ? 1 : 0);
            $pool = $this->questionPool($topic->code);

            if (empty($pool)) {
                continue;
            }

            for ($n = 0; $n < $count; $n++) {
                $template = $pool[$n % count($pool)];
                $variantSuffix = $n >= count($pool)
                    ? ' (Variasi ' . (intdiv($n, count($pool)) + 1) . ')'
                    : '';

                $question = Question::create([
                    'bank_id' => $bank->id,
                    'question_text' => $template['text'] . $variantSuffix,
                    'media_url' => null,
                    'media_type' => 'none',
                    'passage_id' => null,
                    'topic_id' => $topic->id,
                    'type' => 'pg',
                    'difficulty' => $template['difficulty'],
                    'explanation' => $template['explanation'],
                ]);

                foreach ($template['options'] as $letter => $opt) {
                    if ($scoringType === 'weighted_options') {
                        $points = $opt['points'];
                        $isCorrect = $points === 5;
                    } else {
                        $isCorrect = $letter === $template['correct'];
                        $points = $isCorrect ? $pointCorrect : $pointWrong;
                    }

                    QuestionOption::create([
                        'question_id' => $question->id,
                        'option_text' => $opt['text'],
                        'image_url' => null,
                        'points' => $points,
                        'is_correct' => $isCorrect,
                    ]);
                }
            }
        }
    }

    protected function createAllProgramExams(Program $program, array $twkBanks, array $tiuBanks, array $tkpBanks): array
    {
        $exams = [];

        for ($i = 0; $i < 2; $i++) {
            $exam = Exam::create([
                'program_id' => $program->id,
                'topic_id' => null,
                'part_number' => null,
                'focus_mode' => 'all_program',
                'focus_taxonomy_id' => null,
                'title' => 'Try Out CPNS 2026 - Paket ' . ($i + 1),
                'duration_minutes' => 100,
                'passing_score' => null,
                'require_all_sections_pass' => true,
                'uses_section_timers' => false,
                'is_free_preview' => $i === 0,
            ]);

            $exam->attachBank($twkBanks[$i], ['order' => 1, 'min_passing_score' => 65, 'max_score' => 150]);
            $exam->attachBank($tiuBanks[$i], ['order' => 2, 'min_passing_score' => 80, 'max_score' => 175]);
            $exam->attachBank($tkpBanks[$i], ['order' => 3, 'min_passing_score' => 166, 'max_score' => 225]);

            $exams[] = $exam;
        }

        return $exams;
    }

    protected function createFocusExams(Program $program, array $twkTopics, array $tiuTopics, array $twkBanks, array $tiuBanks): array
    {
        $pilarNegara = collect($twkTopics)->firstWhere('code', 'pilar-negara');
        $numerik = collect($tiuTopics)->firstWhere('code', 'numerik');

        $focus1 = $this->createFocusExam(
            $program, $pilarNegara, $twkBanks[2],
            'Fokus Topik: Pilar Negara (TWK)', 15, true, 1
        );

        $focus2 = $this->createFocusExam(
            $program, $numerik, $tiuBanks[2],
            'Fokus Topik: Kemampuan Numerik (TIU)', 20, false, 1
        );

        return [$focus1, $focus2];
    }

    protected function createFocusExam(Program $program, Topic $topic, QuestionBank $bank, string $title, int $duration, bool $isFreePreview, int $partNumber): Exam
    {
        $exam = Exam::create([
            'program_id' => $program->id,
            'topic_id' => $topic->id,
            'part_number' => $partNumber,
            'focus_mode' => 'focus_topic',
            'focus_taxonomy_id' => $topic->taxonomy_id,
            'title' => $title,
            'duration_minutes' => $duration,
            'passing_score' => null,
            'require_all_sections_pass' => false,
            'uses_section_timers' => false,
            'is_free_preview' => $isFreePreview,
        ]);

        $questions = Question::where('bank_id', $bank->id)->where('topic_id', $topic->id)->get();

        if ($questions->isEmpty()) {
            $this->command->warn("Tidak ada soal topik '{$topic->name}' di bank #{$bank->id} -- exam fokus '{$title}' dibuat tanpa soal.");
            return $exam;
        }

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'taxonomy_id' => $topic->taxonomy_id,
            'question_bank_id' => $bank->id,
            'code' => $topic->code,
            'name' => $topic->name,
            'order' => 1,
            'scoring_type' => $bank->scoring_type,
            'min_passing_score' => null,
            'max_score' => $questions->count() * ($bank->point_correct ?? 5),
            'duration_minutes' => null,
            'is_locked_after_next' => false,
        ]);

        foreach ($questions as $question) {
            $exam->questions()->syncWithoutDetaching([
                $question->id => ['exam_section_id' => $section->id],
            ]);
        }

        return $exam;
    }

    protected function createPackages(Program $program, Exam $exam1, Exam $exam2, Exam $focus1, Exam $focus2, Taxonomy $twk, Taxonomy $tiu): array
    {
        $tryout1 = Package::create([
            'program_id' => $program->id,
            'name' => 'Try Out CPNS 2026 - Paket 1',
            'type' => 'latihan_soal',
            'is_focus_topic' => false,
            'price' => 75000,
            'discount_price' => 50000,
            'duration_days' => 30,
            'description' => 'Simulasi lengkap SKD CPNS 2026: TWK, TIU, dan TKP sesuai format & waktu ujian asli.',
            'features' => ['110 soal (TWK 30, TIU 35, TKP 45)', 'Waktu 100 menit sesuai SKD asli', 'Pembahasan lengkap tiap soal', 'Analisis nilai per kategori'],
        ]);
        $tryout1->exams()->attach($exam1->id);

        $tryout2 = Package::create([
            'program_id' => $program->id,
            'name' => 'Try Out CPNS 2026 - Paket 2',
            'type' => 'latihan_soal',
            'is_focus_topic' => false,
            'price' => 75000,
            'discount_price' => 50000,
            'duration_days' => 30,
            'description' => 'Variasi soal kedua untuk simulasi SKD CPNS 2026 -- cocok dikerjakan setelah Paket 1.',
            'features' => ['110 soal (TWK 30, TIU 35, TKP 45)', 'Waktu 100 menit sesuai SKD asli', 'Pembahasan lengkap tiap soal', 'Analisis nilai per kategori'],
        ]);
        $tryout2->exams()->attach($exam2->id);

        $focusTwk = Package::create([
            'program_id' => $program->id,
            'taxonomy_id' => $twk->id,
            'name' => 'Fokus Topik: Pilar Negara (TWK)',
            'type' => 'latihan_soal',
            'is_focus_topic' => true,
            'focus_taxonomy_id' => $twk->id,
            'price' => 25000,
            'duration_days' => 30,
            'description' => 'Latihan fokus 1 topik TWK yang paling sering keluar: Pilar Negara.',
            'features' => ['Fokus 1 topik', 'Cocok untuk mengejar kelemahan spesifik'],
        ]);
        $focusTwk->exams()->attach($focus1->id);

        $focusTiu = Package::create([
            'program_id' => $program->id,
            'taxonomy_id' => $tiu->id,
            'name' => 'Fokus Topik: Kemampuan Numerik (TIU)',
            'type' => 'latihan_soal',
            'is_focus_topic' => true,
            'focus_taxonomy_id' => $tiu->id,
            'price' => 25000,
            'duration_days' => 30,
            'description' => 'Latihan fokus 1 topik TIU: Kemampuan Numerik (deret, hitung cepat, perbandingan).',
            'features' => ['Fokus 1 topik', 'Cocok untuk mengejar kelemahan spesifik'],
        ]);
        $focusTiu->exams()->attach($focus2->id);

        return [$tryout1, $tryout2, $focusTwk, $focusTiu];
    }

    protected function createSubscriptionPlan(Program $program): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Langganan CPNS 2026',
            'tagline' => 'Semua Latihan CPNS, Satu Langganan',
            'description' => 'Akses semua paket latihan soal & try out CPNS 2026 selama masa aktif langganan, tanpa beli paket satuan.',
            'features' => ['Akses semua paket latihan CPNS 2026', 'Pembahasan lengkap tiap soal', 'Leaderboard mingguan', 'Prioritas dukungan CS'],
            'duration_days' => 30,
            'program_slot_count' => null,
            'program_id' => $program->id,
            'price' => 150000,
            'is_active' => true,
            'is_featured' => true,
        ]);
    }

    protected function createPromo(Package $applicablePackage): Promo
    {
        return Promo::create([
            'title' => 'Promo CPNS 2026',
            'description' => 'Diskon khusus untuk paket Try Out CPNS 2026 Paket 1.',
            'terms' => 'Berlaku untuk 1x transaksi per akun. Tidak dapat digabung dengan promo lain.',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'code' => 'CPNS2026',
            'valid_until' => now()->addDays(30)->toDateString(),
            'total_quota' => 100,
            'new_user_only' => false,
            'usage_limit_per_user' => 1,
            'max_discount_amount' => 50000,
            'valid_from' => now(),
            'is_active' => true,
            'applicable_package_id' => $applicablePackage->id,
            'source' => 'manual',
        ]);
    }

    /**
     * Pool soal dummy per kode topik. Setiap item: text, options (A-E),
     * correct (TWK/TIU) atau points 1-5 per opsi (TKP), explanation,
     * difficulty. Kalau jumlah soal yang dibutuhkan lebih besar dari pool,
     * generator akan mengulang dengan label "(Variasi N)".
     */
    protected function questionPool(string $topicCode): array
    {
        return match ($topicCode) {
            'nasionalisme' => [
                [
                    'text' => 'Sikap yang mencerminkan nilai nasionalisme dalam kehidupan sehari-hari seorang ASN adalah...',
                    'options' => [
                        'A' => ['text' => 'Menggunakan produk luar negeri karena kualitasnya lebih baik'],
                        'B' => ['text' => 'Mendahulukan kepentingan bangsa dan negara di atas kepentingan pribadi atau golongan'],
                        'C' => ['text' => 'Bergaul hanya dengan rekan sedaerah asal'],
                        'D' => ['text' => 'Mengutamakan efisiensi kerja tanpa mempertimbangkan dampak sosial'],
                        'E' => ['text' => 'Menghindari diskusi tentang isu kebangsaan di tempat kerja'],
                    ],
                    'correct' => 'B',
                    'explanation' => 'Nasionalisme mengutamakan kepentingan bangsa dan negara di atas kepentingan pribadi/golongan, sejalan dengan sila ketiga Pancasila.',
                    'difficulty' => 'mudah',
                ],
                [
                    'text' => 'Semangat persatuan yang lahir dari ikrar Sumpah Pemuda 1928 paling tepat digambarkan sebagai...',
                    'options' => [
                        'A' => ['text' => 'Pengakuan satu tanah air, satu bangsa, dan satu bahasa persatuan Indonesia'],
                        'B' => ['text' => 'Kesepakatan mendirikan partai politik nasional'],
                        'C' => ['text' => 'Deklarasi kemerdekaan Indonesia'],
                        'D' => ['text' => 'Perjanjian gencatan senjata antar suku'],
                        'E' => ['text' => 'Pembentukan tentara nasional pertama'],
                    ],
                    'correct' => 'A',
                    'explanation' => 'Sumpah Pemuda 1928 berisi ikrar satu nusa, satu bangsa, dan satu bahasa persatuan -- bukan soal partai, kemerdekaan, atau militer.',
                    'difficulty' => 'sedang',
                ],
                [
                    'text' => 'Berikut ini yang BUKAN merupakan wujud nasionalisme di era globalisasi adalah...',
                    'options' => [
                        'A' => ['text' => 'Bangga menggunakan produk dalam negeri'],
                        'B' => ['text' => 'Melestarikan budaya lokal di tengah arus budaya asing'],
                        'C' => ['text' => 'Menutup diri sepenuhnya dari kerja sama internasional'],
                        'D' => ['text' => 'Menjaga nama baik bangsa saat berinteraksi di dunia internasional'],
                        'E' => ['text' => 'Berperan aktif membangun daerah asal maupun negara'],
                    ],
                    'correct' => 'C',
                    'explanation' => 'Nasionalisme modern tidak berarti isolasi total dari dunia internasional; menutup diri sepenuhnya justru bertentangan dengan semangat gotong royong bangsa dalam pergaulan global.',
                    'difficulty' => 'sedang',
                ],
            ],
            'integritas' => [
                [
                    'text' => 'Seorang ASN menemukan kesalahan pencatatan anggaran yang menguntungkan atasannya secara pribadi. Tindakan yang paling mencerminkan integritas adalah...',
                    'options' => [
                        'A' => ['text' => 'Diam saja karena bukan urusan sendiri'],
                        'B' => ['text' => 'Melaporkan temuan tersebut sesuai prosedur yang berlaku'],
                        'C' => ['text' => 'Membicarakannya ke rekan kerja lain agar viral'],
                        'D' => ['text' => 'Meminta bagian dari keuntungan tersebut'],
                        'E' => ['text' => 'Menunggu sampai ada yang menyadari sendiri'],
                    ],
                    'correct' => 'B',
                    'explanation' => 'Integritas menuntut kejujuran dan keberanian melaporkan penyimpangan lewat jalur/prosedur resmi, bukan diam, menyebarkan gosip, atau mengambil keuntungan pribadi.',
                    'difficulty' => 'sedang',
                ],
                [
                    'text' => 'Nilai integritas dalam core values ASN "BerAKHLAK" paling tepat ditunjukkan melalui perilaku...',
                    'options' => [
                        'A' => ['text' => 'Bertindak sesuai nilai, norma, dan etika organisasi dalam segala situasi'],
                        'B' => ['text' => 'Bekerja cepat tanpa memperhatikan aturan'],
                        'C' => ['text' => 'Mengikuti perintah atasan meskipun bertentangan dengan hukum'],
                        'D' => ['text' => 'Menutupi kesalahan demi menjaga nama baik instansi'],
                        'E' => ['text' => 'Membagi tugas kepada bawahan agar pekerjaan cepat selesai'],
                    ],
                    'correct' => 'A',
                    'explanation' => 'Integritas berarti konsisten bertindak sesuai nilai, norma, dan etika, bahkan ketika tidak diawasi -- bukan sekadar patuh buta atau menutupi kesalahan.',
                    'difficulty' => 'sulit',
                ],
            ],
            'bela-negara' => [
                [
                    'text' => 'Upaya bela negara yang paling relevan dilakukan seorang ASN dalam pekerjaan sehari-hari adalah...',
                    'options' => [
                        'A' => ['text' => 'Ikut wajib militer'],
                        'B' => ['text' => 'Bekerja secara profesional, jujur, dan disiplin demi kemajuan pelayanan publik'],
                        'C' => ['text' => 'Membeli senjata untuk pertahanan pribadi'],
                        'D' => ['text' => 'Menghindari tugas yang berisiko'],
                        'E' => ['text' => 'Mengkritik kebijakan pemerintah di media sosial tanpa dasar'],
                    ],
                    'correct' => 'B',
                    'explanation' => 'Bela negara bagi ASN diwujudkan melalui profesionalisme dan dedikasi dalam pekerjaan, bukan hanya lewat wajib militer atau senjata.',
                    'difficulty' => 'mudah',
                ],
                [
                    'text' => 'Dasar hukum kewajiban bela negara bagi warga negara Indonesia diatur dalam UUD 1945 pasal...',
                    'options' => [
                        'A' => ['text' => 'Pasal 27 ayat (3)'],
                        'B' => ['text' => 'Pasal 28 ayat (1)'],
                        'C' => ['text' => 'Pasal 29 ayat (2)'],
                        'D' => ['text' => 'Pasal 31 ayat (1)'],
                        'E' => ['text' => 'Pasal 33 ayat (3)'],
                    ],
                    'correct' => 'A',
                    'explanation' => 'Pasal 27 ayat (3) UUD 1945 menyatakan setiap warga negara berhak dan wajib ikut serta dalam upaya pembelaan negara.',
                    'difficulty' => 'sedang',
                ],
            ],
            'pilar-negara' => [
                [
                    'text' => 'Kedudukan Pancasila sebagai dasar negara berarti Pancasila berfungsi sebagai...',
                    'options' => [
                        'A' => ['text' => 'Sumber dari segala sumber hukum di Indonesia'],
                        'B' => ['text' => 'Lambang organisasi kenegaraan'],
                        'C' => ['text' => 'Alat pemersatu partai politik'],
                        'D' => ['text' => 'Ideologi yang bersifat sementara'],
                        'E' => ['text' => 'Aturan teknis administrasi pemerintahan'],
                    ],
                    'correct' => 'A',
                    'explanation' => 'Sebagai dasar negara, Pancasila menjadi sumber dari segala sumber hukum (grundnorm) yang menjiwai seluruh peraturan perundang-undangan di Indonesia.',
                    'difficulty' => 'mudah',
                ],
                [
                    'text' => 'Perubahan (amandemen) UUD 1945 telah dilakukan sebanyak...',
                    'options' => [
                        'A' => ['text' => '2 kali'],
                        'B' => ['text' => '3 kali'],
                        'C' => ['text' => '4 kali'],
                        'D' => ['text' => '5 kali'],
                        'E' => ['text' => '6 kali'],
                    ],
                    'correct' => 'C',
                    'explanation' => 'UUD 1945 telah diamandemen sebanyak 4 kali, yaitu pada tahun 1999, 2000, 2001, dan 2002.',
                    'difficulty' => 'sedang',
                ],
                [
                    'text' => 'Semboyan "Bhinneka Tunggal Ika" yang menjadi salah satu pilar kebangsaan Indonesia berasal dari kitab...',
                    'options' => [
                        'A' => ['text' => 'Negarakertagama'],
                        'B' => ['text' => 'Sutasoma'],
                        'C' => ['text' => 'Pararaton'],
                        'D' => ['text' => 'Arjunawiwaha'],
                        'E' => ['text' => 'Baratayuda'],
                    ],
                    'correct' => 'B',
                    'explanation' => 'Semboyan "Bhinneka Tunggal Ika" berasal dari kitab Sutasoma karya Mpu Tantular pada masa Majapahit.',
                    'difficulty' => 'sulit',
                ],
            ],
            'bahasa-negara' => [
                [
                    'text' => 'Kalimat berikut yang menggunakan ejaan sesuai PUEBI adalah...',
                    'options' => [
                        'A' => ['text' => 'Rapat itu di laksanakan di ruang serba guna.'],
                        'B' => ['text' => 'Rapat itu dilaksanakan di ruang serbaguna.'],
                        'C' => ['text' => 'Rapat itu di laksanakan diruang serba guna.'],
                        'D' => ['text' => 'Rapat itu dilaksanakan diruang serba-guna.'],
                        'E' => ['text' => 'Rapat itu dilaksanakan di-ruang serbaguna.'],
                    ],
                    'correct' => 'B',
                    'explanation' => '"Dilaksanakan" (awalan) ditulis serangkai, sedangkan "di ruang" (kata depan penunjuk tempat) ditulis terpisah; "serbaguna" ditulis serangkai sebagai satu kata majemuk.',
                    'difficulty' => 'sedang',
                ],
                [
                    'text' => 'Kata baku yang tepat untuk melengkapi kalimat "Pemerintah akan ... anggaran untuk sektor pendidikan" adalah...',
                    'options' => [
                        'A' => ['text' => 'Menganalisa'],
                        'B' => ['text' => 'Mengalokasikan'],
                        'C' => ['text' => 'Mengalokir'],
                        'D' => ['text' => 'Mengaloksikan'],
                        'E' => ['text' => 'Meng-alokasi'],
                    ],
                    'correct' => 'B',
                    'explanation' => 'Bentuk baku dari kata dasar "alokasi" dengan imbuhan meN-...-kan adalah "mengalokasikan".',
                    'difficulty' => 'mudah',
                ],
            ],
            'verbal' => [
                [
                    'text' => 'LUKA : BENGKAK = PUPUK : ...',
                    'options' => [
                        'A' => ['text' => 'Subur'],
                        'B' => ['text' => 'Kebun'],
                        'C' => ['text' => 'Kotoran'],
                        'D' => ['text' => 'Tumbuhan'],
                        'E' => ['text' => 'Kompos'],
                    ],
                    'correct' => 'A',
                    'explanation' => 'Luka menyebabkan bengkak, sama seperti pupuk menyebabkan (tanaman menjadi) subur -- hubungan sebab-akibat.',
                    'difficulty' => 'sedang',
                ],
                [
                    'text' => 'Premis 1: Semua pegawai teladan datang tepat waktu. Premis 2: Sebagian pegawai teladan menjadi mentor pegawai baru. Kesimpulan yang paling tepat adalah...',
                    'options' => [
                        'A' => ['text' => 'Semua pegawai yang datang tepat waktu adalah mentor'],
                        'B' => ['text' => 'Sebagian pegawai yang datang tepat waktu adalah mentor'],
                        'C' => ['text' => 'Semua mentor pegawai baru adalah pegawai teladan'],
                        'D' => ['text' => 'Tidak ada pegawai teladan yang menjadi mentor'],
                        'E' => ['text' => 'Semua pegawai yang datang tepat waktu adalah pegawai teladan'],
                    ],
                    'correct' => 'B',
                    'explanation' => 'Karena semua pegawai teladan datang tepat waktu, dan sebagian dari mereka menjadi mentor, maka sebagian pegawai yang datang tepat waktu adalah mentor.',
                    'difficulty' => 'sulit',
                ],
                [
                    'text' => 'Lima peserta pelatihan (Rian, Sinta, Tono, Una, Vera) presentasi berurutan dengan ketentuan: (1) Sinta tidak presentasi pertama maupun terakhir, (2) Tono presentasi tepat setelah Rian, (3) Una presentasi urutan ketiga. Jika Vera presentasi pertama, siapa yang presentasi urutan keempat?',
                    'options' => [
                        'A' => ['text' => 'Rian'],
                        'B' => ['text' => 'Sinta'],
                        'C' => ['text' => 'Tono'],
                        'D' => ['text' => 'Una'],
                        'E' => ['text' => 'Informasi tidak cukup'],
                    ],
                    'correct' => 'C',
                    'explanation' => 'Urutan yang memenuhi semua syarat: Vera(1), Rian(2), Una(3), Tono(4), Sinta(5). Urutan keempat adalah Tono.',
                    'difficulty' => 'sulit',
                ],
            ],
            'numerik' => [
                [
                    'text' => 'Lanjutkan deret berikut: 7, 13, 25, 49, ..., ...',
                    'options' => [
                        'A' => ['text' => '93, 183'],
                        'B' => ['text' => '97, 193'],
                        'C' => ['text' => '95, 191'],
                        'D' => ['text' => '94, 190'],
                        'E' => ['text' => '92, 192'],
                    ],
                    'correct' => 'B',
                    'explanation' => 'Pola: setiap suku dikali 2 lalu dikurangi 1: 7x2-1=13, 13x2-1=25, 25x2-1=49, 49x2-1=97, 97x2-1=193.',
                    'difficulty' => 'sedang',
                ],
                [
                    'text' => 'Suatu proyek dikerjakan 12 pekerja selesai dalam 20 hari. Berapa tambahan pekerja diperlukan agar proyek selesai dalam 16 hari?',
                    'options' => [
                        'A' => ['text' => '15 orang'],
                        'B' => ['text' => '12 orang'],
                        'C' => ['text' => '8 orang'],
                        'D' => ['text' => '5 orang'],
                        'E' => ['text' => '3 orang'],
                    ],
                    'correct' => 'E',
                    'explanation' => 'Total pekerjaan = 12x20 = 240 hari-orang. Untuk 16 hari dibutuhkan 240/16 = 15 pekerja, artinya tambahan 15-12 = 3 orang.',
                    'difficulty' => 'sedang',
                ],
                [
                    'text' => 'Sebuah toko memberikan diskon berturut-turut 20% lalu tambahan 10% khusus member. Jika total bayar member untuk sebuah gaun adalah Rp144.000, berapa harga label asli gaun tersebut?',
                    'options' => [
                        'A' => ['text' => 'Rp180.000'],
                        'B' => ['text' => 'Rp200.000'],
                        'C' => ['text' => 'Rp220.000'],
                        'D' => ['text' => 'Rp240.000'],
                        'E' => ['text' => 'Rp250.000'],
                    ],
                    'correct' => 'B',
                    'explanation' => 'Harga setelah diskon 20% = 0,8x; setelah tambahan diskon 10% = 0,8x x 0,9 = 0,72x = 144.000, sehingga x = 144.000 / 0,72 = Rp200.000.',
                    'difficulty' => 'sulit',
                ],
            ],
            'figural' => [
                [
                    'text' => '[Soal figural -- dummy teks, gantikan dengan gambar asli] Sebuah pola berisi 4 gambar berurutan: setiap gambar berikutnya adalah gambar sebelumnya yang diputar 90 derajat searah jarum jam. Manakah gambar kelima yang paling tepat melanjutkan pola tersebut?',
                    'options' => [
                        'A' => ['text' => 'Gambar diputar 90° searah jarum jam dari gambar ke-4'],
                        'B' => ['text' => 'Gambar diputar 180° dari gambar ke-4'],
                        'C' => ['text' => 'Gambar sama persis dengan gambar ke-3'],
                        'D' => ['text' => 'Gambar dicerminkan horizontal dari gambar ke-4'],
                        'E' => ['text' => 'Gambar diputar 90° berlawanan arah jarum jam dari gambar ke-4'],
                    ],
                    'correct' => 'A',
                    'explanation' => 'Pola konsisten memutar 90° searah jarum jam pada tiap langkah, sehingga gambar kelima adalah hasil rotasi 90° searah jarum jam dari gambar keempat.',
                    'difficulty' => 'sedang',
                ],
                [
                    'text' => '[Soal figural -- dummy teks, gantikan dengan gambar asli] Perhatikan deret bentuk yang jumlah sisinya bertambah 1 di setiap langkah (segitiga, segiempat, segilima, ...). Bentuk apa yang melanjutkan deret tersebut?',
                    'options' => [
                        'A' => ['text' => 'Segienam'],
                        'B' => ['text' => 'Segilima'],
                        'C' => ['text' => 'Lingkaran'],
                        'D' => ['text' => 'Segiempat'],
                        'E' => ['text' => 'Segitiga'],
                    ],
                    'correct' => 'A',
                    'explanation' => 'Pola menambah satu sisi di setiap langkah (3 -> 4 -> 5 -> ...), sehingga bentuk berikutnya adalah segienam (6 sisi).',
                    'difficulty' => 'mudah',
                ],
            ],
            'pelayanan-publik' => [
                [
                    'text' => 'Anda bertugas melayani masyarakat di loket, namun antrean sangat panjang dan beberapa warga mulai mengeluh. Yang Anda lakukan adalah...',
                    'options' => [
                        'A' => ['text' => 'Tetap bekerja seperti biasa tanpa mempedulikan keluhan', 'points' => 1],
                        'B' => ['text' => 'Meminta warga untuk bersabar tanpa penjelasan lebih lanjut', 'points' => 2],
                        'C' => ['text' => 'Menjelaskan penyebab antrean dan memperkirakan waktu tunggu kepada warga', 'points' => 3],
                        'D' => ['text' => 'Menjelaskan situasi, mempercepat proses semaksimal mungkin, dan berkoordinasi dengan rekan untuk membuka loket tambahan', 'points' => 5],
                        'E' => ['text' => 'Menyalahkan sistem antrean kepada warga yang mengeluh', 'points' => 1],
                    ],
                    'explanation' => 'Respons terbaik menggabungkan komunikasi transparan, tindakan konkret mempercepat layanan, dan inisiatif berkoordinasi -- bukan sekadar menjelaskan atau bersikap pasif.',
                    'difficulty' => 'sedang',
                ],
                [
                    'text' => 'Seorang warga lanjut usia kesulitan memahami prosedur pengurusan dokumen di kantor Anda. Sikap yang paling tepat adalah...',
                    'options' => [
                        'A' => ['text' => 'Meminta warga tersebut membaca sendiri papan pengumuman', 'points' => 1],
                        'B' => ['text' => 'Melayani seadanya karena banyak warga lain yang antre', 'points' => 2],
                        'C' => ['text' => 'Menjelaskan prosedur secara singkat sekali saja', 'points' => 3],
                        'D' => ['text' => 'Mendampingi dan menjelaskan prosedur dengan sabar hingga warga tersebut paham', 'points' => 5],
                        'E' => ['text' => 'Mengarahkan ke petugas lain tanpa penjelasan', 'points' => 2],
                    ],
                    'explanation' => 'Pelayanan publik yang prima menuntut empati dan kesabaran ekstra kepada kelompok rentan seperti lansia, bukan sekadar mengarahkan atau melayani seadanya.',
                    'difficulty' => 'mudah',
                ],
            ],
            'jejaring-kerja' => [
                [
                    'text' => 'Anda ditugaskan menyelesaikan proyek lintas unit yang melibatkan divisi lain yang belum pernah Anda ajak bekerja sama. Langkah pertama yang paling tepat adalah...',
                    'options' => [
                        'A' => ['text' => 'Mengerjakan bagian Anda sendiri tanpa berkoordinasi', 'points' => 1],
                        'B' => ['text' => 'Menunggu divisi lain menghubungi Anda terlebih dahulu', 'points' => 2],
                        'C' => ['text' => 'Mengirim email singkat berisi permintaan data tanpa perkenalan', 'points' => 3],
                        'D' => ['text' => 'Mengajak diskusi awal untuk membangun kesepahaman tujuan dan pembagian peran', 'points' => 5],
                        'E' => ['text' => 'Meminta atasan yang menjembatani seluruh komunikasi', 'points' => 2],
                    ],
                    'explanation' => 'Membangun jejaring kerja yang efektif dimulai dari komunikasi proaktif dan kesepahaman tujuan bersama, bukan menghindar atau melimpahkan sepenuhnya ke pihak lain.',
                    'difficulty' => 'sedang',
                ],
                [
                    'text' => 'Rekan kerja dari instansi mitra meminta bantuan di luar jam kerja resmi untuk kepentingan program bersama. Sikap yang paling tepat adalah...',
                    'options' => [
                        'A' => ['text' => 'Menolak mentah-mentah karena bukan jam kerja', 'points' => 1],
                        'B' => ['text' => 'Membantu seadanya tanpa komunikasi lanjutan', 'points' => 2],
                        'C' => ['text' => 'Membantu jika ada waktu luang saja', 'points' => 3],
                        'D' => ['text' => 'Mendiskusikan kembali skala prioritas dan tetap membantu secara proporsional demi keberlanjutan kerja sama', 'points' => 5],
                        'E' => ['text' => 'Membantu penuh meski mengorbankan waktu istirahat', 'points' => 3],
                    ],
                    'explanation' => 'Menjaga hubungan kerja sama antarinstansi penting, tetapi tetap perlu proporsional dan terkomunikasikan dengan baik agar berkelanjutan.',
                    'difficulty' => 'sulit',
                ],
            ],
            'sosial-budaya' => [
                [
                    'text' => 'Dalam satu tim kerja, terdapat rekan dari latar belakang suku dan agama yang berbeda dengan Anda. Sikap yang paling tepat adalah...',
                    'options' => [
                        'A' => ['text' => 'Menghindari interaksi agar tidak salah paham', 'points' => 1],
                        'B' => ['text' => 'Berinteraksi seperlunya saja', 'points' => 2],
                        'C' => ['text' => 'Bersikap sopan namun tetap menjaga jarak', 'points' => 3],
                        'D' => ['text' => 'Menghargai perbedaan dan membangun kerja sama yang inklusif', 'points' => 5],
                        'E' => ['text' => 'Memaksakan kebiasaan sendiri kepada rekan tersebut', 'points' => 1],
                    ],
                    'explanation' => 'Kepekaan sosial budaya menuntut sikap menghargai keberagaman dan membangun kolaborasi yang inklusif, bukan menghindar atau memaksakan pandangan.',
                    'difficulty' => 'mudah',
                ],
                [
                    'text' => 'Anda ditugaskan di daerah dengan adat istiadat yang berbeda dari daerah asal Anda. Sikap yang paling tepat adalah...',
                    'options' => [
                        'A' => ['text' => 'Tetap menjalankan kebiasaan daerah asal tanpa menyesuaikan diri', 'points' => 1],
                        'B' => ['text' => 'Mengabaikan adat setempat karena dianggap tidak penting', 'points' => 1],
                        'C' => ['text' => 'Mengikuti adat setempat sekadarnya agar tidak dianggap kaku', 'points' => 3],
                        'D' => ['text' => 'Mempelajari dan menghormati adat setempat sambil tetap profesional dalam tugas', 'points' => 5],
                        'E' => ['text' => 'Meminta dipindahtugaskan ke daerah lain', 'points' => 1],
                    ],
                    'explanation' => 'Kemampuan beradaptasi dengan sosial budaya setempat, dengan tetap menjaga profesionalisme, adalah respons terbaik seorang ASN yang ditugaskan lintas daerah.',
                    'difficulty' => 'sedang',
                ],
            ],
            'tik' => [
                [
                    'text' => 'Anda menerima dokumen penting dari sistem informasi kantor yang formatnya belum Anda kuasai sepenuhnya. Sikap yang paling tepat adalah...',
                    'options' => [
                        'A' => ['text' => 'Mengabaikan dokumen tersebut', 'points' => 1],
                        'B' => ['text' => 'Meminta rekan mengerjakan seluruhnya untuk Anda', 'points' => 2],
                        'C' => ['text' => 'Mencoba-coba sendiri tanpa mempelajari panduan', 'points' => 3],
                        'D' => ['text' => 'Mempelajari panduan penggunaan sistem dan bertanya kepada tim IT jika diperlukan', 'points' => 5],
                        'E' => ['text' => 'Menunda pekerjaan hingga ada pelatihan resmi', 'points' => 2],
                    ],
                    'explanation' => 'Kompetensi TIK ASN modern menuntut inisiatif belajar mandiri dan memanfaatkan sumber daya (panduan, tim IT) secara proaktif, bukan menghindar atau bergantung penuh pada orang lain.',
                    'difficulty' => 'sedang',
                ],
                [
                    'text' => 'Kantor Anda baru menerapkan sistem persuratan elektronik menggantikan sistem manual. Sikap yang paling tepat adalah...',
                    'options' => [
                        'A' => ['text' => 'Menolak menggunakan sistem baru karena sudah nyaman dengan cara lama', 'points' => 1],
                        'B' => ['text' => 'Menggunakan sistem baru hanya jika diawasi atasan', 'points' => 2],
                        'C' => ['text' => 'Menggunakan sistem baru dengan bantuan rekan setiap saat', 'points' => 3],
                        'D' => ['text' => 'Mempelajari sistem baru secara mandiri dan mendorong rekan lain untuk ikut beradaptasi', 'points' => 5],
                        'E' => ['text' => 'Menggunakan sistem lama secara diam-diam', 'points' => 1],
                    ],
                    'explanation' => 'Adaptasi terhadap perubahan teknologi kerja perlu disikapi secara proaktif dan mandiri, bahkan turut mendorong rekan kerja lain untuk beradaptasi.',
                    'difficulty' => 'sulit',
                ],
            ],
            'profesionalisme' => [
                [
                    'text' => 'Anda diberi tugas di luar bidang keahlian utama Anda karena keterbatasan SDM di unit kerja. Sikap yang paling tepat adalah...',
                    'options' => [
                        'A' => ['text' => 'Menolak tugas tersebut karena bukan bidang Anda', 'points' => 1],
                        'B' => ['text' => 'Mengerjakan asal selesai tanpa memperhatikan kualitas', 'points' => 2],
                        'C' => ['text' => 'Mengerjakan seadanya sambil menunggu rekan lain yang lebih ahli', 'points' => 3],
                        'D' => ['text' => 'Mempelajari hal yang diperlukan dan mengerjakan tugas dengan standar terbaik yang bisa dicapai', 'points' => 5],
                        'E' => ['text' => 'Melimpahkan tugas tersebut ke bawahan sepenuhnya', 'points' => 2],
                    ],
                    'explanation' => 'Profesionalisme berarti tetap berkomitmen pada kualitas kerja meski di luar keahlian utama, dengan cara belajar cepat dan bertanggung jawab atas hasil.',
                    'difficulty' => 'sedang',
                ],
                [
                    'text' => 'Anda menyelesaikan pekerjaan lebih cepat dari target, namun menemukan ada bagian yang bisa ditingkatkan kualitasnya jika diberi waktu tambahan. Sikap yang paling tepat adalah...',
                    'options' => [
                        'A' => ['text' => 'Menyerahkan pekerjaan apa adanya karena sudah memenuhi target waktu', 'points' => 2],
                        'B' => ['text' => 'Menyerahkan pekerjaan lalu mengerjakan tugas lain tanpa evaluasi', 'points' => 1],
                        'C' => ['text' => 'Menunggu instruksi atasan sebelum melakukan apa pun', 'points' => 2],
                        'D' => ['text' => 'Menggunakan sisa waktu untuk meningkatkan kualitas hasil sebelum diserahkan', 'points' => 5],
                        'E' => ['text' => 'Mengerjakan tugas lain tanpa menyempurnakan hasil sebelumnya', 'points' => 2],
                    ],
                    'explanation' => 'Profesionalisme mendorong inisiatif meningkatkan kualitas hasil kerja saat ada kesempatan, bukan sekadar memenuhi target minimal.',
                    'difficulty' => 'sulit',
                ],
            ],
            'anti-radikalisme' => [
                [
                    'text' => 'Anda mendapati salah satu rekan kerja aktif menyebarkan paham yang mengarah pada intoleransi antarumat beragama di grup percakapan kantor. Sikap yang paling tepat adalah...',
                    'options' => [
                        'A' => ['text' => 'Ikut menyebarkan agar terlihat solid dengan rekan', 'points' => 1],
                        'B' => ['text' => 'Diam saja karena tidak ingin ikut campur', 'points' => 2],
                        'C' => ['text' => 'Keluar dari grup tanpa melakukan tindakan lain', 'points' => 3],
                        'D' => ['text' => 'Mengingatkan secara langsung dan melaporkan sesuai prosedur jika berlanjut', 'points' => 5],
                        'E' => ['text' => 'Membalas dengan hujatan di grup yang sama', 'points' => 2],
                    ],
                    'explanation' => 'Sikap anti-radikalisme yang tepat adalah aktif menolak paham intoleran melalui teguran langsung dan pelaporan sesuai prosedur, bukan diam atau justru membalas dengan cara yang sama buruknya.',
                    'difficulty' => 'sedang',
                ],
                [
                    'text' => 'Sebagai ASN, sikap yang paling tepat terhadap ideologi yang bertentangan dengan Pancasila dan NKRI adalah...',
                    'options' => [
                        'A' => ['text' => 'Bersikap netral dan tidak memihak ideologi apa pun', 'points' => 2],
                        'B' => ['text' => 'Mempelajarinya agar dapat memahami sudut pandang lain tanpa menolak', 'points' => 2],
                        'C' => ['text' => 'Membiarkannya selama tidak mengganggu pekerjaan', 'points' => 1],
                        'D' => ['text' => 'Menolak tegas dan aktif menjaga nilai-nilai Pancasila serta NKRI dalam sikap dan tindakan sehari-hari', 'points' => 5],
                        'E' => ['text' => 'Melaporkannya hanya jika diminta atasan', 'points' => 2],
                    ],
                    'explanation' => 'ASN wajib menolak tegas ideologi yang bertentangan dengan Pancasila dan NKRI, serta aktif menjaga nilai kebangsaan dalam sikap dan tindakan -- bukan bersikap netral atau pasif.',
                    'difficulty' => 'sulit',
                ],
            ],
            default => [],
        };
    }
}
