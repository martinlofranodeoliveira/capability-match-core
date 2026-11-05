<?php
declare(strict_types=1);

namespace Lofrano\CapabilityMatch;

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
final class Solver
{
    public function solve(mixed $items, mixed $destinations, mixed $compatibilityEdges, mixed $limits = []): array
    {
        $budget = null;
        try {
            $settings = new Limits($limits);
            $budget = new Budget($settings->get('relaxationBudget'));
            $problem = new Problem($items, $destinations, $compatibilityEdges, $settings);
            $graph = new Graph($problem, $budget);
            $n = $problem->vertices;
            $sink = $n - 1;
            $potentials = array_fill(0, $n, 0);
            $flow = 0;
            while (true) {
                [$distance, $previous] = $this->shortest($graph, $potentials, $budget);
                if ($distance[$sink] === null) {
                    break;
                }
                foreach ($distance as $vertex => $value) {
                    if ($value !== null) {
                        $potentials[$vertex] = Limits::add($potentials[$vertex], $value);
                    }
                }
                $vertex = $sink;
                $steps = 0;
                while ($vertex !== 0) {
                    $budget->tick();
                    $arc = $previous[$vertex];
                    if ($arc < 0 || ++$steps >= $n || $graph->capacity[$arc] < 1) {
                        throw new Failure('InternalError', 'Invalid augmenting path.');
                    }
                    --$graph->capacity[$arc];
                    ++$graph->capacity[$arc ^ 1];
                    $vertex = $graph->from[$arc];
                }
                if (++$flow > count($problem->items)) {
                    throw new Failure('InternalError', 'Flow exceeds the number of items.');
                }
            }
            // Reachable Dijkstra potentials are not a certificate for other components.
            $certificatePotentials = $this->globalPotentials($graph, $budget);
            $seen = array_fill(0, $n, false);
            $seen[0] = true;
            $queue = [0];
            for ($head = 0; $head < count($queue); ++$head) {
                $u = $queue[$head];
                foreach ($graph->adjacency[$u] as $arc) {
                    $budget->tick();
                    $v = $graph->to[$arc];
                    if ($graph->capacity[$arc] > 0 && !$seen[$v]) {
                        $seen[$v] = true;
                        $queue[] = $v;
                    }
                }
            }
            $sourceSide = [];
            foreach ($seen as $vertex => $reachable) {
                if ($reachable) {
                    $sourceSide[] = $vertex;
                }
            }
            $cutCapacity = 0;
            for ($arc = 0; $arc < count($graph->to); $arc += 2) {
                $budget->tick();
                if ($seen[$graph->from[$arc]] && !$seen[$graph->to[$arc]]) {
                    $cutCapacity = Limits::add($cutCapacity, $graph->initial[$arc]);
                }
            }
            if ($seen[$sink] || $cutCapacity !== $flow) {
                throw new Failure('InternalError', 'Residual cut does not certify the flow.');
            }
            $assignments = [];
            $assigned = [];
            $cost = 0;
            foreach ($problem->edges as $index => $edge) {
                $budget->tick();
                if ($graph->capacity[$graph->edgeArcs[$index]] === 0) {
                    $assignments[] = ['item' => $edge['item'], 'destination' => $edge['destination']];
                    $assigned['i:' . $edge['item']] = true;
                    $cost = Limits::add($cost, $edge['cost']);
                }
            }
            $unassigned = [];
            foreach ($problem->items as $id) {
                if (!isset($assigned['i:' . $id])) {
                    $unassigned[] = $id;
                }
            }
            return [
                'status' => 'Optimal',
                'version' => VERSION,
                'assignments' => $assignments,
                'unassigned' => $unassigned,
                'cardinality' => count($assignments),
                'cost' => $cost,
                'certificate' => [
                    'sourceSide' => $sourceSide,
                    'cutCapacity' => $cutCapacity,
                    'potentials' => $certificatePotentials,
                ],
                'inspections' => $budget->used,
            ];
        } catch (Failure $failure) {
            return ['status' => $failure->reason, 'message' => $failure->getMessage(), 'inspections' => $budget?->used ?? 0];
        }
    }

    private function shortest(Graph $graph, array $potentials, Budget $budget): array
    {
        $n = count($graph->adjacency);
        $distance = array_fill(0, $n, null);
        $previous = array_fill(0, $n, -1);
        $settled = array_fill(0, $n, false);
        $distance[0] = 0;
        $heap = new StableQueue();
        $heap->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
        $serial = 0;
        $heap->insert(0, [0, 0, $serial++]);
        while (!$heap->isEmpty()) {
            $entry = $heap->extract();
            $u = $entry['data'];
            if ($settled[$u] || $entry['priority'][0] !== $distance[$u]) {
                continue;
            }
            $settled[$u] = true;
            foreach ($graph->adjacency[$u] as $arc) {
                $budget->tick();
                if ($graph->capacity[$arc] === 0) {
                    continue;
                }
                $v = $graph->to[$arc];
                $reduced = Limits::subtract(Limits::add($graph->cost[$arc], $potentials[$u]), $potentials[$v]);
                if ($reduced < 0) {
                    throw new Failure('InternalError', 'Negative reduced cost in a source-reachable component.');
                }
                $candidate = Limits::add($distance[$u], $reduced);
                if (!$settled[$v] && ($distance[$v] === null || $candidate < $distance[$v])) {
                    $distance[$v] = $candidate;
                    $previous[$v] = $arc;
                    $heap->insert($v, [$candidate, $v, $serial++]);
                }
            }
        }
        return [$distance, $previous];
    }

    private function globalPotentials(Graph $graph, Budget $budget): array
    {
        $n = count($graph->adjacency);
        // Zero distance to every vertex is an implicit super-source.
        $distance = array_fill(0, $n, 0);
        for ($pass = 0; $pass < $n; ++$pass) {
            $changed = false;
            for ($arc = 0; $arc < count($graph->to); ++$arc) {
                $budget->tick();
                if ($graph->capacity[$arc] === 0) {
                    continue;
                }
                $u = $graph->from[$arc];
                $v = $graph->to[$arc];
                $candidate = Limits::add($distance[$u], $graph->cost[$arc]);
                if ($candidate < $distance[$v]) {
                    $distance[$v] = $candidate;
                    $changed = true;
                }
            }
            if (!$changed) {
                break;
            }
            if ($pass === $n - 1) {
                throw new Failure('InternalError', 'Residual graph contains a negative cycle.');
            }
        }
        $reference = $distance[0];
        foreach ($distance as $vertex => $value) {
            $value = Limits::subtract($value, $reference);
            if ($value < -Limits::POTENTIAL_BOUND || $value > Limits::POTENTIAL_BOUND) {
                throw new Failure('InternalError', 'Certificate potential exceeds the proven range.');
            }
            $distance[$vertex] = $value;
        }
        return $distance;
    }
}
