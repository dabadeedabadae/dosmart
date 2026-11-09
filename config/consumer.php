<?php

$seller = [
    'name' => env('DOSMART_SELLER_NAME'),
    'registration' => env('DOSMART_SELLER_PUBLIC_REGISTRATION'),
    'address_ru' => env('DOSMART_SELLER_ADDRESS_RU'),
    'address_kk' => env('DOSMART_SELLER_ADDRESS_KK'),
    'phone' => env('DOSMART_SELLER_PHONE'),
    'email' => env('DOSMART_CLAIMS_EMAIL'),
];
$details = [
    'delivery_ru' => env('DOSMART_DELIVERY_RULE_RU'),
    'delivery_kk' => env('DOSMART_DELIVERY_RULE_KK'),
    'acceptance_ru' => env('DOSMART_ORDER_ACCEPTANCE_RU'),
    'acceptance_kk' => env('DOSMART_ORDER_ACCEPTANCE_KK'),
    'refusal_ru' => env('DOSMART_DELIVERY_REFUSAL_RU'),
    'refusal_kk' => env('DOSMART_DELIVERY_REFUSAL_KK'),
    'receipt_ru' => env('DOSMART_FISCAL_RECEIPT_RU'),
    'receipt_kk' => env('DOSMART_FISCAL_RECEIPT_KK'),
    'disputes_ru' => env('DOSMART_DISPUTE_CONTACTS_RU'),
    'disputes_kk' => env('DOSMART_DISPUTE_CONTACTS_KK'),
    'data_ru' => env('DOSMART_DATA_DETAILS_RU'),
    'data_kk' => env('DOSMART_DATA_DETAILS_KK'),
];

return [
    'preview' => env('APP_ENV') === 'local',
    'published' => env('DOSMART_CONSUMER_PAGES_APPROVED', false)
        && collect($seller + $details)->every(fn ($value) => is_string($value) && trim($value) !== '')
        && filter_var($seller['email'], FILTER_VALIDATE_EMAIL),
    'seller' => $seller,
    'details' => $details,
];
