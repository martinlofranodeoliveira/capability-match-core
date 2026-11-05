<?php
declare(strict_types=1);

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
use function Lofrano\CapabilityMatch\verifyCertificate;

return static function (TestSuite $suite): void {
    $suite->run('strict validation before graph allocation', static function (TestSuite $t): void {
        $base = demoProblem();
        $cases = [];
        foreach (['', 1, true, 1.0, new stdClass(), "\xc3\x28", str_repeat('x', 129)] as $bad) {
            $p = $base;
            $p['items'][0]['id'] = $bad;
            $cases[] = [$p, 'InvalidId'];
        }
        foreach ([-1, false, 1.0, '1', new stdClass()] as $bad) {
            $p = $base;
            $p['destinations'][0]['capacity'] = $bad;
            $cases[] = [$p, 'InvalidCapacity'];
            $p = $base;
            $p['compatibilityEdges'][0]['cost'] = $bad;
            $cases[] = [$p, 'InvalidCost'];
        }
        foreach ([129, PHP_INT_MAX] as $bad) {
            $p = $base;
            $p['destinations'][0]['capacity'] = $bad;
            $cases[] = [$p, 'LimitExceeded'];
        }
        foreach ([1000001, PHP_INT_MAX] as $bad) {
            $p = $base;
            $p['compatibilityEdges'][0]['cost'] = $bad;
            $cases[] = [$p, 'LimitExceeded'];
        }
        $p = $base;
        $p['items'][] = ['id' => 'a'];
        $cases[] = [$p, 'DuplicateId'];
        $p = $base;
        $p['destinations'][] = ['id' => 'p', 'capacity' => 1];
        $cases[] = [$p, 'DuplicateId'];
        $p = $base;
        $p['compatibilityEdges'][] = $p['compatibilityEdges'][0];
        $cases[] = [$p, 'DuplicateEdge'];
        $colonEdgeDuplicate = syntheticProblem(['0:1', '0'], [['id' => '1', 'capacity' => 1], ['id' => '1:1', 'capacity' => 1]], [
            ['0:1', '1', 0],
            ['0:1', '1', 3],
        ]);
        $cases[] = [$colonEdgeDuplicate, 'DuplicateEdge'];
        foreach (['item', 'destination'] as $field) {
            $p = $base;
            $p['compatibilityEdges'][0][$field] = 'unknown';
            $cases[] = [$p, 'UnknownReference'];
        }
        $p = $base;
        $p['items'][0]['extra'] = 0;
        $cases[] = [$p, 'InvalidInput'];
        $p = $base;
        $p['items'][0] = new stdClass();
        $cases[] = [$p, 'InvalidInput'];
        $p = $base;
        unset($p['items'][0]);
        $cases[] = [$p, 'InvalidInput'];
        $p = $base;
        $p['items'] = array_fill(0, 129, ['id' => 'x']);
        $cases[] = [$p, 'LimitExceeded'];
        $p = $base;
        $p['destinations'] = array_fill(0, 65, ['id' => 'x', 'capacity' => 0]);
        $cases[] = [$p, 'LimitExceeded'];
        $p = $base;
        $p['compatibilityEdges'] = array_fill(0, 4097, $base['compatibilityEdges'][0]);
        $cases[] = [$p, 'LimitExceeded'];
        foreach ($cases as [$problem, $status]) {
            $before = serialize($problem);
            $r = solveFixture($problem, ['relaxationBudget' => 0]);
            $t->same($status, $r['status'], 'validation precedes zero budget');
            $t->same(0, $r['inspections'], 'no graph work on invalid input');
            $t->same($before, serialize($problem), 'invalid input unchanged');
            $t->check(!array_key_exists('assignments', $r), 'no partial result');
        }
        foreach ([['maxItems' => 129], ['maxItems' => -1], ['maxItems' => '1'], ['relaxationBudget' => 50000001], ['relaxationBudget' => true], ['other' => 1], new stdClass()] as $limits) {
            $t->same('InvalidLimits', solveFixture($base, $limits)['status'], 'strict limits');
        }
        $t->same('LimitExceeded', solveFixture($base, ['maxItems' => 1])['status'], 'lower caller limit');
        $t->same('InvalidInput', Lofrano\CapabilityMatch\solve(null, [], [])['status'], 'non-array input');
        $t->same('InvalidInput', verifyCertificate([], [])['status'], 'malformed problem');
        $t->same('InvalidInput', verifyCertificate(new stdClass(), [])['status'], 'object problem');
    });
    $suite->run('budget boundaries and no partial optimum', static function (TestSuite $t): void {
        $p = demoProblem();
        $before = serialize($p);
        $r = solveFixture($p);
        foreach ([0, 1, $r['inspections'] - 1] as $budget) {
            $failure = solveFixture($p, ['relaxationBudget' => $budget]);
            $t->same('BudgetExceeded', $failure['status'], 'solve budget exhaustion');
            $t->same($budget, $failure['inspections'], 'preflight charges every inspection');
            $t->same(['status', 'message', 'inspections'], array_keys($failure), 'no assignment or certificate on failure');
        }
        $t->same($r, solveFixture($p, ['relaxationBudget' => $r['inspections']]), 'exact solve budget');
        $v = verifyCertificate($p, $r);
        foreach ([0, 1, $v['inspections'] - 1] as $budget) {
            $failure = verifyCertificate($p, $r, ['relaxationBudget' => $budget]);
            $t->same('BudgetExceeded', $failure['status'], 'verification is also bounded');
            $t->same($budget, $failure['inspections'], 'verification budget preflight');
            $t->check(!isset($failure['cardinality']), 'verification failure has no optimality facts');
        }
        $t->same($v, verifyCertificate($p, $r, ['relaxationBudget' => $v['inspections']]), 'exact verifier budget');
        $t->same($before, serialize($p), 'budget failures preserve input');
    });
    $suite->run('forged assignment, capacity, cut, totals and potentials', static function (TestSuite $t): void {
        $p = demoProblem();
        $r = solveFixture($p);
        $forgeries = [];
        $x = $r; $x['assignments'][0]['destination'] = 'missing'; $forgeries[] = $x;
        $x = $r; $x['assignments'][1]['item'] = 'a'; $forgeries[] = $x;
        $x = $r; $x['assignments'][0]['destination'] = 'p'; $forgeries[] = $x;
        $x = $r; $x['assignments'] = array_reverse($x['assignments']); $forgeries[] = $x;
        $x = $r; $x['assignments'][0]['capacity'] = 99; $forgeries[] = $x;
        $x = $r; $x['unassigned'] = ['a']; $forgeries[] = $x;
        $x = $r; $x['cardinality'] = 1; $forgeries[] = $x;
        $x = $r; $x['cardinality'] = 2.0; $forgeries[] = $x;
        $x = $r; $x['cost'] = 0; $forgeries[] = $x;
        $x = $r; $x['certificate']['cutCapacity'] = 99; $forgeries[] = $x;
        $x = $r; $x['certificate']['sourceSide'] = []; $forgeries[] = $x;
        $x = $r; $x['certificate']['sourceSide'] = range(0, 5); $forgeries[] = $x;
        $x = $r; $x['certificate']['sourceSide'] = [0, 0]; $forgeries[] = $x;
        $x = $r; $x['certificate']['potentials'][1] = PHP_INT_MIN; $forgeries[] = $x;
        $x = $r; $x['certificate']['potentials'][1] = PHP_INT_MAX; $forgeries[] = $x;
        $x = $r; $x['certificate']['potentials'][1] = '0'; $forgeries[] = $x;
        $x = $r; array_pop($x['certificate']['potentials']); $forgeries[] = $x;
        $x = $r; $x['certificate']['potentials'][0] = 1; $forgeries[] = $x;
        $x = $r; $x['certificate']['graph'] = []; $forgeries[] = $x;
        $x = $r; $x['inspections'] = PHP_INT_MAX; $forgeries[] = $x;
        foreach ($forgeries as $index => $forged) {
            $before = serialize($forged);
            $t->same('InvalidCertificate', verifyCertificate($p, $forged)['status'], 'forgery rejected');
            $t->same($before, serialize($forged), 'verifier does not mutate result');
            $rejectedByIndependent = false;
            try {
                independentlyCheckCertificate($p, $forged);
            } catch (RuntimeException) {
                $rejectedByIndependent = true;
            }
            $t->check($rejectedByIndependent, 'independent checker also rejects forgery ' . $index);
        }
        $changed = $p;
        $changed['destinations'][0]['capacity'] = 0;
        $t->same('InvalidCertificate', verifyCertificate($changed, $r)['status'], 'capacity reconstruction');
        $changed = $p;
        $changed['compatibilityEdges'][1]['cost'] = 2;
        $t->same('InvalidCertificate', verifyCertificate($changed, $r)['status'], 'edge cost change invalidates result');
        $t->same('InvalidCertificate', verifyCertificate($p, null)['status'], 'malformed result');
        $q = syntheticProblem(['a', 'b'], $p['destinations'], [['a', 'p', 4], ['a', 'q', 1], ['b', 'p', 2], ['b', 'q', 8]]);
        $bad = solveFixture($q);
        $bad['assignments'] = [['item' => 'a', 'destination' => 'p'], ['item' => 'b', 'destination' => 'q']];
        $bad['cost'] = 12;
        $t->same('InvalidCertificate', verifyCertificate($q, $bad)['status'], 'feasible maximum flow but nonminimal cost');
    });
    $suite->run('unreachable residual components are checked', static function (TestSuite $t): void {
        $p = syntheticProblem(['a'], [['id' => 'p', 'capacity' => 1]], [['a', 'p', 5]]);
        $r = solveFixture($p);
        $t->same([0], $r['certificate']['sourceSide'], 'only source is reachable');
        $t->same('Verified', verifyCertificate($p, $r)['status'], 'global witness accepted');
        independentlyCheckCertificate($p, $r);
        $r['certificate']['potentials'] = [0, 0, 0, 0];
        // p -> a has cost -5 and positive residual capacity; neither endpoint is reachable.
        $t->same('InvalidCertificate', verifyCertificate($p, $r)['status'], 'unreachable negative reduced cost rejected');
        $rejected = false;
        try {
            independentlyCheckCertificate($p, $r);
        } catch (RuntimeException) {
            $rejected = true;
        }
        $t->check($rejected, 'independent checker rejects unreachable violation');
    });
};
