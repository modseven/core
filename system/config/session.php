<?php

return [

    'cookie' => [
        'driver' => \Modseven\Session\Cookie::class,
        'encrypted' => FALSE,
    ],

    'native' => [
        'driver' => \Modseven\Session\Native::class
    ]

];
