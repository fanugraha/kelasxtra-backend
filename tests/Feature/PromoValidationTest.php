<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Program;
use App\Models\Promo;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromoValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function makePackage(array $overrides = []): Package
    {
        $program = Program::factory()->create();

        return Package::factory()->create(array_merge([
            'program_id' => $program->id,
            'price' => 300000,
            'discount_price' => null,
        ], $overrides));
    }

    protected function makePromo(array $overrides = []): Promo
    {
        return Promo::create(array_merge([
            'title' => 'Promo Test',
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

    // ── validateCode() ───────────────────────────────────────────────────

    public function test_validate_menolak_kalau_tidak_login(): void
    {
        $package = $this->makePackage();
        $this->makePromo();

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TESTPROMO',
            'package_id' => $package->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_validate_404_kalau_kode_tidak_ditemukan(): void
    {
        $package = $this->makePackage();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TIDAKADA',
            'package_id' => $package->id,
        ]);

        $response->assertStatus(404);
    }

    public function test_validate_422_kalau_promo_tidak_aktif(): void
    {
        $package = $this->makePackage();
        $this->makePromo(['is_active' => false]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TESTPROMO',
            'package_id' => $package->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kode promo tidak lagi aktif.');
    }

    public function test_validate_422_kalau_promo_kedaluwarsa(): void
    {
        $package = $this->makePackage();
        $this->makePromo(['valid_until' => now()->subDay()->toDateString()]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TESTPROMO',
            'package_id' => $package->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kode promo sudah kedaluwarsa.');
    }

    public function test_validate_422_kalau_belum_waktunya_berlaku(): void
    {
        $package = $this->makePackage();
        $this->makePromo(['valid_from' => now()->addDay()]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TESTPROMO',
            'package_id' => $package->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kode promo belum mulai berlaku.');
    }

    public function test_validate_422_kalau_tidak_berlaku_untuk_paket_ini(): void
    {
        $package = $this->makePackage();
        $otherPackage = $this->makePackage();
        $this->makePromo(['applicable_package_id' => $otherPackage->id]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TESTPROMO',
            'package_id' => $package->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kode promo tidak berlaku untuk paket ini.');
    }

    public function test_validate_422_kalau_bukan_untuk_akun_ini(): void
    {
        $package = $this->makePackage();
        $otherUser = User::factory()->create();
        $this->makePromo(['restricted_to_user_id' => $otherUser->id]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TESTPROMO',
            'package_id' => $package->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kode promo ini bukan untuk akun kamu.');
    }

    public function test_validate_422_kalau_khusus_siswa_baru_tapi_sudah_pernah_beli(): void
    {
        $package = $this->makePackage();
        $user = User::factory()->create();

        Transaction::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'midtrans_order_id' => 'KX-TEST-'.uniqid(),
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'amount' => 300000,
            'discount_amount' => 0,
            'status' => 'success',
        ]);

        $this->makePromo(['new_user_only' => true]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TESTPROMO',
            'package_id' => $package->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kode promo ini khusus untuk siswa baru.');
    }

    public function test_validate_422_kalau_sudah_capai_batas_pemakaian_per_user(): void
    {
        $package = $this->makePackage();
        $user = User::factory()->create();
        $promo = $this->makePromo(['usage_limit_per_user' => 1]);

        Transaction::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'promo_id' => $promo->id,
            'midtrans_order_id' => 'KX-TEST-'.uniqid(),
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'amount' => 250000,
            'discount_amount' => 50000,
            'status' => 'success',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TESTPROMO',
            'package_id' => $package->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kamu sudah mencapai batas pemakaian kode promo ini.');
    }

    public function test_validate_422_kalau_kuota_total_habis(): void
    {
        $package = $this->makePackage();
        $promo = $this->makePromo(['total_quota' => 1]);

        Transaction::create([
            'user_id' => User::factory()->create()->id,
            'package_id' => $package->id,
            'promo_id' => $promo->id,
            'midtrans_order_id' => 'KX-TEST-'.uniqid(),
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'amount' => 250000,
            'discount_amount' => 50000,
            'status' => 'success',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TESTPROMO',
            'package_id' => $package->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kuota kode promo ini sudah habis.');
    }

    public function test_validate_sukses_diskon_fixed_dihitung_benar(): void
    {
        $package = $this->makePackage(['price' => 300000, 'discount_price' => null]);
        $this->makePromo(['discount_type' => 'fixed', 'discount_value' => 50000]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TESTPROMO',
            'package_id' => $package->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('base_price', 300000)
            ->assertJsonPath('discount_amount', 50000)
            ->assertJsonPath('final_amount', 250000);
    }

    public function test_validate_sukses_diskon_percentage_dihitung_dari_discount_price(): void
    {
        // discount_price harus jadi basis perhitungan kalau ada, bukan price.
        $package = $this->makePackage(['price' => 300000, 'discount_price' => 200000]);
        $this->makePromo(['discount_type' => 'percentage', 'discount_value' => 10]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TESTPROMO',
            'package_id' => $package->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('base_price', 200000)
            ->assertJsonPath('discount_amount', 20000)
            ->assertJsonPath('final_amount', 180000);
    }

    public function test_validate_diskon_percentage_dibatasi_max_discount_amount(): void
    {
        $package = $this->makePackage(['price' => 1000000, 'discount_price' => null]);
        $this->makePromo([
            'discount_type' => 'percentage',
            'discount_value' => 50, // harusnya 500rb, tapi di-cap
            'max_discount_amount' => 100000,
        ]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TESTPROMO',
            'package_id' => $package->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('discount_amount', 100000)
            ->assertJsonPath('final_amount', 900000);
    }

    public function test_validate_diskon_tidak_melebihi_harga_paket(): void
    {
        $package = $this->makePackage(['price' => 30000, 'discount_price' => null]);
        $this->makePromo(['discount_type' => 'fixed', 'discount_value' => 50000]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TESTPROMO',
            'package_id' => $package->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('discount_amount', 30000)
            ->assertJsonPath('final_amount', 0);
    }

    public function test_validate_422_kalau_package_id_tidak_ada(): void
    {
        $this->makePromo();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/promos/validate', [
            'code' => 'TESTPROMO',
            'package_id' => 999999,
        ]);

        $response->assertStatus(422);
    }

    // ── active() ─────────────────────────────────────────────────────────

    public function test_active_hanya_mengembalikan_promo_yang_benar_benar_aktif(): void
    {
        $this->makePromo(['code' => 'AKTIF1', 'is_active' => true, 'valid_until' => null, 'valid_from' => null]);
        $this->makePromo(['code' => 'NONAKTIF', 'is_active' => false]);
        $this->makePromo(['code' => 'EXPIRED', 'valid_until' => now()->subDay()->toDateString()]);
        $this->makePromo(['code' => 'BELUM_MULAI', 'valid_from' => now()->addDay()]);

        $response = $this->getJson('/api/promos/active');

        $response->assertOk();
        $codes = collect($response->json())->pluck('code');

        $this->assertTrue($codes->contains('AKTIF1'));
        $this->assertFalse($codes->contains('NONAKTIF'));
        $this->assertFalse($codes->contains('EXPIRED'));
        $this->assertFalse($codes->contains('BELUM_MULAI'));
    }

    public function test_active_tidak_perlu_login(): void
    {
        $this->makePromo();

        $response = $this->getJson('/api/promos/active');

        $response->assertOk();
    }
}
