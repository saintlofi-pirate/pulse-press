<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Analytics;

use DateTimeImmutable;


if (!defined('ABSPATH')) {
    exit;
}

final class AggregationResult
{
    public DateTimeImmutable $date;
    public int $rowsWritten;
    public int $groupsProcessed;
    public int $tookMicros;

    public function __construct(DateTimeImmutable $date, int $rowsWritten, int $groupsProcessed, int $tookMicros)
    {
        $this->date            = $date;
        $this->rowsWritten     = $rowsWritten;
        $this->groupsProcessed = $groupsProcessed;
        $this->tookMicros      = $tookMicros;
    }
}
