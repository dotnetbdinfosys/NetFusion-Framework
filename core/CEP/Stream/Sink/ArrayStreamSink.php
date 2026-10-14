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

namespace NetFusion\Core\CEP\Stream\Sink;

use NetFusion\Core\CEP\Contracts\SinkInterface;

class ArrayStreamSink implements SinkInterface
{
    protected string $name;
    /** @var array<array{data: mixed, context: array<string, mixed>, timestamp: float}> */
    protected array $records = [];

    public function __construct(string $name = 'array_sink')
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function emit(mixed $data, array $context = []): void
    {
        $this->records[] = [
            'data' => $data,
            'context' => $context,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * @return array<array{data: mixed, context: array<string, mixed>, timestamp: float}>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * @return array<mixed>
     */
    public function getItems(): array
    {
        return array_column($this->records, 'data');
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function clear(): void
    {
        $this->records = [];
    }
}
