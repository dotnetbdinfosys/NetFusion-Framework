<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Injectors;

use NetFusion\Core\Chaos\Exceptions\HttpFaultException;

/**
 * Class HttpFaultInjector
 *
 * Injects synthetic HTTP error codes (500, 502, 503, 504, 429) or throws HttpFaultException.
 */
class HttpFaultInjector extends AbstractFaultInjector
{
    protected int $statusCode;
    protected string $message;
    protected array $headers;
    protected bool $throwException;

    public function __construct(
        string $name = 'http_fault_injector',
        int $statusCode = 500,
        string $message = 'Chaos HTTP Error',
        bool $throwException = true,
        array $headers = [],
        float $probability = 1.0,
        array $config = []
    ) {
        parent::__construct($name, $probability, $config);
        $this->statusCode = $statusCode;
        $this->message = $message;
        $this->throwException = $throwException;
        $this->headers = array_merge([
            'X-Chaos-Injected' => 'true',
            'X-Chaos-Injector' => $name,
        ], $headers);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
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

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    public function inject(array $context = []): array
    {
        $status = isset($context['status_code']) ? (int) $context['status_code'] : $this->statusCode;
        $msg = isset($context['message']) ? (string) $context['message'] : $this->message;
        $shouldThrow = isset($context['throw_exception']) ? (bool) $context['throw_exception'] : $this->throwException;
        $headers = isset($context['headers']) ? array_merge($this->headers, (array) $context['headers']) : $this->headers;

        $this->recordInjection();

        if ($shouldThrow) {
            throw new HttpFaultException($msg, $status, $headers);
        }

        return [
            'injector' => $this->name,
            'status_code' => $status,
            'message' => $msg,
            'headers' => $headers,
            'timestamp' => microtime(true),
        ];
    }
}
