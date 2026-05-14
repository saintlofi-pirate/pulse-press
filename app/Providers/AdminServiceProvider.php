<?php
declare(strict_types=1);

namespace PulsePress\Providers;

use PulsePress\Admin\WidgetStateMetaBox;
use PulsePress\Core\ServiceProvider;
use PulsePress\Reactions\Reactions;
use PulsePress\Settings\Settings;
use PulsePress\Settings\SettingsRepository;
use PulsePress\View\Manifest;
use PulsePress\Visibility\VisibilityResolver;

final class AdminServiceProvider extends ServiceProvider
{
    public const SCRIPT_HANDLE = 'pulsepress-admin';
    public const STYLE_HANDLE  = 'pulsepress-admin';
    public const ENTRY         = 'resources/admin/index.tsx';
    public const PAGE_HOOK     = 'settings_page_pulsepress';

    public function register(): void
    {
        $self = $this;
        $this->app->singleton(self::class, fn () => $self);
        $this->app->singleton(VisibilityResolver::class, function () {
            return new VisibilityResolver($this->app->get(SettingsRepository::class));
        });
        $this->app->singleton(WidgetStateMetaBox::class, fn () => new WidgetStateMetaBox());
    }

    public function boot(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueueAssets']);
        $this->app->get(WidgetStateMetaBox::class)->register();
    }

    public function renderPage(): void
    {
        echo '<div class="wrap"><div id="pulsepress-admin">' . esc_html__('Loading…', 'pulsepress') . '</div></div>';
    }

    public function maybeEnqueueAssets(string $hookSuffix): void
    {
        if ($hookSuffix !== self::PAGE_HOOK) {
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

        $entryUrl     = array_pop($urls['js']);
        $depUrls      = $urls['js'];
        $depHandles   = [];
        foreach ($depUrls as $i => $depUrl) {
            $handle = self::SCRIPT_HANDLE . '-dep-' . $i;
            wp_register_script($handle, $depUrl, [], PULSEPRESS_VERSION, true);
            wp_enqueue_script($handle);
            $depHandles[] = $handle;
        }
        wp_register_script(self::SCRIPT_HANDLE, $entryUrl, $depHandles, PULSEPRESS_VERSION, true);

        $moduleHandles = array_merge($depHandles, [self::SCRIPT_HANDLE]);
        $globalKey     = 'pulsepress_module_handles_admin';
        if (!empty($GLOBALS[$globalKey])) {
            $GLOBALS[$globalKey] = array_merge($GLOBALS[$globalKey], $moduleHandles);
        } else {
            $GLOBALS[$globalKey] = $moduleHandles;
            add_filter('script_loader_tag', static function (string $tag, string $handle) use ($globalKey) {
                $registered = $GLOBALS[$globalKey] ?? [];
                if (in_array($handle, $registered, true) && !str_contains($tag, ' type="module"')) {
                    $tag = preg_replace('/<script /', '<script type="module" ', $tag, 1);
                }
                return $tag;
            }, 10, 2);
        }

        $repository = $this->app->get(SettingsRepository::class);
        $settings   = $repository->get();

        $choices               = Settings::CHOICES;
        $choices['post_types'] = $this->publicPostTypeMap();

        $payload = [
            'restRoot'      => esc_url_raw(rest_url('pulsepress/v1/')),
            'nonce'         => wp_create_nonce('wp_rest'),
            'settings'      => $settings,
            'defaults'      => Settings::DEFAULTS,
            'choices'       => $choices,
            'schemaVersion' => Settings::SCHEMA_VERSION,
            'reactions'     => array_values((array) apply_filters('pulsepress_reaction_types', Reactions::TYPES)),
            'version'       => PULSEPRESS_VERSION,
            'i18n'          => $this->i18n(),
        ];

        $payload = (array) apply_filters('pulsepress_admin_data', $payload);

        wp_localize_script(self::SCRIPT_HANDLE, 'PulsePressAdminData', $payload);
        wp_enqueue_script(self::SCRIPT_HANDLE);
    }

    /** @return array<string, string> */
    private function publicPostTypeMap(): array
    {
        if (!function_exists('get_post_types')) {
            return ['post' => 'Posts', 'page' => 'Pages'];
        }
        $objects = get_post_types(['public' => true], 'objects');
        $map     = [];
        foreach ($objects as $slug => $object) {
            if (!is_string($slug) || $slug === '') {
                continue;
            }
            $label = is_object($object) && isset($object->labels->singular_name) && $object->labels->singular_name !== ''
                ? $object->labels->singular_name
                : (is_object($object) && isset($object->labels->name) ? $object->labels->name : $slug);
            $map[$slug] = (string) $label;
        }
        return $map;
    }

    /** @return array<string, mixed> */
    private function i18n(): array
    {
        return [
            'pageTitle'           => __('Reactions, captures, and analytics for your posts.', 'pulsepress'),
            'saved'               => __('Saved', 'pulsepress'),
            'saving'              => __('Saving…', 'pulsepress'),
            'saveError'           => __('Could not save. Please try again.', 'pulsepress'),
            'resetSection'        => __('Reset to defaults', 'pulsepress'),
            'livePreviewLabel'    => __('Live preview', 'pulsepress'),
            'livePreviewHelper'   => __('A live, read-only preview of how your widget will look on a published post.', 'pulsepress'),
            'livePreviewReadOnly' => __('Preview is read-only.', 'pulsepress'),
            'tabs' => [
                'display'   => __('Display', 'pulsepress'),
                'analytics' => __('Analytics', 'pulsepress'),
                'reactions' => __('Reactions', 'pulsepress'),
                'capture'   => __('Email capture', 'pulsepress'),
                'privacy'   => __('Privacy', 'pulsepress'),
            ],
            'sections' => [
                'displayTitle'    => __('Display', 'pulsepress'),
                'displayHelper'   => __('Choose how and where the reaction widget appears on your site.', 'pulsepress'),
                'reactionsTitle'  => __('Reactions', 'pulsepress'),
                'reactionsHelper' => __('Pick which reactions trigger the email capture prompt and who can react.', 'pulsepress'),
                'captureTitle'    => __('Email capture', 'pulsepress'),
                'captureHelper'   => __('Customise the consent statement and decide how long to keep captures.', 'pulsepress'),
                'privacyTitle'    => __('Privacy', 'pulsepress'),
                'privacyHelper'   => __('Set how PulsePress handles data when the plugin is removed.', 'pulsepress'),
                'analyticsTitle'  => __('Analytics', 'pulsepress'),
                'analyticsHelper' => __('Reactions and captures over the selected window. Defaults to the trailing 30 days.', 'pulsepress'),
            ],
            'captureExport' => [
                'label'           => __('Export captures', 'pulsepress'),
                'helper'          => __('Download every saved email + consent metadata as a CSV file. Anyone with WordPress admin access can run an export.', 'pulsepress'),
                'preparing'       => __('Preparing…', 'pulsepress'),
                'downloadStarted' => __('Download started.', 'pulsepress'),
                'error'           => __('Could not export captures. Please try again.', 'pulsepress'),
                'retry'           => __('Retry', 'pulsepress'),
            ],
            'analytics' => [
                'totalReactionsLabel'      => __('Total reactions', 'pulsepress'),
                'totalReactionsHelper'     => __('All reactions on every post in this window.', 'pulsepress'),
                'totalCapturesLabel'       => __('Email captures', 'pulsepress'),
                'totalCapturesHelper'      => __('Consented emails saved through the inline form.', 'pulsepress'),
                'sentimentRateLabel'       => __('Sentiment', 'pulsepress'),
                'sentimentRateHelper'     => __('Positive reactions divided by total reactions.', 'pulsepress'),
                'captureRateLabel'         => __('Capture rate', 'pulsepress'),
                'captureRateHelper'        => __('Captures divided by positive reactions (upper-bound denominator).', 'pulsepress'),
                'topPostsCaption'          => __('Top posts in the selected window', 'pulsepress'),
                'topPostsColumns'          => [
                    'post'     => __('Post', 'pulsepress'),
                    'total'    => __('Total reactions', 'pulsepress'),
                    'positive' => __('Positive reactions', 'pulsepress'),
                    'captures' => __('Captures', 'pulsepress'),
                ],
                'sentimentInsightTemplate' => __('Your readers are mostly reacting with {type} ({percent}% positive overall).', 'pulsepress'),
                'sentimentInsightFallback' => __('Sentiment will appear once you have a few reactions to summarise.', 'pulsepress'),
                'chartLabel'               => __('Daily reaction counts over the selected window.', 'pulsepress'),
                'emptyState'               => __('No reactions yet — visit a post and react to see numbers here.', 'pulsepress'),
                'loadingState'             => __('Loading analytics…', 'pulsepress'),
                'errorState'               => __('Could not load analytics. Please try again.', 'pulsepress'),
                'retry'                    => __('Retry', 'pulsepress'),
                'clampedNotice'            => __('Window trimmed to fit the analytics performance ceiling. Adjust via the pulsepress_analytics_max_days filter to allow longer ranges.', 'pulsepress'),
                'deletedPost'              => __('(deleted post)', 'pulsepress'),
            ],
            'fields' => [
                'countVisibilityLabel'      => __('Count visibility', 'pulsepress'),
                'countVisibilityHelper'     => __('Show the reaction counts to visitors or hide them.', 'pulsepress'),
                'countVisibilityChoices'    => [
                    'always'    => __('Always show counts', 'pulsepress'),
                    'never'     => __('Never show counts', 'pulsepress'),
                    'threshold' => __('Show only after a threshold', 'pulsepress'),
                ],
                'countThresholdLabel'       => __('Threshold', 'pulsepress'),
                'countThresholdHelper'      => __('Counts appear once a reaction reaches this number.', 'pulsepress'),
                'widgetDesignLabel'         => __('Widget design', 'pulsepress'),
                'widgetDesignHelper'        => __('Two designs ship in Free; Pro adds more.', 'pulsepress'),
                'widgetDesignChoices'       => [
                    'minimal'    => __('Minimal', 'pulsepress'),
                    'expressive' => __('Expressive', 'pulsepress'),
                ],
                'iconStyleLabel'            => __('Icon style', 'pulsepress'),
                'iconStyleHelper'           => __('Choose between hand-curated outline icons or emoji glyphs.', 'pulsepress'),
                'iconStyleChoices'          => [
                    'classic' => __('Classic', 'pulsepress'),
                    'emoji'   => __('Emoji', 'pulsepress'),
                ],
                'themeModeLabel'            => __('Theme mode', 'pulsepress'),
                'themeModeHelper'           => __('Follow the visitor system preference or force a mode.', 'pulsepress'),
                'themeModeChoices'          => [
                    'light' => __('Light', 'pulsepress'),
                    'dark'  => __('Dark', 'pulsepress'),
                    'auto'  => __('Match system', 'pulsepress'),
                ],
                'autoInsertPostTypesLabel'  => __('Auto-insert on', 'pulsepress'),
                'autoInsertPostTypesHelper' => __('Where the widget appears automatically.', 'pulsepress'),
                'hideOnPostTypesLabel'      => __('Never show on', 'pulsepress'),
                'hideOnPostTypesHelper'     => __('Suppress the widget on these post types, even when placed via block or shortcode. Per-post overrides set to "Always show" still win.', 'pulsepress'),
                'autoInsertPositionLabel'   => __('Position', 'pulsepress'),
                'autoInsertPositionHelper'  => __('Above the post body, below it, or both.', 'pulsepress'),
                'autoInsertPositionChoices' => [
                    'above' => __('Above content', 'pulsepress'),
                    'below' => __('Below content', 'pulsepress'),
                    'both'  => __('Above and below', 'pulsepress'),
                ],
                'positiveReactionsLabel'    => __('Positive reactions', 'pulsepress'),
                'positiveReactionsHelper'   => __('These reactions trigger the email capture prompt.', 'pulsepress'),
                'reactionLabels'            => [
                    'love'       => __('Love', 'pulsepress'),
                    'insightful' => __('Insightful', 'pulsepress'),
                    'funny'      => __('Funny', 'pulsepress'),
                    'sad'        => __('Sad', 'pulsepress'),
                    'surprised'  => __('Surprised', 'pulsepress'),
                    'angry'      => __('Angry', 'pulsepress'),
                ],
                'allowGuestReactionsLabel'  => __('Allow guest reactions', 'pulsepress'),
                'allowGuestReactionsHelper' => __('When off, visitors must sign in before reacting.', 'pulsepress'),
                'consentTextLabel'          => __('Consent statement', 'pulsepress'),
                'consentTextHelper'         => __('Shown next to the email capture consent checkbox.', 'pulsepress'),
                'consentVersionLabel'       => __('Consent version', 'pulsepress'),
                'consentVersionHelper'      => __('Bump this whenever you change the consent text.', 'pulsepress'),
                'retentionDaysLabel'        => __('Reaction retention (days)', 'pulsepress'),
                'retentionDaysHelper'       => __('Set to 0 to keep reactions indefinitely.', 'pulsepress'),
                'deleteOnUninstallLabel'    => __('Delete data on uninstall', 'pulsepress'),
                'deleteOnUninstallHelper'   => __('When on, PulsePress drops its tables and options when the plugin is deleted. Default is to keep your data.', 'pulsepress'),
            ],
        ];
    }
}
