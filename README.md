# Capability Match Core

Development version **0.1.0** — an original PHP library by **Lofrano Tecnologia**, licensed under MIT.

Assign one-slot items to compatible finite-capacity destinations. The objective is lexicographic: **maximize the number assigned, then minimize the sum of nonnegative integer preference costs**. The result contains replayable assignments, unassigned IDs, a maximum-cardinality cut and globally feasible residual potentials. It runs offline, without Composer, a model, an API, persistence or telemetry.

[Português](README.pt-BR.md) · [Precise contract](docs/contract.md) · [Design](docs/design.md) · [Organization homepage](https://lofranotecnologia.com.br) · [Repository](https://github.com/martinlofranodeoliveira/capability-match-core)

## Install and check

Obtain the source archive and extract it. From the project root, using **64-bit PHP 8.4 CLI** with the standard JSON and SPL extensions:

```sh
php -r 'if (PHP_INT_SIZE !== 8 || !extension_loaded("json") || !extension_loaded("SPL")) { exit(1); } echo "Runtime OK\n";'
php tests/run.php
php -d zend.assertions=-1 -d assert.active=0 tests/run.php
php bin/capability-match.php demo --assert
php bin/capability-match.php demo --json
```

No dependency installation, lockfile, generated autoloader or network access is required. Include `autoload.php` in an application. The test runner uses explicit checks, not PHP `assert()`, and exits nonzero on failures.

The checked demo prints exactly:

```text
DEMO_OK assigned=2 cost=1
```

Its equipment IDs `a`, `b` and test-port IDs `p`, `q` are synthetic. Greedily taking `a -> p` blocks `b`. The residual algorithm instead returns `a -> q` and `b -> p`. JSON mode emits the complete `problem`, `result` and `verification` envelope, including certificate arrays and deterministic inspection counts. No screenshot or external service is involved.

## Small API example

```php
<?php
declare(strict_types=1);
require 'autoload.php';

use function Lofrano\CapabilityMatch\solve;
use function Lofrano\CapabilityMatch\verifyCertificate;

$problem = [
    'items' => [['id' => '01'], ['id' => '1']],
    'destinations' => [['id' => 'test-port', 'capacity' => 2]],
    'compatibilityEdges' => [
        ['item' => '01', 'destination' => 'test-port', 'cost' => 3],
        ['item' => '1', 'destination' => 'test-port', 'cost' => 0],
    ],
];
$result = solve($problem['items'], $problem['destinations'], $problem['compatibilityEdges']);
if ($result['status'] !== 'Optimal') {
    throw new RuntimeException($result['status']);
}
$checked = verifyCertificate($problem, $result);
if ($checked['status'] !== 'Verified') {
    throw new RuntimeException($checked['status']);
}
echo json_encode(['assigned' => $result['cardinality'], 'cost' => $result['cost']], JSON_THROW_ON_ERROR), "\n";
```

Output:

```json
{"assigned":2,"cost":3}
```

The functions return tagged arrays, not booleans. Check `status` before reading success fields. `new Lofrano\CapabilityMatch\Solver()` and `new Lofrano\CapabilityMatch\Verifier()` expose the same operations as `solve()` and `verify()`. They keep no per-call state. There are no input references or shared mutable graphs.

## Bounded contract at a glance

- Inputs are lists of exact records, not ID-keyed PHP arrays. IDs are nonempty valid UTF-8 strings, at most 128 bytes. `01` and `1` remain distinct. No Unicode normalization is performed; control characters and NUL are allowed as opaque bytes in valid UTF-8.
- Default structural limits, also hard ceilings: 128 items, 64 destinations, 4096 compatibility edges, cost 1,000,000 per edge, capacity 128 per destination. Callers may lower these limits.
- Native integers only: booleans, floats and numeric strings are rejected for costs, capacities and limits. Duplicate IDs within a namespace, duplicate pairs and unknown references are rejected before graph allocation.
- `relaxationBudget` defaults to 20,000,000 inspections, with a hard cap of 50,000,000. Every algorithmic arc visit is charged, even when capacity is zero or no relaxation succeeds. Construction and extraction scans are charged too; bounded schema validation is separate. A public verification call receives its own budget.
- `BudgetExceeded` contains only status, message and inspections used: **no partial assignment, certificate or infeasibility claim**. Zero budget on the demo fails this way. A negative capacity instead returns `InvalidCapacity` during validation.
- IDs and compatibility arcs are sorted by byte order. The priority queue has explicit distance/vertex/insertion tie-breaking. Permuting input collections produces byte-identical `json_encode()` output with the same encoding flags and limits.
- A certificate is mathematical evidence for this input, not a signature or an input hash. Verify against the actual problem; never trust supplied totals or any externally supplied graph.

For example, a bounded call is:

```php
$result = solve($items, $destinations, $edges, ['relaxationBudget' => 100000]);
```

See [the contract](docs/contract.md) for every field, status and budget rule.

## Why this design?

Successive shortest augmenting paths with reverse arcs can undo an earlier choice; greedy matching cannot. Integer potentials permit Dijkstra searches. A final bounded super-source Bellman-Ford computes a witness across **all** residual components, not just those reachable from the source. The independent verifier rebuilds the residual network directly from input and assignment and checks both directions of every original arc.

Tests include all 729 two-item/two-destination fixtures formed by absent/zero/one-cost edges and capacities zero through two, plus 200 seeded fixtures with up to six items and four destinations. A separate exhaustive recursion enumerates all feasible choices, and a second test-only certificate checker checks the residual mathematics. Adversarial tests forge capacities, assignments, cuts, totals and potentials, including an unreachable negative reduced-cost arc.

## Limitations

This is a development library, not a general operations-research or scheduling package. Items require exactly one slot; destinations have independent capacities. There are no time windows, multi-slot demands, fairness guarantees or operational-safety claims. Preference costs are not currency. Payments, financial decisions, clinical decisions and staffing decisions are outside scope. Runtime work is bounded but no latency or benchmark claim is made. The verifier shares input validation with the solver, but not graph construction or optimization code; the independent tests reduce, rather than eliminate, implementation risk.

General contact: [official homepage](https://lofranotecnologia.com.br). Security reports: [GitHub private vulnerability reporting](https://github.com/martinlofranodeoliveira/capability-match-core/security/advisories/new). See [SECURITY.md](SECURITY.md).
