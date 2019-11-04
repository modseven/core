<?php

return [
    'default' => [
        'driver' => \Modseven\Encrypt\Engine\OpenSSL::class,
        'key' => null,
        'cipher' => 'AES-256-CBC',
    ]
];