<?php
declare(strict_types=1);

namespace PulsePress\Reactions;

final class Reactions
{
    public const TYPES = [
        'love',
        'insightful',
        'funny',
        'sad',
        'surprised',
        'angry',
    ];

    public const TYPE_LENGTH_LIMIT = 32;

    public static function isValid(string $type): bool
    {
        $allowed = apply_filters('pulsepress_reaction_types', self::TYPES);
        if (!is_array($allowed)) {
            $allowed = self::TYPES;
        }
        return in_array($type, $allowed, true);
    }
}
