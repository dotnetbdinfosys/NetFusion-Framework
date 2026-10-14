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

namespace NetFusion\Core\Network\Ebpf\Maps;

use NetFusion\Core\Network\Ebpf\Enums\MapType;

/**
 * Interface for eBPF Shared Memory Maps.
 */
interface MapInterface
{
    public function getName(): string;
    public function getType(): MapType;
    public function getMaxEntries(): int;
    public function get(int|string $key): mixed;
    public function set(int|string $key, mixed $value): void;
    public function delete(int|string $key): bool;
    public function count(): int;
    public function clear(): void;
    public function all(): array;
}
