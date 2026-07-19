<?php

namespace App\Notifications;

use App\Models\PracticeLeaderboard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PracticeLeaderboardRewardNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected PracticeLeaderboard $entry)
    {
    }

    /**
     * Cuma pakai channel database (notifikasi in-app), tidak ada
     * email/WhatsApp sesuai keputusan desain.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $rankLabel = [
            'voucher_gold' => 1,
            'voucher_silver' => 2,
            'voucher_bronze' => 3,
            'badge_only' => $this->entry->ranking,
        ][$this->entry->reward_type] ?? $this->entry->ranking;

        $hasVoucher = $this->entry->discount_code !== null;

        return [
            'type' => 'practice_leaderboard_reward',
            'exam_id' => $this->entry->exam_id,
            'periode' => $this->entry->periode,
            'ranking' => $this->entry->ranking,
            'reward_type' => $this->entry->reward_type,
            'discount_code' => $this->entry->discount_code,
            'title' => $hasVoucher
                ? "Selamat! Kamu Rank {$rankLabel} minggu ini \u{1F389}"
                : "Selamat! Kamu Rank {$rankLabel} minggu ini",
            'message' => $hasVoucher
                ? "Kamu dapat kode diskon {$this->entry->discount_code} untuk paket berikutnya. Cek di halaman Reward Saya."
                : 'Kamu sudah masuk jajaran Top 3 minggu ini. Badge sudah nempel di profil kamu!',
        ];
    }
}
