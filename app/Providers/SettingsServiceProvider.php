<?php
declare(strict_types=1);

namespace PulsePress\Providers;

use PulsePress\Core\ServiceProvider;
use PulsePress\Http\Controllers\SettingsController;
use PulsePress\Settings\Settings;
use PulsePress\Settings\SettingsRepository;

final class SettingsServiceProvider extends ServiceProvider
{
    public const REST_NAMESPACE = 'pulsepress/v1';
    public const ADMIN_SLUG     = 'pulsepress';

    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class, fn () => new SettingsRepository());
        $this->app->singleton(SettingsController::class, function () {
            return new SettingsController($this->app->get(SettingsRepository::class));
        });
    }

    public function boot(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('admin_menu', [$this, 'registerAdminMenu']);

        // Lower-priority filter handlers so site-registered filters at default priority 10 still win.
        add_filter('pulsepress_positive_reactions', [$this, 'filterPositiveReactions'], 5);
        add_filter('pulsepress_widget_auto_insert', [$this, 'filterWidgetAutoInsert'], 5, 2);
        add_filter('pulsepress_consent_text_version', [$this, 'filterConsentVersion'], 5);
    }

    public function registerRestRoutes(): void
    {
        $controller = $this->app->get(SettingsController::class);
        $permission = static fn () => current_user_can('manage_options');

        register_rest_route(self::REST_NAMESPACE, '/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [$controller, 'read'],
                'permission_callback' => $permission,
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$controller, 'update'],
                'permission_callback' => $permission,
            ],
        ]);
    }

    public function registerAdminMenu(): void
    {
        $renderCallback = [$this, 'renderAdminPage'];
        if ($this->app->has(AdminServiceProvider::class)) {
            $renderCallback = [$this->app->get(AdminServiceProvider::class), 'renderPage'];
        }

        add_options_page(
            __('PulsePress', 'pulsepress'),
            __('PulsePress', 'pulsepress'),
            'manage_options',
            self::ADMIN_SLUG,
            $renderCallback
        );
    }

    public function renderAdminPage(): void
    {
        echo '<div class="wrap"><div id="pulsepress-admin">' . esc_html__('Loading…', 'pulsepress') . '</div></div>';
    }

    public function filterPositiveReactions(mixed $value): array
    {
        if (is_array($value) && $value !== []) {
            return $value;
        }
        $settings = $this->app->get(SettingsRepository::class)->get();
        return $settings['positive_reactions'] ?? Settings::DEFAULTS['positive_reactions'];
    }

    public function filterWidgetAutoInsert(mixed $value, string $postType = ''): bool
    {
        $settings = $this->app->get(SettingsRepository::class)->get();
        $types    = $settings['auto_insert_post_types'] ?? Settings::DEFAULTS['auto_insert_post_types'];
        return in_array($postType, (array) $types, true);
    }

    public function filterConsentVersion(mixed $value): string
    {
        if (is_string($value) && $value !== Settings::DEFAULTS['consent_text_version']) {
            return $value;
        }
        $settings = $this->app->get(SettingsRepository::class)->get();
        $stored   = $settings['consent_text_version'] ?? Settings::DEFAULTS['consent_text_version'];
        return is_string($stored) && $stored !== '' ? $stored : Settings::DEFAULTS['consent_text_version'];
    }
}
