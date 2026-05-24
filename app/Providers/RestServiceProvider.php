<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Providers;

use Moonfarmer\ReactionsLeadCapture\Core\ServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Http\Controllers\ReactionController;
use Moonfarmer\ReactionsLeadCapture\Reactions\ReactionRepository;
use Moonfarmer\ReactionsLeadCapture\Reactions\Reactions;
use Moonfarmer\ReactionsLeadCapture\Settings\SettingsRepository;


if (!defined('ABSPATH')) {
    exit;
}

final class RestServiceProvider extends ServiceProvider
{
    public const REST_NAMESPACE = 'moonfarmer-reactions-lead-capture/v1';

    public function register(): void
    {
        $this->app->singleton(ReactionRepository::class, function () {
            return new ReactionRepository($GLOBALS['wpdb']);
        });
        $this->app->singleton(ReactionController::class, function () {
            return new ReactionController($this->app->get(ReactionRepository::class));
        });
    }

    public function boot(): void
    {
        add_action('rest_api_init', function (): void {
            /** @var ReactionController $controller */
            $controller = $this->app->get(ReactionController::class);

            $settingsRepo = $this->app->has(SettingsRepository::class)
                ? $this->app->get(SettingsRepository::class)
                : null;

            register_rest_route(self::REST_NAMESPACE, '/react', [
                'methods'             => 'POST',
                'callback'            => [$controller, 'react'],
                'permission_callback' => static function ($request) use ($settingsRepo) {
                    $nonce = (string) ($request->get_header('X-WP-Nonce') ?? '');
                    if (wp_verify_nonce($nonce, 'wp_rest') === false) {
                        return false;
                    }
                    $allowGuests = true;
                    if ($settingsRepo !== null) {
                        $settings    = $settingsRepo->get();
                        $allowGuests = (bool) ($settings['allow_guest_reactions'] ?? true);
                    }
                    if (!$allowGuests && !is_user_logged_in()) {
                        return new \WP_Error(
                            'moonfarmer_reactions_lead_capture_login_required',
                            __('Please sign in to react.', 'moonfarmer-reactions-lead-capture'),
                            ['status' => 401]
                        );
                    }
                    return true;
                },
                'args'                => [
                    'post_id' => [
                        'type'     => 'integer',
                        'required' => true,
                        'minimum'  => 1,
                    ],
                    'reaction_type' => [
                        'type'      => 'string',
                        'required'  => true,
                        'minLength' => 1,
                        'maxLength' => Reactions::TYPE_LENGTH_LIMIT,
                    ],
                ],
            ]);

            register_rest_route(self::REST_NAMESPACE, '/counts/(?P<post_id>\d+)', [
                'methods'             => 'GET',
                'callback'            => [$controller, 'counts'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'post_id' => [
                        'type'     => 'integer',
                        'required' => true,
                        'minimum'  => 1,
                    ],
                ],
            ]);
        });
    }
}
