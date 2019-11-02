<?php

use Modseven\Route;

Route::set('default', '(<controller>(/<action>(/<id>)))')
    ->defaults([
        'controller' => 'Welcome',
        'action' => 'index',
    ]);
