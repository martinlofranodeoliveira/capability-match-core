<?php
declare(strict_types=1);

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
function syntheticProblem(array $ids, array $destinations, array $triples): array
{
    return [
        'items' => array_map(static fn (string $id): array => ['id' => $id], $ids),
        'destinations' => $destinations,
        'compatibilityEdges' => array_map(static fn (array $triple): array => ['item' => $triple[0], 'destination' => $triple[1], 'cost' => $triple[2]], $triples),
    ];
}

function demoProblem(): array
{
    return syntheticProblem(['a', 'b'], [['id' => 'p', 'capacity' => 1], ['id' => 'q', 'capacity' => 1]], [
        ['a', 'p', 0], ['a', 'q', 1], ['b', 'p', 0],
    ]);
}

function solveFixture(array $problem, mixed $limits = []): array
{
    return Lofrano\CapabilityMatch\solve($problem['items'], $problem['destinations'], $problem['compatibilityEdges'], $limits);
}

function allPermutations(array $values): array
{
    if ($values === []) {
        return [[]];
    }
    $out = [];
    foreach ($values as $index => $value) {
        $rest = $values;
        array_splice($rest, $index, 1);
        foreach (allPermutations($rest) as $tail) {
            $out[] = array_merge([$value], $tail);
        }
    }
    return $out;
}
