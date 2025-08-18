<?php

return [
    'proxies' => array_filter(
        array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))),
        fn($proxy) => !empty($proxy)
    ),
];
