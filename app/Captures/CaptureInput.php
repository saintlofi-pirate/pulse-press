<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Captures;

use DateTimeImmutable;


if (!defined('ABSPATH')) {
    exit;
}

final class CaptureInput
{
    public int $postId;
    public string $email;
    public string $reactionType;
    public string $source;
    public string $consentTextVersion;
    public DateTimeImmutable $consentAt;
    public string $ipHash;
    public string $userAgentHash;
    public DateTimeImmutable $purgeAt;

    public function __construct(int $postId, string $email, string $reactionType, string $source, string $consentTextVersion, DateTimeImmutable $consentAt, string $ipHash, string $userAgentHash, DateTimeImmutable $purgeAt)
    {
        $this->postId             = $postId;
        $this->email              = $email;
        $this->reactionType       = $reactionType;
        $this->source             = $source;
        $this->consentTextVersion = $consentTextVersion;
        $this->consentAt          = $consentAt;
        $this->ipHash             = $ipHash;
        $this->userAgentHash      = $userAgentHash;
        $this->purgeAt            = $purgeAt;
    }
}
