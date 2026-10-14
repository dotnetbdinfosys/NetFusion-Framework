<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Exceptions;

class HttpFaultException extends ChaosException
{
    protected int $statusCode;
    protected array $headers;

    public function __construct(string $message = "Chaos HTTP Fault Injected", int $statusCode = 500, array $headers = [], ?\Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
