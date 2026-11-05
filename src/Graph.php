<?php
declare(strict_types=1);

namespace Lofrano\CapabilityMatch;

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
// Solver-private mutable graph; the verifier deliberately does not use it.
final class Graph
{
    public array $adjacency;
    public array $from = [];
    public array $to = [];
    public array $capacity = [];
    public array $initial = [];
    public array $cost = [];
    public array $edgeArcs = [];

    public function __construct(Problem $problem, Budget $budget)
    {
        $this->adjacency = array_fill(0, $problem->vertices, []);
        $offset = 1 + count($problem->items);
        $sink = $problem->vertices - 1;
        foreach ($problem->items as $index => $id) {
            $budget->tick();
            $this->add(0, 1 + $index, 1, 0);
        }
        foreach ($problem->edges as $edge) {
            $budget->tick();
            $u = 1 + $problem->itemIndex['i:' . $edge['item']];
            $v = $offset + $problem->destinationIndex['d:' . $edge['destination']];
            $this->edgeArcs[] = $this->add($u, $v, 1, $edge['cost']);
        }
        foreach ($problem->destinations as $index => $destination) {
            $budget->tick();
            $this->add($offset + $index, $sink, $destination['capacity'], 0);
        }
    }

    private function add(int $u, int $v, int $capacity, int $cost): int
    {
        $arc = count($this->to);
        $this->from[] = $u;
        $this->to[] = $v;
        $this->capacity[] = $capacity;
        $this->initial[] = $capacity;
        $this->cost[] = $cost;
        $this->adjacency[$u][] = $arc;
        $this->from[] = $v;
        $this->to[] = $u;
        $this->capacity[] = 0;
        $this->initial[] = 0;
        $this->cost[] = -$cost;
        $this->adjacency[$v][] = $arc + 1;
        return $arc;
    }
}
