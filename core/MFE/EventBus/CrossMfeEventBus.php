<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\MFE\EventBus;

/**
 * Sandboxed Cross-MFE Event Bus.
 * Enables decoupled, secure communication between isolated Micro-Frontend applications.
 * Can export native browser JavaScript bridge scripts (`window.MfeBus`).
 */
class CrossMfeEventBus
{
    /** @var array<string, array<callable>> [eventName => [callbacks]] */
    protected array $listeners = [];
    /** @var array<string, mixed> Shared global reactive state store */
    protected array $sharedState = [];

    /**
     * Subscribe to an event on the cross-MFE bus.
     */
    public function on(string $event, callable $listener): self
    {
        $this->listeners[$event][] = $listener;
        return $this;
    }

    /**
     * Dispatch an event to all subscribers.
     *
     * @param mixed $payload
     * @return int Number of listeners invoked
     */
    public function dispatch(string $event, mixed $payload = null, string $origin = 'host'): int
    {
        if (!isset($this->listeners[$event])) {
            return 0;
        }

        $invoked = 0;
        foreach ($this->listeners[$event] as $cb) {
            $cb($payload, $origin);
            $invoked++;
        }

        return $invoked;
    }

    /**
     * Set a shared state key and notify subscribers.
     */
    public function setState(string $key, mixed $value, string $origin = 'host'): self
    {
        $oldValue = $this->sharedState[$key] ?? null;
        $this->sharedState[$key] = $value;

        // Broadcast change event
        $this->dispatch('state:change:' . $key, [
            'key' => $key,
            'newValue' => $value,
            'oldValue' => $oldValue,
            'origin' => $origin,
        ], $origin);

        return $this;
    }

    public function getState(string $key, mixed $default = null): mixed
    {
        return $this->sharedState[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllState(): array
    {
        return $this->sharedState;
    }

    /**
     * Generate browser runtime JavaScript script tag for window.MfeBus.
     */
    public function generateBrowserBridge(): string
    {
        $initialStateJson = json_encode($this->sharedState, JSON_UNESCAPED_SLASHES);
        return <<<HTML
<script>
window.MfeBus = window.MfeBus || (function() {
    var state = {$initialStateJson};
    var bus = new EventTarget();
    return {
        on: function(event, cb) {
            bus.addEventListener(event, function(e) { cb(e.detail); });
        },
        dispatch: function(event, data) {
            bus.dispatchEvent(new CustomEvent(event, { detail: data }));
        },
        getState: function(key) { return state[key]; },
        setState: function(key, val) {
            state[key] = val;
            bus.dispatchEvent(new CustomEvent('state:' + key, { detail: val }));
        }
    };
})();
</script>
HTML;
    }

    public function clear(): self
    {
        $this->listeners = [];
        $this->sharedState = [];
        return $this;
    }
}
