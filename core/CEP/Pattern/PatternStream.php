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

namespace NetFusion\Core\CEP\Pattern;

use NetFusion\Core\CEP\Contracts\EventInterface;
use NetFusion\Core\CEP\Contracts\SinkInterface;

class PatternStream
{
    protected NFA $nfa;
    /** @var callable|null */
    protected $selectCallback = null;
    /** @var array<SinkInterface|callable> */
    protected array $sinks = [];

    public function __construct(NFA $nfa)
    {
        $this->nfa = $nfa;
    }

    public static function of(Pattern $pattern): self
    {
        return new self(new NFA($pattern));
    }

    public function select(callable $callback): self
    {
        $this->selectCallback = $callback;
        return $this;
    }

    public function sink(SinkInterface|callable $sink): self
    {
        $this->sinks[] = $sink;
        return $this;
    }

    /**
     * Ingest an event and emit any matched patterns to sinks.
     *
     * @return array<mixed>
     */
    public function process(string $partitionKey, EventInterface $event): array
    {
        $matches = $this->nfa->process($partitionKey, $event);
        $outputs = [];

        foreach ($matches as $match) {
            $output = $this->selectCallback !== null
                ? call_user_func($this->selectCallback, $match)
                : $match;

            $outputs[] = $output;

            foreach ($this->sinks as $sink) {
                if ($sink instanceof SinkInterface) {
                    $sink->emit($output, ['partition_key' => $partitionKey]);
                } elseif (is_callable($sink)) {
                    call_user_func($sink, $output, ['partition_key' => $partitionKey]);
                }
            }
        }

        return $outputs;
    }

    public function getNFA(): NFA
    {
        return $this->nfa;
    }
}
