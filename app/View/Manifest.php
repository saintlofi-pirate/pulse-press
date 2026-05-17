<?php
declare(strict_types=1);

namespace PulsePress\View;


if (!defined('ABSPATH')) {
    exit;
}

final class Manifest
{
    public const CACHE_KEY = 'pulsepress_vite_manifest_v1';
    public const CACHE_TTL = 86400;

    private string $manifestPath;
    private string $distUrl;

    public function __construct(string $manifestPath, string $distUrl)
    {
        $this->manifestPath = $manifestPath;
        $this->distUrl      = $distUrl;
    }

    /**
     * Resolve an entry to its full asset payload.
     *
     * @return array{js: list<string>, css: list<string>}
     */
    public function resolve(string $entry): array
    {
        $manifest = $this->load();
        if ($manifest === null || !isset($manifest[$entry])) {
            return ['js' => [], 'css' => []];
        }

        $visited = [];
        $jsFiles = [];
        $cssFiles = [];
        $this->collect($manifest, $entry, $visited, $jsFiles, $cssFiles);

        return [
            'js'  => array_values(array_unique(array_map(fn ($f) => $this->distUrl . $f, $jsFiles))),
            'css' => array_values(array_unique(array_map(fn ($f) => $this->distUrl . $f, $cssFiles))),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @param array<string, true>                 $visited
     * @param list<string>                        $jsFiles
     * @param list<string>                        $cssFiles
     */
    private function collect(array $manifest, string $key, array &$visited, array &$jsFiles, array &$cssFiles): void
    {
        if (isset($visited[$key]) || !isset($manifest[$key])) {
            return;
        }
        $visited[$key] = true;
        $node = $manifest[$key];

        foreach (($node['imports'] ?? []) as $import) {
            if (is_string($import)) {
                $this->collect($manifest, $import, $visited, $jsFiles, $cssFiles);
            }
        }

        if (isset($node['file']) && is_string($node['file'])) {
            $jsFiles[] = $node['file'];
        }
        foreach (($node['css'] ?? []) as $cssFile) {
            if (is_string($cssFile)) {
                $cssFiles[] = $cssFile;
            }
        }
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
