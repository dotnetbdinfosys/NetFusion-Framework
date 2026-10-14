<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/RpcException.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC
| Purpose   : Custom RPC Exception capturing JSON-RPC 2.0 and Service Mesh error codes.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\RPC;

use RuntimeException;
use Throwable;

class RpcException extends RuntimeException
{
    public const PARSE_ERROR      = -32700;
    public const INVALID_REQUEST  = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS   = -32602;
    public const INTERNAL_ERROR   = -32603;
    public const SERVICE_UNAVAILABLE = -32000;
    public const TIMEOUT          = -32001;
    public const CIRCUIT_OPEN     = -32002;

    public function __construct(
        string $message = '',
        int $code = self::INTERNAL_ERROR,
        ?Throwable $previous = null,
        public readonly mixed $data = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
