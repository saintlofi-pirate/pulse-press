<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Providers;

use Moonfarmer\ReactionsLeadCapture\Captures\CaptureExporter;
use Moonfarmer\ReactionsLeadCapture\Captures\CaptureRepository;
use Moonfarmer\ReactionsLeadCapture\Captures\Captures;
use Moonfarmer\ReactionsLeadCapture\Captures\FraudPurger;
use Moonfarmer\ReactionsLeadCapture\Core\ServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Http\Controllers\CaptureController;
use Moonfarmer\ReactionsLeadCapture\Http\Controllers\ExportController;
use Moonfarmer\ReactionsLeadCapture\Reactions\Reactions;


if (!defined('ABSPATH')) {
    exit;
}

final class CaptureServiceProvider extends ServiceProvider
{
    public const REST_NAMESPACE = 'moonfarmer-reactions-lead-capture/v1';
    public const PURGE_HOOK     = 'moonfarmer_reactions_lead_capture_purge_fraud_metadata';

    public function register(): void
    {
        $this->app->singleton(CaptureRepository::class, function () {
            return new CaptureRepository($GLOBALS['wpdb']);
        });
        $this->app->singleton(FraudPurger::class, function () {
            return new FraudPurger($GLOBALS['wpdb']);
        });
        $this->app->singleton(CaptureController::class, function () {
            return new CaptureController($this->app->get(CaptureRepository::class));
        });
        $this->app->singleton(CaptureExporter::class, function () {
            return new CaptureExporter($GLOBALS['wpdb']);
        });
        $this->app->singleton(ExportController::class, function () {
            return new ExportController($this->app->get(CaptureExporter::class));
        });
    }

    public function boot(): void
    {
        add_action('rest_api_init', function (): void {
            /** @var CaptureController $controller */
            $controller = $this->app->get(CaptureController::class);

            register_rest_route(self::REST_NAMESPACE, '/capture', [
                'methods'             => 'POST',
                'callback'            => [$controller, 'capture'],
                'permission_callback' => static function ($request): bool {
                    $nonce = (string) ($request->get_header('X-WP-Nonce') ?? '');
                    return wp_verify_nonce($nonce, 'wp_rest') !== false;
                },
                'args'                => [
                    'post_id'       => ['type' => 'integer', 'required' => true, 'minimum' => 1],
                    'email'         => ['type' => 'string',  'required' => true, 'minLength' => 1, 'maxLength' => Captures::EMAIL_MAX_LENGTH],
                    'reaction_type' => ['type' => 'string',  'required' => true, 'minLength' => 1, 'maxLength' => Reactions::TYPE_LENGTH_LIMIT],
                    'consent'       => ['type' => 'boolean', 'required' => true],
                    'source'        => ['type' => 'string',  'required' => false, 'minLength' => 1, 'maxLength' => 32],
                ],
            ]);
        });

        add_action(self::PURGE_HOOK, function (): void {
            $this->app->get(FraudPurger::class)->run();
        });

        add_action('rest_api_init', function (): void {
            /** @var ExportController $exportController */
            $exportController = $this->app->get(ExportController::class);
            register_rest_route(self::REST_NAMESPACE, '/captures.csv', [
                'methods'             => 'GET',
                'callback'            => [$exportController, 'download'],
                'permission_callback' => static fn () => current_user_can('manage_options'),
            ]);
        });
    }
}
