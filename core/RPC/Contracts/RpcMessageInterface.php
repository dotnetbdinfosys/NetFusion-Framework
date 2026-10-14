<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/Contracts/RpcMessageInterface.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\Contracts
| Purpose   : Protocol contract for RPC request and response envelopes.
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

namespace NetFusion\Core\RPC\Contracts;

interface RpcMessageInterface
{
    /**
     * Get unique message identifier.
     */
    public function getId(): string;

    /**
     * Get protocol version (e.g., '2.0').
     */
    public function getVersion(): string;

    /**
     * Convert message envelope to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Serialize envelope to JSON string.
     */
    public function toJson(): string;
}
