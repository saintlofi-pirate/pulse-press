<?php
/**
 * Dynamic render callback for the pulsepress/reactions block.
 *
 * WordPress passes $attributes, $content, $block as locals when including this file.
 */
declare(strict_types=1);

if (!isset($attributes) || !is_array($attributes)) {
    $attributes = [];
}
if (!isset($content) || !is_string($content)) {
    $content = '';
}
$block = $block ?? null;

echo \PulsePress\Blocks\ReactionsBlock::render($attributes, $content, $block);
