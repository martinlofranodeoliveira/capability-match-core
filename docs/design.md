# Design and reasoning

Capability Match Core, development 0.1.0. Original implementation by Lofrano Tecnologia, MIT licensed. The project contains 30 purposeful source, test, automation and documentation files, with no runtime dependencies outside PHP's standard extensions.

## Deliberately small model

A unit-demand bipartite network admits a compact explanation and certificate. Source-to-item arcs limit each item to one destination. Compatibility arcs encode the only permitted choices. Destination-to-sink arcs enforce capacities. Maximizing integral source-to-sink flow maximizes fulfilled items; minimizing cost at that flow value minimizes summed preference cost. All capacities and costs are integers, so augmentation preserves integral assignments.

The model intentionally has no time, budgets measured in money, interpersonal fairness, safety constraints, clinical decisions or staffing semantics. It does not infer compatibility: the caller supplies it. A capacity or edge missing from caller data is not repaired or guessed.

## Successive shortest augmenting paths

The empty flow starts with nonnegative residual costs, so zero potentials work for the first Dijkstra. Directed arcs are stored in pairs; `arc ^ 1` identifies a reverse arc. Sending a unit along an augmenting path increases reverse residual capacity, enabling later searches to undo earlier selections. The demo depends on this: the second augmentation can reroute `a` away from `p` so `b` can use it.

Each shortest-path search uses reduced cost `c + p[u] - p[v]`. It explores all source-reachable vertices rather than stopping at the sink. Only strict improvements replace a predecessor. Reachable potentials are increased by their shortest reduced distances. Positive residual arcs leaving the reachable set cannot exist by its definition; augmentation creates reverse arcs only among vertices on that reachable path. Consequently a vertex previously unreachable cannot become reachable in the next iteration. The reachable-only updates suffice for subsequent source searches, but do **not** imply global dual feasibility.

The source-to-item capacity bounds each augmentation to one unit, and there are at most 128 augmentations. A dense hand-written shortest-path matrix would avoid a queue but add unnecessary vertex scans. SPL is available in the target runtime; its undefined order for equal priorities is addressed explicitly with distance, integer node ID and insertion serial comparisons. No comparison subtracts potentially large values.

## Final certificate construction

After no augmenting path remains, a separate Bellman-Ford pass treats every vertex as connected to a zero-cost super-source by initializing every distance to zero. It scans all residual arcs in fixed order, ignoring only zero capacities after charging their inspection. At most `V` passes are allowed; improvement in pass `V` means a negative cycle and explicit `InternalError`, never a false optimum. Subtracting the source distance gives a deterministic source-zero reference.

The triangle inequalities for these distances yield globally feasible residual potentials. Summing reduced costs around any residual cycle cancels the potentials, so all cycles have nonnegative cost. The difference between two feasible flows of the same value decomposes into residual cycles; no cheaper fixed-value assignment exists.

A bounded reachability scan supplies the source side of a cut. No positive residual arc can leave this set, and summing original crossing capacities equals the assigned flow. This separately certifies maximum cardinality. Cost feasibility is not substituted for cardinality evidence, or vice versa.

The final certificate stores only two vertex-sized lists and one cut capacity, not a growing trace. A caller can replay the assignment simply by looking up each exact item/destination pair in its own input.

## Independent production verifier

The verifier does not instantiate Graph, Solver or StableQueue. It normalizes and validates the problem, indexes known compatibility costs, validates assignments and recomputes all objective facts. It reconstructs original and forward/reverse residual arcs itself, deriving each flow from the selected pairs. It checks original cut capacity and every positive residual reduced cost, including arcs in components inaccessible from the source.

Shared code is confined to input normalization, limits, checked arithmetic and the budget counter. That shared validation is a deliberate small surface; a bug there could affect both paths, so tests include a third, independent checker using strict list searches and independently reconstructed residual mathematics. The production verifier is bounded linear scanning after normalization, not another optimization run.

Certificates are not authenticated messages and contain no input digest. Consumers must retain the intended problem and verify against it. A different input can sometimes legitimately have the same optimal assignment and witness; the contract does not promise every possible change invalidates every certificate.

## Identity, reproducibility and integer safety

PHP converts some numeric-string array keys to integers. Inputs therefore use record lists, and all ID lookup keys are prefixed `i:` or `d:`. Edge lookup keys are `e:` followed by two canonical integer indices with a separator, not raw IDs concatenated around a delimiter. The namespaces and numeric indices make these keys unambiguous even for IDs containing colons, NUL or combining marks. Integer-indexed vertex arrays are intentionally positional.

`strcmp` provides byte ordering rather than locale-sensitive or numeric sorting. IDs are never normalized. Invalid UTF-8 is rejected using a UTF-8 validity check before graph construction. The explicit autoloader maps a fixed set of class names to files and does not turn arbitrary class strings or IDs into paths.

Hard bounds cap the network at 194 vertices and 8576 directed arcs. The total objective is at most 128 million and normalized certificate potentials fit a much smaller mathematical bound than the accepted one-billion magnitude. Checked add/subtract/multiply helpers prevent PHP integer overflow from silently becoming float. Caller potentials are checked before reduced-cost arithmetic; counters and capacities have structural ceilings before increment, decrement or negation. No sentinel near the integer limit is added to a distance: unreachable distances are `null`.

## Resource model

Input validation is complete and bounded before allocating a residual graph; malformed large collections fail by count before iteration. Record field counts are checked before field lookups. Budgeting starts with graph work, not schema validation. A budget tick precedes every charged edge visit, even when no relaxation succeeds. Final Bellman-Ford and certificate checks are not exempt. Exact charging is specified in the contract and tested with just-enough and one-short budgets.

With `A <= 8576`, `V <= 194` and `I <= 128`, a conservative solver count is bounded by:

```text
original construction + (I + 1) * A + I * (V - 1)
+ V * A + cut reachability + original cut scan + edge extraction
```

This is below three million inspections. Twenty million is a deliberately generous default within the fixed problem size, not an empirical benchmark. Fifty million is the maximum caller allowance. Time for heap operations, validation and PHP allocation is not measured by this counter, but remains structurally bounded. Queue entries follow charged successful relaxations and are discarded between searches. The library cannot protect a host from inputs already allocated by the host, process-level memory exhaustion or repeated external requests; callers must set their own request and process limits.

Solver failures discard the local graph and return no partial assignment. Verification failures return no optimality facts. No state is retained, so callers may use independent instances without shared graphs. There is no worker, cache or persistence layer.

## Tests as separate evidence

`tests/Oracle.php` recursively enumerates the unassigned choice and every compatible capacity-feasible destination for each item. It has no graph, heap, potentials or optimization-library helpers. With six items and four destinations it explores at most `5^6 = 15625` leaf choices, before capacity restrictions. All 81 absent/zero/one edge patterns on a 2x2 graph are crossed with all nine capacity vectors in `0..2`, giving exactly 729 problems. Another 200 fixtures use a fixed integer PRNG seed and up to six items/four destinations. Every result is compared with the exhaustive optimum and checked by both certificate verifiers.

Other tests cover residual reassignment, no edges, zero capacity, empty networks, maximum item/cost bounds, exact opaque IDs, every collection permutation on tied 2x2 inputs, strict type rejection, duplicate and unknown references, result detachment, stateless instances and budget boundaries. Forgeries target assignment membership, capacities, cuts, objective summaries and potential range/feasibility. A dedicated example tampers a negative-cost reverse arc in a source-unreachable residual component. Checks throw exceptions directly, so disabling PHP assertions does not remove tests. CI runs both assertion configurations and the checked demo, with no publishing or deployment step.

## References and non-goals

Concept references, not copied implementation code:

- PHP integer behavior and overflow: https://www.php.net/manual/en/language.types.integer.php
- PHP array key behavior: https://www.php.net/manual/en/language.types.array.php
- SPL priority queue ordering: https://www.php.net/manual/en/class.splpriorityqueue.php
- Minimum-cost flow overview: https://developers.google.com/optimization/flow/mincostflow
- Reduced-cost mathematical context: https://github.com/google/or-tools/blob/stable/ortools/graph/min_cost_flow.h

No OR-Tools code is included or called. This library makes no broad standards-compliance, deployment, benchmark or production-readiness claim. The purpose is a narrowly specified, inspectable development library with bounded computation and independently checkable results.
