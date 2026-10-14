<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/RpcRequest.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC
| Purpose   : Immutable RPC Request envelope containing ID, service, method, params, and metadata.
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

use JsonSerializable;
use NetFusion\Core\RPC\Contracts\RpcMessageInterface;

class RpcRequest implements RpcMessageInterface, JsonSerializable
{
    public const PROTOCOL_VERSION = '2.0';

    public function __construct(
        public readonly string $id,
        public readonly string $service,
        public readonly string $method,
        public readonly array  $params = [],
        public readonly array  $metadata = [],
        public readonly string $version = self::PROTOCOL_VERSION
    ) {}

    public static function create(
        string $service,
        string $method,
        array $params = [],
        array $metadata = [],
        ?string $id = null
    ): self {
        $msgId = $id ?? bin2hex(random_bytes(8));
        return new self($msgId, $service, $method, $params, $metadata);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? bin2hex(random_bytes(8))),
            service: (string) ($data['service'] ?? ''),
            method: (string) ($data['method'] ?? ''),
            params: (array) ($data['params'] ?? []),
            metadata: (array) ($data['metadata'] ?? []),
            version: (string) ($data['jsonrpc'] ?? $data['version'] ?? self::PROTOCOL_VERSION)
        );
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RpcException('Invalid JSON RPC request payload.', -32700);
        }
        return self::fromArray($data);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function toArray(): array
    {
        return [
            'jsonrpc'  => $this->version,
            'id'       => $this->id,
            'service'  => $this->service,
            'method'   => $this->method,
            'params'   => $this->params,
            'metadata' => $this->metadata,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
