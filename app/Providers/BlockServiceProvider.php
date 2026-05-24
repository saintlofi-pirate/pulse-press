<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Providers;

use Moonfarmer\ReactionsLeadCapture\Blocks\Shortcode;
use Moonfarmer\ReactionsLeadCapture\Core\ServiceProvider;


if (!defined('ABSPATH')) {
    exit;
}

final class BlockServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_action('init', [$this, 'registerWordPressArtifacts']);
    }

    public function registerWordPressArtifacts(): void
    {
        if (function_exists('register_block_type')) {
            register_block_type(MOONFARMER_REACTIONS_LEAD_CAPTURE_DIR . 'blocks/reactions');
        }
        if (function_exists('add_shortcode')) {
            add_shortcode(Shortcode::TAG, [Shortcode::class, 'render']);
        }
    }
}
