<?php
declare(strict_types=1);

defined('ABSPATH') || exit;
if (!function_exists('pulsepress')) {
    function pulsepress(): \PulsePress\Core\Application
    {
        $app = \PulsePress\Core\Application::getInstance();
        if ($app === null) {
            throw new RuntimeException('PulsePress application has not booted yet.');
        }
        return $app;
    }
}
