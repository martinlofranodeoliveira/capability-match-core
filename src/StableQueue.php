<?php
declare(strict_types=1);

namespace Lofrano\CapabilityMatch;

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
final class StableQueue extends \SplPriorityQueue
{
    public function compare(mixed $priority1, mixed $priority2): int
    {
        // SPL is a max heap. Prefer smaller distance, then vertex, then insertion serial.
        // Comparisons, rather than subtraction, avoid arithmetic overflow.
        for ($i = 0; $i < 3; ++$i) {
            $order = $priority2[$i] <=> $priority1[$i];
            if ($order !== 0) {
                return $order;
            }
        }
        return 0;
    }
}
