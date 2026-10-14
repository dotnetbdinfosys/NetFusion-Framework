<?php

declare(strict_types=1);

namespace NetFusion\Core\SchemaRegistry\CloudEvents;

class EventMeshRouter
{
    /** @var array<string, array{type: string, source: ?string, handler: callable}> */
    private array $subscriptions = [];

    /** @var array<int, array{event_id: string, type: string, source: string, dispatched_to: int, timestamp: float}> */
    private array $dispatchLog = [];

    /**
     * Subscribe to CloudEvents matching type and optional source wildcard pattern.
     *
     * @return string Subscription ID
     */
    public function subscribe(string $typePattern, callable $handler, ?string $sourcePattern = null): string
    {
        $id = uniqid('sub_', true);
        $this->subscriptions[$id] = [
            'type' => $typePattern,
            'source' => $sourcePattern,
            'handler' => $handler,
        ];

        return $id;
    }

    public function unsubscribe(string $subscriptionId): bool
    {
        if (isset($this->subscriptions[$subscriptionId])) {
            unset($this->subscriptions[$subscriptionId]);
            return true;
        }

        return false;
    }

    /**
     * Publish a CloudEvent across the mesh and invoke matching subscribers.
     *
     * @return array<string, mixed> Map of subscriptionId => execution result
     */
    public function publish(CloudEvent $event): array
    {
        $results = [];
        $dispatchedCount = 0;

        foreach ($this->subscriptions as $subId => $sub) {
            if ($this->matchesPattern($event->getType(), $sub['type'])) {
                if ($sub['source'] === null || $this->matchesPattern($event->getSource(), $sub['source'])) {
                    $results[$subId] = ($sub['handler'])($event);
                    $dispatchedCount++;
                }
            }
        }

        $this->dispatchLog[] = [
            'event_id' => $event->getId(),
            'type' => $event->getType(),
            'source' => $event->getSource(),
            'dispatched_to' => $dispatchedCount,
            'timestamp' => microtime(true),
        ];

        return $results;
    }

    public function getSubscriptionCount(): int
    {
        return count($this->subscriptions);
    }

    /**
     * @return array<int, array{event_id: string, type: string, source: string, dispatched_to: int, timestamp: float}>
     */
    public function getDispatchLog(): array
    {
        return $this->dispatchLog;
    }

    private function matchesPattern(string $value, string $pattern): bool
    {
        if ($pattern === '*' || $pattern === $value) {
            return true;
        }

        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace(['.', '*'], ['\.', '.*'], $pattern) . '$/';
            return (bool) preg_match($regex, $value);
        }

        return false;
    }
}
