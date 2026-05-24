<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Blocks;


if (!defined('ABSPATH')) {
    exit;
}

final class WidgetMarkup
{
    public static function container(int $postId): string
    {
        $attrs = [
            'class'                   => 'moonfarmer-reactions-lead-capture',
            'data-moonfarmer-reactions-lead-capture-widget'  => '',
            'data-moonfarmer-reactions-lead-capture-post-id' => (string) $postId,
        ];

        $filtered = apply_filters('moonfarmer_reactions_lead_capture_widget_container_attrs', $attrs, $postId);
        if (!is_array($filtered)) {
            $filtered = $attrs;
        }

        return '<div' . self::attrString($filtered) . '></div>';
    }

    /** @param array<string, string> $attrs */
    private static function attrString(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $escapedKey = function_exists('esc_attr') ? esc_attr($key) : htmlspecialchars($key, ENT_QUOTES);
            if ($value === '' || $value === true) {
                $parts[] = $escapedKey;
                continue;
            }
            $escapedVal = function_exists('esc_attr') ? esc_attr((string) $value) : htmlspecialchars((string) $value, ENT_QUOTES);
            $parts[] = sprintf('%s="%s"', $escapedKey, $escapedVal);
        }
        return $parts === [] ? '' : ' ' . implode(' ', $parts);
    }
}
