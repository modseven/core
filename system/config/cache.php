<?php

return [
    'default'   => 'file',                             // allows to specify default cache directl from config file
    'prefix'    => 'cache1_',                          // used to avoid duplicates when using _sanitize_id
    'file'      => [
        'driver'           => \Modseven\Cache\Driver\File::class,
        'cache_dir'        => APPPATH . 'cache',
        'default_expire'   => 3600,
        'ignore_on_delete' => [
            '.gitignore',
            '.git',
            '.svn'
        ]
    ]
    /**
    'memcached' => [
        'driver'  => \Modseven\Cache\Memcached::class,
        'servers' => [
            [
                'host'   => '172.17.215.46',
                'port'   => 11211,
                'weight' => 1,
            ],
        ],
        'options' => [
            \Memcached::OPT_DISTRIBUTION => \Memcached::DISTRIBUTION_CONSISTENT,
        ],
    ],
    'sqlite'    => [
        'driver'         => \Modseven\Cache\Sqlite::class,
        'default_expire' => 3600,
        'database'       => APPPATH . 'cache' . DIRECTORY_SEPARATOR . 'modseven-cache.sql3',
        'schema'         => 'CREATE TABLE caches(id VARCHAR(127) PRIMARY KEY, tags VARCHAR(255), expiration INTEGER, cache TEXT)',
    ]
    **/
];