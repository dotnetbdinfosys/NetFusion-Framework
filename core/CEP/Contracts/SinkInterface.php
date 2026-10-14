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

namespace NetFusion\Core\CEP\Contracts;

interface SinkInterface
{
    /**
     * Get sink connector name.
     */
    public function getName(): string;

    /**
     * Send/write processed result or alert to sink.
     *
     * @param mixed $data Output data or pattern match or aggregated window result
     * @param array<string, mixed> $context Pipeline execution context
     */
    public function emit(mixed $data, array $context = []): void;
}
