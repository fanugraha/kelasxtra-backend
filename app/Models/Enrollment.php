<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'class_id',
        'package_id',
        'transaction_id',
        'status',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    // Dipakai AccessControlService: enrollment aktif = status 'active' DAN belum lewat end_date.
    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->end_date === null || $this->end_date->isFuture() || $this->end_date->isToday());
    }

    // Query-level version of isActive() — dipakai AccessControlService supaya
    // aturan "aktif" cuma didefinisikan di satu tempat, bukan ditulis ulang
    // sebagai raw where() di service.
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', Carbon::today());
            });
    }
}