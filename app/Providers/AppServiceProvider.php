<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Providers;

use Moonfarmer\ReactionsLeadCapture\Core\ServiceProvider;


if (!defined('ABSPATH')) {
    exit;
}

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Add new providers and bindings here in later sessions.
    }

    public function boot(): void
    {
        // Wire feature side-effects (hooks, routes, assets) here in later sessions.
    }
}
