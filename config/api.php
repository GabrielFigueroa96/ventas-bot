<?php

return [
    'whatsapp' => [
        'key'             => env('WHATSAPP_API_KEY'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    ],
    'webhook' => [
        'token'  => env('WEBHOOK_API_TOKEN'),
        'secret' => env('GATEWAY_SECRET'),   // token que envía el gateway para autenticar
        'log_url' => env('GATEWAY_LOG_URL'),  // URL del gateway para registrar salientes
    ],
    'openai' => [
        'key' => env('OPENAI_API_KEY')
    ]
];
