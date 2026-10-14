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

namespace NetFusion\Core\ServiceMesh\Proxy;

use JsonSerializable;
use NetFusion\Core\ServiceMesh\Security\SecurityContext;
use NetFusion\Core\ServiceMesh\Security\SpiffeId;

class MeshRequestContext implements JsonSerializable
{
    protected string $requestId;
    protected string $targetService;
    protected string $path;
    protected string $method;
    /** @var array<string, string> */
    protected array $headers;
    protected mixed $body;
    protected ?string $sourceService = null;
    protected ?SpiffeId $sourceSpiffeId = null;
    protected ?SecurityContext $securityContext = null;

    /**
     * @param string $targetService
     * @param string $path
     * @param string $method
     * @param array<string, string> $headers
     * @param mixed $body
     * @param string|null $sourceService
     * @param SpiffeId|null $sourceSpiffeId
     * @param SecurityContext|null $securityContext
     * @param string|null $requestId
     */
    public function __construct(
        string $targetService,
        string $path = '/',
        string $method = 'GET',
        array $headers = [],
        mixed $body = null,
        ?string $sourceService = null,
        ?SpiffeId $sourceSpiffeId = null,
        ?SecurityContext $securityContext = null,
        ?string $requestId = null
    ) {
        $this->targetService = $targetService;
        $this->path = $path ?: '/';
        $this->method = strtoupper($method);
        $this->headers = $headers;
        $this->body = $body;
        $this->sourceService = $sourceService;
        $this->sourceSpiffeId = $sourceSpiffeId;
        $this->securityContext = $securityContext;
        $this->requestId = $requestId ?? self::generateRequestId();
    }

    public static function create(
        string $targetService,
        string $path = '/',
        string $method = 'GET',
        array $headers = [],
        mixed $body = null
    ): self {
        return new self($targetService, $path, $method, $headers, $body);
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getTargetService(): string
    {
        return $this->targetService;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        if (isset($this->headers[$name])) {
            return (string) $this->headers[$name];
        }

        $lower = strtolower($name);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $lower) {
                return (string) $v;
            }
        }

        return $default;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    public function getSourceService(): ?string
    {
        return $this->sourceService;
    }

    public function getSourceSpiffeId(): ?SpiffeId
    {
        return $this->sourceSpiffeId;
    }

    public function getSecurityContext(): ?SecurityContext
    {
        return $this->securityContext;
    }

    public function withSecurityContext(SecurityContext $context): self
    {
        $clone = clone $this;
        $clone->securityContext = $context;
        $clone->sourceSpiffeId = $context->getPeerSpiffeId();
        return $clone;
    }

    public function withSource(string $sourceService, ?SpiffeId $spiffeId = null): self
    {
        $clone = clone $this;
        $clone->sourceService = $sourceService;
        $clone->sourceSpiffeId = $spiffeId ?? SpiffeId::of($sourceService);
        return $clone;
    }

    protected static function generateRequestId(): string
    {
        return 'req_' . bin2hex(random_bytes(8));
    }

    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'target_service' => $this->targetService,
            'path' => $this->path,
            'method' => $this->method,
            'headers' => $this->headers,
            'source_service' => $this->sourceService,
            'source_spiffe_id' => $this->sourceSpiffeId?->toString(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
