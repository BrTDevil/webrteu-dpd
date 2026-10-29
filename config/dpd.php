<?php

declare(strict_types=1);

return [

    'base_url' => env('DPD_BASE_URL', 'https://api.dpd.ro/v1/'),

    'username' => env('DPD_USERNAME'),
    'password' => env('DPD_PASSWORD'),
    'language' => env('DPD_LANGUAGE', 'RO'),

    /*
    | Opțional, dacă îl primești de la DPD.
    */
    'client_system_id' => env('DPD_CLIENT_SYSTEM_ID'),

    /*
    | clientId-ul expeditorului din contract (php artisan dpd:contract).
    */
    'sender_client_id' => env('DPD_SENDER_CLIENT_ID'),

    'default_service_id' => env('DPD_SERVICE_ID'),

    /*
    | Procesarea rambursului: CASH | POSTAL_MONEY_TRANSFER.
    */
    'cod_processing' => env('DPD_COD_PROCESSING', 'CASH'),

    /*
    | Cine plătește transportul: SENDER | RECIPIENT | THIRD_PARTY.
    */
    'payer' => env('DPD_PAYER', 'SENDER'),

    'timeout' => (int) env('DPD_TIMEOUT', 20),

    'tables' => [
        'sites' => 'dpd_sites',
        'offices' => 'dpd_offices',
    ],

];
