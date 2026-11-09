<?php

return [
    'terminal_identity_url' => env('TERMINAL_IDENTITY_URL', ''),
    'terminal_institution_map' => json_decode(env('TERMINAL_INSTITUTION_MAP', '{}'), true) ?: [],
    'terminal_token' => env('TERMINAL_API_TOKEN', ''),
    'kaspi_url' => env('KASPI_PAYMENT_URL', ''),
    'whatsapp' => env('DOSMART_WHATSAPP', ''),
    'draft_days' => 4,
    'delivery_fee' => 3000,
    'service_hours' => env('DOSMART_SERVICE_HOURS', '17:00–21:00'),
    'service_timezone_label' => 'по времени Казахстана (UTC+5)',
    'consent_version' => 'pilot-2026-09-v2',
];
