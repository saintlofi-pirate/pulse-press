<?php
declare(strict_types=1);

use PulsePress\View\Manifest;

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir() . '/pulsepress-manifest-' . uniqid();
    mkdir($this->tmpDir, 0o777, true);
    $this->manifestPath = $this->tmpDir . '/manifest.json';
    $this->distUrl      = 'https://example.test/wp-content/plugins/pulse-press/dist/';
});

afterEach(function () {
    if (file_exists($this->manifestPath)) {
        unlink($this->manifestPath);
    }
    @rmdir($this->tmpDir);
});

it('returns empty lists when the manifest file does not exist', function () {
    $manifest = new Manifest($this->manifestPath, $this->distUrl);
    expect($manifest->resolve('resources/widget/index.ts'))->toBe(['js' => [], 'css' => []]);
});

it('resolves js and css URLs from a present manifest', function () {
    file_put_contents($this->manifestPath, json_encode([
        'resources/widget/index.ts' => [
            'file' => 'js/widget.abc123.js',
            'css'  => ['assets/widget.def456.css'],
        ],
    ]));

    $manifest = new Manifest($this->manifestPath, $this->distUrl);
    $result   = $manifest->resolve('resources/widget/index.ts');

    expect($result['js'])->toBe([$this->distUrl . 'js/widget.abc123.js']);
    expect($result['css'])->toBe([$this->distUrl . 'assets/widget.def456.css']);
});

it('returns empty css when the manifest entry omits the css array', function () {
    file_put_contents($this->manifestPath, json_encode([
        'resources/widget/index.ts' => ['file' => 'js/widget.abc123.js'],
    ]));

    $manifest = new Manifest($this->manifestPath, $this->distUrl);
    $result   = $manifest->resolve('resources/widget/index.ts');

    expect($result['js'])->toBe([$this->distUrl . 'js/widget.abc123.js']);
    expect($result['css'])->toBe([]);
});

it('follows imports to include shared chunks before the entry', function () {
    file_put_contents($this->manifestPath, json_encode([
        '_shared.abc.js' => ['file' => 'js/shared.abc.js'],
        'resources/admin/index.tsx' => [
            'file'    => 'js/admin.def.js',
            'imports' => ['_shared.abc.js'],
            'css'     => ['assets/admin.ghi.css'],
        ],
    ]));

    $manifest = new Manifest($this->manifestPath, $this->distUrl);
    $result   = $manifest->resolve('resources/admin/index.tsx');

    // Shared chunk comes first; entry comes last (so deps are evaluated first).
    expect($result['js'])->toBe([
        $this->distUrl . 'js/shared.abc.js',
        $this->distUrl . 'js/admin.def.js',
    ]);
    expect($result['css'])->toBe([$this->distUrl . 'assets/admin.ghi.css']);
});

it('caches the parsed manifest under the mtime key', function () {
    file_put_contents($this->manifestPath, json_encode([
        'resources/widget/index.ts' => ['file' => 'js/first.js'],
    ]));

    $manifest = new Manifest($this->manifestPath, $this->distUrl);
    $manifest->resolve('resources/widget/index.ts');

    $cached = \Tests\Stubs\TransientStore::get(Manifest::CACHE_KEY);
    expect($cached)->toBeArray();
    expect($cached['mtime'])->toBeInt();
    expect($cached['data'])->toHaveKey('resources/widget/index.ts');
});

it('refreshes the cache when the manifest mtime changes', function () {
    file_put_contents($this->manifestPath, json_encode([
        'resources/widget/index.ts' => ['file' => 'js/old.js'],
    ]));
    $manifest = new Manifest($this->manifestPath, $this->distUrl);
    $manifest->resolve('resources/widget/index.ts');

    sleep(1);
    file_put_contents($this->manifestPath, json_encode([
        'resources/widget/index.ts' => ['file' => 'js/new.js'],
    ]));

    $result = $manifest->resolve('resources/widget/index.ts');
    expect($result['js'])->toBe([$this->distUrl . 'js/new.js']);
});
