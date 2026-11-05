# Security

## Reporting

Report suspected vulnerabilities privately using [GitHub private vulnerability reporting](https://github.com/martinlofranodeoliveira/capability-match-core/security/advisories/new). Please do not put exploit details, secrets or personal data in a public issue. For general contact, use the [official Lofrano Tecnologia homepage](https://lofranotecnologia.com.br).

Useful reports include the affected source version, a minimal synthetic problem/result, PHP integer width and version, configured limits, expected behavior and observed status. Do not send real assignment subjects or confidential compatibility data. No response-time commitment is made.

## Scope and defensive use

The current documented development version is 0.1.0. This is not a production-readiness or operational-safety assurance. The package handles untrusted problem and result arrays within fixed structural ceilings, rejects malformed UTF-8 and noninteger arithmetic inputs, and uses explicit bounded arc-inspection budgets. It has no network, persistence, worker, telemetry or credential feature.

Certificates prove properties only for the supplied bounded mathematical problem. They do not authenticate the problem, its provenance or its suitability. Verify against trusted intended input, check `status` explicitly, and never treat `BudgetExceeded` as infeasibility. Do not trust result counts, costs, capacities or a graph supplied by another party; the provided verifier reconstructs the residual graph itself.

IDs may contain NUL and control characters. Escape IDs for logs, terminals, HTML and JSON consumers. Never interpret them as file paths or commands. The CLI accepts only its built-in synthetic demo, not arbitrary jobs or files.

Host applications must bound request frequency, parsing, serialized input sizes and process resources before allocating caller arrays. A per-call arc budget is not a wall-clock deadline, global rate limit or protection against process-level memory exhaustion. Keep the supported 64-bit PHP runtime updated. Financial, clinical and staffing decisions, time-window scheduling and fairness guarantees are outside the contract.
