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

namespace NetFusion\Core\Workflow\Compensation;

/**
 * Class CompensationCoordinator
 *
 * Tracks completed activities and their compensation handlers.
 * When triggered, executes handlers in reverse completion order (LIFO).
 *
 * BPMN 2.0 §10.4 — Compensation Boundary Events
 *
 * Usage:
 *   $coord = new CompensationCoordinator();
 *   $coord->register('charge_card', new CompensationHandler(
 *       'charge_card',
 *       fn($output) => refundCharge($output['charge_id']),
 *       ['charge_id' => 'ch_123']
 *   ));
 *   // On saga failure:
 *   $coord->compensate();
 */
class CompensationCoordinator
{
    /** @var CompensationHandler[] Ordered by registration (completion) time */
    private array $handlers = [];

    /** @var array[] Results from executed compensation */
    private array $results  = [];

    private bool $triggered = false;

    // ------------------------------------------------------------------
    // Registration
    // ------------------------------------------------------------------

    public function register(string $activityId, CompensationHandler $handler): void
    {
        $this->handlers[$activityId] = $handler;
    }

    public function deregister(string $activityId): void
    {
        unset($this->handlers[$activityId]);
    }

    public function hasHandler(string $activityId): bool
    {
        return isset($this->handlers[$activityId]);
    }

    public function getRegisteredActivities(): array
    {
        return array_keys($this->handlers);
    }

    // ------------------------------------------------------------------
    // Compensation
    // ------------------------------------------------------------------

    /**
     * Trigger compensation for all registered handlers in reverse order.
     *
     * @param  array $context Process context data
     * @return array[] Compensation results keyed by activity ID
     */
    public function compensate(array $context = []): array
    {
        $this->triggered = true;
        $this->results   = [];

        // LIFO: reverse the registration order
        $reversed = array_reverse($this->handlers, true);

        foreach ($reversed as $activityId => $handler) {
            try {
                $result = $handler->compensate($context);
                $this->results[$activityId] = array_merge(['success' => true], $result);
            } catch (\Throwable $e) {
                $this->results[$activityId] = [
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return $this->results;
    }

    /**
     * Trigger compensation for a specific activity only.
     */
    public function compensateActivity(string $activityId, array $context = []): array
    {
        if (!isset($this->handlers[$activityId])) {
            throw new \RuntimeException("No compensation handler for activity: {$activityId}");
        }

        return $this->handlers[$activityId]->compensate($context);
    }

    // ------------------------------------------------------------------
    // Status
    // ------------------------------------------------------------------

    public function wasTriggered(): bool  { return $this->triggered; }
    public function getResults(): array   { return $this->results; }
    public function count(): int          { return count($this->handlers); }
    public function reset(): void         { $this->handlers = []; $this->results = []; $this->triggered = false; }
}
