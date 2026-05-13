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

    /** @param list<string> $handles */
    private function ensureModuleScripts(array $handles): void
    {
        $key = 'pulsepress_module_handles_widget';
        if (!empty($GLOBALS[$key])) {
            $GLOBALS[$key] = array_merge($GLOBALS[$key], $handles);
            return;
        }
        $GLOBALS[$key] = $handles;
        add_filter('script_loader_tag', static function (string $tag, string $handle) use ($key) {
            $registered = $GLOBALS[$key] ?? [];
            if (in_array($handle, $registered, true) && !str_contains($tag, ' type="module"')) {
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

        if (str_contains($content, 'data-pulsepress-widget')) {
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

        return $content . \PulsePress\Blocks\WidgetMarkup::container($postId);
    }
}
