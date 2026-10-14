<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Data\Crdt;

use InvalidArgumentException;
use NetFusion\Core\Data\Crdt\Contracts\CrdtInterface;

class MVRegister implements CrdtInterface
{
    /** @var array<int, array{value: mixed, clock: VectorClock}> */
    protected array $versions = [];

    public function __construct(
        mixed $initialValue = null,
        ?VectorClock $initialClock = null
    ) {
        if ($initialValue !== null) {
            $clock = $initialClock ?? new VectorClock();
            $this->versions[] = ['value' => $initialValue, 'clock' => $clock];
        }
    }

    public function set(mixed $value, string $nodeId): self
    {
        // Calculate merged clock from all current versions
        $combinedClock = new VectorClock();
        foreach ($this->versions as $v) {
            $combinedClock->merge($v['clock']);
        }
        $combinedClock->increment($nodeId);

        $this->versions = [
            ['value' => $value, 'clock' => $combinedClock]
        ];

        return $this;
    }

    public function getValue(): array
    {
        $values = [];
        foreach ($this->versions as $v) {
            $values[] = $v['value'];
        }
        return $values;
    }

    public function getValues(): array
    {
        return $this->getValue();
    }

    public function isConflicted(): bool
    {
        return count($this->versions) > 1;
    }

    public function merge(CrdtInterface $other): static
    {
        if (!$other instanceof self) {
            throw new InvalidArgumentException('Cannot merge MVRegister with non-MVRegister CRDT.');
        }

        $allVersions = array_merge($this->versions, $other->getVersions());
        $surviving = [];

        foreach ($allVersions as $v1) {
            $dominated = false;
            foreach ($allVersions as $v2) {
                if ($v1['clock']->happensBefore($v2['clock'])) {
                    $dominated = true;
                    break;
                }
            }

            if (!$dominated) {
                // Ensure no duplicate identical clock & value
                $exists = false;
                foreach ($surviving as $s) {
                    if ($s['value'] === $v1['value'] && $s['clock']->isEqualTo($v1['clock'])) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $surviving[] = $v1;
                }
            }
        }

        $this->versions = $surviving;
        return $this;
    }

    /**
     * @return array<int, array{value: mixed, clock: VectorClock}>
     */
    public function getVersions(): array
    {
        return $this->versions;
    }

    public function getState(): array
    {
        $serialized = [];
        foreach ($this->versions as $v) {
            $serialized[] = [
                'value' => $v['value'],
                'clock' => $v['clock']->toArray(),
            ];
        }

        return [
            'type' => 'mv-register',
            'versions' => $serialized,
        ];
    }

    public static function fromState(array $state): static
    {
        $instance = new static();
        $versions = [];
        foreach ((array)($state['versions'] ?? []) as $v) {
            $versions[] = [
                'value' => $v['value'],
                'clock' => new VectorClock((array)($v['clock'] ?? [])),
            ];
        }
        $instance->versions = $versions;
        return $instance;
    }

    public function jsonSerialize(): array
    {
        return [
            'values' => $this->getValue(),
            'conflicted' => $this->isConflicted(),
            'state' => $this->getState(),
        ];
    }
}
