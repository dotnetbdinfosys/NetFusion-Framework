<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\ServiceMesh\Policy;

use NetFusion\Core\ServiceMesh\Spiffe\SpiffeId;

/**
 * Class MeshRule
 *
 * Defines an authorization rule matching source identity, target identity,
 * HTTP methods, and path patterns.
 */
class MeshRule
{
    public function __construct(
        private readonly string $name,
        private readonly string $action = 'ALLOW', // 'ALLOW' | 'DENY'
        private readonly array $fromSpiffePatterns = ['*'],
        private readonly array $toSpiffePatterns = ['*'],
        private readonly array $methods = ['*'],
        private readonly array $paths = ['*'],
        private readonly array $headers = []
    ) {}

    public static function allow(string $name): self
    {
        return new self($name, 'ALLOW');
    }

    public static function deny(string $name): self
    {
        return new self($name, 'DENY');
    }

    public function from(string|array $spiffePatterns): self
    {
        $patterns = is_array($spiffePatterns) ? $spiffePatterns : [$spiffePatterns];
        return new self($this->name, $this->action, $patterns, $this->toSpiffePatterns, $this->methods, $this->paths, $this->headers);
    }

    public function to(string|array $spiffePatterns): self
    {
        $patterns = is_array($spiffePatterns) ? $spiffePatterns : [$spiffePatterns];
        return new self($this->name, $this->action, $this->fromSpiffePatterns, $patterns, $this->methods, $this->paths, $this->headers);
    }

    public function methods(string|array $methods): self
    {
        $methodsArr = array_map('strtoupper', is_array($methods) ? $methods : [$methods]);
        return new self($this->name, $this->action, $this->fromSpiffePatterns, $this->toSpiffePatterns, $methodsArr, $this->paths, $this->headers);
    }

    public function paths(string|array $paths): self
    {
        $pathsArr = is_array($paths) ? $paths : [$paths];
        return new self($this->name, $this->action, $this->fromSpiffePatterns, $this->toSpiffePatterns, $this->methods, $pathsArr, $this->headers);
    }

    public function headers(array $headers): self
    {
        return new self($this->name, $this->action, $this->fromSpiffePatterns, $this->toSpiffePatterns, $this->methods, $this->paths, $headers);
    }

    public function matches(
        ServiceIdentity $source,
        SpiffeId $target,
        string $method,
        string $path,
        array $headers = []
    ): bool {
        // 1. Check Source SPIFFE
        if (!$this->matchesPatterns((string) $source->getSpiffeId(), $this->fromSpiffePatterns)) {
            return false;
        }

        // 2. Check Target SPIFFE
        if (!$this->matchesPatterns((string) $target, $this->toSpiffePatterns)) {
            return false;
        }

        // 3. Check Method
        $method = strtoupper($method);
        if (!in_array('*', $this->methods, true) && !in_array($method, $this->methods, true)) {
            return false;
        }

        // 4. Check Path
        if (!$this->matchesPathPatterns($path, $this->paths)) {
            return false;
        }

        // 5. Check Headers
        foreach ($this->headers as $hKey => $hVal) {
            $hKey = strtolower($hKey);
            $actualVal = null;
            foreach ($headers as $k => $v) {
                if (strtolower($k) === $hKey) {
                    $actualVal = $v;
                    break;
                }
            }
            if ($actualVal === null || ($hVal !== '*' && $actualVal !== $hVal)) {
                return false;
            }
        }

        return true;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    private function matchesPatterns(string $value, array $patterns): bool
    {
        if (in_array('*', $patterns, true)) {
            return true;
        }

        foreach ($patterns as $pattern) {
            if ($pattern === $value) {
                return true;
            }
            if (str_ends_with($pattern, '/*')) {
                $prefix = substr($pattern, 0, -2);
                if (str_starts_with($value, $prefix)) return true;
            } elseif (str_ends_with($pattern, '*')) {
                $prefix = substr($pattern, 0, -1);
                if (str_starts_with($value, $prefix)) return true;
            }
        }

        return false;
    }

    private function matchesPathPatterns(string $path, array $patterns): bool
    {
        if (in_array('*', $patterns, true)) {
            return true;
        }

        foreach ($patterns as $pattern) {
            if ($pattern === $path) {
                return true;
            }
            if (str_ends_with($pattern, '/*')) {
                $prefix = substr($pattern, 0, -1); // Keep the slash
                if (str_starts_with($path, $prefix)) return true;
            } elseif (str_ends_with($pattern, '*')) {
                $prefix = substr($pattern, 0, -1);
                if (str_starts_with($path, $prefix)) return true;
            }
        }

        return false;
    }
}
