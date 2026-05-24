<?php
declare(strict_types=1);



if (!defined('ABSPATH')) {
    exit;
}

use Moonfarmer\ReactionsLeadCapture\Providers\AdminServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Providers\AnalyticsServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Providers\AppServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Providers\BlockServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Providers\CaptureServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Providers\DatabaseServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Providers\RestServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Providers\SettingsServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Providers\WidgetServiceProvider;

return [
    'providers' => [
        AppServiceProvider::class,
        DatabaseServiceProvider::class,
        SettingsServiceProvider::class,
        AdminServiceProvider::class,
        RestServiceProvider::class,
        CaptureServiceProvider::class,
        WidgetServiceProvider::class,
        BlockServiceProvider::class,
        AnalyticsServiceProvider::class,
    ],
];
