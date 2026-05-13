<?php
declare(strict_types=1);

namespace PulsePress\Captures;

use DateTimeImmutable;

final readonly class CaptureInput
{
    public function __construct(
        public int $postId,
        public string $email,
        public string $reactionType,
        public string $source,
        public string $consentTextVersion,
        public DateTimeImmutable $consentAt,
        public string $ipHash,
        public string $userAgentHash,
        public DateTimeImmutable $purgeAt,
    ) {
    }
}
