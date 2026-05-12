<?php
declare(strict_types=1);

namespace PulsePress\Providers;

use PulsePress\Core\ServiceProvider;

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
