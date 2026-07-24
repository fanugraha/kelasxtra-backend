<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StreakService
{
    /**
     * Hitung streak latihan harian user untuk sebuah program.
     *
     * Aturan:
     * - 1 hari dianggap "aktif" kalau ada minimal 1 ExamAttempt selesai
     *   (finished_at) pada hari itu, untuk exam di program tsb.
     * - Kalau user belum latihan hari ini, dikasih masa tenggang (mulai
     *   hitung dari kemarin) -- supaya belum kehilangan streak sebelum
     *   harinya sendiri berakhir.
     * - Freeze: maksimal 1 hari bolong per minggu kalender (ISO week) boleh
     *   dilewati tanpa memutus streak. Bolong kedua di minggu yang sama
     *   menghentikan hitungan mundur.
     */
    public function currentStreak(User $user, int $programId): array
    {
        $activeDates = $this->activityDates($user, $programId);
        $today = Carbon::today();
        $activeToday = $activeDates->contains(fn ($d) => $d->isSameDay($today));

        if ($activeDates->isEmpty()) {
            return ['count' => 0, 'active_today' => false, 'last_active_date' => null];
        }

        $dateSet = $activeDates->map(fn ($d) => $d->toDateString())->flip();
        $usedFreezeWeeks = [];
        $cursor = $activeToday ? $today->copy() : $today->copy()->subDay();
        $count = 0;
        $iterations = 0;

        while ($iterations < 400) {
            $iterations++;
            $dateStr = $cursor->toDateString();

            if ($dateSet->has($dateStr)) {
                $count++;
                $cursor->subDay();
                continue;
            }

            $weekKey = $cursor->format('o-W');

            if (! isset($usedFreezeWeeks[$weekKey])) {
                $usedFreezeWeeks[$weekKey] = true;
                $cursor->subDay();
                continue;
            }

            break;
        }

        return [
            'count' => $count,
            'active_today' => $activeToday,
            'last_active_date' => $activeDates->first()->toDateString(),
        ];
    }

    protected function activityDates(User $user, int $programId): Collection
    {
        return ExamAttempt::where('user_id', $user->id)
            ->whereHas('exam', fn ($q) => $q->where('program_id', $programId))
            ->whereNotNull('finished_at')
            ->pluck('finished_at')
            ->map(fn ($dt) => Carbon::parse($dt)->startOfDay())
            ->unique(fn ($d) => $d->toDateString())
            ->sortByDesc(fn ($d) => $d->toDateString())
            ->values();
    }
}
