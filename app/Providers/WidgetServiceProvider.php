<?php
declare(strict_types=1);

namespace PulsePress\Providers;

use PulsePress\Core\ServiceProvider;
use PulsePress\Reactions\Reactions;
use PulsePress\Settings\Settings;
use PulsePress\Settings\SettingsRepository;
use PulsePress\View\Manifest;

defined('ABSPATH') || exit;

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

        if (empty($urls['js'])) {
            return;
        }

        foreach ($urls['css'] as $i => $cssUrl) {
            $handle = self::STYLE_HANDLE . '-' . $i;
            wp_register_style($handle, $cssUrl, [], PULSEPRESS_VERSION);
            wp_enqueue_style($handle);
        }

        $entryUrl   = array_pop($urls['js']);
        $depUrls    = $urls['js'];
        $depHandles = [];
        foreach ($depUrls as $i => $depUrl) {
            $handle = self::SCRIPT_HANDLE . '-dep-' . $i;
            wp_register_script($handle, $depUrl, [], PULSEPRESS_VERSION, true);
            wp_enqueue_script($handle);
            $depHandles[] = $handle;
        }
        wp_register_script(self::SCRIPT_HANDLE, $entryUrl, $depHandles, PULSEPRESS_VERSION, true);
        $this->ensureModuleScripts(array_merge($depHandles, [self::SCRIPT_HANDLE]));

        $postId   = is_singular() ? (int) get_the_ID() : 0;
        $settings = $this->app->has(SettingsRepository::class)
            ? $this->app->get(SettingsRepository::class)->get()
            : Settings::DEFAULTS;
        $payload = [
            'root'                => esc_url_raw(rest_url('pulsepress/v1/')),
            'nonce'               => wp_create_nonce('wp_rest'),
            'postId'              => $postId,
            'reactions'           => array_values((array) apply_filters('pulsepress_reaction_types', Reactions::TYPES)),
            'positiveReactions'   => array_values((array) apply_filters('pulsepress_positive_reactions', $settings['positive_reactions'] ?? Reactions::DEFAULT_POSITIVE)),
            'allowGuestReactions' => (bool) ($settings['allow_guest_reactions'] ?? Settings::DEFAULTS['allow_guest_reactions']),
            'isLoggedIn'          => function_exists('is_user_logged_in') ? \is_user_logged_in() : false,
            'iconStyle'           => (string) ($settings['icon_style'] ?? Settings::DEFAULTS['icon_style']),
            'themeMode'           => (string) ($settings['theme_mode'] ?? Settings::DEFAULTS['theme_mode']),
            'widgetDesign'        => (string) ($settings['widget_design'] ?? Settings::DEFAULTS['widget_design']),
            'countVisibility'     => (string) ($settings['count_visibility'] ?? Settings::DEFAULTS['count_visibility']),
            'countThreshold'      => (int) ($settings['count_threshold'] ?? Settings::DEFAULTS['count_threshold']),
            'i18n'                => [
                'loading'         => __('Loading reactions…', 'pulse-press'),
                'error'           => __('Sorry, your reaction could not be saved. Please try again.', 'pulse-press'),
                'activeSuffix'    => __(', selected', 'pulse-press'),
                'groupLabel'      => __('Reactions', 'pulse-press'),
                'announceReacted' => __('Reacted with {type}.', 'pulse-press'),
                'announceUpdated' => __('Updated reaction to {type}.', 'pulse-press'),
                'capture'         => [
                    'prompt'          => (string) ($settings['consent_text'] ?? Settings::DEFAULTS['consent_text']),
                    'label'           => __('Email address', 'pulse-press'),
                    'placeholder'     => __('you@example.com', 'pulse-press'),
                    'consent'         => (string) ($settings['consent_text'] ?? Settings::DEFAULTS['consent_text']),
                    'consentHelper'   => __('We will only use your email to send new-post notifications. Unsubscribe any time.', 'pulse-press'),
                    'submit'          => __('Subscribe', 'pulse-press'),
                    'submitting'      => __('Submitting…', 'pulse-press'),
                    'thanks'          => __('Thanks — we will keep you in the loop.', 'pulse-press'),
                    'alreadyCaptured' => __('We already have your email saved for this post.', 'pulse-press'),
                    'networkError'    => __('Sorry, that did not go through. Please try again.', 'pulse-press'),
                    'expiredNonce'    => __('Your session has expired. Please refresh the page and try again.', 'pulse-press'),
                    'dismiss'         => __('Dismiss', 'pulse-press'),
                ],
            ],
        ];

        $payload = (array) apply_filters('pulsepress_widget_data', $payload);

        wp_add_inline_script(
            self::SCRIPT_HANDLE,
            'var PulsePressData = ' . wp_json_encode($payload) . ';',
            'before'
        );
        wp_enqueue_script(self::SCRIPT_HANDLE);
    }

    /** @param list<string> $handles */
    private function ensureModuleScripts(array $handles): void
    {
        if (!empty($GLOBALS['pulsepress_module_handles_widget'])) {
            $GLOBALS['pulsepress_module_handles_widget'] = array_merge($GLOBALS['pulsepress_module_handles_widget'], $handles);
            return;
        }
        $GLOBALS['pulsepress_module_handles_widget'] = $handles;
        add_filter('script_loader_tag', static function (string $tag, string $handle) {
            $registered = $GLOBALS['pulsepress_module_handles_widget'] ?? [];
            if (in_array($handle, $registered, true) && strpos($tag, ' type="module"') === false) {
                $tag = preg_replace('/<script /', '<script type="module" ', $tag, 1);
            }
            return $tag;
        }, 10, 2);
    }

    public function maybeAppendWidget(string $content): string
    {
        if (is_admin() || !is_singular()) {
            return $content;
        }

        if (strpos($content, 'data-pulsepress-widget') !== false) {
            return $content;
        }

        $postId = (int) get_the_ID();
        if ($postId <= 0) {
            return $content;
        }

        if ($this->app->has(\PulsePress\Visibility\VisibilityResolver::class)) {
            /** @var \PulsePress\Visibility\VisibilityResolver $resolver */
            $resolver = $this->app->get(\PulsePress\Visibility\VisibilityResolver::class);
            if (!$resolver->shouldRender($postId, 'auto')) {
                return $content;
            }
        } else {
            $postType   = get_post_type();
            $defaultOn  = $postType === 'post';
            $autoInsert = (bool) apply_filters('pulsepress_widget_auto_insert', $defaultOn, $postType);
            if (!$autoInsert) {
                return $content;
            }
        }

        $settings = $this->app->has(SettingsRepository::class)
            ? $this->app->get(SettingsRepository::class)->get()
            : Settings::DEFAULTS;
        $position = (string) ($settings['auto_insert_position'] ?? Settings::DEFAULTS['auto_insert_position']);
        $widget   = \PulsePress\Blocks\WidgetMarkup::container($postId);

        if ($position === 'above') {
            return $widget . $content;
        }
        if ($position === 'both') {
            return $widget . $content . $widget;
        }

        return $content . $widget;
    }
}
