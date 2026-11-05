<?php
declare(strict_types=1);

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
// Complete enumeration, not a graph algorithm. No library helpers are used.
function exhaustiveOracle(array $problem): array
{
    $choices = [];
    foreach ($problem['items'] as $item) {
        $options = [];
        foreach ($problem['compatibilityEdges'] as $edge) {
            if ($edge['item'] !== $item['id']) {
                continue;
            }
            foreach ($problem['destinations'] as $index => $destination) {
                if ($destination['id'] === $edge['destination']) {
                    $options[] = [$index, $edge['cost']];
                }
            }
        }
        $choices[] = $options;
    }
    $bestCardinality = -1;
    $bestCost = 0;
    $used = array_fill(0, count($problem['destinations']), 0);
    $visit = function (int $item, int $cardinality, int $cost) use (&$visit, &$used, &$bestCardinality, &$bestCost, $choices, $problem): void {
        if ($item === count($choices)) {
            if ($cardinality > $bestCardinality || ($cardinality === $bestCardinality && $cost < $bestCost)) {
                $bestCardinality = $cardinality;
                $bestCost = $cost;
            }
            return;
        }
        // Enumerate the unassigned choice and every capacity-feasible destination.
        $visit($item + 1, $cardinality, $cost);
        foreach ($choices[$item] as [$destination, $preference]) {
            if ($used[$destination] < $problem['destinations'][$destination]['capacity']) {
                ++$used[$destination];
                $visit($item + 1, $cardinality + 1, $cost + $preference);
                --$used[$destination];
            }
        }
    };
    $visit(0, 0, 0);
    return ['cardinality' => $bestCardinality, 'cost' => $bestCost];
}
