<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Program;
use App\Models\Promo;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionCheckoutTest extends TestCase
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

    protected function makePromo(array $overrides = []): Promo
    {
        return Promo::create(array_merge([
            'title' => 'Promo Test',
            'description' => null,
            'terms' => null,
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'code' => 'TESTPROMO',
            'valid_until' => null,
            'total_quota' => null,
            'new_user_only' => false,
            'usage_limit_per_user' => null,
            'max_discount_amount' => null,
            'valid_from' => null,
            'is_active' => true,
            'applicable_package_id' => null,
            'restricted_to_user_id' => null,
            'source' => 'manual',
            'leaderboard_entry_id' => null,
        ], $overrides));
    }

    /**
     * Stub MidtransService::createTransaction() supaya meniru logic asli
     * (hitung amount dari discount promo, buat baris Transaction) TANPA
     * benar-benar memanggil Snap::getSnapToken() (HTTP ke Midtrans).
     */
    protected function mockMidtransCreatesTransaction(): void
    {
        $this->mock(MidtransService::class, function ($mock) {
            $mock->shouldReceive('createTransaction')
                ->once()
                ->andReturnUsing(function ($user, $package, $promo) {
                    $basePrice = (float) ($package->discount_price ?? $package->price);
                    $discount = $promo ? $promo->calculateDiscount($package) : 0;
                    $amount = max($basePrice - $discount, 0);

                    $transaction = Transaction::create([
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'promo_id' => $promo?->id,
                        'midtrans_order_id' => 'TEST-'.uniqid(),
                        'invoice_number' => 'INV-TEST-'.uniqid(),
                        'amount' => $amount,
                        'discount_amount' => $discount,
                        'payment_method' => null,
                        'status' => 'pending',
                        'expires_at' => now()->addHours(24),
                    ]);

                    $transaction->setAttribute('snap_token', 'mock-snap-token-new');

                    return $transaction;
                });
        });
    }

    public function test_checkout_berhasil_membuat_transaksi_baru_untuk_paket_yang_belum_dimiliki(): void
    {
        $this->mockMidtransCreatesTransaction();

        $package = $this->makePackage();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/transactions/checkout', ['package_id' => $package->id]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('package_id', $package->id)
            ->assertJsonPath('promo_id', null)
            ->assertJsonPath('snap_token', 'mock-snap-token-new');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'pending',
        ]);
    }

    public function test_checkout_menolak_kalau_sudah_punya_enrollment_aktif_untuk_paket_lifetime(): void
    {
        $package = $this->makePackage(['duration_days' => null]); // lifetime
        $user = User::factory()->create();

        Enrollment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'start_date' => now()->subDays(5),
            'end_date' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/transactions/checkout', ['package_id' => $package->id]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kamu sudah memiliki paket ini.');
    }

    public function test_checkout_menolak_kalau_enrollment_paket_berdurasi_masih_aktif(): void
    {
        $package = $this->makePackage(['duration_days' => 30]);
        $user = User::factory()->create();

        Enrollment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(20),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/transactions/checkout', ['package_id' => $package->id]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kamu sudah memiliki paket ini.');
    }

    public function test_checkout_diizinkan_kalau_enrollment_paket_berdurasi_sudah_expired(): void
    {
        $this->mockMidtransCreatesTransaction();

        $package = $this->makePackage(['duration_days' => 30]);
        $user = User::factory()->create();

        Enrollment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'expired',
            'start_date' => now()->subDays(60),
            'end_date' => now()->subDays(30),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/transactions/checkout', ['package_id' => $package->id]);

        $response->assertStatus(201);
    }

    public function test_checkout_resume_transaksi_pending_yang_sudah_ada_alih_alih_bikin_baru(): void
    {
        $package = $this->makePackage();
        $user = User::factory()->create();

        $existing = Transaction::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'promo_id' => null,
            'midtrans_order_id' => 'KX-OLD-ORDER',
            'invoice_number' => 'INV-OLD-0001',
            'amount' => 300000,
            'discount_amount' => 0,
            'payment_method' => null,
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        $this->mock(MidtransService::class, function ($mock) {
            $mock->shouldReceive('resumeTransaction')->once()->andReturn('mock-snap-token-resumed');
            $mock->shouldNotReceive('createTransaction');
        });

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/transactions/checkout', ['package_id' => $package->id]);

        $response->assertStatus(200)
            ->assertJsonPath('id', $existing->id)
            ->assertJsonPath('snap_token', 'mock-snap-token-resumed');

        // Hanya 1 baris transaksi -- tidak ada baris baru dibuat.
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_checkout_promo_code_tidak_ditemukan_mengembalikan_404(): void
    {
        $package = $this->makePackage();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/transactions/checkout', [
            'package_id' => $package->id,
            'promo_code' => 'TIDAKADA',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Kode promo tidak ditemukan.');
    }

    public function test_checkout_promo_tidak_aktif_ditolak_422(): void
    {
        $package = $this->makePackage();
        $user = User::factory()->create();

        $this->makePromo(['is_active' => false]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/transactions/checkout', [
            'package_id' => $package->id,
            'promo_code' => 'TESTPROMO',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kode promo tidak lagi aktif.');
    }

    public function test_checkout_dengan_promo_valid_menyimpan_promo_id_dan_diskon_dihitung(): void
    {
        $this->mockMidtransCreatesTransaction();

        $package = $this->makePackage(['price' => 300000, 'discount_price' => null]);
        $user = User::factory()->create();

        $promo = $this->makePromo([
            'discount_type' => 'fixed',
            'discount_value' => 50000,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/transactions/checkout', [
            'package_id' => $package->id,
            'promo_code' => 'TESTPROMO',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('promo_id', $promo->id)
            ->assertJsonPath('amount', '250000.00')
            ->assertJsonPath('discount_amount', '50000.00');
    }
    protected function makePlan(array $overrides = []): \App\Models\SubscriptionPlan
    {
        $program = Program::factory()->create();

        return \App\Models\SubscriptionPlan::factory()->create(array_merge([
            'program_id' => $program->id,
            'program_slot_count' => null,
            'price' => 150000,
            'duration_days' => 30,
        ], $overrides));
    }

    /**
     * Stub MidtransService::createSubscriptionTransaction() -- mirror
     * mockMidtransCreatesTransaction() tapi untuk jalur plan_id.
     */
    protected function mockMidtransCreatesSubscriptionTransaction(): void
    {
        $this->mock(MidtransService::class, function ($mock) {
            $mock->shouldReceive('createSubscriptionTransaction')
                ->once()
                ->andReturnUsing(function ($user, $plan, $programIds) {
                    $transaction = Transaction::create([
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'selected_program_ids' => $programIds,
                        'midtrans_order_id' => 'TEST-SUB-'.uniqid(),
                        'invoice_number' => 'INV-SUB-TEST-'.uniqid(),
                        'amount' => $plan->price,
                        'discount_amount' => 0,
                        'payment_method' => null,
                        'status' => 'pending',
                        'expires_at' => now()->addHours(24),
                    ]);

                    $transaction->setAttribute('snap_token', 'mock-snap-token-sub-new');

                    return $transaction;
                });
        });
    }

    public function test_checkout_subscription_berhasil_untuk_plan_fix_program(): void
    {
        $this->mockMidtransCreatesSubscriptionTransaction();

        $plan = $this->makePlan();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/transactions/checkout', ['plan_id' => $plan->id]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('plan_id', $plan->id)
            ->assertJsonPath('snap_token', 'mock-snap-token-sub-new');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
        ]);
    }

    public function test_checkout_subscription_berhasil_untuk_plan_multi_program_dengan_program_ids_lengkap(): void
    {
        $this->mockMidtransCreatesSubscriptionTransaction();

        $programA = Program::factory()->create();
        $programB = Program::factory()->create();
        $plan = \App\Models\SubscriptionPlan::factory()->multiProgram(2)->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/transactions/checkout', [
            'plan_id' => $plan->id,
            'program_ids' => [$programA->id, $programB->id],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('plan_id', $plan->id);
    }

    public function test_checkout_subscription_ditolak_kalau_jumlah_program_ids_tidak_sesuai_slot_count(): void
    {
        $programA = Program::factory()->create();
        $plan = \App\Models\SubscriptionPlan::factory()->multiProgram(2)->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Cuma kirim 1 program_id padahal plan butuh 2.
        $response = $this->postJson('/api/transactions/checkout', [
            'plan_id' => $plan->id,
            'program_ids' => [$programA->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Plan ini butuh tepat 2 program dipilih.');
    }

    public function test_checkout_subscription_ditolak_kalau_sudah_punya_subscription_aktif_yang_cover_program_sama(): void
    {
        $program = Program::factory()->create();
        $plan = $this->makePlan(['program_id' => $program->id]);
        $user = User::factory()->create();

        $existingPlan = $this->makePlan(['program_id' => $program->id]);
        $subscription = \App\Models\Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $existingPlan->id,
            'status' => 'active',
        ]);
        $subscription->programs()->sync([$program->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/transactions/checkout', ['plan_id' => $plan->id]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kamu sudah punya langganan aktif yang mencakup program ini.');
    }

    public function test_checkout_subscription_resume_transaksi_pending_yang_sudah_ada(): void
    {
        $plan = $this->makePlan();
        $user = User::factory()->create();

        $existing = Transaction::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'selected_program_ids' => [$plan->program_id],
            'midtrans_order_id' => 'KX-OLD-SUB-ORDER',
            'invoice_number' => 'INV-OLD-SUB-0001',
            'amount' => 150000,
            'discount_amount' => 0,
            'payment_method' => null,
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        $this->mock(MidtransService::class, function ($mock) {
            $mock->shouldReceive('resumeTransaction')->once()->andReturn('mock-snap-token-sub-resumed');
            $mock->shouldNotReceive('createSubscriptionTransaction');
        });

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/transactions/checkout', ['plan_id' => $plan->id]);

        $response->assertStatus(200)
            ->assertJsonPath('id', $existing->id)
            ->assertJsonPath('snap_token', 'mock-snap-token-sub-resumed');

        $this->assertDatabaseCount('transactions', 1);
    }

}
