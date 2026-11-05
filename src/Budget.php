<?php
declare(strict_types=1);

namespace Lofrano\CapabilityMatch;

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
final class Budget
{
    public int $used = 0;

    public function __construct(public readonly int $limit)
    {
    }

    public function tick(): void
    {
        // Check before looking at an arc, including zero-capacity arcs.
        if ($this->used >= $this->limit) {
            throw new Failure('BudgetExceeded', 'The arc-inspection budget was exhausted.');
        }
        ++$this->used;
    }
}
