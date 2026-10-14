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

namespace NetFusion\Core\CEP\Stream;

use NetFusion\Core\CEP\Contracts\EventInterface;
use NetFusion\Core\CEP\Contracts\SinkInterface;
use NetFusion\Core\CEP\Contracts\WindowAssignerInterface;
use NetFusion\Core\CEP\Contracts\WindowFunctionInterface;
use NetFusion\Core\CEP\Window\WindowAggregate;
use NetFusion\Core\CEP\Window\WindowState;

class WindowedStream
{
    protected DataStream $parentStream;
    protected WindowAssignerInterface $assigner;
    protected ?KeyedStream $keyedStream;
    protected ?WindowState $windowState = null;
    /** @var array<SinkInterface|callable> */
    protected array $sinks = [];

    public function __construct(
        DataStream $parentStream,
        WindowAssignerInterface $assigner,
        ?KeyedStream $keyedStream = null
    ) {
        $this->parentStream = $parentStream;
        $this->assigner = $assigner;
        $this->keyedStream = $keyedStream;
    }

    public function aggregate(WindowFunctionInterface|callable $function): self
    {
        $windowFunction = $function instanceof WindowFunctionInterface
            ? $function
            : WindowAggregate::custom($function);

        $this->windowState = new WindowState($this->assigner, $windowFunction);

        $this->parentStream->sink(function (EventInterface $event) {
            $key = $this->keyedStream !== null ? $this->keyedStream->getKey($event) : 'global';
            $results = $this->windowState->addEvent($key, $event, microtime(true));

            foreach ($results as $result) {
                $this->dispatchResult($result, $key);
            }
        });

        return $this;
    }

    public function sum(string $field): self
    {
        return $this->aggregate(WindowAggregate::sum($field));
    }

    public function avg(string $field): self
    {
        return $this->aggregate(WindowAggregate::avg($field));
    }

    public function min(string $field): self
    {
        return $this->aggregate(WindowAggregate::min($field));
    }

    public function max(string $field): self
    {
        return $this->aggregate(WindowAggregate::max($field));
    }

    public function count(): self
    {
        return $this->aggregate(WindowAggregate::count());
    }

    public function stddev(string $field): self
    {
        return $this->aggregate(WindowAggregate::stddev($field));
    }

    public function percentile(string $field, float $p = 95.0): self
    {
        return $this->aggregate(WindowAggregate::percentile($field, $p));
    }

    public function sink(SinkInterface|callable $sink): self
    {
        $this->sinks[] = $sink;
        return $this;
    }

    public function getWindowState(): ?WindowState
    {
        return $this->windowState;
    }

    protected function dispatchResult(mixed $result, string $key): void
    {
        foreach ($this->sinks as $sink) {
            if ($sink instanceof SinkInterface) {
                $sink->emit($result, ['key' => $key, 'stream' => $this->parentStream->getName()]);
            } elseif (is_callable($sink)) {
                call_user_func($sink, $result, ['key' => $key, 'stream' => $this->parentStream->getName()]);
            }
        }
    }
}
