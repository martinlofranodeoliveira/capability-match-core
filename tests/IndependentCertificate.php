<?php
declare(strict_types=1);

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
// A second test-only mathematical checker. No library classes or functions are called.
function independentlyCheckCertificate(array $problem, array $result): void
{
    $require = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException('Independent certificate: ' . $message);
        }
    };
    $record = static function (mixed $value, array $fields) use ($require): void {
        $require(is_array($value) && count($value) === count($fields), 'record shape');
        foreach ($fields as $field) {
            $require(array_key_exists($field, $value), 'record field');
        }
    };
    $record($result, ['status', 'version', 'assignments', 'unassigned', 'cardinality', 'cost', 'certificate', 'inspections']);
    $items = array_column($problem['items'], 'id');
    usort($items, static fn (string $a, string $b): int => strcmp($a, $b));
    $destinations = $problem['destinations'];
    usort($destinations, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
    $destinationIds = array_column($destinations, 'id');
    $n = count($items) + count($destinations) + 2;
    $sink = $n - 1;
    $matched = array_fill(0, count($items), null);
    $used = array_fill(0, count($destinations), 0);
    $total = 0;
    $require(($result['status'] ?? null) === 'Optimal' && ($result['version'] ?? null) === '0.1.0', 'status or version');
    $require(isset($result['inspections']) && is_int($result['inspections']) && $result['inspections'] >= 0 && $result['inspections'] <= 50000000, 'inspections');
    $require(is_array($result['assignments'] ?? null) && array_is_list($result['assignments']), 'assignments list');
    $require(is_array($result['unassigned'] ?? null) && array_is_list($result['unassigned']), 'unassigned list');
    $previousItem = null;
    foreach ($result['assignments'] as $assignment) {
        $require(is_array($assignment) && count($assignment) === 2 && array_key_exists('item', $assignment) && array_key_exists('destination', $assignment), 'assignment shape');
        $require(is_string($assignment['item']) && is_string($assignment['destination']), 'assignment string IDs');
        $require($previousItem === null || strcmp($previousItem, $assignment['item']) < 0, 'assignment byte order');
        $previousItem = $assignment['item'];
        $i = array_search($assignment['item'], $items, true);
        $d = array_search($assignment['destination'], $destinationIds, true);
        $require($i !== false && $d !== false, 'unknown ID');
        $require($matched[$i] === null, 'duplicate item');
        $found = false;
        foreach ($problem['compatibilityEdges'] as $edge) {
            if ($edge['item'] === $assignment['item'] && $edge['destination'] === $assignment['destination']) {
                $total += $edge['cost'];
                $found = true;
                break;
            }
        }
        $require($found, 'not compatible');
        $matched[$i] = $d;
        ++$used[$d];
        $require($used[$d] <= $destinations[$d]['capacity'], 'capacity');
    }
    $unassigned = [];
    foreach ($items as $i => $id) {
        if ($matched[$i] === null) {
            $unassigned[] = $id;
        }
    }
    $flow = count($items) - count($unassigned);
    $require($result['cardinality'] === $flow && $result['cost'] === $total, 'totals');
    $require($result['unassigned'] === $unassigned, 'unassigned');
    $record($result['certificate'], ['sourceSide', 'cutCapacity', 'potentials']);
    $side = $result['certificate']['sourceSide'] ?? null;
    $potential = $result['certificate']['potentials'] ?? null;
    $require(is_array($side) && array_is_list($side), 'cut list');
    $require(is_array($potential) && array_is_list($potential) && count($potential) === $n && $potential[0] === 0, 'potential shape');
    foreach ($potential as $value) {
        $require(is_int($value) && $value >= -1000000000 && $value <= 1000000000, 'potential range');
    }
    $require(in_array(0, $side, true) && !in_array($sink, $side, true), 'separation');
    $last = -1;
    foreach ($side as $vertex) {
        $require(is_int($vertex) && $vertex > $last && $vertex < $n, 'cut shape');
        $last = $vertex;
    }
    $originals = [];
    foreach ($items as $i => $id) {
        $originals[] = [0, 1 + $i, 1, 0, $matched[$i] === null ? 0 : 1];
    }
    foreach ($problem['compatibilityEdges'] as $edge) {
        $i = array_search($edge['item'], $items, true);
        $d = array_search($edge['destination'], $destinationIds, true);
        $originals[] = [1 + $i, 1 + count($items) + $d, 1, $edge['cost'], $matched[$i] === $d ? 1 : 0];
    }
    foreach ($destinations as $d => $destination) {
        $originals[] = [1 + count($items) + $d, $sink, $destination['capacity'], 0, $used[$d]];
    }
    $cutCapacity = 0;
    foreach ($originals as [$u, $v, $capacity, $cost, $amount]) {
        $require($amount >= 0 && $amount <= $capacity, 'flow bound');
        if (in_array($u, $side, true) && !in_array($v, $side, true)) {
            $cutCapacity += $capacity;
        }
        $arcs = [[$u, $v, $capacity - $amount, $cost], [$v, $u, $amount, -$cost]];
        foreach ($arcs as [$from, $to, $remaining, $arcCost]) {
            if ($remaining > 0) {
                $require(!(in_array($from, $side, true) && !in_array($to, $side, true)), 'residual cut');
                $require($arcCost + $potential[$from] - $potential[$to] >= 0, 'global reduced cost');
            }
        }
    }
    $require($cutCapacity === $flow && $result['certificate']['cutCapacity'] === $cutCapacity, 'cut capacity');
}
