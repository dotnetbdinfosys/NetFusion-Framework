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
use Stringable;

class SpiffeId implements Stringable, JsonSerializable
{
    protected string $trustDomain;
    protected string $namespace;
    protected string $serviceAccount;

    public function __construct(string $trustDomain, string $namespace = 'default', string $serviceAccount = 'default')
    {
        $this->trustDomain = trim($trustDomain, '/');
        $this->namespace = $namespace;
        $this->serviceAccount = $serviceAccount;
    }

    public static function of(string $serviceAccount, string $namespace = 'default', string $trustDomain = 'cluster.local'): self
    {
        return new self($trustDomain, $namespace, $serviceAccount);
    }

    public static function fromString(string $spiffeString): self
    {
        // Format: spiffe://<trust_domain>/ns/<namespace>/sa/<service_account>
        // Or: spiffe://<trust_domain>/<path>
        if (!str_starts_with($spiffeString, 'spiffe://')) {
            return new self('cluster.local', 'default', $spiffeString);
        }

        $uri = substr($spiffeString, 9);
        $parts = explode('/', $uri);
        $trustDomain = array_shift($parts) ?: 'cluster.local';

        $namespace = 'default';
        $serviceAccount = 'default';

        for ($i = 0; $i < count($parts); $i++) {
            if ($parts[$i] === 'ns' && isset($parts[$i + 1])) {
                $namespace = $parts[$i + 1];
                $i++;
            } elseif ($parts[$i] === 'sa' && isset($parts[$i + 1])) {
                $serviceAccount = $parts[$i + 1];
                $i++;
            }
        }

        if ($serviceAccount === 'default' && !empty($parts)) {
            $serviceAccount = end($parts);
        }

        return new self($trustDomain, $namespace, $serviceAccount);
    }

    public function getTrustDomain(): string
    {
        return $this->trustDomain;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getServiceAccount(): string
    {
        return $this->serviceAccount;
    }

    public function toString(): string
    {
        return sprintf('spiffe://%s/ns/%s/sa/%s', $this->trustDomain, $this->namespace, $this->serviceAccount);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Check if this SPIFFE ID matches a pattern (e.g. "spiffe://cluster.local/ns/prod/*").
     */
    public function matches(string $pattern): bool
    {
        if ($pattern === '*' || $pattern === $this->toString()) {
            return true;
        }

        $regex = str_replace(
            ['\*', '\?'],
            ['.*', '.'],
            preg_quote($pattern, '#')
        );

        return (bool) preg_match('#^' . $regex . '$#i', $this->toString());
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }
}
