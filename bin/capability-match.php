<?php
declare(strict_types=1);

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
require_once __DIR__ . '/../autoload.php';

use function Lofrano\CapabilityMatch\solve;
use function Lofrano\CapabilityMatch\verifyCertificate;

if (($argv[1] ?? '') !== 'demo' || count($argv) > 3 || (isset($argv[2]) && !in_array($argv[2], ['--assert', '--json'], true))) {
    fwrite(STDERR, "Usage: php bin/capability-match.php demo [--assert|--json]\n");
    exit(2);
}

// Synthetic equipment a/b and compatible test ports p/q. No external inputs or I/O.
$problem = [
    'items' => [['id' => 'a'], ['id' => 'b']],
    'destinations' => [['id' => 'p', 'capacity' => 1], ['id' => 'q', 'capacity' => 1]],
    'compatibilityEdges' => [
        ['item' => 'a', 'destination' => 'p', 'cost' => 0],
        ['item' => 'a', 'destination' => 'q', 'cost' => 1],
        ['item' => 'b', 'destination' => 'p', 'cost' => 0],
    ],
];
try {
    $result = solve($problem['items'], $problem['destinations'], $problem['compatibilityEdges']);
    $verification = verifyCertificate($problem, $result);
    $expected = [['item' => 'a', 'destination' => 'q'], ['item' => 'b', 'destination' => 'p']];
    if ($result['status'] !== 'Optimal' || $verification['status'] !== 'Verified'
        || $result['assignments'] !== $expected || $result['cardinality'] !== 2 || $result['cost'] !== 1) {
        fwrite(STDERR, "DEMO_FAILED: solver, certificate or expected assignment check failed.\n");
        exit(1);
    }
    if (($argv[2] ?? '') === '--json') {
        echo json_encode(['problem' => $problem, 'result' => $result, 'verification' => $verification], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
    } else {
        echo "DEMO_OK assigned=2 cost=1\n";
    }
} catch (Throwable $failure) {
    fwrite(STDERR, 'DEMO_FAILED: ' . $failure->getMessage() . "\n");
    exit(1);
}
