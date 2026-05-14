<?php
declare(strict_types=1);

namespace PulsePress\Admin;

use PulsePress\Visibility\VisibilityResolver;
use WP_Post;

final class WidgetStateMetaBox
{
    public const META_BOX_ID    = 'pulsepress-widget-state';
    public const NONCE_ACTION   = 'pulsepress_widget_state';
    public const NONCE_FIELD    = 'pulsepress_widget_state_nonce';

    public function register(): void
    {
        add_action('init', [$this, 'registerMeta']);
        add_action('add_meta_boxes', [$this, 'addBoxes']);
    }

    public function registerMeta(): void
    {
        foreach ($this->applicablePostTypes() as $postType) {
            register_post_meta($postType, VisibilityResolver::META_KEY, [
                'type'              => 'string',
                'single'            => true,
                'default'           => VisibilityResolver::MODE_AUTO,
                'show_in_rest'      => true,
                'sanitize_callback' => [VisibilityResolver::class, 'sanitiseMode'],
                'auth_callback'     => static function ($allowed, $meta_key, $object_id) {
                    return current_user_can('edit_post', (int) $object_id);
                },
            ]);
        }
    }

    public function addBoxes(): void
    {
        foreach ($this->applicablePostTypes() as $postType) {
            add_meta_box(
                self::META_BOX_ID,
                __('PulsePress reactions', 'pulsepress'),
                [$this, 'render'],
                $postType,
                'side',
                'default'
            );
            add_action('save_post_' . $postType, [$this, 'save'], 10, 2);
        }
    }

    public function render(WP_Post $post): void
    {
        $current = (string) get_post_meta($post->ID, VisibilityResolver::META_KEY, true);
        $current = VisibilityResolver::sanitiseMode($current);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $options = [
            VisibilityResolver::MODE_AUTO => [
                'label' => __('Auto (follow global settings)', 'pulsepress'),
                'help'  => __('Use the site-wide auto-insert configuration.', 'pulsepress'),
            ],
            VisibilityResolver::MODE_ON => [
                'label' => __('Always show', 'pulsepress'),
                'help'  => __('Force the widget to render on this post, even if its post type is excluded globally.', 'pulsepress'),
            ],
            VisibilityResolver::MODE_OFF => [
                'label' => __('Always hide', 'pulsepress'),
                'help'  => __('Suppress the widget on this post, including via block or shortcode.', 'pulsepress'),
            ],
        ];

        echo '<fieldset class="pulsepress-meta-box">';
        echo '<legend class="screen-reader-text">' . esc_html__('PulsePress widget visibility', 'pulsepress') . '</legend>';
        foreach ($options as $value => $entry) {
            $id = 'pulsepress-state-' . $value;
            printf(
                '<p style="margin:0 0 .5rem"><label for="%1$s" style="display:flex;gap:.4rem;align-items:flex-start"><input type="radio" id="%1$s" name="pulsepress_widget_state" value="%2$s"%3$s /> <span><strong>%4$s</strong><br><span style="color:#646970;font-size:.85em">%5$s</span></span></label></p>',
                esc_attr($id),
                esc_attr($value),
                checked($current, $value, false),
                esc_html($entry['label']),
                esc_html($entry['help'])
            );
        }
        echo '</fieldset>';
    }

    public function save(int $postId, WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST[self::NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::NONCE_FIELD], self::NONCE_ACTION)) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }
        if (!isset($_POST['pulsepress_widget_state'])) {
            return;
        }

        $value = VisibilityResolver::sanitiseMode((string) wp_unslash($_POST['pulsepress_widget_state']));
        update_post_meta($postId, VisibilityResolver::META_KEY, $value);
    }

    /** @return string[] */
    public function applicablePostTypes(): array
    {
        $types = function_exists('get_post_types') ? get_post_types(['public' => true]) : ['post'];
        $types = (array) apply_filters('pulsepress_meta_box_post_types', $types);
        return array_values(array_filter($types, 'is_string'));
    }
}
