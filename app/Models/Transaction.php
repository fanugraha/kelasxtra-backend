<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'package_id',
        'promo_id',
        'midtrans_order_id',
        'amount',
        'discount_amount',
        'payment_method',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TransactionLog::class);
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class);
    }

    // Sinkronisasi otomatis: apa pun cara admin mengubah status transaksi
    // (form edit manual, tombol aksi, dsb), enrollment terkait ikut disesuaikan.
    // success -> akses diberikan (active), failed/expired -> akses dicabut (expired).
    protected static function booted(): void
    {
        static::updated(function (Transaction $transaction) {
            if (! $transaction->wasChanged('status')) {
                return;
            }

            $enrollment = $transaction->enrollment;

            if (! $enrollment) {
                return;
            }

            match ($transaction->status) {
                'success' => $enrollment->update([
                    'status' => 'active',
                    'start_date' => $enrollment->start_date ?? now(),
                ]),
                'failed', 'expired' => $enrollment->update([
                    'status' => 'expired',
                ]),
                default => null,
            };
        });
    }
}
