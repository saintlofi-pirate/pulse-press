<?php

/**
 * Dynamic render callback for the pulsepress/reactions block.
 *
 * WordPress passes $attributes, $content, $block as locals when including this file.
 */
declare(strict_types=1);


if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WordPress provides these render.php locals.
if (!isset($attributes) || !is_array($attributes)) {
    $attributes = [];
}
if (!isset($content) || !is_string($content)) {
    $content = '';
}
$block = $block ?? null;
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ReactionsBlock returns plugin-owned markup with escaped attributes.
echo \PulsePress\Blocks\ReactionsBlock::render($attributes, $content, $block);
