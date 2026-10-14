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

namespace NetFusion\Core\IMDG\ContinuousQuery;

use NetFusion\Core\IMDG\Contracts\ContinuousQueryListenerInterface;

class ContinuousQueryManager
{
    /** @var array<string, array{listener: ContinuousQueryListenerInterface|callable, filter: ?callable}> */
    protected array $listeners = [];

    public function addListener(ContinuousQueryListenerInterface|callable $listener, ?callable $filter = null): string
    {
        $id = uniqid('cql_', true);
        $this->listeners[$id] = [
            'listener' => $listener,
            'filter' => $filter,
        ];
        return $id;
    }

    public function removeListener(string $listenerId): bool
    {
        if (isset($this->listeners[$listenerId])) {
            unset($this->listeners[$listenerId]);
            return true;
        }
        return false;
    }

    public function dispatch(EntryEvent $event): void
    {
        foreach ($this->listeners as $entry) {
            $listener = $entry['listener'];
            $filter = $entry['filter'];

            if ($filter !== null && !$filter($event)) {
                continue;
            }

            if ($listener instanceof ContinuousQueryListenerInterface) {
                $listener->onEvent($event);
            } elseif (is_callable($listener)) {
                $listener($event);
            }
        }
    }

    public function clear(): void
    {
        $this->listeners = [];
    }
}
