<?php

return [
    'prisoner_loopback_demo' => env('DOSMART_PRISONER_LOOPBACK_DEMO', false),
    'prisoner_gateway_secret' => env('DOSMART_PRISONER_GATEWAY_SECRET', ''),
    'prisoner_gateway_institution' => env('DOSMART_PRISONER_GATEWAY_INSTITUTION'),
    'terminal_identity_url' => env('TERMINAL_IDENTITY_URL', ''),
    'terminal_institution_map' => json_decode(env('TERMINAL_INSTITUTION_MAP', '{}'), true) ?: [],
    'terminal_token' => env('TERMINAL_API_TOKEN', ''),
    'kaspi_url' => env('KASPI_PAYMENT_URL', ''),
    'kaspi_replacement_url' => env('DOSMART_KASPI_REPLACEMENT_URL', ''),
    'kaspi_protected_orders' => array_map('trim', explode(',', (string) env('DOSMART_KASPI_PROTECTED_ORDERS', ''))),
    'whatsapp' => env('DOSMART_WHATSAPP', ''),
    'draft_days' => 4,
    'delivery_fee' => 3000,
    // МРП по закону о республиканском бюджете; подставляется в формы как значение по умолчанию.
    'mrp' => [2025 => 3932, 2026 => 4325],
    'service_hours' => env('DOSMART_SERVICE_HOURS', '17:00–21:00'),
    'service_timezone_label' => 'по времени Казахстана (UTC+5)',
    'consent_version' => 'pilot-2026-09-v2',
];
