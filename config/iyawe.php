<?php

return [
    'documents_disk' => env('IYAWE_DOCUMENTS_DISK', 'local'),
    'trusted_proxies' => array_values(array_filter(explode(',', (string) env('IYAWE_TRUSTED_PROXIES', '')))),
    'system_admin' => [
        'name' => env('IYAWE_SYSTEM_ADMIN_NAME', 'iYawe System Administrator'),
        'email' => env('IYAWE_SYSTEM_ADMIN_EMAIL'),
        'password' => env('IYAWE_SYSTEM_ADMIN_PASSWORD'),
    ],

    'password_reset_enabled' => in_array(env('MAIL_MAILER', 'log'), ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend'], true)
        || (env('APP_ENV') === 'testing' && env('MAIL_MAILER') === 'array'),
];
