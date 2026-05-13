<?php
declare(strict_types=1);

use PulsePress\Providers\AppServiceProvider;
use PulsePress\Providers\CaptureServiceProvider;
use PulsePress\Providers\DatabaseServiceProvider;
use PulsePress\Providers\RestServiceProvider;
use PulsePress\Providers\WidgetServiceProvider;

return [
    'providers' => [
        AppServiceProvider::class,
        DatabaseServiceProvider::class,
        RestServiceProvider::class,
        CaptureServiceProvider::class,
        WidgetServiceProvider::class,
    ],
];
