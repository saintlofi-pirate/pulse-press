<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Reactions;

use WP_REST_Request;


if (!defined('ABSPATH')) {
    exit;
}

final class UserHash
{
    public const SALT_SCOPE            = 'moonfarmer_reactions_lead_capture_dedup';
    public const SALT_SCOPE_CAPTURE_IP = 'moonfarmer_reactions_lead_capture_capture_ip';
    public const SALT_SCOPE_CAPTURE_UA = 'moonfarmer_reactions_lead_capture_capture_ua';

    public static function compute(string $ip, string $userAgent): string
    {
        return self::hashWithScope($ip . '|' . $userAgent, self::SALT_SCOPE);
    }

    public static function hashIp(string $ip): string
    {
        return self::hashWithScope($ip, self::SALT_SCOPE_CAPTURE_IP);
    }

    public static function hashUserAgent(string $userAgent): string
    {
        return self::hashWithScope($userAgent, self::SALT_SCOPE_CAPTURE_UA);
    }

    public static function fromRequest(WP_REST_Request $request): string
    {
        [$ip, $userAgent] = self::resolveFromRequest($request);
        return self::compute($ip, $userAgent);
    }

    /** @return array{ip: string, userAgent: string, ipHash: string, userAgentHash: string} */
    public static function captureFingerprintFromRequest(WP_REST_Request $request): array
    {
        [$ip, $userAgent] = self::resolveFromRequest($request);
        return [
            'ip'            => $ip,
            'userAgent'     => $userAgent,
            'ipHash'        => self::hashIp($ip),
            'userAgentHash' => self::hashUserAgent($userAgent),
        ];
    }

    /** @return array{0: string, 1: string} */
    private static function resolveFromRequest(WP_REST_Request $request): array
    {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR'])) : '';
        $ip     = (string) apply_filters('moonfarmer_reactions_lead_capture_client_ip', $remote, $request);

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Unslashed and sanitized on the next line.
        $rawUa     = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        $userAgent = sanitize_text_field(wp_unslash($rawUa));

        return [$ip, $userAgent];
    }

    private static function hashWithScope(string $payload, string $scope): string
    {
        return hash_hmac('sha256', $payload, wp_salt('auth') . $scope);
    }
}
