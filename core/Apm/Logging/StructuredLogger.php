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

namespace NetFusion\Core\Apm\Logging;

use Psr\Log\LogLevel;

/**
 * Class StructuredLogger
 *
 * PSR-3 compatible structured JSON logger.
 *
 * Emits log records as JSON lines to a configurable output target
 * (file, STDOUT, STDERR, or in-memory buffer for testing).
 *
 * Log levels: emergency, alert, critical, error, warning, notice, info, debug
 */
class StructuredLogger
{
    private const LEVELS = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    ];

    /** @var array[] In-memory log buffer (when $output is null) */
    private array  $buffer   = [];
    private string $channel;
    private int    $minLevel;
    private array  $context  = [];
    private ?string $outputFile;

    public function __construct(
        string  $channel     = 'app',
        string  $minLevel    = 'debug',
        ?string $outputFile  = null
    ) {
        $this->channel    = $channel;
        $this->minLevel   = self::LEVELS[$minLevel] ?? 100;
        $this->outputFile = $outputFile;
    }

    // ------------------------------------------------------------------
    // Context enrichment
    // ------------------------------------------------------------------

    /** Merge persistent context into every log record */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    // ------------------------------------------------------------------
    // PSR-3 level methods
    // ------------------------------------------------------------------

    public function emergency(string $message, array $context = []): void { $this->log('emergency', $message, $context); }
    public function alert(string $message, array $context = []): void     { $this->log('alert',     $message, $context); }
    public function critical(string $message, array $context = []): void  { $this->log('critical',  $message, $context); }
    public function error(string $message, array $context = []): void     { $this->log('error',     $message, $context); }
    public function warning(string $message, array $context = []): void   { $this->log('warning',   $message, $context); }
    public function notice(string $message, array $context = []): void    { $this->log('notice',    $message, $context); }
    public function info(string $message, array $context = []): void      { $this->log('info',      $message, $context); }
    public function debug(string $message, array $context = []): void     { $this->log('debug',     $message, $context); }

    public function log(string $level, string $message, array $context = []): void
    {
        if ((self::LEVELS[$level] ?? 0) < $this->minLevel) {
            return;
        }

        $record = [
            'timestamp' => date('Y-m-d\TH:i:s.') . substr((string) microtime(), 2, 3) . 'Z',
            'level'     => strtoupper($level),
            'channel'   => $this->channel,
            'message'   => $this->interpolate($message, $context),
            'context'   => array_merge($this->context, $context),
        ];

        // Remove 'exception' from context (handle specially)
        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Throwable) {
            $ex = $record['context']['exception'];
            $record['exception'] = [
                'class'   => get_class($ex),
                'message' => $ex->getMessage(),
                'file'    => $ex->getFile() . ':' . $ex->getLine(),
                'trace'   => array_slice(explode("\n", $ex->getTraceAsString()), 0, 5),
            ];
            unset($record['context']['exception']);
        }

        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        if ($this->outputFile !== null) {
            file_put_contents($this->outputFile, $line, FILE_APPEND | LOCK_EX);
        } else {
            $this->buffer[] = $record;
        }
    }

    // ------------------------------------------------------------------
    // Buffer access (for testing / in-memory mode)
    // ------------------------------------------------------------------

    public function getBuffer(): array { return $this->buffer; }
    public function clearBuffer(): void { $this->buffer = []; }
    public function count(): int { return count($this->buffer); }

    public function getByLevel(string $level): array
    {
        $upper = strtoupper($level);
        return array_values(array_filter($this->buffer, fn($r) => $r['level'] === $upper));
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && !is_object($val)) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }
        return strtr($message, $replace);
    }
}
