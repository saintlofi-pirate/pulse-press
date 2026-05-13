<?php
declare(strict_types=1);

namespace PulsePress\Providers;

use PulsePress\Core\ServiceProvider;
use PulsePress\Reactions\Reactions;
use PulsePress\View\Manifest;

final class WidgetServiceProvider extends ServiceProvider
{
    public const SCRIPT_HANDLE = 'pulsepress-widget';
    public const STYLE_HANDLE  = 'pulsepress-widget';
    public const ENTRY         = 'resources/widget/index.ts';

    public function register(): void
    {
        $this->app->singleton(Manifest::class, function () {
            return new Manifest(
                PULSEPRESS_DIR . 'dist/.vite/manifest.json',
                PULSEPRESS_URL . 'dist/'
            );
        });
    }

    public function boot(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('the_content', [$this, 'maybeAppendWidget'], 20);
    }

    public function enqueueAssets(): void
    {
        if (is_admin()) {
            return;
        }
        if (!is_singular('post') && !apply_filters('pulsepress_widget_enqueue', false)) {
            return;
        }

        /** @var Manifest $manifest */
        $manifest = $this->app->get(Manifest::class);
        $urls     = $manifest->resolve(self::ENTRY);

        if ($urls['js'] === null) {
            return;
        }

        if ($urls['css'] !== null) {
            wp_register_style(self::STYLE_HANDLE, $urls['css'], [], PULSEPRESS_VERSION);
            wp_enqueue_style(self::STYLE_HANDLE);
        }

        wp_register_script(self::SCRIPT_HANDLE, $urls['js'], [], PULSEPRESS_VERSION, true);

        $postId  = is_singular() ? (int) get_the_ID() : 0;
        $payload = [
            'root'      => esc_url_raw(rest_url('pulsepress/v1/')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'postId'    => $postId,
            'reactions' => array_values((array) apply_filters('pulsepress_reaction_types', Reactions::TYPES)),
            'i18n'      => [
                'loading'      => __('Loading…', 'pulsepress'),
                'error'        => __('Please try again.', 'pulsepress'),
                'activeSuffix' => __(' (selected)', 'pulsepress'),
            ],
        ];

        $payload = (array) apply_filters('pulsepress_widget_data', $payload);

        wp_localize_script(self::SCRIPT_HANDLE, 'PulsePressData', $payload);
        wp_enqueue_script(self::SCRIPT_HANDLE);
    }

    public function maybeAppendWidget(string $content): string
    {
        if (is_admin() || !is_singular()) {
            return $content;
        }

        $postType   = get_post_type();
        $defaultOn  = $postType === 'post';
        $autoInsert = (bool) apply_filters('pulsepress_widget_auto_insert', $defaultOn, $postType);

        if (!$autoInsert) {
            return $content;
        }

        $postId = (int) get_the_ID();
        if ($postId <= 0) {
            return $content;
        }

        $container = sprintf(
            '<div class="pulsepress" data-pulsepress-widget data-pulsepress-post-id="%d"></div>',
            $postId
        );

        return $content . $container;
    }
}
