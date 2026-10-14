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

namespace NetFusion\Core\Payment\Exceptions;

class GatewayException extends PaymentException
{
    public function __construct(
        string $message = '',
        protected string $processor = '',
        protected string $rawErrorCode = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getProcessor(): string
    {
        return $this->processor;
    }

    public function getRawErrorCode(): string
    {
        return $this->rawErrorCode;
    }
}
