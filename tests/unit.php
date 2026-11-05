<?php
declare(strict_types=1);

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
use function Lofrano\CapabilityMatch\verifyCertificate;

return static function (TestSuite $suite): void {
    $suite->run('regression objectives and witnesses', static function (TestSuite $t): void {
        $p = demoProblem();
        $r = solveFixture($p);
        $t->same('Optimal', $r['status'], 'demo status');
        $t->same([['item' => 'a', 'destination' => 'q'], ['item' => 'b', 'destination' => 'p']], $r['assignments'], 'reverse-arc reassignment');
        $t->same(2, $r['cardinality'], 'demo cardinality');
        $t->same(1, $r['cost'], 'demo cost');
        $p2 = syntheticProblem(['a', 'b'], $p['destinations'], [['a', 'p', 4], ['a', 'q', 1], ['b', 'p', 2], ['b', 'q', 8]]);
        $r2 = solveFixture($p2);
        $t->same(2, $r2['cardinality'], 'second cardinality');
        $t->same(3, $r2['cost'], 'second cost');
        $p3 = syntheticProblem(['a', 'b', 'c'], [['id' => 'p', 'capacity' => 1]], [['a', 'p', 0], ['b', 'p', 1], ['c', 'p', 2]]);
        $r3 = solveFixture($p3);
        $t->same([['item' => 'a', 'destination' => 'p']], $r3['assignments'], 'cheapest bottleneck');
        $t->same(['b', 'c'], $r3['unassigned'], 'bottleneck unassigned');
        $t->same(1, $r3['cardinality'], 'bottleneck cardinality');
        $t->same(0, $r3['cost'], 'bottleneck cost');
        $t->same(1, $r3['certificate']['cutCapacity'], 'bottleneck cut');
        $p4 = syntheticProblem(['a', 'b'], [['id' => 'p', 'capacity' => 2]], []);
        $r4 = solveFixture($p4);
        $t->same(0, $r4['cardinality'], 'no edges cardinality');
        $t->same(0, $r4['cost'], 'no edges cost');
        $t->same(['a', 'b'], $r4['unassigned'], 'no edges unassigned');
        $p5 = syntheticProblem([], [], []);
        $r5 = solveFixture($p5, ['relaxationBudget' => 0]);
        $t->same('Optimal', $r5['status'], 'empty zero-budget instance');
        $p6 = syntheticProblem(['a'], [['id' => 'p', 'capacity' => 0]], [['a', 'p', 0]]);
        $r6 = solveFixture($p6);
        $t->same(0, $r6['cardinality'], 'zero capacity');
        $p7 = syntheticProblem(['a', 'b', 'c'], [['id' => 'p', 'capacity' => 1], ['id' => 'q', 'capacity' => 1], ['id' => 'r', 'capacity' => 1]], [
            ['a', 'p', 0], ['a', 'q', 1], ['b', 'p', 2], ['b', 'r', 4], ['c', 'p', 0],
        ]);
        $r7 = solveFixture($p7);
        $t->same(3, $r7['cardinality'], 'multi-hop reverse-arc cardinality');
        $t->same(5, $r7['cost'], 'multi-hop reverse-arc cost');
        $t->same([['item' => 'a', 'destination' => 'q'], ['item' => 'b', 'destination' => 'r'], ['item' => 'c', 'destination' => 'p']], $r7['assignments'], 'two-hop residual reroute');
        foreach ([[$p, $r], [$p2, $r2], [$p3, $r3], [$p4, $r4], [$p5, $r5], [$p6, $r6], [$p7, $r7]] as [$problem, $result]) {
            $t->same('Verified', verifyCertificate($problem, $result)['status'], 'library verification');
            independentlyCheckCertificate($problem, $result);
            $t->check(true, 'independent verification');
        }
    });
    $suite->run('opaque identity and namespaces', static function (TestSuite $t): void {
        $numeric = syntheticProblem(['01', '1'], [['id' => 'port', 'capacity' => 2]], [['01', 'port', 2], ['1', 'port', 1]]);
        $numericResult = solveFixture($numeric);
        $t->same(2, $numericResult['cardinality'], '01 and 1 both assigned to capacity-2 destination');
        $t->same(3, $numericResult['cost'], '01 and 1 combined cost');
        $t->same([['item' => '01', 'destination' => 'port'], ['item' => '1', 'destination' => 'port']], $numericResult['assignments'], '01 precedes 1 in byte order');
        $t->same('Verified', verifyCertificate($numeric, $numericResult)['status'], '01 and 1 verified');
        independentlyCheckCertificate($numeric, $numericResult);
        $ids = ['01', '1', "e\u{0301}", "\u{00e9}", "nul\0\n", 'a:b', 'a'];
        $ports = [['id' => '1', 'capacity' => 7], ['id' => 'b:c', 'capacity' => 0], ['id' => 'c', 'capacity' => 0]];
        $edges = [];
        foreach ($ids as $id) {
            $edges[] = [$id, '1', 0];
        }
        $edges[] = ['a', 'b:c', 0];
        $edges[] = ['a:b', 'c', 0];
        $p = syntheticProblem($ids, $ports, $edges);
        $r = solveFixture($p);
        $t->same(7, $r['cardinality'], 'numeric, control and combining IDs retained');
        $assigned = array_column($r['assignments'], 'item');
        foreach ($ids as $id) {
            $t->check(in_array($id, $assigned, true), 'exact identity retained');
        }
        $t->same('Verified', verifyCertificate($p, $r)['status'], 'opaque verification');
        independentlyCheckCertificate($p, $r);
    });
    $suite->run('canonical tie ordering under every permutation', static function (TestSuite $t): void {
        foreach ([0, 1] as $cost) {
            $p = syntheticProblem(['a', 'b'], [['id' => 'p', 'capacity' => 1], ['id' => 'q', 'capacity' => 1]], [
                ['a', 'p', $cost], ['a', 'q', $cost], ['b', 'p', $cost], ['b', 'q', $cost],
            ]);
            $canonical = json_encode(solveFixture($p), JSON_THROW_ON_ERROR);
            foreach (allPermutations($p['items']) as $items) {
                foreach (allPermutations($p['destinations']) as $destinations) {
                    foreach (allPermutations($p['compatibilityEdges']) as $edges) {
                        $r = Lofrano\CapabilityMatch\solve($items, $destinations, $edges);
                        $t->same($canonical, json_encode($r, JSON_THROW_ON_ERROR), 'byte-identical canonical result');
                    }
                }
            }
        }
        $reorderedKeys = [
            'items' => [['id' => 'b'], ['id' => 'a']],
            'destinations' => [['capacity' => 1, 'id' => 'q'], ['capacity' => 1, 'id' => 'p']],
            'compatibilityEdges' => [
                ['cost' => 0, 'destination' => 'p', 'item' => 'b'],
                ['destination' => 'q', 'cost' => 1, 'item' => 'a'],
                ['cost' => 0, 'item' => 'a', 'destination' => 'p'],
            ],
        ];
        $t->same(json_encode(solveFixture(demoProblem()), JSON_THROW_ON_ERROR), json_encode(solveFixture($reorderedKeys), JSON_THROW_ON_ERROR), 'record key order does not affect canonical output');
    });
    $suite->run('bounds, independent instances and input ownership', static function (TestSuite $t): void {
        $ids = [];
        $edges = [];
        for ($i = 0; $i < 128; ++$i) {
            $ids[] = 'equipment-' . $i;
            $edges[] = ['equipment-' . $i, 'port', 1000000];
        }
        $p = syntheticProblem($ids, [['id' => 'port', 'capacity' => 128]], $edges);
        $before = serialize($p);
        $first = new Lofrano\CapabilityMatch\Solver();
        $second = new Lofrano\CapabilityMatch\Solver();
        $r = $first->solve($p['items'], $p['destinations'], $p['compatibilityEdges']);
        $t->same(128, $r['cardinality'], 'maximum item count');
        $t->same(128000000, $r['cost'], 'maximum total cost');
        $t->same('Verified', verifyCertificate($p, $r)['status'], 'large-bound verification');
        $t->same($r, $second->solve($p['items'], $p['destinations'], $p['compatibilityEdges']), 'independent instance');
        $t->same($before, serialize($p), 'input unchanged');
        $r['assignments'][0]['item'] = 'mutated result';
        $t->same($before, serialize($p), 'result detached from input');
        $d = demoProblem();
        $t->same(solveFixture($d), $first->solve($d['items'], $d['destinations'], $d['compatibilityEdges']), 'reused instance has no state');
    });
};
