<?php
declare(strict_types=1);

namespace PulsePress\Core;

defined('ABSPATH') || exit;
class Asset
{
    /** @var array<string, array{handle:string, path:string, deps:array, ver:string, in_footer:bool, context:string, condition:?callable, localize:array}> */
    protected array $scripts = [];

    /** @var array<string, array{handle:string, path:string, deps:array, ver:string, media:string, context:string, condition:?callable}> */
    protected array $styles = [];

    public function __construct(protected string $pluginSlug = 'pulsepress')
    {
    }

    public function url(string $path): string
    {
        return PULSEPRESS_URL . 'dist/' . ltrim($path, '/');
    }

    public function registerScript(string $handle, string $path, array $deps = [], ?string $ver = null, bool $inFooter = true, string $context = 'both', ?callable $condition = null): self
    {
        $this->scripts[$handle] = [
            'handle'    => $handle,
            'path'      => $path,
            'deps'      => $deps,
            'ver'       => $ver ?? PULSEPRESS_VERSION,
            'in_footer' => $inFooter,
            'context'   => $context,
            'condition' => $condition,
            'localize'  => [],
        ];
        return $this;
    }

    public function registerStyle(string $handle, string $path, array $deps = [], ?string $ver = null, string $media = 'all', string $context = 'both', ?callable $condition = null): self
    {
        $this->styles[$handle] = [
            'handle'    => $handle,
            'path'      => $path,
            'deps'      => $deps,
            'ver'       => $ver ?? PULSEPRESS_VERSION,
            'media'     => $media,
            'context'   => $context,
            'condition' => $condition,
        ];
        return $this;
    }

    public function localizeScript(string $handle, string $objectName, array $data): self
    {
        if (isset($this->scripts[$handle])) {
            $this->scripts[$handle]['localize'][] = compact('objectName', 'data');
        }
        return $this;
    }

    public function bindWordPress(): void
    {
        add_action('wp_enqueue_scripts', fn () => $this->enqueue('frontend'));
        add_action('admin_enqueue_scripts', function ($hook): void {
            $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ($page === '' || strpos($page, $this->pluginSlug) === false) {
                return;
            }
            $this->enqueue('admin', $hook);
        });
    }

    protected function enqueue(string $context, ?string $hook = null): void
    {
        foreach ($this->scripts as $script) {
            if ($script['context'] !== 'both' && $script['context'] !== $context) {
                continue;
            }
            if (is_callable($script['condition']) && !($script['condition'])($hook)) {
                continue;
            }
            wp_enqueue_script($script['handle'], $this->url($script['path']), $script['deps'], $script['ver'], $script['in_footer']);
            foreach ($script['localize'] as $entry) {
                wp_localize_script($script['handle'], $entry['objectName'], $entry['data']);
            }
        }
        foreach ($this->styles as $style) {
            if ($style['context'] !== 'both' && $style['context'] !== $context) {
                continue;
            }
            if (is_callable($style['condition']) && !($style['condition'])($hook)) {
                continue;
            }
            wp_enqueue_style($style['handle'], $this->url($style['path']), $style['deps'], $style['ver'], $style['media']);
        }
    }
}
