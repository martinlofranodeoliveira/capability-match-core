# Contributing

Capability Match Core is development version 0.1.0, maintained under the Lofrano Tecnologia name. General contact is available through the [official homepage](https://lofranotecnologia.com.br). Use repository issues for non-sensitive bugs and design discussion. For vulnerabilities, use [private vulnerability reporting](https://github.com/martinlofranodeoliveira/capability-match-core/security/advisories/new), not a public issue.

## Local checks

Use 64-bit PHP 8.4 CLI with JSON and SPL; no Composer dependencies are needed. From the source root:

```sh
php tests/run.php
php -d zend.assertions=-1 -d assert.active=0 tests/run.php
php bin/capability-match.php demo --assert
php bin/capability-match.php demo --json
```

Do not substitute PHP `assert()` for explicit test checks. A test must fail with a nonzero runner exit even when assertions are compiled out. Keep fixtures synthetic and deterministic. Do not add personal data, secrets, telemetry, network tests, arbitrary script execution or client code.

## Changes worth reviewing carefully

- State the contract change and update both READMEs, contract, design and unreleased changelog as appropriate.
- Preserve list-record inputs, byte-order identity and prefixed lookup keys. Include IDs such as `01`, `1`, combining Unicode and NUL in identity tests.
- Charge every new algorithmic arc inspection before access, including failed relaxations and zero capacities. Add just-enough and one-short budget tests.
- Keep arithmetic checked before PHP can overflow into float. Do not introduce saturation, loose numeric equality or numeric-string coercion.
- Do not make the verifier depend on the solver graph or shortest-path helpers. Reconstruct both residual directions from input and assignment.
- Maintain the independent complete enumeration oracle. A solver-versus-itself test is not evidence of optimality.
- Test disconnected residual components, not only source-reachable nodes. Any new certificate format needs negative forgery tests as well as success cases.
- Avoid new dependencies unless necessary for the bounded contract. Explain memory/work limits and deterministic tie-breaking.

Original contributions must be compatible with the MIT license. Do not copy private client code or solver implementation code with incompatible licensing. Attribution must not invent users, clients, metrics or deployment history. CI is for checks only, with read-only permissions and bounded job duration; do not add publishing, release or remote-deployment workflows.
