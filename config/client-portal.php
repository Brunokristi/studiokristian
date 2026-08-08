<?php

return [
    'provider' => [
        'company_name' => env('PORTAL_PROVIDER_COMPANY_NAME', config('app.name')),
        'registration_number' => env('PORTAL_PROVIDER_REGISTRATION_NUMBER'),
        'address' => env('PORTAL_PROVIDER_ADDRESS'),
    ],
    'max_upload_kilobytes' => (int) env('PORTAL_MAX_UPLOAD_KB', 51200),
];