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
use NetFusion\Core\CEP\Contracts\StreamInterface;
use NetFusion\Core\CEP\Contracts\WindowAssignerInterface;
use NetFusion\Core\CEP\Pattern\Pattern;
use NetFusion\Core\CEP\Pattern\PatternStream;

class DataStream implements StreamInterface
{
    protected string $name;
    /** @var array<callable> */
    protected array $transformers = [];
    /** @var array<SinkInterface|callable> */
    protected array $sinks = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function named(string $name): self
    {
        return new self($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function filter(callable $predicate): self
    {
        $this->transformers[] = function (mixed $item) use ($predicate) {
            if ($item === null) {
                return null;
            }
            return call_user_func($predicate, $item) ? $item : null;
        };
        return $this;
    }

    public function map(callable $mapper): self
    {
        $this->transformers[] = function (mixed $item) use ($mapper) {
            if ($item === null) {
                return null;
            }
            return call_user_func($mapper, $item);
        };
        return $this;
    }

    public function flatMap(callable $flatMapper): self
    {
        $this->transformers[] = function (mixed $item) use ($flatMapper) {
            if ($item === null) {
                return [];
            }
            $res = call_user_func($flatMapper, $item);
            return is_array($res) ? $res : [$res];
        };
        return $this;
    }

    /**
     * Partition stream by attribute name or closure.
     */
    public function keyBy(string|callable $keySelector): KeyedStream
    {
        return new KeyedStream($this, $keySelector);
    }

    /**
     * Apply windowing on un-keyed stream.
     */
    public function window(WindowAssignerInterface $assigner): WindowedStream
    {
        return new WindowedStream($this, $assigner);
    }

    /**
     * Apply pattern matching on un-keyed stream.
     */
    public function pattern(Pattern $pattern): PatternStream
    {
        $ps = PatternStream::of($pattern);
        $this->sink(function (EventInterface $event) use ($ps) {
            $ps->process('global', $event);
        });
        return $ps;
    }

    public function sink(SinkInterface|callable $sink): self
    {
        $this->sinks[] = $sink;
        return $this;
    }

    /**
     * Process an incoming event through the stream transformations and dispatch to sinks.
     */
    public function emit(EventInterface $event): array
    {
        $currentItems = [$event];

        foreach ($this->transformers as $transformer) {
            $nextItems = [];
            foreach ($currentItems as $item) {
                if ($item === null) {
                    continue;
                }
                $transformed = call_user_func($transformer, $item);
                if ($transformed !== null) {
                    if (is_array($transformed) && isset($transformed[0])) {
                        foreach ($transformed as $subItem) {
                            if ($subItem !== null) {
                                $nextItems[] = $subItem;
                            }
                        }
                    } else {
                        $nextItems[] = $transformed;
                    }
                }
            }
            $currentItems = $nextItems;
            if (empty($currentItems)) {
                break;
            }
        }

        $outputs = [];
        foreach ($currentItems as $output) {
            $outputs[] = $output;
            foreach ($this->sinks as $sink) {
                if ($sink instanceof SinkInterface) {
                    $sink->emit($output, ['stream' => $this->name]);
                } elseif (is_callable($sink)) {
                    call_user_func($sink, $output, ['stream' => $this->name]);
                }
            }
        }

        return $outputs;
    }
}
