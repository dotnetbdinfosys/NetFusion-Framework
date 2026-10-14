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

namespace NetFusion\Core\ServiceMesh\Security;

use JsonSerializable;

class MeshAuthorizationPolicy implements JsonSerializable
{
    public const ACTION_ALLOW = 'ALLOW';
    public const ACTION_DENY = 'DENY';

    protected string $name;
    protected string $action;
    /** @var array<string> */
    protected array $sourceSpiffePatterns = [];
    /** @var array<string> */
    protected array $destinationServices = [];
    /** @var array<string> */
    protected array $methods = [];
    /** @var array<string> */
    protected array $paths = [];
    /** @var array<string, string> */
    protected array $requiredHeaders = [];
    /** @var array<string, mixed> */
    protected array $requiredClaims = [];
    protected bool $enabled = true;

    public function __construct(string $name, string $action = self::ACTION_ALLOW)
    {
        $this->name = $name;
        $this->action = strtoupper($action);
    }

    public static function allow(string $name): self
    {
        return new self($name, self::ACTION_ALLOW);
    }

    public static function deny(string $name): self
    {
        return new self($name, self::ACTION_DENY);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function from(string ...$spiffePatterns): self
    {
        $this->sourceSpiffePatterns = array_merge($this->sourceSpiffePatterns, $spiffePatterns);
        return $this;
    }

    public function to(string ...$services): self
    {
        $this->destinationServices = array_merge($this->destinationServices, $services);
        return $this;
    }

    public function methods(string ...$methods): self
    {
        $this->methods = array_map('strtoupper', array_merge($this->methods, $methods));
        return $this;
    }

    public function paths(string ...$paths): self
    {
        $this->paths = array_merge($this->paths, $paths);
        return $this;
    }

    public function requireHeader(string $name, string $value): self
    {
        $this->requiredHeaders[$name] = $value;
        return $this;
    }

    public function requireClaim(string $name, mixed $value): self
    {
        $this->requiredClaims[$name] = $value;
        return $this;
    }

    /**
     * Evaluate if incoming request matches this policy rule.
     *
     * @param SecurityContext $ctx
     * @param string $destService
     * @param string $method
     * @param string $path
     * @param array<string, string> $headers
     */
    public function matches(
        SecurityContext $ctx,
        string $destService,
        string $method,
        string $path,
        array $headers = []
    ): bool {
        if (!$this->enabled) {
            return false;
        }

        // 1. Destination Service Match
        if (!empty($this->destinationServices)) {
            $destMatched = false;
            foreach ($this->destinationServices as $pattern) {
                if ($pattern === '*' || $pattern === $destService || fnmatch($pattern, $destService)) {
                    $destMatched = true;
                    break;
                }
            }
            if (!$destMatched) {
                return false;
            }
        }

        // 2. Source SPIFFE Identity Match
        if (!empty($this->sourceSpiffePatterns)) {
            $peerId = $ctx->getPeerSpiffeId();
            if ($peerId === null) {
                return false; // Anonymous when identity required
            }

            $sourceMatched = false;
            foreach ($this->sourceSpiffePatterns as $pattern) {
                if ($peerId->matches($pattern)) {
                    $sourceMatched = true;
                    break;
                }
            }
            if (!$sourceMatched) {
                return false;
            }
        }

        // 3. HTTP Method Match
        if (!empty($this->methods) && !in_array('*', $this->methods, true)) {
            if (!in_array(strtoupper($method), $this->methods, true)) {
                return false;
            }
        }

        // 4. HTTP Path Match
        if (!empty($this->paths)) {
            $pathMatched = false;
            foreach ($this->paths as $pathPattern) {
                if ($pathPattern === '*' || $pathPattern === $path || fnmatch($pathPattern, $path)) {
                    $pathMatched = true;
                    break;
                }
            }
            if (!$pathMatched) {
                return false;
            }
        }

        // 5. Header Matching
        foreach ($this->requiredHeaders as $hKey => $hVal) {
            if (!isset($headers[$hKey]) && !isset($headers[strtolower($hKey)])) {
                return false;
            }
            $actual = $headers[$hKey] ?? $headers[strtolower($hKey)];
            if ($actual !== $hVal) {
                return false;
            }
        }

        // 6. JWT Claims Matching
        foreach ($this->requiredClaims as $cKey => $cVal) {
            if ($ctx->getClaim($cKey) !== $cVal) {
                return false;
            }
        }

        return true;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'action' => $this->action,
            'enabled' => $this->enabled,
            'source_patterns' => $this->sourceSpiffePatterns,
            'destination_services' => $this->destinationServices,
            'methods' => $this->methods,
            'paths' => $this->paths,
            'required_headers' => $this->requiredHeaders,
            'required_claims' => $this->requiredClaims,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
