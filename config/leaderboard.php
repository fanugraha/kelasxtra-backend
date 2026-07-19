<?php

return [
    // Maksimal voucher yang bisa didapat 1 siswa per minggu, walau dia
    // menang Rank 1-3 di banyak exam. Lebih dari ini, tetap dapat badge
    // tapi tidak dapat voucher lagi.
    'max_voucher_per_user_per_week' => env('LEADERBOARD_MAX_VOUCHER_PER_USER_PER_WEEK', 2),

    // Minimal jumlah peserta yang mengerjakan exam dalam 1 periode supaya
    // reward voucher untuk exam itu berlaku. Exam dengan peserta kurang dari
    // ini tetap dapat ranking & badge, tapi tidak dapat voucher (mencegah
    // "farming" di exam yang sepi peminat).
    'min_participants_for_reward' => env('LEADERBOARD_MIN_PARTICIPANTS_FOR_REWARD', 10),

    // Masa berlaku voucher reward, dalam hari. Selaras dengan siklus reset
    // mingguan — voucher yang tidak dipakai akan hangus begitu periode
    // berikutnya dimulai.
    'voucher_valid_days' => env('LEADERBOARD_VOUCHER_VALID_DAYS', 7),

    // Nominal potongan (max_discount_amount) untuk tiap posisi juara.
    'reward_amounts' => [
        1 => env('LEADERBOARD_REWARD_RANK_1', 5000),
        2 => env('LEADERBOARD_REWARD_RANK_2', 3000),
        3 => env('LEADERBOARD_REWARD_RANK_3', 1500),
    ],

    // Rank berapa saja yang dianggap "milestone" -- dipakai buat filter
    // notifikasi rank-change. Tembus ke salah satu angka ini (dan sebelumnya
    // belum pernah tembus di periode yang sama) -> layak dicatat sebagai event.
    'rank_notify_milestones' => [50, 10, 3],

    // Minimal kenaikan posisi supaya tetap dianggap layak notifikasi meski
    // tidak menembus milestone manapun (mis. dari rank 80 ke rank 65).
    'rank_notify_threshold' => env('LEADERBOARD_RANK_NOTIFY_THRESHOLD', 10),
];
