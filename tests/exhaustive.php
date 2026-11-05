<?php
declare(strict_types=1);

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
use function Lofrano\CapabilityMatch\verifyCertificate;

return static function (TestSuite $suite): void {
    $compare = static function (TestSuite $t, array $p, string $label): void {
        $oracle = exhaustiveOracle($p);
        $r = solveFixture($p);
        $t->same('Optimal', $r['status'], $label . ' status');
        $t->same($oracle['cardinality'], $r['cardinality'], $label . ' exhaustive cardinality');
        $t->same($oracle['cost'], $r['cost'], $label . ' exhaustive cost');
        $t->same('Verified', verifyCertificate($p, $r)['status'], $label . ' verifier');
        independentlyCheckCertificate($p, $r);
        $t->check(true, $label . ' independent witness');
    };
    $suite->run('all 729 two-by-two problems', static function (TestSuite $t) use ($compare): void {
        $cases = 0;
        for ($pattern = 0; $pattern < 81; ++$pattern) {
            $digits = $pattern;
            $edges = [];
            foreach ([['a', 'p'], ['a', 'q'], ['b', 'p'], ['b', 'q']] as [$item, $destination]) {
                $state = $digits % 3;
                $digits = intdiv($digits, 3);
                if ($state !== 0) {
                    $edges[] = [$item, $destination, $state - 1];
                }
            }
            for ($p = 0; $p <= 2; ++$p) {
                for ($q = 0; $q <= 2; ++$q) {
                    $problem = syntheticProblem(['a', 'b'], [['id' => 'p', 'capacity' => $p], ['id' => 'q', 'capacity' => $q]], $edges);
                    $compare($t, $problem, 'tiny ' . $cases);
                    ++$cases;
                }
            }
        }
        $t->same(729, $cases, '81 graphs times 9 capacity vectors');
    });
    $suite->run('200 seeded complete-enumeration fixtures', static function (TestSuite $t) use ($compare): void {
        $state = 20260119;
        $next = static function (int $bound) use (&$state): int {
            // This product is below 2^47 on the required 64-bit runtime.
            $state = ($state * 48271) % 2147483647;
            return $state % $bound;
        };
        $cases = 0;
        for ($case = 0; $case < 200; ++$case) {
            $itemCount = $next(7);
            $destinationCount = $next(5);
            $ids = [];
            $destinations = [];
            $edges = [];
            for ($i = 0; $i < $itemCount; ++$i) {
                $ids[] = 'item-' . $i;
            }
            for ($d = 0; $d < $destinationCount; ++$d) {
                $destinations[] = ['id' => 'port-' . $d, 'capacity' => $next(4)];
            }
            foreach ($ids as $id) {
                foreach ($destinations as $destination) {
                    if ($next(4) !== 0) {
                        $edges[] = [$id, $destination['id'], $next(10)];
                    }
                }
            }
            $p = syntheticProblem($ids, $destinations, $edges);
            $compare($t, $p, 'seeded ' . $case);
            if ($case < 20) {
                $permuted = [
                    'items' => array_reverse($p['items']),
                    'destinations' => array_reverse($p['destinations']),
                    'compatibilityEdges' => array_reverse($p['compatibilityEdges']),
                ];
                $t->same(json_encode(solveFixture($p), JSON_THROW_ON_ERROR), json_encode(solveFixture($permuted), JSON_THROW_ON_ERROR), 'seeded canonical serialization');
            }
            ++$cases;
        }
        $t->same(200, $cases, 'exact seeded fixture count');
    });
};
