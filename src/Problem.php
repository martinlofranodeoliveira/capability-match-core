<?php
declare(strict_types=1);

namespace Lofrano\CapabilityMatch;

// Copyright 2026 Lofrano Tecnologia. MIT licensed.
final class Problem
{
    public readonly array $items;
    public readonly array $destinations;
    public readonly array $edges;
    public readonly array $itemIndex;
    public readonly array $destinationIndex;
    public readonly int $vertices;

    public function __construct(mixed $items, mixed $destinations, mixed $edges, Limits $limits)
    {
        self::listOf($items, $limits->get('maxItems'));
        self::listOf($destinations, $limits->get('maxDestinations'));
        self::listOf($edges, $limits->get('maxEdges'));
        $ids = [];
        $seen = [];
        foreach ($items as $record) {
            self::record($record, ['id']);
            $id = self::id($record['id']);
            if (isset($seen['i:' . $id])) {
                throw new Failure('DuplicateId', 'Duplicate item ID.');
            }
            $seen['i:' . $id] = true;
            $ids[] = $id;
        }
        usort($ids, static fn (string $a, string $b): int => strcmp($a, $b));
        $itemIndex = [];
        foreach ($ids as $index => $id) {
            $itemIndex['i:' . $id] = $index;
        }
        $ports = [];
        $seen = [];
        foreach ($destinations as $record) {
            self::record($record, ['id', 'capacity']);
            $id = self::id($record['id']);
            $capacity = $record['capacity'];
            if (!is_int($capacity) || $capacity < 0) {
                throw new Failure('InvalidCapacity', 'Capacity must be a nonnegative native integer.');
            }
            if ($capacity > $limits->get('maxCapacity')) {
                throw new Failure('LimitExceeded', 'Destination capacity exceeds the limit.');
            }
            if (isset($seen['d:' . $id])) {
                throw new Failure('DuplicateId', 'Duplicate destination ID.');
            }
            $seen['d:' . $id] = true;
            $ports[] = ['id' => $id, 'capacity' => $capacity];
        }
        usort($ports, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
        $destinationIndex = [];
        foreach ($ports as $index => $port) {
            $destinationIndex['d:' . $port['id']] = $index;
        }
        $links = [];
        $seen = [];
        foreach ($edges as $record) {
            self::record($record, ['item', 'destination', 'cost']);
            $item = self::id($record['item']);
            $destination = self::id($record['destination']);
            $cost = $record['cost'];
            if (!is_int($cost) || $cost < 0) {
                throw new Failure('InvalidCost', 'Cost must be a nonnegative native integer.');
            }
            if ($cost > $limits->get('maxCost')) {
                throw new Failure('LimitExceeded', 'Preference cost exceeds the limit.');
            }
            if (!isset($itemIndex['i:' . $item]) || !isset($destinationIndex['d:' . $destination])) {
                throw new Failure('UnknownReference', 'Compatibility edge references an unknown ID.');
            }
            $key = 'e:' . $itemIndex['i:' . $item] . ':' . $destinationIndex['d:' . $destination];
            if (isset($seen[$key])) {
                throw new Failure('DuplicateEdge', 'Duplicate item-destination edge.');
            }
            $seen[$key] = true;
            $links[] = ['item' => $item, 'destination' => $destination, 'cost' => $cost];
        }
        usort($links, static function (array $a, array $b): int {
            $first = strcmp($a['item'], $b['item']);
            return $first !== 0 ? $first : strcmp($a['destination'], $b['destination']);
        });
        $vertices = count($ids) + count($ports) + 2;
        // Preflight numeric bounds before any residual graph is allocated.
        $pathBound = Limits::multiplyNonnegative($vertices - 1, $limits->get('maxCost'));
        Limits::multiplyNonnegative(count($ids), $limits->get('maxCost'));
        if ($pathBound > Limits::POTENTIAL_BOUND) {
            throw new Failure('ArithmeticOverflow', 'Configured path bound exceeds the certificate range.');
        }
        $this->items = $ids;
        $this->destinations = $ports;
        $this->edges = $links;
        $this->itemIndex = $itemIndex;
        $this->destinationIndex = $destinationIndex;
        $this->vertices = $vertices;
    }

    public static function record(mixed $value, array $keys): void
    {
        if (!is_array($value) || count($value) !== count($keys)) {
            throw new Failure('InvalidInput', 'Record has an incorrect field set.');
        }
        foreach ($keys as $key) {
            if (!array_key_exists($key, $value)) {
                throw new Failure('InvalidInput', 'Record is missing a required field.');
            }
        }
    }

    private static function listOf(mixed $value, int $limit): void
    {
        if (!is_array($value)) {
            throw new Failure('InvalidInput', 'Collections must be lists.');
        }
        if (count($value) > $limit) {
            throw new Failure('LimitExceeded', 'Collection size exceeds the limit.');
        }
        if (!array_is_list($value)) {
            throw new Failure('InvalidInput', 'Collections must have consecutive integer list indexes.');
        }
    }

    public static function id(mixed $value): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 128 || preg_match('//u', $value) !== 1) {
            throw new Failure('InvalidId', 'IDs must be nonempty well-formed UTF-8 strings of at most 128 bytes.');
        }
        return $value;
    }
}
