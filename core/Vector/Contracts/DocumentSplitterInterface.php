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

namespace NetFusion\Core\Vector\Contracts;

use NetFusion\Core\Vector\Chunking\Chunk;

interface DocumentSplitterInterface
{
    /**
     * Split a raw document text into structured chunks.
     *
     * @param string $text
     * @param array<string, mixed> $metadata
     * @return array<Chunk>
     */
    public function splitText(string $text, array $metadata = []): array;
}
