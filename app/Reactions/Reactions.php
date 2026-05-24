<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Reactions;


if (!defined('ABSPATH')) {
    exit;
}

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

    public const DEFAULT_POSITIVE = ['love', 'insightful', 'funny'];

    public const TYPE_LENGTH_LIMIT = 32;

    public static function isValid(string $type): bool
    {
        $allowed = apply_filters('moonfarmer_reactions_lead_capture_reaction_types', self::TYPES);
        if (!is_array($allowed)) {
            $allowed = self::TYPES;
        }
        return in_array($type, $allowed, true);
    }
}
