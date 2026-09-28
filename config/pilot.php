<?php

return [
    'terminal_identity_url' => env('TERMINAL_IDENTITY_URL', ''),
    'terminal_institution_map' => json_decode(env('TERMINAL_INSTITUTION_MAP', '{}'), true) ?: [],
    'terminal_token' => env('TERMINAL_API_TOKEN', ''),
    'kaspi_url' => env('KASPI_PAYMENT_URL', ''),
    'whatsapp' => env('DOSMART_WHATSAPP', ''),
    'draft_days' => 4,
    'consent_version' => 'pilot-2026-09-v2',
];
