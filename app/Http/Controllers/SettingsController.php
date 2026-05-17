<?php
declare(strict_types=1);

namespace PulsePress\Http\Controllers;

use PulsePress\Settings\Settings;
use PulsePress\Settings\SettingsRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;


if (!defined('ABSPATH')) {
    exit;
}

final class SettingsController
{
    private SettingsRepository $repository;

    public function __construct(SettingsRepository $repository)
    {
        $this->repository = $repository;
    }

    public function read(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'settings' => $this->repository->get(),
            'defaults' => Settings::DEFAULTS,
            'choices'  => Settings::CHOICES,
            'schema_version' => Settings::SCHEMA_VERSION,
        ], 200);
    }

    public function update(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            return new WP_Error(
                'pulsepress_settings_invalid',
                __('Settings payload must be a JSON object.', 'pulse-press'),
                ['status' => 422]
            );
        }

        $merged = $this->repository->save($body);
        return new WP_REST_Response([
            'settings' => $merged,
            'defaults' => Settings::DEFAULTS,
            'choices'  => Settings::CHOICES,
        ], 200);
    }
}
