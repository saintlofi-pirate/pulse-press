<?php
declare(strict_types=1);

namespace PulsePress\Settings;

use PulsePress\Reactions\Reactions;

final class Settings
{
    public const SCHEMA_VERSION = 1;
    public const OPTION_NAME    = 'pulsepress_settings';

    public const VISIBILITY_CHOICES = ['always', 'never', 'threshold'];
    public const DESIGN_CHOICES     = ['minimal', 'expressive'];
    public const ICON_CHOICES       = ['classic', 'emoji'];
    public const THEME_CHOICES      = ['light', 'dark', 'auto'];
    public const POSITION_CHOICES   = ['above', 'below', 'both'];

    public const DEFAULTS = [
        'count_visibility'       => 'always',
        'count_threshold'        => 5,
        'widget_design'          => 'minimal',
        'icon_style'             => 'classic',
        'theme_mode'             => 'auto',
        'auto_insert_post_types' => ['post'],
        'auto_insert_position'   => 'below',
        'positive_reactions'     => ['love', 'insightful', 'funny'],
        'allow_guest_reactions'  => true,
        'consent_text'           => 'I agree to receive new-post updates.',
        'consent_text_version'   => 'v1',
        'delete_on_uninstall'    => false,
        'retention_days'         => 0,
    ];

    public const CHOICES = [
        'count_visibility'     => self::VISIBILITY_CHOICES,
        'widget_design'        => self::DESIGN_CHOICES,
        'icon_style'           => self::ICON_CHOICES,
        'theme_mode'           => self::THEME_CHOICES,
        'auto_insert_position' => self::POSITION_CHOICES,
    ];

    /** @return array<string, mixed> */
    public static function sanitise(array $input): array
    {
        $clean = [];

        if (array_key_exists('count_visibility', $input)) {
            $clean['count_visibility'] = self::oneOf($input['count_visibility'], self::VISIBILITY_CHOICES, self::DEFAULTS['count_visibility']);
        }
        if (array_key_exists('count_threshold', $input)) {
            $clean['count_threshold'] = self::intRange($input['count_threshold'], 0, 1000, self::DEFAULTS['count_threshold']);
        }
        if (array_key_exists('widget_design', $input)) {
            $clean['widget_design'] = self::oneOf($input['widget_design'], self::DESIGN_CHOICES, self::DEFAULTS['widget_design']);
        }
        if (array_key_exists('icon_style', $input)) {
            $clean['icon_style'] = self::oneOf($input['icon_style'], self::ICON_CHOICES, self::DEFAULTS['icon_style']);
        }
        if (array_key_exists('theme_mode', $input)) {
            $clean['theme_mode'] = self::oneOf($input['theme_mode'], self::THEME_CHOICES, self::DEFAULTS['theme_mode']);
        }
        if (array_key_exists('auto_insert_post_types', $input)) {
            $clean['auto_insert_post_types'] = self::stringArray($input['auto_insert_post_types'], null, self::DEFAULTS['auto_insert_post_types']);
        }
        if (array_key_exists('auto_insert_position', $input)) {
            $clean['auto_insert_position'] = self::oneOf($input['auto_insert_position'], self::POSITION_CHOICES, self::DEFAULTS['auto_insert_position']);
        }
        if (array_key_exists('positive_reactions', $input)) {
            $clean['positive_reactions'] = self::stringArray($input['positive_reactions'], Reactions::TYPES, self::DEFAULTS['positive_reactions']);
        }
        if (array_key_exists('allow_guest_reactions', $input)) {
            $clean['allow_guest_reactions'] = self::boolean($input['allow_guest_reactions'], self::DEFAULTS['allow_guest_reactions']);
        }
        if (array_key_exists('consent_text', $input)) {
            $clean['consent_text'] = self::text($input['consent_text'], 2000, self::DEFAULTS['consent_text']);
        }
        if (array_key_exists('consent_text_version', $input)) {
            $clean['consent_text_version'] = self::text($input['consent_text_version'], 32, self::DEFAULTS['consent_text_version']);
        }
        if (array_key_exists('delete_on_uninstall', $input)) {
            $clean['delete_on_uninstall'] = self::boolean($input['delete_on_uninstall'], self::DEFAULTS['delete_on_uninstall']);
        }
        if (array_key_exists('retention_days', $input)) {
            $clean['retention_days'] = self::intRange($input['retention_days'], 0, 3650, self::DEFAULTS['retention_days']);
        }

        return $clean;
    }

    public static function oneOf(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    public static function intRange(mixed $value, int $min, int $max, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }
        $i = (int) $value;
        if ($i < $min) {
            return $default;
        }
        if ($i > $max) {
            return $max;
        }
        return $i;
    }

    public static function boolean(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($lower, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        return $default;
    }

    /** @param string[]|null $allowed */
    public static function stringArray(mixed $value, ?array $allowed, array $default): array
    {
        if (!is_array($value)) {
            return $default;
        }
        $clean = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                continue;
            }
            if ($allowed !== null && !in_array($item, $allowed, true)) {
                continue;
            }
            if (!in_array($item, $clean, true)) {
                $clean[] = $item;
            }
        }
        return $clean === [] ? $default : $clean;
    }

    public static function text(mixed $value, int $maxLength, string $default): string
    {
        if (!is_string($value)) {
            return $default;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $default;
        }
        return mb_substr($trimmed, 0, $maxLength);
    }
}
