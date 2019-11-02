<?php

use Modseven\Cache\Memcached;

return [
    'default' => 'memcached',                            // allows to specify default cache directl from config file
    'prefix'  => 'cache1_',                          //used to avoid duplicates when using _sanitize_id
    'memcached' => [
        'driver' => Memcached::class,
        'servers' => [
            [
                'host' => '172.17.215.46',
                'port' => 11211,
                'weight' => 1,
            ],
        ],
        'options' => [
            Memcached::OPT_DISTRIBUTION => Memcached::DISTRIBUTION_CONSISTENT,
        ],
    ],
];
