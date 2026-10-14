<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Guardrails;

use NetFusion\Core\Chaos\Exceptions\EmergencyKillSwitchActivatedException;

/**
 * Class EmergencyKillSwitch
 *
 * Global circuit-breaker that immediately stops all chaos experiments and active fault injections.
 */
class EmergencyKillSwitch
{
    protected static ?self $instance = null;
    protected bool $active = false;
    protected array $activationDetails = [];
    protected ?string $lockFilePath = null;
    /** @var array<int, callable> */
    protected array $onActivateCallbacks = [];

    public function __construct(?string $lockFilePath = null)
    {
        $this->lockFilePath = $lockFilePath ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'netfusion_chaos_kill_switch.lock');
        $this->syncFromDisk();
    }

    public static function getInstance(?string $lockFilePath = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($lockFilePath);
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        if (self::$instance !== null) {
            self::$instance->deactivate();
            self::$instance = null;
        }
    }

    public function onActivate(callable $callback): static
    {
        $this->onActivateCallbacks[] = $callback;
        return $this;
    }

    /**
     * Activate the emergency kill switch.
     */
    public function activate(string $reason = 'Manual emergency trigger', ?string $initiatedBy = 'system'): void
    {
        $this->active = true;
        $this->activationDetails = [
            'active' => true,
            'reason' => $reason,
            'initiated_by' => $initiatedBy,
            'activated_at' => microtime(true),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        // Persist to lock file
        if ($this->lockFilePath !== null) {
            @file_put_contents($this->lockFilePath, json_encode($this->activationDetails, JSON_PRETTY_PRINT));
        }

        // Fire onActivate callbacks
        foreach ($this->onActivateCallbacks as $cb) {
            try {
                $cb($this->activationDetails);
            } catch (\Throwable) {
                // Ignore callback failures during emergency shutdown
            }
        }
    }

    /**
     * Deactivate the emergency kill switch and clear state.
     */
    public function deactivate(): void
    {
        $this->active = false;
        $this->activationDetails = [];

        if ($this->lockFilePath !== null && file_exists($this->lockFilePath)) {
            @unlink($this->lockFilePath);
        }
    }

    /**
     * Check if the kill switch is currently engaged.
     */
    public function isActive(): bool
    {
        $this->syncFromDisk();
        return $this->active;
    }

    /**
     * Get activation details.
     */
    public function getActivationDetails(): array
    {
        $this->syncFromDisk();
        return $this->activationDetails;
    }

    /**
     * Ensure kill switch is NOT active, otherwise throw exception.
     *
     * @throws EmergencyKillSwitchActivatedException
     */
    public function assertSafe(): void
    {
        if ($this->isActive()) {
            throw new EmergencyKillSwitchActivatedException(
                "Emergency Kill Switch is ACTIVE: " . ($this->activationDetails['reason'] ?? 'Unknown reason'),
                $this->activationDetails
            );
        }
    }

    protected function syncFromDisk(): void
    {
        if ($this->lockFilePath !== null && file_exists($this->lockFilePath)) {
            $content = @file_get_contents($this->lockFilePath);
            if ($content !== false && $content !== '') {
                $data = json_decode($content, true);
                if (is_array($data) && !empty($data['active'])) {
                    $this->active = true;
                    $this->activationDetails = $data;
                    return;
                }
            }
        }
    }
}
