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

namespace NetFusion\Core\ServiceMesh\Exceptions;

class FaultInjectedException extends ServiceMeshException
{
    protected int $httpStatusCode;

    public function __construct(string $message = 'Injected fault error', int $httpStatusCode = 503)
    {
        parent::__construct($message, $httpStatusCode);
        $this->httpStatusCode = $httpStatusCode;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }
}
