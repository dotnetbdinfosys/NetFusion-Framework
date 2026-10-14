<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Exceptions;

class DatabaseFaultException extends ChaosException
{
    protected string $faultType;
    protected ?string $sqlState;

    public function __construct(string $message = "Chaos Database Fault Injected", string $faultType = "deadlock", ?string $sqlState = "40001", ?\Throwable $previous = null)
    {
        $this->faultType = $faultType;
        $this->sqlState = $sqlState;
        parent::__construct($message, 0, $previous);
    }

    public function getFaultType(): string
    {
        return $this->faultType;
    }

    public function getSqlState(): ?string
    {
        return $this->sqlState;
    }
}
