<?php
declare(strict_types=1);

namespace PulsePress\Providers;

use PulsePress\Blocks\Shortcode;
use PulsePress\Core\ServiceProvider;

final class BlockServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_action('init', [$this, 'registerWordPressArtifacts']);
    }

    public function registerWordPressArtifacts(): void
    {
        if (function_exists('register_block_type')) {
            register_block_type(PULSEPRESS_DIR . 'blocks/reactions');
        }
        if (function_exists('add_shortcode')) {
            add_shortcode(Shortcode::TAG, [Shortcode::class, 'render']);
        }
    }
}
