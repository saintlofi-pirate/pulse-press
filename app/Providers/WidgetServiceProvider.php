<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Providers;

use Moonfarmer\ReactionsLeadCapture\Blocks\Shortcode;
use Moonfarmer\ReactionsLeadCapture\Core\ServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Reactions\Reactions;
use Moonfarmer\ReactionsLeadCapture\Settings\Settings;
use Moonfarmer\ReactionsLeadCapture\Settings\SettingsRepository;
use Moonfarmer\ReactionsLeadCapture\View\Manifest;
use Moonfarmer\ReactionsLeadCapture\Visibility\VisibilityResolver;


if (!defined('ABSPATH')) {
    exit;
}

final class WidgetServiceProvider extends ServiceProvider
{
    public const SCRIPT_HANDLE = 'moonfarmer-reactions-lead-capture-widget';
    public const STYLE_HANDLE  = 'moonfarmer-reactions-lead-capture-widget';
    public const ENTRY         = 'resources/widget/index.ts';

    public function register(): void
    {
        $this->app->singleton(Manifest::class, function () {
            return new Manifest(
                MOONFARMER_REACTIONS_LEAD_CAPTURE_DIR . 'dist/.vite/manifest.json',
                MOONFARMER_REACTIONS_LEAD_CAPTURE_URL . 'dist/'
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
            wp_register_style($handle, $cssUrl, [], MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION);
            wp_enqueue_style($handle);
        }

        $entryUrl   = array_pop($urls['js']);
        $depUrls    = $urls['js'];
        $depHandles = [];
        foreach ($depUrls as $i => $depUrl) {
            $handle = self::SCRIPT_HANDLE . '-dep-' . $i;
            wp_register_script($handle, $depUrl, [], MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION, true);
            wp_script_add_data($handle, 'type', 'module');
            wp_enqueue_script($handle);
            $depHandles[] = $handle;
        }
        wp_register_script(self::SCRIPT_HANDLE, $entryUrl, $depHandles, MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION, true);
        wp_script_add_data(self::SCRIPT_HANDLE, 'type', 'module');
        $this->ensureModuleScripts(array_merge($depHandles, [self::SCRIPT_HANDLE]));

        $postId   = is_singular() ? (int) get_the_ID() : 0;
        $settings = $this->app->has(SettingsRepository::class)
            ? $this->app->get(SettingsRepository::class)->get()
            : Settings::DEFAULTS;
        $enabledReactions = (array) ($settings['enabled_reactions'] ?? Reactions::TYPES);
        $payload = [
            'root'                => esc_url_raw(rest_url('moonfarmer-reactions-lead-capture/v1/')),
            'nonce'               => wp_create_nonce('wp_rest'),
            'postId'              => $postId,
            'reactions'           => array_values((array) apply_filters('moonfarmer_reactions_lead_capture_reaction_types', $enabledReactions)),
            'positiveReactions'   => array_values((array) apply_filters('moonfarmer_reactions_lead_capture_positive_reactions', $settings['positive_reactions'] ?? Reactions::DEFAULT_POSITIVE)),
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
                'loading'         => __('Loading reactions…', 'moonfarmer-reactions-lead-capture'),
                'error'           => __('Sorry, your reaction could not be saved. Please try again.', 'moonfarmer-reactions-lead-capture'),
                'activeSuffix'    => __(', selected', 'moonfarmer-reactions-lead-capture'),
                'groupLabel'      => __('Reactions', 'moonfarmer-reactions-lead-capture'),
                'announceReacted' => __('Reacted with {type}.', 'moonfarmer-reactions-lead-capture'),
                'announceUpdated' => __('Updated reaction to {type}.', 'moonfarmer-reactions-lead-capture'),
                'capture'         => [
                    'prompt'          => __('Get future post updates', 'moonfarmer-reactions-lead-capture'),
                    'label'           => __('Email address', 'moonfarmer-reactions-lead-capture'),
                    'placeholder'     => __('you@example.com', 'moonfarmer-reactions-lead-capture'),
                    'consent'         => (string) ($settings['consent_text'] ?? Settings::DEFAULTS['consent_text']),
                    'consentHelper'   => __('We will only use your email to send new-post notifications. Unsubscribe any time.', 'moonfarmer-reactions-lead-capture'),
                    'submit'          => __('Subscribe', 'moonfarmer-reactions-lead-capture'),
                    'submitting'      => __('Submitting…', 'moonfarmer-reactions-lead-capture'),
                    'thanks'          => __('You are subscribed. Thanks - we will send future post updates.', 'moonfarmer-reactions-lead-capture'),
                    'alreadyCaptured' => __('We already have your email saved for this post.', 'moonfarmer-reactions-lead-capture'),
                    'networkError'    => __('Sorry, that did not go through. Please try again.', 'moonfarmer-reactions-lead-capture'),
                    'expiredNonce'    => __('Your session has expired. Please refresh the page and try again.', 'moonfarmer-reactions-lead-capture'),
                    'dismiss'         => __('Dismiss', 'moonfarmer-reactions-lead-capture'),
                ],
            ],
        ];

        $payload = (array) apply_filters('moonfarmer_reactions_lead_capture_widget_data', $payload);

        wp_add_inline_script(
            self::SCRIPT_HANDLE,
            'window.MoonfarmerReactionsLeadCaptureData = ' . wp_json_encode($payload) . ';',
            'before'
        );
        wp_enqueue_script(self::SCRIPT_HANDLE);
    }

    private function shouldEnqueueAssets(): bool
    {
        if ((bool) apply_filters('moonfarmer_reactions_lead_capture_widget_enqueue', false)) {
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

        if (function_exists('has_block') && has_block('moonfarmer-reactions-lead-capture/reactions', $post)) {
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
        return (bool) apply_filters('moonfarmer_reactions_lead_capture_widget_auto_insert', $defaultOn, $postType);
    }

    /** @param list<string> $handles */
    private function ensureModuleScripts(array $handles): void
    {
        $handlesWithRenderedIds = array_merge($handles, array_map(static function (string $handle): string {
            return $handle . '-js';
        }, $handles));

        if (!empty($GLOBALS['moonfarmer_reactions_lead_capture_module_handles_widget'])) {
            $GLOBALS['moonfarmer_reactions_lead_capture_module_handles_widget'] = array_merge($GLOBALS['moonfarmer_reactions_lead_capture_module_handles_widget'], $handlesWithRenderedIds);
            return;
        }
        $GLOBALS['moonfarmer_reactions_lead_capture_module_handles_widget'] = $handlesWithRenderedIds;
        add_filter('wp_script_attributes', static function (array $attributes): array {
            $registered = $GLOBALS['moonfarmer_reactions_lead_capture_module_handles_widget'] ?? [];
            $id         = isset($attributes['id']) ? (string) $attributes['id'] : '';
            if ($id !== '' && in_array($id, $registered, true)) {
                $attributes['type'] = 'module';
            }

            return $attributes;
        });
        add_filter('script_loader_tag', static function (string $tag, string $handle) {
            $registered = $GLOBALS['moonfarmer_reactions_lead_capture_module_handles_widget'] ?? [];
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

        if (strpos($content, 'data-moonfarmer-reactions-lead-capture-widget') !== false) {
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
            $autoInsert = (bool) apply_filters('moonfarmer_reactions_lead_capture_widget_auto_insert', $defaultOn, $postType);
            if (!$autoInsert) {
                return $content;
            }
        }

        $settings = $this->app->has(SettingsRepository::class)
            ? $this->app->get(SettingsRepository::class)->get()
            : Settings::DEFAULTS;
        $position = (string) ($settings['auto_insert_position'] ?? Settings::DEFAULTS['auto_insert_position']);
        $design   = (string) ($settings['widget_design'] ?? Settings::DEFAULTS['widget_design']);
        $widget   = \Moonfarmer\ReactionsLeadCapture\Blocks\WidgetMarkup::container($postId);

        if ($position === 'above' || ($position === 'below' && $design === 'vertical_rail')) {
            return $widget . $content;
        }
        if ($position === 'both') {
            return $widget . $content . $widget;
        }

        return $content . $widget;
    }
}
