<?php
declare(strict_types=1);

namespace PulsePress\Visibility;

use PulsePress\Settings\SettingsRepository;

final class VisibilityResolver
{
    public const META_KEY = '_pulsepress_widget_state';

    public const MODE_AUTO = 'auto';
    public const MODE_ON   = 'on';
    public const MODE_OFF  = 'off';

    public const MODES = [self::MODE_AUTO, self::MODE_ON, self::MODE_OFF];

    public function __construct(private SettingsRepository $settings)
    {
    }

    public function mode(int $postId): string
    {
        if ($postId <= 0) {
            return self::MODE_AUTO;
        }

        $stored = (string) get_post_meta($postId, self::META_KEY, true);
        $mode   = self::sanitiseMode($stored);
        $mode   = apply_filters('pulsepress_visibility_mode', $mode, $postId, '');

        return self::sanitiseMode($mode);
    }

    public function shouldRender(int $postId, string $context): bool
    {
        if ($postId <= 0) {
            return false;
        }

        $mode = $this->mode($postId);
        if ($mode === self::MODE_ON) {
            return true;
        }
        if ($mode === self::MODE_OFF) {
            return false;
        }

        $postType = get_post_type($postId);
        if (!is_string($postType) || $postType === '') {
            return false;
        }

        $settings = $this->settings->get();
        $hideList = is_array($settings['hide_on_post_types'] ?? null) ? $settings['hide_on_post_types'] : [];
        if (in_array($postType, $hideList, true)) {
            return false;
        }

        return match ($context) {
            'block', 'shortcode' => true,
            default              => in_array($postType, (array) ($settings['auto_insert_post_types'] ?? []), true),
        };
    }

    public static function sanitiseMode(mixed $value): string
    {
        if (is_string($value) && in_array($value, self::MODES, true)) {
            return $value;
        }
        return self::MODE_AUTO;
    }
}
