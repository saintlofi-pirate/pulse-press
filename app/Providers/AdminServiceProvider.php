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

defined('ABSPATH') || exit;
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
        echo '<div class="wrap"><div id="pulsepress-admin">' . esc_html__('Loading…', 'pulse-press') . '</div></div>';
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
        if (!empty($GLOBALS['pulsepress_module_handles_admin'])) {
            $GLOBALS['pulsepress_module_handles_admin'] = array_merge($GLOBALS['pulsepress_module_handles_admin'], $moduleHandles);
        } else {
            $GLOBALS['pulsepress_module_handles_admin'] = $moduleHandles;
            add_filter('script_loader_tag', static function (string $tag, string $handle) {
                $registered = $GLOBALS['pulsepress_module_handles_admin'] ?? [];
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

        $i18n = $this->i18n();

        $payload = [
            'restRoot'        => esc_url_raw(rest_url('pulsepress/v1/')),
            'nonce'           => wp_create_nonce('wp_rest'),
            'settings'        => $settings,
            'defaults'        => Settings::DEFAULTS,
            'choices'         => $choices,
            'schemaVersion'   => Settings::SCHEMA_VERSION,
            'reactions'       => array_values((array) apply_filters('pulsepress_reaction_types', Reactions::TYPES)),
            'version'         => PULSEPRESS_VERSION,
            'i18n'            => $i18n,
            'tabs'            => $this->publicTabs($i18n),
            'metricCards'     => array_values((array) apply_filters('pulsepress_admin_metric_cards', [])),
            'analyticsPanels' => array_values((array) apply_filters('pulsepress_admin_analytics_panels', [])),
        ];

        $payload = (array) apply_filters('pulsepress_admin_data', $payload);

        wp_localize_script(self::SCRIPT_HANDLE, 'PulsePressAdminData', $payload);
        wp_enqueue_script(self::SCRIPT_HANDLE);
    }

    /**
     * @param array<string, mixed> $i18n
     * @return list<array{id: string, label: string, order: int}>
     */
    private function publicTabs(array $i18n): array
    {
        $labels = is_array($i18n['tabs'] ?? null) ? $i18n['tabs'] : [];
        $base   = [
            ['id' => 'display',   'label' => (string) ($labels['display']   ?? 'Display'),       'order' => 10],
            ['id' => 'analytics', 'label' => (string) ($labels['analytics'] ?? 'Analytics'),     'order' => 20],
            ['id' => 'reactions', 'label' => (string) ($labels['reactions'] ?? 'Reactions'),     'order' => 30],
            ['id' => 'capture',   'label' => (string) ($labels['capture']   ?? 'Email capture'), 'order' => 40],
            ['id' => 'privacy',   'label' => (string) ($labels['privacy']   ?? 'Privacy'),       'order' => 50],
        ];

        $extra = (array) apply_filters('pulsepress_admin_tabs', []);
        $seen  = [];
        $all   = [];
        foreach ($base as $tab) {
            $seen[$tab['id']] = true;
            $all[]            = $tab;
        }
        foreach ($extra as $tab) {
            if (!is_array($tab)) {
                continue;
            }
            $id = isset($tab['id']) ? (string) $tab['id'] : '';
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $all[]     = [
                'id'    => $id,
                'label' => isset($tab['label']) ? (string) $tab['label'] : $id,
                'order' => isset($tab['order']) ? (int) $tab['order'] : 1000,
            ];
        }

        usort($all, static function (array $a, array $b): int {
            $cmp = $a['order'] <=> $b['order'];
            return $cmp !== 0 ? $cmp : strcmp($a['id'], $b['id']);
        });

        return $all;
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
            'pageTitle'           => __('Reactions, captures, and analytics for your posts.', 'pulse-press'),
            'saved'               => __('Saved', 'pulse-press'),
            'saving'              => __('Saving…', 'pulse-press'),
            'saveError'           => __('Could not save. Please try again.', 'pulse-press'),
            'resetSection'        => __('Reset to defaults', 'pulse-press'),
            'livePreviewLabel'    => __('Live preview', 'pulse-press'),
            'livePreviewHelper'   => __('A live, read-only preview of how your widget will look on a published post.', 'pulse-press'),
            'livePreviewReadOnly' => __('Preview is read-only.', 'pulse-press'),
            'tabs' => [
                'display'   => __('Display', 'pulse-press'),
                'analytics' => __('Analytics', 'pulse-press'),
                'reactions' => __('Reactions', 'pulse-press'),
                'capture'   => __('Email capture', 'pulse-press'),
                'privacy'   => __('Privacy', 'pulse-press'),
            ],
            'sections' => [
                'displayTitle'    => __('Display', 'pulse-press'),
                'displayHelper'   => __('Choose how and where the reaction widget appears on your site.', 'pulse-press'),
                'reactionsTitle'  => __('Reactions', 'pulse-press'),
                'reactionsHelper' => __('Pick which reactions trigger the email capture prompt and who can react.', 'pulse-press'),
                'captureTitle'    => __('Email capture', 'pulse-press'),
                'captureHelper'   => __('Customise the consent statement and decide how long to keep captures.', 'pulse-press'),
                'privacyTitle'    => __('Privacy', 'pulse-press'),
                'privacyHelper'   => __('Set how PulsePress handles data when the plugin is removed.', 'pulse-press'),
                'analyticsTitle'  => __('Analytics', 'pulse-press'),
                'analyticsHelper' => __('Reactions and captures over the selected window. Defaults to the trailing 30 days.', 'pulse-press'),
            ],
            'extension' => [
                'fallback'    => __('This section is provided by another plugin that didn’t load. Try refreshing the page.', 'pulse-press'),
                'sectionLabel' => __('Extension region', 'pulse-press'),
            ],
            'toggle' => [
                'on'  => __('On', 'pulse-press'),
                'off' => __('Off', 'pulse-press'),
            ],
            'captureExport' => [
                'label'           => __('Export captures', 'pulse-press'),
                'helper'          => __('Download every saved email + consent metadata as a CSV file. Anyone with WordPress admin access can run an export.', 'pulse-press'),
                'preparing'       => __('Preparing…', 'pulse-press'),
                'downloadStarted' => __('Download started.', 'pulse-press'),
                'error'           => __('Could not export captures. Please try again.', 'pulse-press'),
                'retry'           => __('Retry', 'pulse-press'),
            ],
            'analytics' => [
                'totalReactionsLabel'      => __('Total reactions', 'pulse-press'),
                'totalReactionsHelper'     => __('All reactions on every post in this window.', 'pulse-press'),
                'totalCapturesLabel'       => __('Email captures', 'pulse-press'),
                'totalCapturesHelper'      => __('Consented emails saved through the inline form.', 'pulse-press'),
                'sentimentRateLabel'       => __('Sentiment', 'pulse-press'),
                'sentimentRateHelper'     => __('Positive reactions divided by total reactions.', 'pulse-press'),
                'captureRateLabel'         => __('Capture rate', 'pulse-press'),
                'captureRateHelper'        => __('Captures divided by positive reactions (upper-bound denominator).', 'pulse-press'),
                'topPostsCaption'          => __('Top posts in the selected window', 'pulse-press'),
                'topPostsColumns'          => [
                    'post'     => __('Post', 'pulse-press'),
                    'total'    => __('Total reactions', 'pulse-press'),
                    'positive' => __('Positive reactions', 'pulse-press'),
                    'captures' => __('Captures', 'pulse-press'),
                ],
                'sentimentInsightTemplate' => __('Your readers are mostly reacting with {type} ({percent}% positive overall).', 'pulse-press'),
                'sentimentInsightFallback' => __('Sentiment will appear once you have a few reactions to summarise.', 'pulse-press'),
                'chartLabel'               => __('Daily reaction counts over the selected window.', 'pulse-press'),
                'emptyState'               => __('No reactions yet — visit a post and react to see numbers here.', 'pulse-press'),
                'loadingState'             => __('Loading analytics…', 'pulse-press'),
                'errorState'               => __('Could not load analytics. Please try again.', 'pulse-press'),
                'retry'                    => __('Retry', 'pulse-press'),
                'clampedNotice'            => __('Window trimmed to fit the analytics performance ceiling. Adjust via the pulsepress_analytics_max_days filter to allow longer ranges.', 'pulse-press'),
                'deletedPost'              => __('(deleted post)', 'pulse-press'),
            ],
            'fields' => [
                'countVisibilityLabel'      => __('Count visibility', 'pulse-press'),
                'countVisibilityHelper'     => __('Show the reaction counts to visitors or hide them.', 'pulse-press'),
                'countVisibilityChoices'    => [
                    'always'    => __('Always show counts', 'pulse-press'),
                    'never'     => __('Never show counts', 'pulse-press'),
                    'threshold' => __('Show only after a threshold', 'pulse-press'),
                ],
                'countThresholdLabel'       => __('Threshold', 'pulse-press'),
                'countThresholdHelper'      => __('Counts appear once a reaction reaches this number.', 'pulse-press'),
                'widgetDesignLabel'         => __('Widget design', 'pulse-press'),
                'widgetDesignHelper'        => __('Two designs ship in Free; Pro adds more.', 'pulse-press'),
                'widgetDesignChoices'       => [
                    'minimal'    => __('Minimal', 'pulse-press'),
                    'expressive' => __('Expressive', 'pulse-press'),
                ],
                'iconStyleLabel'            => __('Icon style', 'pulse-press'),
                'iconStyleHelper'           => __('Choose between hand-curated outline icons or emoji glyphs.', 'pulse-press'),
                'iconStyleChoices'          => [
                    'classic' => __('Classic', 'pulse-press'),
                    'emoji'   => __('Emoji', 'pulse-press'),
                ],
                'themeModeLabel'            => __('Theme mode', 'pulse-press'),
                'themeModeHelper'           => __('Follow the visitor system preference or force a mode.', 'pulse-press'),
                'themeModeChoices'          => [
                    'light' => __('Light', 'pulse-press'),
                    'dark'  => __('Dark', 'pulse-press'),
                    'auto'  => __('Match system', 'pulse-press'),
                ],
                'autoInsertPostTypesLabel'  => __('Auto-insert on', 'pulse-press'),
                'autoInsertPostTypesHelper' => __('Where the widget appears automatically.', 'pulse-press'),
                'hideOnPostTypesLabel'      => __('Never show on', 'pulse-press'),
                'hideOnPostTypesHelper'     => __('Suppress the widget on these post types, even when placed via block or shortcode. Per-post overrides set to "Always show" still win.', 'pulse-press'),
                'autoInsertPositionLabel'   => __('Position', 'pulse-press'),
                'autoInsertPositionHelper'  => __('Above the post body, below it, or both.', 'pulse-press'),
                'autoInsertPositionChoices' => [
                    'above' => __('Above content', 'pulse-press'),
                    'below' => __('Below content', 'pulse-press'),
                    'both'  => __('Above and below', 'pulse-press'),
                ],
                'positiveReactionsLabel'    => __('Positive reactions', 'pulse-press'),
                'positiveReactionsHelper'   => __('These reactions trigger the email capture prompt.', 'pulse-press'),
                'reactionLabels'            => [
                    'love'       => __('Love', 'pulse-press'),
                    'insightful' => __('Insightful', 'pulse-press'),
                    'funny'      => __('Funny', 'pulse-press'),
                    'sad'        => __('Sad', 'pulse-press'),
                    'surprised'  => __('Surprised', 'pulse-press'),
                    'angry'      => __('Angry', 'pulse-press'),
                ],
                'allowGuestReactionsLabel'  => __('Allow guest reactions', 'pulse-press'),
                'allowGuestReactionsHelper' => __('When off, visitors must sign in before reacting.', 'pulse-press'),
                'consentTextLabel'          => __('Consent statement', 'pulse-press'),
                'consentTextHelper'         => __('Shown next to the email capture consent checkbox.', 'pulse-press'),
                'consentVersionLabel'       => __('Consent version', 'pulse-press'),
                'consentVersionHelper'      => __('Bump this whenever you change the consent text.', 'pulse-press'),
                'retentionDaysLabel'        => __('Reaction retention (days)', 'pulse-press'),
                'retentionDaysHelper'       => __('Set to 0 to keep reactions indefinitely.', 'pulse-press'),
                'deleteOnUninstallLabel'    => __('Delete data on uninstall', 'pulse-press'),
                'deleteOnUninstallHelper'   => __('When on, PulsePress drops its tables and options when the plugin is deleted. Default is to keep your data.', 'pulse-press'),
            ],
        ];
    }
}
