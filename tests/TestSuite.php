<?php
declare(strict_types=1);

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
final class TestSuite
{
    public int $checks = 0;
    public int $groups = 0;
    public int $failures = 0;

    public function check(bool $condition, string $message): void
    {
        ++$this->checks;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public function same(mixed $expected, mixed $actual, string $message): void
    {
        $this->check($expected === $actual, $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }

    public function run(string $name, callable $test): void
    {
        ++$this->groups;
        try {
            $test($this);
            echo 'PASS ', $name, "\n";
        } catch (Throwable $failure) {
            ++$this->failures;
            fwrite(STDERR, 'FAIL ' . $name . ': ' . $failure->getMessage() . "\n");
        }
    }
}
