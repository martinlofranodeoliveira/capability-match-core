<?php
declare(strict_types=1);

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
spl_autoload_register(static function (string $class): void {
    static $files = [
        'Lofrano\\CapabilityMatch\\Failure' => 'Failure.php',
        'Lofrano\\CapabilityMatch\\Limits' => 'Limits.php',
        'Lofrano\\CapabilityMatch\\Budget' => 'Budget.php',
        'Lofrano\\CapabilityMatch\\Problem' => 'Problem.php',
        'Lofrano\\CapabilityMatch\\Graph' => 'Graph.php',
        'Lofrano\\CapabilityMatch\\StableQueue' => 'StableQueue.php',
        'Lofrano\\CapabilityMatch\\Solver' => 'Solver.php',
        'Lofrano\\CapabilityMatch\\Verifier' => 'Verifier.php',
    ];
    if (isset($files[$class])) {
        require_once __DIR__ . '/src/' . $files[$class];
    }
});
require_once __DIR__ . '/src/functions.php';
