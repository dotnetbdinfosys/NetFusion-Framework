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

namespace NetFusion\Core\ServiceMesh\Routing;

use JsonSerializable;
use NetFusion\Core\ServiceMesh\Exceptions\FaultInjectedException;

class FaultInjector implements JsonSerializable
{
    protected ?int $delayMs = null;
    protected float $delayPercentage = 0.0;

    protected ?int $abortHttpStatus = null;
    protected float $abortPercentage = 0.0;
    protected string $abortMessage = 'Fault injected abort';

    public static function delay(int $delayMs, float $percentage = 100.0): self
    {
        $instance = new self();
        $instance->delayMs = max(0, $delayMs);
        $instance->delayPercentage = max(0.0, min(100.0, $percentage));
        return $instance;
    }

    public static function abort(int $httpStatus = 503, float $percentage = 100.0, string $message = 'Injected fault error'): self
    {
        $instance = new self();
        $instance->abortHttpStatus = $httpStatus;
        $instance->abortPercentage = max(0.0, min(100.0, $percentage));
        $instance->abortMessage = $message;
        return $instance;
    }

    public function withDelay(int $delayMs, float $percentage = 100.0): self
    {
        $this->delayMs = max(0, $delayMs);
        $this->delayPercentage = max(0.0, min(100.0, $percentage));
        return $this;
    }

    public function withAbort(int $httpStatus = 503, float $percentage = 100.0, string $message = 'Injected fault error'): self
    {
        $this->abortHttpStatus = $httpStatus;
        $this->abortPercentage = max(0.0, min(100.0, $percentage));
        $this->abortMessage = $message;
        return $this;
    }

    /**
     * Execute fault injection if probability matches.
     *
     * @throws FaultInjectedException
     */
    public function execute(?callable $sleepHandler = null): void
    {
        // 1. Evaluate delay injection
        if ($this->delayMs !== null && $this->delayMs > 0 && $this->delayPercentage > 0.0) {
            $rand = (float) (random_int(1, 10000) / 100.0);
            if ($rand <= $this->delayPercentage) {
                if ($sleepHandler !== null) {
                    call_user_func($sleepHandler, $this->delayMs);
                } else {
                    usleep($this->delayMs * 1000);
                }
            }
        }

        // 2. Evaluate abort injection
        if ($this->abortHttpStatus !== null && $this->abortPercentage > 0.0) {
            $rand = (float) (random_int(1, 10000) / 100.0);
            if ($rand <= $this->abortPercentage) {
                throw new FaultInjectedException($this->abortMessage, $this->abortHttpStatus);
            }
        }
    }

    public function getDelayMs(): ?int
    {
        return $this->delayMs;
    }

    public function getAbortHttpStatus(): ?int
    {
        return $this->abortHttpStatus;
    }

    public function toArray(): array
    {
        return [
            'delay_ms' => $this->delayMs,
            'delay_percentage' => $this->delayPercentage,
            'abort_status' => $this->abortHttpStatus,
            'abort_percentage' => $this->abortPercentage,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
