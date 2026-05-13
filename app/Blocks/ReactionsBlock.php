<?php
declare(strict_types=1);

namespace PulsePress\Blocks;

final class ReactionsBlock
{
    public static function render(array $attributes, string $content = '', mixed $block = null): string
    {
        $postId = isset($attributes['postId']) ? (int) $attributes['postId'] : 0;
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
