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

namespace NetFusion\Core\Actors;

use Closure;
use NetFusion\Core\Actors\Core\DeadLetter;

class DeadLetterQueue
{
    /** @var list<DeadLetter> */
    protected array $letters = [];

    /** @var list<Closure> */
    protected array $subscribers = [];

    /**
     * Publish an undeliverable message to dead letters.
     */
    public function publish(DeadLetter $deadLetter): void
    {
        $this->letters[] = $deadLetter;

        foreach ($this->subscribers as $subscriber) {
            $subscriber($deadLetter);
        }
    }

    /**
     * Subscribe a listener to dead letters.
     */
    public function subscribe(Closure $listener): void
    {
        $this->subscribers[] = $listener;
    }

    /**
     * Retrieve all collected dead letters.
     *
     * @return list<DeadLetter>
     */
    public function all(): array
    {
        return $this->letters;
    }

    /**
     * Count dead letters.
     */
    public function count(): int
    {
        return count($this->letters);
    }

    /**
     * Clear dead letters.
     */
    public function clear(): void
    {
        $this->letters = [];
    }
}
