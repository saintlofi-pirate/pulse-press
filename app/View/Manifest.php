<?php
declare(strict_types=1);

namespace PulsePress\View;

final class Manifest
{
    public const CACHE_KEY = 'pulsepress_vite_manifest_v1';
    public const CACHE_TTL = 86400;

    public function __construct(private string $manifestPath, private string $distUrl)
    {
    }

    /** @return array{js: ?string, css: ?string} */
    public function resolve(string $entry): array
    {
        $manifest = $this->load();
        if ($manifest === null || !isset($manifest[$entry])) {
            return ['js' => null, 'css' => null];
        }

        $node = $manifest[$entry];
        $js   = isset($node['file']) ? $this->distUrl . $node['file'] : null;

        $css = null;
        if (isset($node['css']) && is_array($node['css']) && isset($node['css'][0])) {
            $css = $this->distUrl . (string) $node['css'][0];
        }

        return ['js' => $js, 'css' => $css];
    }

    /** @return array<string, array<string, mixed>>|null */
    private function load(): ?array
    {
        if (!file_exists($this->manifestPath)) {
            return null;
        }

        $mtime  = (int) filemtime($this->manifestPath);
        $cached = get_transient(self::CACHE_KEY);

        if (is_array($cached) && ($cached['mtime'] ?? 0) === $mtime && isset($cached['data']) && is_array($cached['data'])) {
            return $cached['data'];
        }

        $raw  = file_get_contents($this->manifestPath);
        $data = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return null;
        }

        set_transient(self::CACHE_KEY, ['mtime' => $mtime, 'data' => $data], self::CACHE_TTL);

        return $data;
    }
}
