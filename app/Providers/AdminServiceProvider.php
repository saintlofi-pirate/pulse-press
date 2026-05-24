<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Providers;

use Moonfarmer\ReactionsLeadCapture\Admin\WidgetStateMetaBox;
use Moonfarmer\ReactionsLeadCapture\Core\ServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Reactions\Reactions;
use Moonfarmer\ReactionsLeadCapture\Settings\Settings;
use Moonfarmer\ReactionsLeadCapture\Settings\SettingsRepository;
use Moonfarmer\ReactionsLeadCapture\View\Manifest;
use Moonfarmer\ReactionsLeadCapture\Visibility\VisibilityResolver;


if (!defined('ABSPATH')) {
    exit;
}

final class AdminServiceProvider extends ServiceProvider
{
    public const SCRIPT_HANDLE = 'moonfarmer-reactions-lead-capture-admin';
    public const STYLE_HANDLE  = 'moonfarmer-reactions-lead-capture-admin';
    public const ENTRY         = 'resources/admin/index.tsx';
    public const PAGE_HOOK     = 'settings_page_moonfarmer-reactions-lead-capture';
    public const LEGACY_PAGE_HOOK = 'settings_page_moonfarmer-reactions-lead-capture';

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
        echo '<div class="wrap"><div id="moonfarmer-reactions-lead-capture-admin">' . esc_html__('Loading…', 'moonfarmer-reactions-lead-capture') . '</div></div>';
    }

    public function maybeEnqueueAssets(string $hookSuffix): void
    {
        if (!in_array($hookSuffix, [self::PAGE_HOOK, self::LEGACY_PAGE_HOOK], true)) {
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

        $entryUrl     = array_pop($urls['js']);
        $depUrls      = $urls['js'];
        $depHandles   = [];
        foreach ($depUrls as $i => $depUrl) {
            $handle = self::SCRIPT_HANDLE . '-dep-' . $i;
            wp_register_script($handle, $depUrl, [], MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION, true);
            wp_enqueue_script($handle);
            $depHandles[] = $handle;
        }
        wp_register_script(self::SCRIPT_HANDLE, $entryUrl, $depHandles, MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION, true);

        $moduleHandles = array_merge($depHandles, [self::SCRIPT_HANDLE]);
        if (!empty($GLOBALS['moonfarmer_reactions_lead_capture_module_handles_admin'])) {
            $GLOBALS['moonfarmer_reactions_lead_capture_module_handles_admin'] = array_merge($GLOBALS['moonfarmer_reactions_lead_capture_module_handles_admin'], $moduleHandles);
        } else {
            $GLOBALS['moonfarmer_reactions_lead_capture_module_handles_admin'] = $moduleHandles;
            add_filter('script_loader_tag', static function (string $tag, string $handle) {
                $registered = $GLOBALS['moonfarmer_reactions_lead_capture_module_handles_admin'] ?? [];
                if (in_array($handle, $registered, true) && strpos($tag, ' type="module"') === false) {
                    $tag = preg_replace('/<script /', '<script type="module" ', $tag, 1);
                }
                return $tag;
            }, 10, 2);
        }

        $repository = $this->app->get(SettingsRepository::class);
        $settings   = $repository->get();

        $choices               = Settings::CHOICES;
        $choices['post_types'] = $this->publicPostTypeMap();
        $choices['posts']      = $this->publicContentMap();

        $i18n = $this->i18n();

        $payload = [
            'restRoot'        => esc_url_raw(rest_url('moonfarmer-reactions-lead-capture/v1/')),
            'nonce'           => wp_create_nonce('wp_rest'),
            'settings'        => $settings,
            'defaults'        => Settings::DEFAULTS,
            'choices'         => $choices,
            'schemaVersion'   => Settings::SCHEMA_VERSION,
            'reactions'       => array_values((array) apply_filters('moonfarmer_reactions_lead_capture_reaction_types', Reactions::TYPES)),
            'version'         => MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION,
            'i18n'            => $i18n,
            'tabs'            => $this->publicTabs($i18n),
            'metricCards'     => array_values((array) apply_filters('moonfarmer_reactions_lead_capture_admin_metric_cards', [])),
            'analyticsPanels' => array_values((array) apply_filters('moonfarmer_reactions_lead_capture_admin_analytics_panels', [])),
        ];

        $payload = (array) apply_filters('moonfarmer_reactions_lead_capture_admin_data', $payload);

        wp_localize_script(self::SCRIPT_HANDLE, 'MoonfarmerReactionsLeadCaptureAdminData', $payload);
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

        $extra = (array) apply_filters('moonfarmer_reactions_lead_capture_admin_tabs', []);
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

    /** @return array<string, string> */
    private function publicContentMap(): array
    {
        if (!function_exists('get_posts')) {
            return [];
        }

        $items = get_posts([
            'post_type'      => array_keys($this->publicPostTypeMap()),
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);

        $map = [];
        foreach ($items as $postId) {
            $id = (int) $postId;
            if ($id <= 0) {
                continue;
            }
            $title = get_the_title($id);
            $type  = get_post_type($id);
            $map[(string) $id] = sprintf(
                '%s #%d%s',
                $title !== '' ? $title : __('(no title)', 'moonfarmer-reactions-lead-capture'),
                $id,
                is_string($type) && $type !== '' ? ' · ' . $type : ''
            );
        }

        return $map;
    }

    /** @return array<string, mixed> */
    private function i18n(): array
    {
        return [
            'pageTitle'           => __('Reactions, captures, and analytics for your posts.', 'moonfarmer-reactions-lead-capture'),
            'saved'               => __('Saved', 'moonfarmer-reactions-lead-capture'),
            'saving'              => __('Saving…', 'moonfarmer-reactions-lead-capture'),
            'saveError'           => __('Could not save. Please try again.', 'moonfarmer-reactions-lead-capture'),
            'resetSection'        => __('Reset to defaults', 'moonfarmer-reactions-lead-capture'),
            'livePreviewLabel'    => __('Live preview', 'moonfarmer-reactions-lead-capture'),
            'livePreviewHelper'   => __('A live, read-only preview of how your widget will look on a published post.', 'moonfarmer-reactions-lead-capture'),
            'livePreviewReadOnly' => __('Preview is read-only.', 'moonfarmer-reactions-lead-capture'),
            'tabs' => [
                'display'   => __('Display', 'moonfarmer-reactions-lead-capture'),
                'analytics' => __('Analytics', 'moonfarmer-reactions-lead-capture'),
                'reactions' => __('Reactions', 'moonfarmer-reactions-lead-capture'),
                'capture'   => __('Email capture', 'moonfarmer-reactions-lead-capture'),
                'privacy'   => __('Privacy', 'moonfarmer-reactions-lead-capture'),
            ],
            'sections' => [
                'displayTitle'    => __('Display', 'moonfarmer-reactions-lead-capture'),
                'displayHelper'   => __('Choose how and where the reaction widget appears on your site.', 'moonfarmer-reactions-lead-capture'),
                'reactionsTitle'  => __('Reactions', 'moonfarmer-reactions-lead-capture'),
                'reactionsHelper' => __('Pick which reactions appear in the widget, which ones trigger email capture, and who can react.', 'moonfarmer-reactions-lead-capture'),
                'captureTitle'    => __('Email capture', 'moonfarmer-reactions-lead-capture'),
                'captureHelper'   => __('Customise the consent statement and decide how long to keep captures.', 'moonfarmer-reactions-lead-capture'),
                'privacyTitle'    => __('Privacy', 'moonfarmer-reactions-lead-capture'),
                'privacyHelper'   => __('Set how Moonfarmer Reactions Lead Capture handles data when the plugin is removed.', 'moonfarmer-reactions-lead-capture'),
                'analyticsTitle'  => __('Analytics', 'moonfarmer-reactions-lead-capture'),
                'analyticsHelper' => __('Reactions and captures over the selected window. Defaults to the trailing 30 days.', 'moonfarmer-reactions-lead-capture'),
            ],
            'extension' => [
                'fallback'    => __('This section is provided by another plugin that didn’t load. Try refreshing the page.', 'moonfarmer-reactions-lead-capture'),
                'sectionLabel' => __('Extension region', 'moonfarmer-reactions-lead-capture'),
            ],
            'toggle' => [
                'on'  => __('On', 'moonfarmer-reactions-lead-capture'),
                'off' => __('Off', 'moonfarmer-reactions-lead-capture'),
            ],
            'captureExport' => [
                'label'           => __('Export captures', 'moonfarmer-reactions-lead-capture'),
                'helper'          => __('Download every saved email + consent metadata as a CSV file. Anyone with WordPress admin access can run an export.', 'moonfarmer-reactions-lead-capture'),
                'preparing'       => __('Preparing…', 'moonfarmer-reactions-lead-capture'),
                'downloadStarted' => __('Download started.', 'moonfarmer-reactions-lead-capture'),
                'error'           => __('Could not export captures. Please try again.', 'moonfarmer-reactions-lead-capture'),
                'retry'           => __('Retry', 'moonfarmer-reactions-lead-capture'),
            ],
            'analytics' => [
                'totalReactionsLabel'      => __('Total reactions', 'moonfarmer-reactions-lead-capture'),
                'totalReactionsHelper'     => __('All reactions on every post in this window.', 'moonfarmer-reactions-lead-capture'),
                'totalCapturesLabel'       => __('Email captures', 'moonfarmer-reactions-lead-capture'),
                'totalCapturesHelper'      => __('Consented emails saved through the inline form.', 'moonfarmer-reactions-lead-capture'),
                'sentimentRateLabel'       => __('Sentiment', 'moonfarmer-reactions-lead-capture'),
                'sentimentRateHelper'     => __('Positive reactions divided by total reactions.', 'moonfarmer-reactions-lead-capture'),
                'captureRateLabel'         => __('Capture rate', 'moonfarmer-reactions-lead-capture'),
                'captureRateHelper'        => __('Captures divided by positive reactions (upper-bound denominator).', 'moonfarmer-reactions-lead-capture'),
                'topPostsCaption'          => __('Top posts in the selected window', 'moonfarmer-reactions-lead-capture'),
                'topPostsColumns'          => [
                    'post'     => __('Post', 'moonfarmer-reactions-lead-capture'),
                    'total'    => __('Total reactions', 'moonfarmer-reactions-lead-capture'),
                    'positive' => __('Positive reactions', 'moonfarmer-reactions-lead-capture'),
                    'captures' => __('Captures', 'moonfarmer-reactions-lead-capture'),
                ],
                'sentimentInsightTemplate' => __('Your readers are mostly reacting with {type} ({percent}% positive overall).', 'moonfarmer-reactions-lead-capture'),
                'sentimentInsightFallback' => __('Sentiment will appear once you have a few reactions to summarise.', 'moonfarmer-reactions-lead-capture'),
                'chartLabel'               => __('Daily reaction counts over the selected window.', 'moonfarmer-reactions-lead-capture'),
                'emptyState'               => __('No reactions yet — visit a post and react to see numbers here.', 'moonfarmer-reactions-lead-capture'),
                'loadingState'             => __('Loading analytics…', 'moonfarmer-reactions-lead-capture'),
                'errorState'               => __('Could not load analytics. Please try again.', 'moonfarmer-reactions-lead-capture'),
                'retry'                    => __('Retry', 'moonfarmer-reactions-lead-capture'),
                'clampedNotice'            => __('Window trimmed to fit the analytics performance ceiling. Adjust via the moonfarmer_reactions_lead_capture_analytics_max_days filter to allow longer ranges.', 'moonfarmer-reactions-lead-capture'),
                'deletedPost'              => __('(deleted post)', 'moonfarmer-reactions-lead-capture'),
            ],
            'fields' => [
                'countVisibilityLabel'      => __('Count visibility', 'moonfarmer-reactions-lead-capture'),
                'countVisibilityHelper'     => __('Show the reaction counts to visitors or hide them.', 'moonfarmer-reactions-lead-capture'),
                'countVisibilityChoices'    => [
                    'always'    => __('Always show counts', 'moonfarmer-reactions-lead-capture'),
                    'never'     => __('Never show counts', 'moonfarmer-reactions-lead-capture'),
                    'threshold' => __('Show only after a threshold', 'moonfarmer-reactions-lead-capture'),
                ],
                'countThresholdLabel'       => __('Threshold', 'moonfarmer-reactions-lead-capture'),
                'countThresholdHelper'      => __('Counts appear once a reaction reaches this number.', 'moonfarmer-reactions-lead-capture'),
                'widgetDesignLabel'         => __('Widget design', 'moonfarmer-reactions-lead-capture'),
                'widgetDesignHelper'        => __('Includes Stitch-inspired layouts: clean bars, progress split, vertical rail, and clap counter patterns.', 'moonfarmer-reactions-lead-capture'),
                'widgetDesignChoices'       => [
                    'minimal'        => __('Minimal', 'moonfarmer-reactions-lead-capture'),
                    'expressive'     => __('Expressive', 'moonfarmer-reactions-lead-capture'),
                    'minimalist'     => __('Minimalist', 'moonfarmer-reactions-lead-capture'),
                    'subtle_text'    => __('Subtle text', 'moonfarmer-reactions-lead-capture'),
                    'progress_split' => __('Progress split', 'moonfarmer-reactions-lead-capture'),
                    'vertical_rail'  => __('Vertical rail', 'moonfarmer-reactions-lead-capture'),
                    'clap_counter'   => __('Clap counter', 'moonfarmer-reactions-lead-capture'),
                ],
                'iconStyleLabel'            => __('Icon style', 'moonfarmer-reactions-lead-capture'),
                'iconStyleHelper'           => __('Choose between hand-curated outline icons or emoji glyphs.', 'moonfarmer-reactions-lead-capture'),
                'iconStyleChoices'          => [
                    'classic' => __('Classic', 'moonfarmer-reactions-lead-capture'),
                    'emoji'   => __('Emoji', 'moonfarmer-reactions-lead-capture'),
                ],
                'themeModeLabel'            => __('Theme mode', 'moonfarmer-reactions-lead-capture'),
                'themeModeHelper'           => __('Follow the visitor system preference or force a mode.', 'moonfarmer-reactions-lead-capture'),
                'themeModeChoices'          => [
                    'light' => __('Light', 'moonfarmer-reactions-lead-capture'),
                    'dark'  => __('Dark', 'moonfarmer-reactions-lead-capture'),
                    'auto'  => __('Match system', 'moonfarmer-reactions-lead-capture'),
                ],
                'primaryColorLabel'         => __('Primary color', 'moonfarmer-reactions-lead-capture'),
                'primaryColorHelper'        => __('Used for selected states, progress fills, focus accents, and motion effects.', 'moonfarmer-reactions-lead-capture'),
                'animationModeLabel'        => __('Animation', 'moonfarmer-reactions-lead-capture'),
                'animationModeHelper'       => __('Control the amount of motion used when visitors press a reaction.', 'moonfarmer-reactions-lead-capture'),
                'animationModeChoices'      => [
                    'none'       => __('None', 'moonfarmer-reactions-lead-capture'),
                    'subtle'     => __('Subtle', 'moonfarmer-reactions-lead-capture'),
                    'spring'     => __('Spring', 'moonfarmer-reactions-lead-capture'),
                    'burst'      => __('Burst', 'moonfarmer-reactions-lead-capture'),
                    'float'      => __('Float', 'moonfarmer-reactions-lead-capture'),
                    'glow'       => __('Glow', 'moonfarmer-reactions-lead-capture'),
                    'count_bump' => __('Count bump', 'moonfarmer-reactions-lead-capture'),
                    'trail'      => __('Trail', 'moonfarmer-reactions-lead-capture'),
                ],
                'autoInsertPostTypesLabel'  => __('Auto-insert on', 'moonfarmer-reactions-lead-capture'),
                'autoInsertPostTypesHelper' => __('Where the widget appears automatically.', 'moonfarmer-reactions-lead-capture'),
                'hideOnPostTypesLabel'      => __('Never show on', 'moonfarmer-reactions-lead-capture'),
                'hideOnPostTypesHelper'     => __('Suppress the widget on these post types, even when placed via block or shortcode. Per-post overrides set to "Always show" still win.', 'moonfarmer-reactions-lead-capture'),
                'hideOnPostIdsLabel'        => __('Never show on posts/pages', 'moonfarmer-reactions-lead-capture'),
                'hideOnPostIdsHelper'       => __('Enter comma-separated post IDs, or choose a published post/page below. These IDs always suppress the widget.', 'moonfarmer-reactions-lead-capture'),
                'hideOnPostIdsPlaceholder'  => __('Example: 12, 48, 103', 'moonfarmer-reactions-lead-capture'),
                'hideOnPostIdsSelectLabel'  => __('Add by title', 'moonfarmer-reactions-lead-capture'),
                'hideOnPostIdsSelectOption' => __('Select a post or page…', 'moonfarmer-reactions-lead-capture'),
                'autoInsertPositionLabel'   => __('Position', 'moonfarmer-reactions-lead-capture'),
                'autoInsertPositionHelper'  => __('Above the post body, below it, or both.', 'moonfarmer-reactions-lead-capture'),
                'autoInsertPositionChoices' => [
                    'above' => __('Above content', 'moonfarmer-reactions-lead-capture'),
                    'below' => __('Below content', 'moonfarmer-reactions-lead-capture'),
                    'both'  => __('Above and below', 'moonfarmer-reactions-lead-capture'),
                ],
                'enabledReactionsLabel'     => __('Visible reactions', 'moonfarmer-reactions-lead-capture'),
                'enabledReactionsHelper'    => __('Only selected reactions appear on the frontend widget.', 'moonfarmer-reactions-lead-capture'),
                'positiveReactionsLabel'    => __('Positive reactions', 'moonfarmer-reactions-lead-capture'),
                'positiveReactionsHelper'   => __('These reactions trigger the email capture prompt.', 'moonfarmer-reactions-lead-capture'),
                'reactionLabels'            => [
                    'love'       => __('Love', 'moonfarmer-reactions-lead-capture'),
                    'insightful' => __('Insightful', 'moonfarmer-reactions-lead-capture'),
                    'funny'      => __('Funny', 'moonfarmer-reactions-lead-capture'),
                    'sad'        => __('Sad', 'moonfarmer-reactions-lead-capture'),
                    'surprised'  => __('Surprised', 'moonfarmer-reactions-lead-capture'),
                    'angry'      => __('Angry', 'moonfarmer-reactions-lead-capture'),
                ],
                'allowGuestReactionsLabel'  => __('Allow guest reactions', 'moonfarmer-reactions-lead-capture'),
                'allowGuestReactionsHelper' => __('When off, visitors must sign in before reacting.', 'moonfarmer-reactions-lead-capture'),
                'consentTextLabel'          => __('Consent statement', 'moonfarmer-reactions-lead-capture'),
                'consentTextHelper'         => __('Shown next to the email capture consent checkbox.', 'moonfarmer-reactions-lead-capture'),
                'consentVersionLabel'       => __('Consent version', 'moonfarmer-reactions-lead-capture'),
                'consentVersionHelper'      => __('Bump this whenever you change the consent text.', 'moonfarmer-reactions-lead-capture'),
                'retentionDaysLabel'        => __('Reaction retention (days)', 'moonfarmer-reactions-lead-capture'),
                'retentionDaysHelper'       => __('Set to 0 to keep reactions indefinitely.', 'moonfarmer-reactions-lead-capture'),
                'deleteOnUninstallLabel'    => __('Delete data on uninstall', 'moonfarmer-reactions-lead-capture'),
                'deleteOnUninstallHelper'   => __('When on, Moonfarmer Reactions Lead Capture drops its tables and options when the plugin is deleted. Default is to keep your data.', 'moonfarmer-reactions-lead-capture'),
            ],
        ];
    }
}
