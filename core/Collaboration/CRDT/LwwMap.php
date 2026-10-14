<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Collaboration\CRDT;

/**
 * Class LwwMap
 *
 * Last-Write-Wins Map CRDT for collaborative metadata and key-value properties.
 * Resolves concurrent updates deterministically using timestamp and peerId tie-breaking.
 */
class LwwMap
{
    /** @var array<string, array{value: mixed, timestamp: float, peerId: string, deleted: bool}> */
    private array $entries = [];

    public function __construct(
        public readonly string $peerId = 'peer-1'
    ) {
    }

    public function set(string $key, mixed $value, ?float $timestamp = null): void
    {
        $ts = $timestamp ?? microtime(true);
        $this->applyEntry($key, $value, $ts, $this->peerId, false);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (isset($this->entries[$key]) && !$this->entries[$key]['deleted']) {
            return $this->entries[$key]['value'];
        }
        return $default;
    }

    public function has(string $key): bool
    {
        return isset($this->entries[$key]) && !$this->entries[$key]['deleted'];
    }

    public function delete(string $key, ?float $timestamp = null): void
    {
        $ts = $timestamp ?? microtime(true);
        $val = $this->entries[$key]['value'] ?? null;
        $this->applyEntry($key, $val, $ts, $this->peerId, true);
    }

    public function all(): array
    {
        $res = [];
        foreach ($this->entries as $key => $entry) {
            if (!$entry['deleted']) {
                $res[$key] = $entry['value'];
            }
        }
        return $res;
    }

    public function applyEntry(string $key, mixed $value, float $timestamp, string $peerId, bool $deleted): bool
    {
        if (!isset($this->entries[$key])) {
            $this->entries[$key] = [
                'value' => $value,
                'timestamp' => $timestamp,
                'peerId' => $peerId,
                'deleted' => $deleted,
            ];
            return true;
        }

        $existing = $this->entries[$key];

        // Compare timestamps
        if ($timestamp > $existing['timestamp']) {
            $this->entries[$key] = [
                'value' => $value,
                'timestamp' => $timestamp,
                'peerId' => $peerId,
                'deleted' => $deleted,
            ];
            return true;
        }

        // Tie-break with peerId if timestamps are equal
        if (abs($timestamp - $existing['timestamp']) < 0.000001) {
            if (strcmp($peerId, $existing['peerId']) > 0) {
                $this->entries[$key] = [
                    'value' => $value,
                    'timestamp' => $timestamp,
                    'peerId' => $peerId,
                    'deleted' => $deleted,
                ];
                return true;
            }
        }

        return false;
    }

    public function merge(LwwMap $other): void
    {
        foreach ($other->entries as $key => $entry) {
            $this->applyEntry($key, $entry['value'], $entry['timestamp'], $entry['peerId'], $entry['deleted']);
        }
    }

    public function toArray(): array
    {
        return [
            'peerId' => $this->peerId,
            'entries' => $this->entries,
        ];
    }

    public static function fromArray(array $data): self
    {
        $instance = new self((string) ($data['peerId'] ?? 'peer-1'));
        $instance->entries = (array) ($data['entries'] ?? []);
        return $instance;
    }
}
