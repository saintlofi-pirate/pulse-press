<?php
declare(strict_types=1);

namespace PulsePress\Blocks;

use PulsePress\Core\Application;
use PulsePress\Visibility\VisibilityResolver;

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

        $app = Application::getInstance();
        if ($app !== null && $app->has(VisibilityResolver::class)) {
            /** @var VisibilityResolver $resolver */
            $resolver = $app->get(VisibilityResolver::class);
            if (!$resolver->shouldRender($postId, 'block')) {
                return '';
            }
        }

        return WidgetMarkup::container($postId);
    }
}
