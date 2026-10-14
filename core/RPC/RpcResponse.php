<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/RpcResponse.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC
| Purpose   : Immutable RPC Response envelope with result, error, and execution telemetry.
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

class RpcResponse implements RpcMessageInterface, JsonSerializable
{
    public const PROTOCOL_VERSION = '2.0';

    public function __construct(
        public readonly string $id,
        public readonly mixed  $result = null,
        public readonly ?array $error = null,
        public readonly float  $executionTimeMs = 0.0,
        public readonly array  $metadata = [],
        public readonly string $version = self::PROTOCOL_VERSION
    ) {}

    public static function success(
        string $id,
        mixed $result,
        float $executionTimeMs = 0.0,
        array $metadata = []
    ): self {
        return new self(
            id: $id,
            result: $result,
            error: null,
            executionTimeMs: $executionTimeMs,
            metadata: $metadata
        );
    }

    public static function error(
        string $id,
        string $message,
        int $code = -32603,
        mixed $data = null,
        float $executionTimeMs = 0.0
    ): self {
        return new self(
            id: $id,
            result: null,
            error: [
                'code'    => $code,
                'message' => $message,
                'data'    => $data,
            ],
            executionTimeMs: $executionTimeMs
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            result: $data['result'] ?? null,
            error: isset($data['error']) && is_array($data['error']) ? $data['error'] : null,
            executionTimeMs: (float) ($data['execution_time_ms'] ?? 0.0),
            metadata: (array) ($data['metadata'] ?? []),
            version: (string) ($data['jsonrpc'] ?? $data['version'] ?? self::PROTOCOL_VERSION)
        );
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RpcException('Invalid JSON RPC response payload.', -32700);
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

    public function isSuccess(): bool
    {
        return $this->error === null;
    }

    public function getResult(): mixed
    {
        if ($this->error !== null) {
            throw new RpcException(
                $this->error['message'] ?? 'RPC Error',
                (int) ($this->error['code'] ?? -32603)
            );
        }
        return $this->result;
    }

    public function getError(): ?array
    {
        return $this->error;
    }

    public function getExecutionTimeMs(): float
    {
        return $this->executionTimeMs;
    }

    public function toArray(): array
    {
        $res = [
            'jsonrpc'           => $this->version,
            'id'                => $this->id,
            'execution_time_ms' => $this->executionTimeMs,
        ];

        if ($this->error !== null) {
            $res['error'] = $this->error;
        } else {
            $res['result'] = $this->result;
        }

        if (!empty($this->metadata)) {
            $res['metadata'] = $this->metadata;
        }

        return $res;
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
