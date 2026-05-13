<?php
declare(strict_types=1);

use PulsePress\Providers\AppServiceProvider;
use PulsePress\Providers\DatabaseServiceProvider;
use PulsePress\Providers\RestServiceProvider;

return [
    'providers' => [
        AppServiceProvider::class,
        DatabaseServiceProvider::class,
        RestServiceProvider::class,
    ],
];
