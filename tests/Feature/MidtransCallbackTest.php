<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function makePackage(array $overrides = []): Package
    {
        $program = Program::factory()->create();

        return Package::factory()->create(array_merge([
            'program_id' => $program->id,
            'price' => 300000,
            'discount_price' => null,
            'duration_days' => 30,
        ], $overrides));
    }

    protected function makeTransaction(array $overrides = []): Transaction
    {
        $user = User::factory()->create();
        $package = $this->makePackage();

        return Transaction::create(array_merge([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'promo_id' => null,
            'midtrans_order_id' => 'ORDER-'.uniqid(),
            'invoice_number' => 'INV-'.uniqid(),
            'amount' => 300000,
            'discount_amount' => 0,
            'payment_method' => null,
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ], $overrides));
    }

    /**
     * Sama persis dengan rumus signature di MidtransCallbackController::
     * handleCallback() -- sha512(order_id + status_code + gross_amount + server_key).
     */
    protected function validSignature(string $orderId, string $statusCode, string $grossAmount): string
    {
        $serverKey = config('services.midtrans.server_key');

        return hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
    }

    protected function postCallback(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/midtrans/callback', $payload);
    }

    public function test_signature_tidak_valid_ditolak_403(): void
    {
        $transaction = $this->makeTransaction();

        $response = $this->postCallback([
            'order_id' => $transaction->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => '300000.00',
            'transaction_status' => 'settlement',
            'signature_key' => 'salah-total',
        ]);

        $response->assertStatus(403);
        $this->assertSame('pending', $transaction->fresh()->status);
    }

    public function test_order_id_tidak_ditemukan_mengembalikan_404(): void
    {
        $signature = $this->validSignature('ORDER-TIDAK-ADA', '200', '300000.00');

        $response = $this->postCallback([
            'order_id' => 'ORDER-TIDAK-ADA',
            'status_code' => '200',
            'gross_amount' => '300000.00',
            'transaction_status' => 'settlement',
            'signature_key' => $signature,
        ]);

        $response->assertStatus(404);
    }

    public function test_settlement_mengubah_status_jadi_success_dan_mengaktifkan_enrollment(): void
    {
        $transaction = $this->makeTransaction();
        $signature = $this->validSignature($transaction->midtrans_order_id, '200', '300000.00');

        $response = $this->postCallback([
            'order_id' => $transaction->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => '300000.00',
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'signature_key' => $signature,
        ]);

        $response->assertOk();

        $transaction->refresh();
        $this->assertSame('success', $transaction->status);
        $this->assertNotNull($transaction->paid_at);
        $this->assertSame('bank_transfer', $transaction->payment_method);

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $transaction->user_id,
            'package_id' => $transaction->package_id,
            'status' => 'active',
        ]);
    }

    public function test_capture_dengan_fraud_accept_dianggap_success(): void
    {
        $transaction = $this->makeTransaction();
        $signature = $this->validSignature($transaction->midtrans_order_id, '200', '300000.00');

        $response = $this->postCallback([
            'order_id' => $transaction->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => '300000.00',
            'transaction_status' => 'capture',
            'fraud_status' => 'accept',
            'payment_type' => 'credit_card',
            'signature_key' => $signature,
        ]);

        $response->assertOk();
        $this->assertSame('success', $transaction->fresh()->status);
    }

    public function test_capture_dengan_fraud_challenge_tidak_mengubah_status(): void
    {
        $transaction = $this->makeTransaction();
        $signature = $this->validSignature($transaction->midtrans_order_id, '200', '300000.00');

        $response = $this->postCallback([
            'order_id' => $transaction->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => '300000.00',
            'transaction_status' => 'capture',
            'fraud_status' => 'challenge',
            'signature_key' => $signature,
        ]);

        $response->assertOk();
        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $transaction->user_id]);
    }

    public function test_deny_dan_cancel_mengubah_status_jadi_failed(): void
    {
        $transaction = $this->makeTransaction();
        $signature = $this->validSignature($transaction->midtrans_order_id, '200', '300000.00');

        $response = $this->postCallback([
            'order_id' => $transaction->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => '300000.00',
            'transaction_status' => 'deny',
            'signature_key' => $signature,
        ]);

        $response->assertOk();
        $this->assertSame('failed', $transaction->fresh()->status);
    }

    public function test_expire_mengubah_status_jadi_expired(): void
    {
        $transaction = $this->makeTransaction();
        $signature = $this->validSignature($transaction->midtrans_order_id, '200', '300000.00');

        $response = $this->postCallback([
            'order_id' => $transaction->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => '300000.00',
            'transaction_status' => 'expire',
            'signature_key' => $signature,
        ]);

        $response->assertOk();
        $this->assertSame('expired', $transaction->fresh()->status);
    }

    public function test_webhook_duplikat_dengan_status_sama_diabaikan_idempotent(): void
    {
        $transaction = $this->makeTransaction(['status' => 'success', 'paid_at' => now()->subHour()]);
        $signature = $this->validSignature($transaction->midtrans_order_id, '200', '300000.00');

        $originalPaidAt = $transaction->paid_at;

        $response = $this->postCallback([
            'order_id' => $transaction->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => '300000.00',
            'transaction_status' => 'settlement',
            'signature_key' => $signature,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Webhook sudah diproses sebelumnya (Idempotent)');

        // paid_at tidak berubah -- tidak ada update yang benar-benar terjadi.
        $this->assertTrue($originalPaidAt->equalTo($transaction->fresh()->paid_at));
    }

    public function test_webhook_setelah_status_final_failed_diabaikan(): void
    {
        $transaction = $this->makeTransaction(['status' => 'failed']);
        $signature = $this->validSignature($transaction->midtrans_order_id, '200', '300000.00');

        $response = $this->postCallback([
            'order_id' => $transaction->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => '300000.00',
            'transaction_status' => 'settlement',
            'signature_key' => $signature,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Transaksi sudah final, webhook diabaikan');

        $this->assertSame('failed', $transaction->fresh()->status);
    }

    public function test_transisi_success_ke_refunded_tetap_diizinkan_meski_success_itu_final(): void
    {
        $transaction = $this->makeTransaction(['status' => 'success', 'paid_at' => now()->subDay()]);

        Enrollment::create([
            'user_id' => $transaction->user_id,
            'package_id' => $transaction->package_id,
            'transaction_id' => $transaction->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(29),
        ]);

        $signature = $this->validSignature($transaction->midtrans_order_id, '200', '300000.00');

        $response = $this->postCallback([
            'order_id' => $transaction->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => '300000.00',
            'transaction_status' => 'refund',
            'signature_key' => $signature,
        ]);

        $response->assertOk();

        $transaction->refresh();
        $this->assertSame('refunded', $transaction->status);

        // Transaction::booted() harus otomatis mencabut enrollment saat refunded.
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $transaction->user_id,
            'package_id' => $transaction->package_id,
            'status' => 'expired',
        ]);
    }
}
