<?php
declare(strict_types=1);

namespace Lofrano\CapabilityMatch;

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
final class Failure extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
