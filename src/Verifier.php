<?php
declare(strict_types=1);

namespace Lofrano\CapabilityMatch;

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
// No Graph, Solver, priority queue, shortest-path or solver residual helper is used here.
final class Verifier
{
    public function verify(mixed $problem, mixed $result, mixed $limits = []): array
    {
        $budget = null;
        try {
            $settings = new Limits($limits);
            $budget = new Budget($settings->get('relaxationBudget'));
            Problem::record($problem, ['items', 'destinations', 'compatibilityEdges']);
            $p = new Problem($problem['items'], $problem['destinations'], $problem['compatibilityEdges'], $settings);
            self::record($result, ['status', 'version', 'assignments', 'unassigned', 'cardinality', 'cost', 'certificate', 'inspections']);
            self::need($result['status'] === 'Optimal' && $result['version'] === VERSION, 'Only version 0.1.0 Optimal results are certificates.');
            self::need(is_int($result['inspections']) && $result['inspections'] >= 0 && $result['inspections'] <= Limits::BUDGET_CAP, 'Invalid work metadata.');
            self::list($result['assignments'], count($p->items));
            self::list($result['unassigned'], count($p->items));
            $costs = [];
            foreach ($p->edges as $edge) {
                $budget->tick();
                $i = $p->itemIndex['i:' . $edge['item']];
                $d = $p->destinationIndex['d:' . $edge['destination']];
                $costs['e:' . $i . ':' . $d] = $edge['cost'];
            }
            $assigned = [];
            $selectedEdges = [];
            $usage = array_fill(0, count($p->destinations), 0);
            $cost = 0;
            $last = null;
            foreach ($result['assignments'] as $assignment) {
                $budget->tick();
                self::record($assignment, ['item', 'destination']);
                $item = $assignment['item'];
                $destination = $assignment['destination'];
                self::need(is_string($item) && is_string($destination), 'Assignment IDs must be strings.');
                // Bound lengths before any attacker-controlled map lookup.
                self::need(strlen($item) <= 128 && strlen($destination) <= 128, 'Assignment ID is too long.');
                self::need(isset($p->itemIndex['i:' . $item]) && isset($p->destinationIndex['d:' . $destination]), 'Unknown assignment ID.');
                self::need($last === null || strcmp($last, $item) < 0, 'Assignments must be unique and sorted by item bytes.');
                $last = $item;
                $i = $p->itemIndex['i:' . $item];
                $d = $p->destinationIndex['d:' . $destination];
                $key = 'e:' . $i . ':' . $d;
                self::need(array_key_exists($key, $costs), 'Assignment is not a compatibility edge.');
                self::need(!isset($assigned['i:' . $item]), 'Item is assigned more than once.');
                $assigned['i:' . $item] = true;
                $selectedEdges[$key] = true;
                ++$usage[$d];
                self::need($usage[$d] <= $p->destinations[$d]['capacity'], 'Assignment exceeds destination capacity.');
                $cost = Limits::add($cost, $costs[$key]);
            }
            $cardinality = count($result['assignments']);
            $unassigned = [];
            foreach ($p->items as $id) {
                if (!isset($assigned['i:' . $id])) {
                    $unassigned[] = $id;
                }
            }
            self::need($result['cardinality'] === $cardinality, 'Cardinality does not match the assignment.');
            self::need($result['cost'] === $cost, 'Cost does not match the assignment.');
            self::need($result['unassigned'] === $unassigned, 'Unassigned items do not match the assignment.');
            $certificate = $result['certificate'];
            self::record($certificate, ['sourceSide', 'cutCapacity', 'potentials']);
            $n = $p->vertices;
            $sink = $n - 1;
            self::list($certificate['sourceSide'], $n);
            self::list($certificate['potentials'], $n);
            self::need(count($certificate['potentials']) === $n, 'Wrong number of potentials.');
            $potentials = $certificate['potentials'];
            foreach ($potentials as $value) {
                self::need(is_int($value) && $value >= -Limits::POTENTIAL_BOUND && $value <= Limits::POTENTIAL_BOUND, 'Potential is outside the integer range.');
            }
            self::need($potentials[0] === 0, 'Potentials must be normalized at the source.');
            $side = array_fill(0, $n, false);
            $previous = -1;
            foreach ($certificate['sourceSide'] as $vertex) {
                self::need(is_int($vertex) && $vertex > $previous && $vertex < $n, 'Cut vertices must be increasing valid integers.');
                $side[$vertex] = true;
                $previous = $vertex;
            }
            self::need($side[0] && !$side[$sink], 'Cut must contain source and exclude sink.');
            $residual = [];
            $cutCapacity = 0;
            // Independently reconstruct BOTH directions from canonical originals and assignment flow.
            $original = static function (int $u, int $v, int $capacity, int $arcCost, int $flow) use (&$residual, &$cutCapacity, $side, $budget): void {
                $budget->tick();
                self::need($flow >= 0 && $flow <= $capacity, 'Invalid reconstructed flow.');
                $residual[] = [$u, $v, $capacity - $flow, $arcCost];
                $residual[] = [$v, $u, $flow, -$arcCost];
                if ($side[$u] && !$side[$v]) {
                    $cutCapacity = Limits::add($cutCapacity, $capacity);
                }
            };
            foreach ($p->items as $i => $id) {
                $original(0, 1 + $i, 1, 0, isset($assigned['i:' . $id]) ? 1 : 0);
            }
            $offset = 1 + count($p->items);
            foreach ($p->edges as $edge) {
                $i = $p->itemIndex['i:' . $edge['item']];
                $d = $p->destinationIndex['d:' . $edge['destination']];
                $flow = isset($selectedEdges['e:' . $i . ':' . $d]) ? 1 : 0;
                $original(1 + $i, $offset + $d, 1, $edge['cost'], $flow);
            }
            foreach ($p->destinations as $d => $destination) {
                $original($offset + $d, $sink, $destination['capacity'], 0, $usage[$d]);
            }
            foreach ($residual as $arc) {
                $budget->tick();
                [$u, $v, $capacity, $arcCost] = $arc;
                if ($capacity > 0) {
                    self::need(!($side[$u] && !$side[$v]), 'Positive residual arc crosses the cut.');
                    $reduced = Limits::subtract(Limits::add($arcCost, $potentials[$u]), $potentials[$v]);
                    self::need($reduced >= 0, 'Residual potential inequality fails.');
                }
            }
            self::need($cutCapacity === $cardinality && $certificate['cutCapacity'] === $cutCapacity, 'Cut capacity does not equal the reconstructed flow.');
            return ['status' => 'Verified', 'cardinality' => $cardinality, 'cost' => $cost, 'inspections' => $budget->used];
        } catch (Failure $failure) {
            return ['status' => $failure->reason, 'message' => $failure->getMessage(), 'inspections' => $budget?->used ?? 0];
        }
    }

    private static function need(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new Failure('InvalidCertificate', $message);
        }
    }

    private static function record(mixed $value, array $keys): void
    {
        self::need(is_array($value) && count($value) === count($keys), 'Incorrect certificate record fields.');
        foreach ($keys as $key) {
            self::need(array_key_exists($key, $value), 'Missing certificate record field.');
        }
    }

    private static function list(mixed $value, int $maximum): void
    {
        self::need(is_array($value) && count($value) <= $maximum && array_is_list($value), 'Certificate collection is not a bounded list.');
    }
}
