<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Injectors;

use Throwable;
use RuntimeException;

/**
 * Class ExceptionInjector
 *
 * Injects arbitrary PHP runtime exceptions to evaluate error boundaries and fallback handlers.
 */
class ExceptionInjector extends AbstractFaultInjector
{
    /** @var class-string<Throwable> */
    protected string $exceptionClass;
    protected string $message;
    protected int $code;
    protected bool $throwException;

    public function __construct(
        string $name = 'exception_injector',
        string $exceptionClass = RuntimeException::class,
        string $message = 'Chaos injected exception',
        int $code = 0,
        bool $throwException = true,
        float $probability = 1.0,
        array $config = []
    ) {
        parent::__construct($name, $probability, $config);
        $this->exceptionClass = $exceptionClass;
        $this->message = $message;
        $this->code = $code;
        $this->throwException = $throwException;
    }

    public function getExceptionClass(): string
    {
        return $this->exceptionClass;
    }

    public function setExceptionClass(string $class): static
    {
        $this->exceptionClass = $class;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $msg): static
    {
        $this->message = $msg;
        return $this;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCode(int $code): static
    {
        $this->code = $code;
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

    public function inject(array $context = []): mixed
    {
        $class = isset($context['exception_class']) ? (string) $context['exception_class'] : $this->exceptionClass;
        $msg = isset($context['message']) ? (string) $context['message'] : $this->message;
        $code = isset($context['code']) ? (int) $context['code'] : $this->code;
        $shouldThrow = isset($context['throw_exception']) ? (bool) $context['throw_exception'] : $this->throwException;

        $this->recordInjection();

        if (!class_exists($class) || !is_subclass_of($class, Throwable::class)) {
            $class = RuntimeException::class;
        }

        $exception = new $class($msg, $code);

        if ($shouldThrow) {
            throw $exception;
        }

        return [
            'injector' => $this->name,
            'exception_class' => $class,
            'message' => $msg,
            'code' => $code,
            'exception' => $exception,
            'timestamp' => microtime(true),
        ];
    }
}
