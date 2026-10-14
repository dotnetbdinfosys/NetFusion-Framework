<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Command.php
| Module    : Core\Console
| Namespace : NetFusion\Core\Console
| Purpose   : Abstract base CLI command class supporting options, arguments, and ANSI colored output.
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

namespace NetFusion\Core\Console;

use NetFusion\Core\Application;

/**
 * NetFusion Base Console Command
 *
 * Provides argument parsing, option retrieval (--flag or --key=val),
 * formatted ANSI terminal output, and lifecycle management.
 *
 * @package NetFusion\Core\Console
 * @version 1.0.0
 */
abstract class Command
{
    /**
     * The console command name (e.g. 'make:controller').
     */
    protected string $name = '';

    /**
     * The console command description.
     */
    protected string $description = '';

    /**
     * Parsed positional arguments.
     *
     * @var string[]
     */
    protected array $arguments = [];

    /**
     * Parsed options (--key=value or --flag).
     *
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * @param Application $app
     */
    public function __construct(
        protected readonly Application $app
    ) {}

    /**
     * Execute the console command.
     *
     * @return int Exit code (0 for success, non-zero for failure).
     */
    abstract public function handle(): int;

    /**
     * Set parsed input arguments and options.
     *
     * @param string[]             $arguments
     * @param array<string, mixed> $options
     */
    public function setInput(array $arguments, array $options): static
    {
        $this->arguments = $arguments;
        $this->options   = $options;
        return $this;
    }

    /**
     * Get an argument by 0-based index or return default.
     */
    public function argument(int $index, ?string $default = null): ?string
    {
        return $this->arguments[$index] ?? $default;
    }

    /**
     * Get an option by name (--option=value).
     */
    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Check if a boolean flag option was passed (--flag).
     */
    public function hasOption(string $key): bool
    {
        return isset($this->options[$key]);
    }

    /**
     * Get the command name.
     */
    public function getName(): string
    {
        if ($this->name !== '') {
            return $this->name;
        }

        if (isset($this->signature) && $this->signature !== '') {
            return explode(' ', $this->signature)[0];
        }

        return '';
    }

    /**
     * Get the command description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    // =========================================================================
    //  Terminal Output Helpers
    // =========================================================================

    /**
     * Write raw message to stdout.
     */
    public function write(string $message): void
    {
        echo $message;
    }

    /**
     * Write a line of text to stdout.
     */
    public function line(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }

    /**
     * Write an info message (cyan/blue).
     */
    public function info(string $message): void
    {
        $this->line($this->color($message, '36'));
    }

    /**
     * Write a success message (green).
     */
    public function success(string $message): void
    {
        $this->line($this->color('✔ ' . $message, '32'));
    }

    /**
     * Write a warning message (yellow).
     */
    public function warn(string $message): void
    {
        $this->line($this->color('⚠ ' . $message, '33'));
    }

    /**
     * Write a warning message (alias).
     */
    public function warning(string $message): void
    {
        $this->warn($message);
    }

    /**
     * Write an error message (red).
     */
    public function error(string $message): void
    {
        $this->line($this->color('✖ ' . $message, '31'));
    }

    /**
     * Render an ANSI colored string.
     */
    protected function color(string $text, string $code): string
    {
        // Simple ANSI escape sequence
        return "\033[{$code}m{$text}\033[0m";
    }

    /**
     * Render an ASCII table in the console.
     *
     * @param string[] $headers
     * @param array<int, array<int, string>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $colWidths = [];
        foreach ($headers as $idx => $header) {
            $colWidths[$idx] = strlen($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $idx => $cell) {
                $len = strlen((string) $cell);
                if (!isset($colWidths[$idx]) || $len > $colWidths[$idx]) {
                    $colWidths[$idx] = $len;
                }
            }
        }

        // Top border
        $sep = '+';
        foreach ($colWidths as $w) {
            $sep .= str_repeat('-', $w + 2) . '+';
        }

        $this->line($sep);

        // Header
        $headerRow = '|';
        foreach ($headers as $idx => $header) {
            $headerRow .= ' ' . str_pad($header, $colWidths[$idx]) . ' |';
        }
        $this->line($this->color($headerRow, '36;1'));
        $this->line($sep);

        // Rows
        foreach ($rows as $row) {
            $rStr = '|';
            foreach ($headers as $idx => $h) {
                $cell = (string) ($row[$idx] ?? '');
                $rStr .= ' ' . str_pad($cell, $colWidths[$idx]) . ' |';
            }
            $this->line($rStr);
        }

        // Bottom border
        $this->line($sep);
    }
}
