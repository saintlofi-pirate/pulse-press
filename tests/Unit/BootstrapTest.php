<?php
declare(strict_types=1);

it('ships a plugin entry file that declares the version constant', function () {
    $entry = file_get_contents(dirname(__DIR__, 2) . '/moonfarmer-reactions-lead-capture.php');
    expect($entry)
        ->toContain("define('MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION'")
        ->and($entry)->toContain("define('MOONFARMER_REACTIONS_LEAD_CAPTURE_FILE'")
        ->and($entry)->toContain("define('MOONFARMER_REACTIONS_LEAD_CAPTURE_DIR'")
        ->and($entry)->toContain("define('MOONFARMER_REACTIONS_LEAD_CAPTURE_URL'");
});

it('autoloads the core application class via Composer', function () {
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Core\Application::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Core\Container::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Core\ServiceProvider::class, true))->toBeTrue();
});

it('autoloads the database schema and migrator classes', function () {
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Database\Schema::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Database\Migrator::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Providers\DatabaseServiceProvider::class, true))->toBeTrue();
});

it('autoloads the reaction domain, REST controller, and provider', function () {
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Reactions\Reactions::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Reactions\UserHash::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Reactions\ReactionRepository::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Http\Controllers\ReactionController::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Http\RestException::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Providers\RestServiceProvider::class, true))->toBeTrue();
});

it('autoloads the widget manifest and provider', function () {
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\View\Manifest::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Providers\WidgetServiceProvider::class, true))->toBeTrue();
});

it('autoloads the capture domain, controller, and provider', function () {
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Captures\Captures::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Captures\CaptureInput::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Captures\CaptureRecord::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Captures\CaptureRepository::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Captures\FraudPurger::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Http\Controllers\CaptureController::class, true))->toBeTrue();
    expect(class_exists(\Moonfarmer\ReactionsLeadCapture\Providers\CaptureServiceProvider::class, true))->toBeTrue();
});

it('does not retain starter strings in Moonfarmer Reactions Lead Capture-owned files', function () {
    $root = dirname(__DIR__, 2);
    $paths = [
        $root . '/moonfarmer-reactions-lead-capture.php',
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
