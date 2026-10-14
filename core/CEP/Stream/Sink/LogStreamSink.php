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

namespace NetFusion\Core\CEP\Stream\Sink;

use NetFusion\Core\CEP\Contracts\SinkInterface;

class LogStreamSink implements SinkInterface
{
    protected string $name;
    protected ?string $logFile;
    protected string $level;

    public function __construct(string $name = 'log_sink', ?string $logFile = null, string $level = 'INFO')
    {
        $this->name = $name;
        $this->logFile = $logFile;
        $this->level = strtoupper($level);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function emit(mixed $data, array $context = []): void
    {
        $message = sprintf(
            "[%s] [CEP:%s] [%s] %s\n",
            date('Y-m-d H:i:s'),
            $this->level,
            $this->name,
            json_encode(['data' => $data, 'context' => $context], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        if ($this->logFile !== null) {
            @file_put_contents($this->logFile, $message, FILE_APPEND | LOCK_EX);
        }
    }
}
