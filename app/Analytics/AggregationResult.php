<?php
declare(strict_types=1);

namespace PulsePress\Analytics;

use DateTimeImmutable;

final readonly class AggregationResult
{
    public function __construct(
        public DateTimeImmutable $date,
        public int $rowsWritten,
        public int $groupsProcessed,
        public int $tookMicros,
    ) {
    }
}
