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
use NetFusion\Core\CEP\Pattern\Pattern;
use NetFusion\Core\CEP\Pattern\PatternStream;

class KeyedStream
{
    protected DataStream $parentStream;
    /** @var string|callable */
    protected $keySelector;
    /** @var array<SinkInterface|callable> */
    protected array $sinks = [];

    public function __construct(DataStream $parentStream, string|callable $keySelector)
    {
        $this->parentStream = $parentStream;
        $this->keySelector = $keySelector;
    }

    public function getKey(EventInterface $event): string
    {
        if (is_string($this->keySelector)) {
            $val = $event->get($this->keySelector);
            return $val !== null ? (string) $val : 'default';
        }

        return (string) call_user_func($this->keySelector, $event);
    }

    public function window(WindowAssignerInterface $assigner): WindowedStream
    {
        return new WindowedStream($this->parentStream, $assigner, $this);
    }

    public function pattern(Pattern $pattern): PatternStream
    {
        $ps = PatternStream::of($pattern);
        $this->parentStream->sink(function (EventInterface $event) use ($ps) {
            $key = $this->getKey($event);
            $ps->process($key, $event);
        });
        return $ps;
    }

    public function process(callable $processor): self
    {
        $this->parentStream->sink(function (EventInterface $event) use ($processor) {
            $key = $this->getKey($event);
            call_user_func($processor, $key, $event);
        });
        return $this;
    }

    public function sink(SinkInterface|callable $sink): self
    {
        $this->parentStream->sink(function (EventInterface $event) use ($sink) {
            $key = $this->getKey($event);
            if ($sink instanceof SinkInterface) {
                $sink->emit($event, ['key' => $key, 'stream' => $this->parentStream->getName()]);
            } elseif (is_callable($sink)) {
                call_user_func($sink, $event, ['key' => $key, 'stream' => $this->parentStream->getName()]);
            }
        });
        return $this;
    }

    public function getParentStream(): DataStream
    {
        return $this->parentStream;
    }
}
