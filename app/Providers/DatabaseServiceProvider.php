<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Providers;

use Moonfarmer\ReactionsLeadCapture\Core\ServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Database\Migrator;
use Moonfarmer\ReactionsLeadCapture\Database\Schema;


if (!defined('ABSPATH')) {
    exit;
}

final class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Schema::class, fn () => new Schema());
        $this->app->singleton(Migrator::class, function () {
            return new Migrator($GLOBALS['wpdb'], $this->app->get(Schema::class));
        });
    }

    public function boot(): void
    {
        /** @var Migrator $migrator */
        $migrator = $this->app->get(Migrator::class);

        if ($migrator->currentVersion() < $migrator->latestVersion()) {
            $migrator->migrate();
        }
    }
}
