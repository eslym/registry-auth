<?php

return [
    'expiration' => env('PASSWORD_EXPIRATION_DAYS', false), // days
    'validation' => [
        'min' => env('PASSWORD_MIN_LENGTH', 8),
        'max' => env('PASSWORD_MAX_LENGTH', 64),
        'mixed_case' => env('PASSWORD_MIXED_CASE', true),
        'numbers' => env('PASSWORD_NUMBERS', true),
        'symbols' => env('PASSWORD_SYMBOLS', true),
        'custom_rules' => env('PASSWORD_CUSTOM_RULES', []),
        'uncompromised' => env('PASSWORD_UNCOMPROMISED', env('APP_ENV', 'production') === 'production'),
        'threshold' => env('PASSWORD_UNCOMPROMISED_THRESHOLD', 3)
    ]
];
