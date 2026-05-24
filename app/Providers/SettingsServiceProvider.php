<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Providers;

use Moonfarmer\ReactionsLeadCapture\Core\ServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Http\Controllers\SettingsController;
use Moonfarmer\ReactionsLeadCapture\Settings\Settings;
use Moonfarmer\ReactionsLeadCapture\Settings\SettingsRepository;


if (!defined('ABSPATH')) {
    exit;
}

final class SettingsServiceProvider extends ServiceProvider
{
    public const REST_NAMESPACE = 'moonfarmer-reactions-lead-capture/v1';
    public const ADMIN_SLUG     = 'moonfarmer-reactions-lead-capture';

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
        add_filter('moonfarmer_reactions_lead_capture_positive_reactions', [$this, 'filterPositiveReactions'], 5);
        add_filter('moonfarmer_reactions_lead_capture_widget_auto_insert', [$this, 'filterWidgetAutoInsert'], 5, 2);
        add_filter('moonfarmer_reactions_lead_capture_consent_text_version', [$this, 'filterConsentVersion'], 5);
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
            __('Moonfarmer Reactions Lead Capture', 'moonfarmer-reactions-lead-capture'),
            __('Moonfarmer Reactions Lead Capture', 'moonfarmer-reactions-lead-capture'),
            'manage_options',
            self::ADMIN_SLUG,
            $renderCallback
        );
    }

    public function renderAdminPage(): void
    {
        echo '<div class="wrap"><div id="moonfarmer-reactions-lead-capture-admin">' . esc_html__('Loading…', 'moonfarmer-reactions-lead-capture') . '</div></div>';
    }

    public function filterPositiveReactions($value): array
    {
        if (is_array($value) && $value !== []) {
            return $value;
        }
        $settings = $this->app->get(SettingsRepository::class)->get();
        return $settings['positive_reactions'] ?? Settings::DEFAULTS['positive_reactions'];
    }

    public function filterWidgetAutoInsert($value, string $postType = ''): bool
    {
        $settings = $this->app->get(SettingsRepository::class)->get();
        $types    = $settings['auto_insert_post_types'] ?? Settings::DEFAULTS['auto_insert_post_types'];
        return in_array($postType, (array) $types, true);
    }

    public function filterConsentVersion($value): string
    {
        if (is_string($value) && $value !== Settings::DEFAULTS['consent_text_version']) {
            return $value;
        }
        $settings = $this->app->get(SettingsRepository::class)->get();
        $stored   = $settings['consent_text_version'] ?? Settings::DEFAULTS['consent_text_version'];
        return is_string($stored) && $stored !== '' ? $stored : Settings::DEFAULTS['consent_text_version'];
    }
}
