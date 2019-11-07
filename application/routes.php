<?php

use Modseven\Route;

// If you are not using composer, we have to inject the Demo Applciation Namespace
\Modseven\Core::registerModule('Application\\', APPPATH . 'classes');

Route::set('default', '(<controller>(/<action>(/<id>)))')
    ->defaults([
        'namespace' => 'Application',
        'controller' => 'Welcome',
        'action' => 'index',
    ]);
