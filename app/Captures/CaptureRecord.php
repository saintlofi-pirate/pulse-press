<?php
declare(strict_types=1);

namespace PulsePress\Captures;


if (!defined('ABSPATH')) {
    exit;
}

final class CaptureRecord
{
    public const STATUS_INSERTED       = 'inserted';
    public const STATUS_ALREADY_EXISTS = 'already_exists';

    public int $id;
    public CaptureInput $input;
    public string $status;

    public function __construct(int $id, CaptureInput $input, string $status)
    {
        $this->id     = $id;
        $this->input  = $input;
        $this->status = $status;
    }
}
