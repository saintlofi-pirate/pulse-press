<?php
declare(strict_types=1);

use PulsePress\Providers\AppServiceProvider;
use PulsePress\Providers\DatabaseServiceProvider;

return [
    'providers' => [
        AppServiceProvider::class,
        DatabaseServiceProvider::class,
    ],
];
