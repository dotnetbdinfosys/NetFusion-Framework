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

namespace NetFusion\Core\Saga;

use JsonSerializable;

class SagaStepResult implements JsonSerializable
{
    /**
     * @param string $stepName
     * @param bool $success
     * @param mixed $result
     * @param string|null $error
     * @param bool $compensated
     * @param float $executionTimeMs
     */
    public function __construct(
        public string $stepName,
        public bool $success,
        public mixed $result = null,
        public ?string $error = null,
        public bool $compensated = false,
        public float $executionTimeMs = 0.0
    ) {
    }

    public static function success(string $name, mixed $result = null, float $timeMs = 0.0): self
    {
        return new self($name, true, $result, null, false, $timeMs);
    }

    public static function failure(string $name, string $error, float $timeMs = 0.0): self
    {
        return new self($name, false, null, $error, false, $timeMs);
    }

    public function markCompensated(): self
    {
        $this->compensated = true;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'step' => $this->stepName,
            'success' => $this->success,
            'result' => $this->result,
            'error' => $this->error,
            'compensated' => $this->compensated,
            'execution_time_ms' => $this->executionTimeMs,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
