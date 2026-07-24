<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Package;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Taxonomy;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Simulasi alur penuh siswa: admin bikin soal -> bikin exam+package
 * -> user beli (checkout + webhook Midtrans) -> user ngerjain ujian
 * -> attempt selesai & dinilai.
 *
 * Tahap 1-4 (soal sampai attempt selesai) sudah diverifikasi terhadap kode
 * controller/model asli. Tahap 5-6 (leaderboard & performa) BELUM disertakan
 * di sini -- masih perlu lihat GeneratePracticeLeaderboardJob,
 * PracticeLeaderboardController, dan Promo model dulu sebelum ditulis,
 * supaya tidak menebak lagi.
 */
class StudentJourneyEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Server key dipakai untuk hitung signature webhook Midtrans di test ini.
        Config::set('services.midtrans.server_key', 'test-server-key');
    }

    public function test_alur_penuh_dari_soal_dibuat_sampai_attempt_selesai_dinilai(): void
    {
        // ========================================
        // TAHAP 1: Admin bikin soal (Question Bank + Question + Options)
        // ========================================
        $program = Program::factory()->create();

        $taxonomy = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'category',
            'code' => 'TX-' . uniqid(),
            'name' => 'TWK',
        ]);

        $bank = QuestionBank::factory()->create([
            'program_id' => $program->id,
            'taxonomy_id' => $taxonomy->id,
            'scoring_type' => 'single_correct',
            'point_correct' => 5,
            'point_wrong' => 0,
        ]);

        $question = Question::create([
            'bank_id' => $bank->id,
            'question_text' => 'Ibu kota Indonesia adalah?',
            'type' => 'pg',
            'difficulty' => 'mudah',
        ]);

        $correctOption = QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => 'Jakarta',
            'is_correct' => true,
            'points' => 5,
        ]);

        QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => 'Bandung',
            'is_correct' => false,
            'points' => 0,
        ]);

        $this->assertDatabaseHas('questions', ['id' => $question->id]);

        // ========================================
        // TAHAP 2: Admin susun Exam + Section, lalu tempelkan soal
        // ========================================
        $exam = Exam::factory()->create(['program_id' => $program->id]);

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'taxonomy_id' => $taxonomy->id,
            'question_bank_id' => $bank->id,
            'code' => 'SEC-' . uniqid(),
            'name' => 'TWK Section',
            'order' => 0,
            'scoring_type' => 'single_correct',
            'min_passing_score' => 65,
            'max_score' => 100,
        ]);

        // exam_questions pivot: exam <-> question, dengan exam_section_id
        $exam->questions()->attach($question->id, ['exam_section_id' => $section->id]);

        // Package butuh program_id ATAU taxonomy_id (lihat Package::booted saving guard)
        $package = Package::create([
            'program_id' => $program->id,
            'name' => 'Paket TWK Basic',
            'type' => 'latihan_soal',
            'price' => 50000,
            'duration_days' => 30,
        ]);

        // package_exam pivot: package <-> exam
        $package->exams()->attach($exam->id);

        // ========================================
        // TAHAP 3: User beli Package (checkout + webhook Midtrans)
        // ========================================
        $user = User::factory()->create();

        // Mock MidtransService supaya checkout tidak benar-benar hit API Midtrans.
        // createTransaction tetap bikin Transaction record asli di DB (alur checkout
        // tetap teruji), cuma panggilan Snap::getSnapToken() yang di-skip.
        $this->partialMock(MidtransService::class, function ($mock) {
            $mock->shouldReceive('createTransaction')
                ->andReturnUsing(function ($u, $pkg, $promo = null) {
                    $amount = (float) ($pkg->discount_price ?? $pkg->price);

                    $transaction = Transaction::create([
                        'user_id' => $u->id,
                        'package_id' => $pkg->id,
                        'promo_id' => $promo?->id,
                        'midtrans_order_id' => 'TEST-ORDER-' . uniqid(),
                        'invoice_number' => 'INV-TEST-' . uniqid(),
                        'amount' => $amount,
                        'discount_amount' => 0,
                        'payment_method' => null,
                        'status' => 'pending',
                        'expires_at' => now()->addHours(24),
                    ]);

                    $transaction->setAttribute('snap_token', 'fake-snap-token-for-test');

                    return $transaction;
                });
        });

        $checkoutResponse = $this->actingAs($user)->postJson('/api/transactions/checkout', [
            'package_id' => $package->id,
        ]);

        $checkoutResponse->assertStatus(201);

        $transaction = Transaction::where('user_id', $user->id)
            ->where('package_id', $package->id)
            ->latest()
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('pending', $transaction->status);

        // Simulasikan webhook Midtrans "settlement" masuk, dengan signature valid
        $statusCode = '200';
        $grossAmount = '50000.00';
        $serverKey = Config::get('services.midtrans.server_key');
        $signature = hash('sha512', $transaction->midtrans_order_id . $statusCode . $grossAmount . $serverKey);

        $webhookResponse = $this->postJson('/api/midtrans/callback', [
            'order_id' => $transaction->midtrans_order_id,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => 'bank_transfer',
            'signature_key' => $signature,
        ]);

        $webhookResponse->assertStatus(200);

        $transaction->refresh();
        $this->assertEquals('success', $transaction->status);

        // Pastikan enrollment otomatis aktif
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
        ]);

        // Idempotency check: webhook settlement yang sama masuk dua kali tidak boleh error / dobel enrollment
        $this->postJson('/api/midtrans/callback', [
            'order_id' => $transaction->midtrans_order_id,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => 'bank_transfer',
            'signature_key' => $signature,
        ])->assertStatus(200);

        $this->assertDatabaseCount('enrollments', 1);

        // ========================================
        // TAHAP 4: User mengerjakan ujian
        // ========================================
        $startResponse = $this->actingAs($user)->postJson('/api/exams/start', [
            'exam_id' => $exam->id,
            'bank_id' => $bank->id,
        ]);

        $startResponse->assertStatus(201); // TODO: cek status code asli yang dikembalikan start()

        $attemptId = $startResponse->json('id') ?? $startResponse->json('data.id'); // TODO: sesuaikan struktur response start()
        $this->assertNotNull($attemptId, 'attemptId tidak ditemukan di response start exam - cek struktur JSON aslinya.');

        // User tidak enroll ke exam lain -> tidak boleh bisa start
        $otherExam = Exam::factory()->create(['program_id' => $program->id]);
        $this->actingAs($user)
            ->postJson('/api/exams/start', [
                'exam_id' => $otherExam->id,
                'bank_id' => $bank->id,
            ])
            ->assertStatus(422); // TODO: sesuaikan - kemungkinan gagal di validasi bank_id (exam ini tidak pakai bank ini), atau 403 dari canAttemptExam

        // Submit jawaban benar
        $this->actingAs($user)->postJson("/api/exam-attempts/{$attemptId}/answer", [
            'question_id' => $question->id,
            'selected_option_id' => $correctOption->id,
        ])->assertStatus(200);

        // Finish ujian
        $finishResponse = $this->actingAs($user)->postJson("/api/exam-attempts/{$attemptId}/finish");
        $finishResponse->assertStatus(200);

        // Attempt yang sudah selesai tidak boleh disubmit ulang
        $this->actingAs($user)
            ->postJson("/api/exam-attempts/{$attemptId}/answer", [
                'question_id' => $question->id,
                'selected_option_id' => $correctOption->id,
            ])->assertStatus(422);

        // Skor kehitung dengan benar (1 soal benar x point_correct 5)
        $this->assertDatabaseHas('exam_attempt_section_scores', [
            'exam_attempt_id' => $attemptId,
            'exam_section_id' => $section->id,
        ]);

        // ========================================
        // TAHAP 5 & 6: Leaderboard & Performa
        // ========================================
        // TODO: belum ditulis - perlu lihat dulu:
        //   - App\Jobs\GeneratePracticeLeaderboardJob (constructor & handle())
        //   - App\Http\Controllers\Api\PracticeLeaderboardController
        //   - App\Models\Promo (struktur field restricted_to_user_id, source, leaderboard_entry_id)
        // supaya tidak menebak nama field/endpoint seperti kejadian sebelumnya.
    }
}
