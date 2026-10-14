<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/WasiP2/WasiP2Runtime.php
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

namespace NetFusion\Core\Wasm\Component\WasiP2;

/**
 * WASI Preview 2 (P2) System Calls and Resource Interfaces.
 * Implements wasi:cli/environment, wasi:clocks/monotonic-clock, wasi:filesystem/types, wasi:http/outgoing-handler.
 */
class WasiP2Runtime
{
    private array $env = [];
    private array $arguments = [];
    private string $stdoutBuffer = '';
    private string $stderrBuffer = '';
    /** @var array<string, string> Virtual in-memory filesystem */
    private array $virtualFs = [];

    public function __construct(array $env = [], array $arguments = [])
    {
        $this->env = $env;
        $this->arguments = $arguments;
    }

    public function setEnv(string $key, string $value): self
    {
        $this->env[$key] = $value;
        return $this;
    }

    public function getEnv(): array
    {
        return $this->env;
    }

    public function setArguments(array $args): self
    {
        $this->arguments = $args;
        return $this;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * wasi:clocks/monotonic-clock:now() -> u64 (nanoseconds)
     */
    public function monotonicNow(): int
    {
        return (int)(hrtime(true));
    }

    /**
     * wasi:clocks/wall-clock:now() -> datetime (seconds, nanoseconds)
     */
    public function wallClockNow(): array
    {
        $now = microtime(true);
        $sec = (int)$now;
        $nanosec = (int)(($now - $sec) * 1e9);
        return ['seconds' => $sec, 'nanoseconds' => $nanosec];
    }

    /**
     * wasi:cli/stdout:write(bytes)
     */
    public function writeStdout(string $data): void
    {
        $this->stdoutBuffer .= $data;
    }

    public function getStdout(): string
    {
        return $this->stdoutBuffer;
    }

    public function clearStdout(): void
    {
        $this->stdoutBuffer = '';
    }

    /**
     * wasi:filesystem/types:write-file(path, data)
     */
    public function writeFile(string $path, string $data): bool
    {
        $this->virtualFs[$path] = $data;
        return true;
    }

    /**
     * wasi:filesystem/types:read-file(path)
     */
    public function readFile(string $path): ?string
    {
        return $this->virtualFs[$path] ?? null;
    }

    public function fileExists(string $path): bool
    {
        return isset($this->virtualFs[$path]);
    }

    /**
     * wasi:http/outgoing-handler:handle(method, url, headers, body)
     */
    public function handleOutgoingHttp(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        return [
            'status'  => 200,
            'headers' => array_merge(['content-type' => 'application/json'], $headers),
            'body'    => json_encode(['mock' => true, 'url' => $url, 'method' => $method]),
        ];
    }

    /**
     * Export all WASI P2 host functions for Wasm instance linking.
     */
    public function getHostFunctions(): array
    {
        return [
            'wasi:cli/environment#get-environment' => fn(): array => $this->env,
            'wasi:cli/environment#get-arguments'   => fn(): array => $this->arguments,
            'wasi:clocks/monotonic-clock#now'      => fn(): int => $this->monotonicNow(),
            'wasi:filesystem/types#read-file'      => fn(string $path): ?string => $this->readFile($path),
            'wasi:filesystem/types#write-file'     => fn(string $path, string $data): bool => $this->writeFile($path, $data),
            'wasi:http/outgoing-handler#handle'    => fn(string $method, string $url): array => $this->handleOutgoingHttp($method, $url),
        ];
    }
}
