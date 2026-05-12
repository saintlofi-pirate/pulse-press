<?php
declare(strict_types=1);

it('ships a plugin entry file that declares the version constant', function () {
    $entry = file_get_contents(dirname(__DIR__, 2) . '/pulsepress.php');
    expect($entry)
        ->toContain("define('PULSEPRESS_VERSION'")
        ->and($entry)->toContain("define('PULSEPRESS_FILE'")
        ->and($entry)->toContain("define('PULSEPRESS_DIR'")
        ->and($entry)->toContain("define('PULSEPRESS_URL'");
});

it('autoloads the core application class via Composer', function () {
    expect(class_exists(\PulsePress\Core\Application::class, true))->toBeTrue();
    expect(class_exists(\PulsePress\Core\Container::class, true))->toBeTrue();
    expect(class_exists(\PulsePress\Core\ServiceProvider::class, true))->toBeTrue();
});

it('does not retain starter strings in PulsePress-owned files', function () {
    $root = dirname(__DIR__, 2);
    $paths = [
        $root . '/pulsepress.php',
        $root . '/composer.json',
        $root . '/package.json',
        $root . '/vite.config.js',
        $root . '/readme.txt',
    ];
    foreach (glob($root . '/app/**/*.php') ?: [] as $path) {
        $paths[] = $path;
    }
    foreach (glob($root . '/app/*.php') ?: [] as $path) {
        $paths[] = $path;
    }
    foreach ($paths as $path) {
        if (!file_exists($path)) {
            continue;
        }
        $contents = file_get_contents($path);
        expect($contents)
            ->not->toContain('WPPluginMatrix')
            ->and($contents)->not->toContain('WP_PLUGIN_MATRIX')
            ->and($contents)->not->toContain('wp-plugin-matrix');
    }
});
