# Development 0.1.0 contract

This document defines the supported bounded assignment problem, not general scheduling or a broad optimization standard. Author: Lofrano Tecnologia. The public entry point is `autoload.php`; namespace: `Lofrano\CapabilityMatch`.

## Public operations

```php
solve($items, $destinations, $compatibilityEdges, $limits = []): array
verifyCertificate($problem, $result, $limits = []): array
```

The equivalent instance operations are `Solver::solve(...)` and `Verifier::verify(...)`. All public arguments are accepted as `mixed` so malformed shapes can receive tagged failures rather than incidental PHP parameter type errors. No arguments are passed by reference. No result contains references to caller data. Calls use local graphs, queues and counters; instances retain no mutable per-call state. The library performs no filesystem access, network access, persistence, clock reads, background work or output after the explicit autoloader loads source files.

Requires 64-bit PHP; the supported test runtime is PHP 8.4 CLI with JSON and SPL. Runtime availability is checked before validation or graph construction.

## Input grammar

All collections are PHP lists (`array_is_list`), including empty lists. All records have exactly the specified fields; record key order is irrelevant. Extra fields are rejected.

- `items`: list of `['id' => string]`.
- `destinations`: list of `['id' => string, 'capacity' => int]`.
- `compatibilityEdges`: list of `['item' => string, 'destination' => string, 'cost' => int]`.
- The verifier's `problem`: `['items' => ..., 'destinations' => ..., 'compatibilityEdges' => ...]`, with no embedded limits.

IDs are nonempty well-formed UTF-8, at most 128 **bytes**. Byte identity is exact: no normalization, trimming, case folding or numeric comparison. NUL and control characters are allowed. JSON consumers must decode and escape these correctly; do not use IDs as paths, terminal escape sequences or unescaped markup. Item and destination namespaces are separate: the same ID may occur once in each. Repetition within either namespace is invalid. Duplicate compatibility pairs are invalid, even with different costs. Every compatibility reference must exist.

Capacities and costs must satisfy `is_int`, be nonnegative and fit configured limits. Numeric strings, floats and booleans are not integers for this API. Every item consumes one slot; a destination accepts at most its capacity. Empty sets, no compatibility edges and zero capacities are valid. Unassigned items are a legitimate optimal result, not an error.

## Limits and arithmetic

The optional limits record accepts only these names:

| Name | Default | Hard ceiling |
| --- | ---: | ---: |
| `maxItems` | 128 | 128 |
| `maxDestinations` | 64 | 64 |
| `maxEdges` | 4096 | 4096 |
| `maxCost` | 1000000 | 1000000 |
| `maxCapacity` | 128 | 128 |
| `relaxationBudget` | 20000000 | 50000000 |

Values are native integers from zero through their ceilings. Structural limits can only be lowered. Unknown names fail with `InvalidLimits`. A valid nonnegative input exceeding a configured limit fails with `LimitExceeded`.

Before residual allocation, all IDs, record shapes, references, duplicate checks, capacities, costs and structural limits are validated. Numeric preflight checks multiply item count by maximum cost and simple-path length by maximum cost with checked multiplication. At the hard ceilings there are at most 194 vertices, 4288 original arcs and 8576 directed residual arcs; at most 128 units of flow; total cost at most 128000000. A simple residual path has at most 193 arcs, with cost magnitude at most 193000000. Normalized super-source shortest-path potentials therefore fit within the accepted magnitude 1000000000. User-supplied potentials are range-checked before any arithmetic. Potential/cost/distance additions and subtractions are checked before PHP can promote an overflow to float. Small counters, node offsets, capacities and negated costs have prevalidated fixed bounds. No floating-point objective arithmetic is used.

## Objective and determinism

First maximize assigned cardinality. Among those assignments minimize summed preference cost. Costs are ordinal application preferences, not currency. There is no fairness or time-scheduling objective.

IDs are sorted with `strcmp`, independently in each namespace. Compatibility arcs are sorted by item bytes then destination bytes. Node numbering is:

1. Source: `0`.
2. Sorted items: `1` through `I`.
3. Sorted destinations: `I + 1` through `I + D`.
4. Sink: `I + D + 1`.

Original arcs are inserted source-to-item, compatibility, destination-to-sink, in those orders. Each original is immediately followed by its reverse arc. Dijkstra priority is ascending `(distance, vertex, insertionSerial)`. Equal-distance predecessor choices are not replaced. Bellman-Ford scans the fixed directed arc order. This defines a deterministic algorithmic tie-break, **not** a promise of lexicographically smallest optimal matching. With the same problem, limits, runtime contract and JSON flags, reordering input lists or record keys yields identical serialized results. Changing limits that do not affect work or validation does not change a successful result.

## Success from solve

Record field order is fixed as follows:

```text
status: "Optimal"
version: "0.1.0"
assignments: list of {item: string, destination: string}, sorted by item bytes
unassigned: list of item IDs sorted by bytes
cardinality: integer
cost: integer
certificate:
    sourceSide: strictly increasing list of vertex integers
    cutCapacity: integer
    potentials: list of V integers, indexed by vertex, potentials[0] = 0
inspections: integer
```

Every item occurs exactly once in either assignments or unassigned. The certificate is bounded by vertex count, not by algorithm history. There is no solver graph, trace, supplied capacity or per-assignment cost in the result.

## Independent verification obligations

The verifier shares only input normalization/validation and bounded arithmetic with the solver, not graph helpers or searches. It accepts only exact version 0.1.0 `Optimal` result records. It validates bounded lists, exact assignment fields, sorted unique assigned items, exact membership, compatibility and capacities. It recomputes cost, cardinality and unassigned IDs and compares them with the supplied values using strict equality.

It then independently reconstructs the full network from the input and chosen assignment:

- Source to each item: capacity 1, cost 0, flow 0 or 1.
- Every input compatibility edge: capacity 1, input cost, flow 0 or 1.
- Each destination to sink: input capacity, cost 0, flow equal to its assignment usage.

For **each** original `(u, v, capacity, cost, flow)`, it constructs forward residual `(u, v, capacity - flow, cost)` and reverse residual `(v, u, flow, -cost)`. Flow feasibility and conservation follow from checked one-item membership and destination usage; no supplied flow or graph is accepted.

The cut must contain source, exclude sink, have valid sorted vertex IDs, have no positive-capacity residual arc leaving it, and have **original** crossing capacity equal to reconstructed assignment cardinality and `cutCapacity`. This certifies maximum cardinality.

Every potential must be a native integer in `[-1000000000, 1000000000]`, with source reference zero. For **every** positive-capacity residual arc, regardless of source reachability, the verifier requires:

```text
cost(u,v) + potential[u] - potential[v] >= 0
```

This rules out cost-improving residual cycles at the fixed maximum flow value. In particular, zero potentials on a source-unreachable assigned positive-cost compatibility edge fail on its negative-cost reverse arc. Source-reachable Dijkstra potentials alone are not sufficient evidence.

Success is `['status' => 'Verified', 'cardinality' => recomputedInt, 'cost' => recomputedInt, 'inspections' => verifierWork]`. `Verified` proves only the documented input problem, not input authenticity, safety, fairness or any external facts. There is no cryptographic binding: a changed problem is accepted only if all facts and witnesses still verify for that changed problem. Solver `inspections` is range-checked metadata, not proof of past execution; it is never used to budget or trust verification work. Verification always maintains its own counter.

## Work budget

Each public solve or verify call creates a fresh counter. Verification does not consume a solver call's unused allowance. Validation is a separate bounded pass over the hard-limited input records; it finishes before graph allocation or budgeted graph work, so invalid capacity is reported even at budget zero.

After validation, every algorithmic edge/arc inspection is charged **before** inspection, whether or not it has capacity or improves anything:

- Solver: one charge per original arc created; every directed adjacency arc visited by each Dijkstra; each augmentation path arc; every directed arc in every final Bellman-Ford pass; every adjacency arc in cut reachability; each original arc checked for cut capacity; every compatibility arc inspected for extraction.
- Verifier: every compatibility edge indexed for membership; every assignment edge checked; every original arc reconstructed and included in cut arithmetic; every reconstructed directed residual arc checked, including zero-capacity arcs.

Vertex-only operations, schema checks and heap bookkeeping do not consume arc inspections. They remain bounded by structural ceilings and charged work: a Dijkstra heap insertion follows a charged successful relaxation, and predecessor paths are at most `V - 1` arcs. There is no unbounded search in verification. No early sink exit leaves Dijkstra distance updates incomplete.

Default 20 million is above the conservative ceiling of approximately 3 million solver inspections for this graph model: at most 129 full Dijkstra scans, 128 simple augmentation paths, 194 full Bellman-Ford passes, and bounded construction/cut/extraction scans. The hard 50 million ceiling prevents callers from requesting an unbounded work allowance; it is not a wall-time limit. Small user budgets intentionally permit early failure. Exhaustion leaves the counter equal to the allowed budget and returns no partial assignment, optimum or infeasibility assertion. An entirely empty network needs no inspections and may succeed at budget zero.

## Failure records

Every expected failure has exactly `status`, `message`, `inspections`, in that order. Messages are explanatory; program against the status. There is no success payload on failure.

| Status | Meaning |
| --- | --- |
| `UnsupportedRuntime` | Required integer width or extensions unavailable |
| `InvalidLimits` | Malformed, unknown, noninteger or out-of-range limit |
| `InvalidInput` | Malformed problem record or collection |
| `InvalidId` | Invalid input ID type, size or UTF-8 |
| `DuplicateId` | Repeated ID within one namespace |
| `InvalidCapacity` | Negative or noninteger capacity |
| `InvalidCost` | Negative or noninteger preference cost |
| `LimitExceeded` | Valid shape/value exceeds a configured structural bound |
| `DuplicateEdge` | Repeated item-destination pair |
| `UnknownReference` | Compatibility refers to an absent item or destination |
| `InvalidCertificate` | Result schema, assignment, summaries, cut or potentials fail verification |
| `BudgetExceeded` | Next charged inspection would exceed allowance |
| `ArithmeticOverflow` | A checked integer operation cannot be represented |
| `InternalError` | Solver invariant, negative residual cycle or certificate-construction invariant fails |

Malformed problem inputs to the verifier use the same input errors as solve; malformed results use `InvalidCertificate`. Runtime and limit validation come first, followed by complete input validation, then graph/certificate work. When multiple input errors coexist, the first encountered validation error is returned; error precedence among multiple invalid records is not canonical. Unexpected programming errors and PHP resource exhaustion are not converted to mathematical claims.

## CLI

Only the built-in synthetic demo is supported:

```sh
php bin/capability-match.php demo
php bin/capability-match.php demo --assert
php bin/capability-match.php demo --json
```

All modes solve and verify, then explicitly check `a -> q`, `b -> p`, cardinality 2 and cost 1. Default and `--assert` print exactly `DEMO_OK assigned=2 cost=1` followed by LF. `--json` prints a pretty JSON envelope containing the complete problem, result and verifier response, followed by LF. Success exits 0, demo failure exits 1 with a stderr diagnostic, and unsupported arguments exit 2 with usage on stderr. No arbitrary files, jobs or scripts can be supplied to this CLI.
