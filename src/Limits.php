<?php
declare(strict_types=1);

namespace Lofrano\CapabilityMatch;

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
final class Limits
{
    public const DEFAULTS = [
        'maxItems' => 128,
        'maxDestinations' => 64,
        'maxEdges' => 4096,
        'maxCost' => 1000000,
        'maxCapacity' => 128,
        'relaxationBudget' => 20000000,
    ];
    public const BUDGET_CAP = 50000000;
    public const POTENTIAL_BOUND = 1000000000;
    public readonly array $values;

    public function __construct(mixed $values = [])
    {
        if (PHP_INT_SIZE !== 8 || !extension_loaded('json') || !extension_loaded('SPL')) {
            throw new Failure('UnsupportedRuntime', '64-bit PHP with JSON and SPL is required.');
        }
        if (!is_array($values) || count($values) > count(self::DEFAULTS)) {
            throw new Failure('InvalidLimits', 'Limits must be a record of recognized integer limits.');
        }
        foreach ($values as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, self::DEFAULTS)) {
                throw new Failure('InvalidLimits', 'Unknown limit name.');
            }
            $ceiling = $key === 'relaxationBudget' ? self::BUDGET_CAP : self::DEFAULTS[$key];
            if (!is_int($value) || $value < 0 || $value > $ceiling) {
                throw new Failure('InvalidLimits', 'Limit is not an integer within its hard ceiling.');
            }
        }
        $this->values = array_replace(self::DEFAULTS, $values);
    }

    public function get(string $key): int
    {
        return $this->values[$key];
    }

    public static function add(int $a, int $b): int
    {
        if (($b > 0 && $a > PHP_INT_MAX - $b) || ($b < 0 && $a < PHP_INT_MIN - $b)) {
            throw new Failure('ArithmeticOverflow', 'Integer addition would overflow.');
        }
        return $a + $b;
    }

    public static function subtract(int $a, int $b): int
    {
        if (($b > 0 && $a < PHP_INT_MIN + $b) || ($b < 0 && $a > PHP_INT_MAX + $b)) {
            throw new Failure('ArithmeticOverflow', 'Integer subtraction would overflow.');
        }
        return $a - $b;
    }

    public static function multiplyNonnegative(int $a, int $b): int
    {
        if ($a < 0 || $b < 0 || ($a !== 0 && $b > intdiv(PHP_INT_MAX, $a))) {
            throw new Failure('ArithmeticOverflow', 'Nonnegative integer multiplication would overflow.');
        }
        return $a * $b;
    }
}
