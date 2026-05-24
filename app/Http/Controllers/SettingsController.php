<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Http\Controllers;

use Moonfarmer\ReactionsLeadCapture\Settings\Settings;
use Moonfarmer\ReactionsLeadCapture\Settings\SettingsRepository;
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
                'moonfarmer_reactions_lead_capture_settings_invalid',
                __('Settings payload must be a JSON object.', 'moonfarmer-reactions-lead-capture'),
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
