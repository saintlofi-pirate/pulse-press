<?php
declare(strict_types=1);

namespace PulsePress\Blocks;

final class Shortcode
{
    public const TAG = 'pulsepress';

    public static function render(array|string $attrs, ?string $content = null, string $tag = self::TAG): string
    {
        $atts = is_array($attrs) ? $attrs : [];
        $atts = shortcode_atts(['post_id' => 0], $atts, $tag);

        $postId = (int) ($atts['post_id'] ?? 0);
        if ($postId <= 0) {
            $resolved = get_the_ID();
            $postId   = is_int($resolved) && $resolved > 0 ? $resolved : 0;
        }
        if ($postId <= 0) {
            return '';
        }
        if (get_post_status($postId) === false) {
            return '';
        }
        if (!is_post_publicly_viewable($postId)) {
            return '';
        }

        return WidgetMarkup::container($postId);
    }
}
