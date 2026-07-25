<?php

namespace App\Models;

use App\Models\SubscriptionPlan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promo extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'terms',
        'discount_type',
        'discount_value',
        'code',
        'valid_until',
        'total_quota',
        'new_user_only',
        'usage_limit_per_user',
        'max_discount_amount',
        'valid_from',
        'is_active',
        'applicable_package_id',
        'restricted_to_user_id',
        'source',
        'leaderboard_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'valid_until' => 'date',
            'valid_from' => 'datetime',
            'new_user_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function applicablePackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'applicable_package_id');
    }

    public function restrictedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restricted_to_user_id');
    }

    /**
     * Cek semua aturan pemakaian promo untuk user & paket tertentu.
     * Dipakai bareng oleh PromoController::validateCode() (pre-check) dan
     * TransactionController::checkout() (enforcement final) — supaya
     * aturannya konsisten di 1 tempat, tidak perlu diubah di 2 lokasi
     * kalau nanti ada penyesuaian.
     *
     * Return null kalau lolos semua, atau string pesan error kalau ditolak.
     */
    public function checkUsableBy(User $user, Package|SubscriptionPlan $item): ?string
    {
        if (! $this->is_active) {
            return 'Kode promo tidak lagi aktif.';
        }

        if ($this->restricted_to_user_id !== null && $this->restricted_to_user_id !== $user->id) {
            return 'Kode promo ini bukan untuk akun kamu.';
        }

        if ($this->valid_from && now()->lt($this->valid_from)) {
            return 'Kode promo belum mulai berlaku.';
        }

        if ($this->valid_until && now()->toDateString() > $this->valid_until->toDateString()) {
            return 'Kode promo sudah kedaluwarsa.';
        }

        // applicable_package_id men-scope promo ke 1 Package spesifik.
        // Kalau di-set, promo cuma valid untuk checkout Package itu -- bukan
        // untuk subscription plan manapun (scoping plan belum didukung).
        if ($this->applicable_package_id) {
            if (! ($item instanceof Package) || $this->applicable_package_id !== $item->id) {
                return 'Kode promo tidak berlaku untuk paket ini.';
            }
        }

        if ($this->new_user_only) {
            $hasSuccessfulTransaction = Transaction::where('user_id', $user->id)
                ->where('status', 'success')
                ->exists();

            if ($hasSuccessfulTransaction) {
                return 'Kode promo ini khusus untuk siswa baru.';
            }
        }

        if ($this->usage_limit_per_user !== null) {
            $usedByUser = Transaction::where('promo_id', $this->id)
                ->where('user_id', $user->id)
                ->where('status', 'success')
                ->count();

            if ($usedByUser >= $this->usage_limit_per_user) {
                return 'Kamu sudah mencapai batas pemakaian kode promo ini.';
            }
        }

        if ($this->total_quota !== null) {
            $totalUsed = Transaction::where('promo_id', $this->id)
                ->where('status', 'success')
                ->count();

            if ($totalUsed >= $this->total_quota) {
                return 'Kuota kode promo ini sudah habis.';
            }
        }

        return null;
    }

    /**
     * Hitung nominal potongan untuk paket tertentu, sudah termasuk cap
     * max_discount_amount dan pembatasan tidak melebihi harga paket.
     */
    public function calculateDiscount(Package|SubscriptionPlan $item): float
    {
        // SubscriptionPlan tidak punya discount_price seperti Package.
        $basePrice = $item instanceof Package
            ? (float) ($item->discount_price ?? $item->price)
            : (float) $item->price;

        $discountAmount = $this->discount_type === 'percentage'
            ? $basePrice * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        if ($this->max_discount_amount !== null) {
            $discountAmount = min($discountAmount, (float) $this->max_discount_amount);
        }

        return round(min($discountAmount, $basePrice), 2);
    }
}
