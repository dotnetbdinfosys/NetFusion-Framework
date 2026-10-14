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

namespace NetFusion\Core\Actors\Supervision;

use Closure;
use Throwable;

abstract class SupervisorStrategy
{
    public const RESUME = 'resume';
    public const RESTART = 'restart';
    public const STOP = 'stop';
    public const ESCALATE = 'escalate';

    /**
     * @param int          $maxRetries      Maximum number of restarts within window
     * @param float        $withinTimeRange Time window in seconds (e.g. 60s)
     * @param Closure|null $decider         Custom decider fn(Throwable $e): string
     */
    public function __construct(
        public readonly int $maxRetries = 10,
        public readonly float $withinTimeRange = 60.0,
        protected ?Closure $decider = null
    ) {}

    /**
     * Determine directive action for the thrown exception.
     */
    public function handle(Throwable $exception): string
    {
        if ($this->decider !== null) {
            $decider = $this->decider;
            return (string)$decider($exception);
        }

        return self::RESTART;
    }

    abstract public function isOneForOne(): bool;
}
