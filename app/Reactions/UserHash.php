<?php
declare(strict_types=1);

namespace PulsePress\Reactions;

use WP_REST_Request;

final class UserHash
{
    public const SALT_SCOPE = 'pulsepress_dedup';

    public static function compute(string $ip, string $userAgent): string
    {
        return hash_hmac(
            'sha256',
            $ip . '|' . $userAgent,
            wp_salt('auth') . self::SALT_SCOPE
        );
    }

    public static function fromRequest(WP_REST_Request $request): string
    {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $ip     = (string) apply_filters('pulsepress_client_ip', $remote, $request);

        $rawUa     = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $userAgent = sanitize_text_field(wp_unslash($rawUa));

        return self::compute($ip, $userAgent);
    }
}
