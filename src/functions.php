<?php
declare(strict_types=1);

namespace Lofrano\CapabilityMatch;

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
const VERSION = '0.1.0';

function solve(mixed $items, mixed $destinations, mixed $compatibilityEdges, mixed $limits = []): array
{
    return (new Solver())->solve($items, $destinations, $compatibilityEdges, $limits);
}

function verifyCertificate(mixed $problem, mixed $result, mixed $limits = []): array
{
    return (new Verifier())->verify($problem, $result, $limits);
}
