<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Injectors;

use NetFusion\Core\Chaos\Exceptions\DatabaseFaultException;

/**
 * Class DatabaseFaultInjector
 *
 * Simulates database deadlocks, connection drops, lock wait timeouts, and query degradation.
 */
class DatabaseFaultInjector extends AbstractFaultInjector
{
    public const TYPE_DEADLOCK = 'deadlock';
    public const TYPE_TIMEOUT = 'timeout';
    public const TYPE_CONNECTION_REFUSED = 'connection_refused';
    public const TYPE_SLOW_QUERY = 'slow_query';

    protected string $faultType;
    protected int $slowQueryMs;
    protected bool $throwException;

    public function __construct(
        string $name = 'database_fault_injector',
        string $faultType = self::TYPE_DEADLOCK,
        int $slowQueryMs = 250,
        bool $throwException = true,
        float $probability = 1.0,
        array $config = []
    ) {
        parent::__construct($name, $probability, $config);
        $this->faultType = $faultType;
        $this->slowQueryMs = max(0, $slowQueryMs);
        $this->throwException = $throwException;
    }

    public function getFaultType(): string
    {
        return $this->faultType;
    }

    public function setFaultType(string $faultType): static
    {
        $this->faultType = $faultType;
        return $this;
    }

    public function getSlowQueryMs(): int
    {
        return $this->slowQueryMs;
    }

    public function setSlowQueryMs(int $ms): static
    {
        $this->slowQueryMs = max(0, $ms);
        return $this;
    }

    public function isThrowException(): bool
    {
        return $this->throwException;
    }

    public function setThrowException(bool $throw): static
    {
        $this->throwException = $throw;
        return $this;
    }

    public function inject(array $context = []): array
    {
        $type = isset($context['fault_type']) ? (string) $context['fault_type'] : $this->faultType;
        $shouldThrow = isset($context['throw_exception']) ? (bool) $context['throw_exception'] : $this->throwException;
        $slowMs = isset($context['slow_query_ms']) ? (int) $context['slow_query_ms'] : $this->slowQueryMs;

        $this->recordInjection();

        if ($type === self::TYPE_SLOW_QUERY) {
            $simulate = isset($context['simulate_only']) ? (bool) $context['simulate_only'] : false;
            if (!$simulate && $slowMs > 0) {
                usleep($slowMs * 1000);
            }
            return [
                'injector' => $this->name,
                'fault_type' => self::TYPE_SLOW_QUERY,
                'slow_query_ms' => $slowMs,
                'timestamp' => microtime(true),
            ];
        }

        if ($type === self::TYPE_DEADLOCK) {
            if ($shouldThrow) {
                throw new DatabaseFaultException(
                    "SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction",
                    self::TYPE_DEADLOCK,
                    '40001'
                );
            }
            return [
                'injector' => $this->name,
                'fault_type' => self::TYPE_DEADLOCK,
                'sql_state' => '40001',
                'message' => 'Simulated Deadlock',
                'timestamp' => microtime(true),
            ];
        }

        if ($type === self::TYPE_TIMEOUT) {
            if ($shouldThrow) {
                throw new DatabaseFaultException(
                    "SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction",
                    self::TYPE_TIMEOUT,
                    'HY000'
                );
            }
            return [
                'injector' => $this->name,
                'fault_type' => self::TYPE_TIMEOUT,
                'sql_state' => 'HY000',
                'message' => 'Simulated Lock Wait Timeout',
                'timestamp' => microtime(true),
            ];
        }

        // Default or connection refused
        if ($shouldThrow) {
            throw new DatabaseFaultException(
                "SQLSTATE[08006] [7] could not connect to server: Connection refused",
                self::TYPE_CONNECTION_REFUSED,
                '08006'
            );
        }

        return [
            'injector' => $this->name,
            'fault_type' => self::TYPE_CONNECTION_REFUSED,
            'sql_state' => '08006',
            'message' => 'Simulated Connection Refused',
            'timestamp' => microtime(true),
        ];
    }
}
