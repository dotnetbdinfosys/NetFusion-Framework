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

namespace NetFusion\Core\Wasm\Wasi;

use NetFusion\Core\Wasm\Vm\LinearMemory;

/**
 * Sandboxed WASI (WebAssembly System Interface) Preview 1 Provider.
 * Intercepts stdout/stderr, provides clocks, entropy, and environment in memory.
 */
class WasiSnapshotPreview1 implements HostFunctionInterface
{
    public const ESUCCESS = 0;
    public const EBADF = 8;
    public const EINVAL = 28;

    private string $stdout = '';
    private string $stderr = '';
    private ?int $exitCode = null;
    /** @var array<string, string> */
    private array $env = [];
    /** @var array<string> */
    private array $args = [];

    public function __construct(array $env = [], array $args = [])
    {
        $this->env = $env;
        $this->args = $args;
    }

    public function getModuleName(): string
    {
        return 'wasi_snapshot_preview1';
    }

    public function getStdout(): string
    {
        return $this->stdout;
    }

    public function getStderr(): string
    {
        return $this->stderr;
    }

    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    public function clearBuffers(): void
    {
        $this->stdout = '';
        $this->stderr = '';
        $this->exitCode = null;
    }

    public function getFunctions(?LinearMemory $memory = null): array
    {
        return [
            'fd_write' => function (int $fd, int $iovsPtr, int $iovsLen, int $nwrittenPtr) use ($memory): int {
                if ($memory === null) {
                    return self::EBADF;
                }

                $totalWritten = 0;
                for ($i = 0; $i < $iovsLen; $i++) {
                    // Each ciovec is: ptr (i32, 4 bytes), len (i32, 4 bytes)
                    $iovOffset = $iovsPtr + ($i * 8);
                    $bufPtr = $memory->readI32($iovOffset);
                    $bufLen = $memory->readI32($iovOffset + 4);

                    if ($bufLen > 0) {
                        $chunk = $memory->readBytes($bufPtr, $bufLen);
                        $totalWritten += $bufLen;

                        if ($fd === 1) {
                            $this->stdout .= $chunk;
                        } elseif ($fd === 2) {
                            $this->stderr .= $chunk;
                        }
                    }
                }

                // Write total written byte count to nwrittenPtr
                if ($nwrittenPtr > 0) {
                    $memory->writeI32($nwrittenPtr, $totalWritten);
                }

                return self::ESUCCESS;
            },

            'fd_read' => function (int $fd, int $iovsPtr, int $iovsLen, int $nreadPtr) use ($memory): int {
                if ($memory !== null && $nreadPtr > 0) {
                    $memory->writeI32($nreadPtr, 0);
                }
                return self::ESUCCESS;
            },

            'fd_close' => fn(int $fd): int => self::ESUCCESS,
            'fd_seek' => fn(int $fd, int $offset, int $whence, int $newOffsetPtr): int => self::ESUCCESS,

            'clock_time_get' => function (int $clockId, int $precision, int $timePtr) use ($memory): int {
                if ($memory !== null && $timePtr > 0) {
                    // Nanoseconds since Unix epoch
                    $nanos = (int) (microtime(true) * 1_000_000_000);
                    $memory->writeI64($timePtr, $nanos);
                }
                return self::ESUCCESS;
            },

            'random_get' => function (int $bufPtr, int $bufLen) use ($memory): int {
                if ($memory !== null && $bufLen > 0) {
                    $bytes = random_bytes($bufLen);
                    $memory->writeBytes($bufPtr, $bytes);
                }
                return self::ESUCCESS;
            },

            'proc_exit' => function (int $exitCode): int {
                $this->exitCode = $exitCode;
                return self::ESUCCESS;
            },

            'environ_sizes_get' => function (int $countPtr, int $sizePtr) use ($memory): int {
                if ($memory !== null) {
                    $totalSize = 0;
                    foreach ($this->env as $k => $v) {
                        $totalSize += strlen("{$k}={$v}\x00");
                    }
                    $memory->writeI32($countPtr, count($this->env));
                    $memory->writeI32($sizePtr, $totalSize);
                }
                return self::ESUCCESS;
            },

            'environ_get' => function (int $environPtr, int $environBufPtr) use ($memory): int {
                if ($memory === null) {
                    return self::EINVAL;
                }

                $bufOffset = $environBufPtr;
                $idx = 0;
                foreach ($this->env as $k => $v) {
                    $entry = "{$k}={$v}\x00";
                    $memory->writeI32($environPtr + ($idx * 4), $bufOffset);
                    $memory->writeBytes($bufOffset, $entry);
                    $bufOffset += strlen($entry);
                    $idx++;
                }

                return self::ESUCCESS;
            },

            'args_sizes_get' => function (int $argcPtr, int $argvBufSizePtr) use ($memory): int {
                if ($memory !== null) {
                    $totalSize = 0;
                    foreach ($this->args as $arg) {
                        $totalSize += strlen("{$arg}\x00");
                    }
                    $memory->writeI32($argcPtr, count($this->args));
                    $memory->writeI32($argvBufSizePtr, $totalSize);
                }
                return self::ESUCCESS;
            },

            'args_get' => function (int $argvPtr, int $argvBufPtr) use ($memory): int {
                if ($memory === null) {
                    return self::EINVAL;
                }

                $bufOffset = $argvBufPtr;
                $idx = 0;
                foreach ($this->args as $arg) {
                    $entry = "{$arg}\x00";
                    $memory->writeI32($argvPtr + ($idx * 4), $bufOffset);
                    $memory->writeBytes($bufOffset, $entry);
                    $bufOffset += strlen($entry);
                    $idx++;
                }

                return self::ESUCCESS;
            },
        ];
    }
}
