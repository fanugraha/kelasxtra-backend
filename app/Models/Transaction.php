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
        'plan_id',
        'selected_program_ids',
        'promo_id',
        'midtrans_order_id',
        'invoice_number',
        'amount',
        'discount_amount',
        'payment_method',
        'status',
        'paid_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'selected_program_ids' => 'array',
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

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
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

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'transaction_id');
    }

    // Sinkronisasi otomatis: apa pun cara status transaksi berubah (webhook,
    // reconcile job, atau admin edit manual lewat Filament), enrollment
    // terkait ikut disesuaikan.
    // success  -> akses diberikan (active)
    // failed/expired/refunded -> akses dicabut (expired)
    protected static function booted(): void
    {
        static::updated(function (Transaction $transaction) {
            if (! $transaction->wasChanged('status')) {
                return;
            }

            $enrollment = $transaction->enrollment;

            if ($enrollment) {
                match ($transaction->status) {
                    'success' => $enrollment->update([
                        'status' => 'active',
                        'start_date' => $enrollment->start_date ?? now(),
                    ]),
                    'failed', 'expired', 'refunded' => $enrollment->update([
                        'status' => 'expired',
                    ]),
                    default => null,
                };
            }

            $subscription = $transaction->subscription;

            if ($subscription) {
                match ($transaction->status) {
                    'success' => $subscription->update([
                        'status' => 'active',
                        'start_date' => $subscription->start_date ?? now(),
                    ]),
                    'failed', 'expired', 'refunded' => $subscription->update([
                        'status' => 'expired',
                    ]),
                    default => null,
                };
            }
        });
    }
}
