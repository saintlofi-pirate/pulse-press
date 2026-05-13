<?php
declare(strict_types=1);

namespace PulsePress\Captures;

final readonly class CaptureRecord
{
    public const STATUS_INSERTED       = 'inserted';
    public const STATUS_ALREADY_EXISTS = 'already_exists';

    public function __construct(
        public int $id,
        public CaptureInput $input,
        public string $status,
    ) {
    }
}
