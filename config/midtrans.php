<?php

return [
    // Ambil dari dashboard Midtrans (Sandbox dulu, ganti ke Production nanti)
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),

    // true = sandbox, false = production
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),

    // Opsi tambahan SDK Midtrans
    'is_sanitized' => true,
    'is_3ds' => true,
];
