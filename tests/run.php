<?php
declare(strict_types=1);

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
if (PHP_INT_SIZE !== 8 || !extension_loaded('json') || !extension_loaded('SPL')) {
    fwrite(STDERR, "TEST_FAILED: 64-bit PHP, JSON and SPL are required.\n");
    exit(1);
}
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/TestSuite.php';
require_once __DIR__ . '/Oracle.php';
require_once __DIR__ . '/IndependentCertificate.php';
require_once __DIR__ . '/fixtures.php';

$suite = new TestSuite();
try {
    foreach (['unit.php', 'adversarial.php', 'exhaustive.php'] as $file) {
        $register = require __DIR__ . '/' . $file;
        $register($suite);
    }
} catch (Throwable $failure) {
    fwrite(STDERR, 'TEST_FAILED: runner error: ' . $failure->getMessage() . "\n");
    exit(1);
}
if ($suite->failures !== 0) {
    fwrite(STDERR, 'TEST_FAILED groups=' . $suite->groups . ' failures=' . $suite->failures . "\n");
    exit(1);
}
echo 'TEST_OK groups=', $suite->groups, ' checks=', $suite->checks, "\n";
