<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Captures;


if (!defined('ABSPATH')) {
    exit;
}

final class Captures
{
    public const SOURCES = ['inline', 'block', 'shortcode'];

    public const DEFAULT_VERSION = 'v1';

    public const PURGE_DAYS = 30;

    public const EMAIL_MAX_LENGTH = 190;

    public static function isValidSource(string $source): bool
    {
        $allowed = apply_filters('moonfarmer_reactions_lead_capture_capture_sources', self::SOURCES);
        if (!is_array($allowed)) {
            $allowed = self::SOURCES;
        }
        return in_array($source, $allowed, true);
    }

    public static function consentTextVersion(): string
    {
        $value = apply_filters('moonfarmer_reactions_lead_capture_consent_text_version', self::DEFAULT_VERSION);
        return is_string($value) && $value !== '' ? $value : self::DEFAULT_VERSION;
    }
}
