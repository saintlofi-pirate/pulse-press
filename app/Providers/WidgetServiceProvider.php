<?php
declare(strict_types=1);

namespace PulsePress\Providers;

use PulsePress\Core\ServiceProvider;
use PulsePress\Reactions\Reactions;
use PulsePress\Settings\Settings;
use PulsePress\Settings\SettingsRepository;
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

        $postId   = is_singular() ? (int) get_the_ID() : 0;
        $settings = $this->app->has(SettingsRepository::class)
            ? $this->app->get(SettingsRepository::class)->get()
            : Settings::DEFAULTS;
        $payload = [
            'root'              => esc_url_raw(rest_url('pulsepress/v1/')),
            'nonce'             => wp_create_nonce('wp_rest'),
            'postId'            => $postId,
            'reactions'         => array_values((array) apply_filters('pulsepress_reaction_types', Reactions::TYPES)),
            'positiveReactions' => array_values((array) apply_filters('pulsepress_positive_reactions', $settings['positive_reactions'] ?? Reactions::DEFAULT_POSITIVE)),
            'iconStyle'         => (string) ($settings['icon_style'] ?? Settings::DEFAULTS['icon_style']),
            'themeMode'         => (string) ($settings['theme_mode'] ?? Settings::DEFAULTS['theme_mode']),
            'widgetDesign'      => (string) ($settings['widget_design'] ?? Settings::DEFAULTS['widget_design']),
            'countVisibility'   => (string) ($settings['count_visibility'] ?? Settings::DEFAULTS['count_visibility']),
            'countThreshold'    => (int) ($settings['count_threshold'] ?? Settings::DEFAULTS['count_threshold']),
            'i18n'              => [
                'loading'         => __('Loading reactions…', 'pulsepress'),
                'error'           => __('Sorry, your reaction could not be saved. Please try again.', 'pulsepress'),
                'activeSuffix'    => __(', selected', 'pulsepress'),
                'groupLabel'      => __('Reactions', 'pulsepress'),
                'announceReacted' => __('Reacted with {type}.', 'pulsepress'),
                'announceUpdated' => __('Updated reaction to {type}.', 'pulsepress'),
                'capture'         => [
                    'prompt'          => __('Liked this? Get the next one in your inbox.', 'pulsepress'),
                    'label'           => __('Email address', 'pulsepress'),
                    'placeholder'     => __('you@example.com', 'pulsepress'),
                    'consent'         => __('I agree to receive new-post updates.', 'pulsepress'),
                    'consentHelper'   => __('We will only use your email to send new-post notifications. Unsubscribe any time.', 'pulsepress'),
                    'submit'          => __('Subscribe', 'pulsepress'),
                    'submitting'      => __('Submitting…', 'pulsepress'),
                    'thanks'          => __('Thanks — we will keep you in the loop.', 'pulsepress'),
                    'alreadyCaptured' => __('We already have your email saved for this post.', 'pulsepress'),
                    'networkError'    => __('Sorry, that did not go through. Please try again.', 'pulsepress'),
                    'expiredNonce'    => __('Your session has expired. Please refresh the page and try again.', 'pulsepress'),
                    'dismiss'         => __('Dismiss', 'pulsepress'),
                ],
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
