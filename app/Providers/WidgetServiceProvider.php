<?php
declare(strict_types=1);

namespace PulsePress\Providers;

use PulsePress\Blocks\Shortcode;
use PulsePress\Core\ServiceProvider;
use PulsePress\Reactions\Reactions;
use PulsePress\Settings\Settings;
use PulsePress\Settings\SettingsRepository;
use PulsePress\View\Manifest;
use PulsePress\Visibility\VisibilityResolver;


if (!defined('ABSPATH')) {
    exit;
}

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
        if (!$this->shouldEnqueueAssets()) {
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
            wp_script_add_data($handle, 'type', 'module');
            wp_enqueue_script($handle);
            $depHandles[] = $handle;
        }
        wp_register_script(self::SCRIPT_HANDLE, $entryUrl, $depHandles, PULSEPRESS_VERSION, true);
        wp_script_add_data(self::SCRIPT_HANDLE, 'type', 'module');
        $this->ensureModuleScripts(array_merge($depHandles, [self::SCRIPT_HANDLE]));

        $postId   = is_singular() ? (int) get_the_ID() : 0;
        $settings = $this->app->has(SettingsRepository::class)
            ? $this->app->get(SettingsRepository::class)->get()
            : Settings::DEFAULTS;
        $enabledReactions = (array) ($settings['enabled_reactions'] ?? Reactions::TYPES);
        $payload = [
            'root'                => esc_url_raw(rest_url('pulsepress/v1/')),
            'nonce'               => wp_create_nonce('wp_rest'),
            'postId'              => $postId,
            'reactions'           => array_values((array) apply_filters('pulsepress_reaction_types', $enabledReactions)),
            'positiveReactions'   => array_values((array) apply_filters('pulsepress_positive_reactions', $settings['positive_reactions'] ?? Reactions::DEFAULT_POSITIVE)),
            'allowGuestReactions' => (bool) ($settings['allow_guest_reactions'] ?? Settings::DEFAULTS['allow_guest_reactions']),
            'isLoggedIn'          => function_exists('is_user_logged_in') ? \is_user_logged_in() : false,
            'iconStyle'           => (string) ($settings['icon_style'] ?? Settings::DEFAULTS['icon_style']),
            'themeMode'           => (string) ($settings['theme_mode'] ?? Settings::DEFAULTS['theme_mode']),
            'primaryColor'        => (string) ($settings['primary_color'] ?? Settings::DEFAULTS['primary_color']),
            'widgetDesign'        => (string) ($settings['widget_design'] ?? Settings::DEFAULTS['widget_design']),
            'animationMode'       => (string) ($settings['animation_mode'] ?? Settings::DEFAULTS['animation_mode']),
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
                    'prompt'          => __('Get future post updates', 'pulse-press'),
                    'label'           => __('Email address', 'pulse-press'),
                    'placeholder'     => __('you@example.com', 'pulse-press'),
                    'consent'         => (string) ($settings['consent_text'] ?? Settings::DEFAULTS['consent_text']),
                    'consentHelper'   => __('We will only use your email to send new-post notifications. Unsubscribe any time.', 'pulse-press'),
                    'submit'          => __('Subscribe', 'pulse-press'),
                    'submitting'      => __('Submitting…', 'pulse-press'),
                    'thanks'          => __('You are subscribed. Thanks - we will send future post updates.', 'pulse-press'),
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
            'window.PulsePressData = ' . wp_json_encode($payload) . ';',
            'before'
        );
        wp_enqueue_script(self::SCRIPT_HANDLE);
    }

    private function shouldEnqueueAssets(): bool
    {
        if ((bool) apply_filters('pulsepress_widget_enqueue', false)) {
            return true;
        }

        if (!is_singular()) {
            return false;
        }

        $postId = (int) get_the_ID();
        if ($postId <= 0) {
            return false;
        }

        if ($this->contentHasWidgetPlacement()) {
            return $this->canRenderWidget($postId, 'block') || $this->canRenderWidget($postId, 'shortcode');
        }

        return $this->canRenderWidget($postId, 'auto');
    }

    private function contentHasWidgetPlacement(): bool
    {
        $post = function_exists('get_post') ? get_post() : null;
        $content = is_object($post) && isset($post->post_content) && is_string($post->post_content)
            ? $post->post_content
            : '';

        if ($content === '') {
            return false;
        }

        if (function_exists('has_block') && has_block('pulsepress/reactions', $post)) {
            return true;
        }

        return function_exists('has_shortcode') && has_shortcode($content, Shortcode::TAG);
    }

    private function canRenderWidget(int $postId, string $context): bool
    {
        if ($this->app->has(VisibilityResolver::class)) {
            /** @var VisibilityResolver $resolver */
            $resolver = $this->app->get(VisibilityResolver::class);
            return $resolver->shouldRender($postId, $context);
        }

        if ($context === 'block' || $context === 'shortcode') {
            return true;
        }

        $postType   = get_post_type();
        $defaultOn  = $postType === 'post';
        return (bool) apply_filters('pulsepress_widget_auto_insert', $defaultOn, $postType);
    }

    /** @param list<string> $handles */
    private function ensureModuleScripts(array $handles): void
    {
        $handlesWithRenderedIds = array_merge($handles, array_map(static function (string $handle): string {
            return $handle . '-js';
        }, $handles));

        if (!empty($GLOBALS['pulsepress_module_handles_widget'])) {
            $GLOBALS['pulsepress_module_handles_widget'] = array_merge($GLOBALS['pulsepress_module_handles_widget'], $handlesWithRenderedIds);
            return;
        }
        $GLOBALS['pulsepress_module_handles_widget'] = $handlesWithRenderedIds;
        add_filter('wp_script_attributes', static function (array $attributes): array {
            $registered = $GLOBALS['pulsepress_module_handles_widget'] ?? [];
            $id         = isset($attributes['id']) ? (string) $attributes['id'] : '';
            if ($id !== '' && in_array($id, $registered, true)) {
                $attributes['type'] = 'module';
            }

            return $attributes;
        });
        add_filter('script_loader_tag', static function (string $tag, string $handle) {
            $registered = $GLOBALS['pulsepress_module_handles_widget'] ?? [];
            $isRegisteredHandle = in_array($handle, $registered, true);
            $isRegisteredTag    = false;
            foreach ($registered as $registeredHandle) {
                if (strpos($tag, 'id="' . $registeredHandle . '-js') !== false || strpos($tag, "id='" . $registeredHandle . "-js") !== false) {
                    $isRegisteredTag = true;
                    break;
                }
            }

            if (($isRegisteredHandle || $isRegisteredTag) && strpos($tag, ' type="module"') === false) {
                $tag = preg_replace('/<script(?![^>]*\btype=)(?=[^>]*\bsrc=)/', '<script type="module"', $tag, 1);
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

        if ($this->app->has(VisibilityResolver::class)) {
            /** @var VisibilityResolver $resolver */
            $resolver = $this->app->get(VisibilityResolver::class);
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
        $design   = (string) ($settings['widget_design'] ?? Settings::DEFAULTS['widget_design']);
        $widget   = \PulsePress\Blocks\WidgetMarkup::container($postId);

        if ($position === 'above' || ($position === 'below' && $design === 'vertical_rail')) {
            return $widget . $content;
        }
        if ($position === 'both') {
            return $widget . $content . $widget;
        }

        return $content . $widget;
    }
}
