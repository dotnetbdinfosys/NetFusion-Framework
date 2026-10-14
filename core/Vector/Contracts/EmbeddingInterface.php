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

interface EmbeddingInterface
{
    /**
     * Generate dense vector embedding for single text.
     */
    public function embedText(string $text): VectorInterface;

    /**
     * Generate dense vector embeddings for a batch of texts.
     *
     * @param array<string> $texts
     * @return array<VectorInterface>
     */
    public function embedBatch(array $texts): array;

    /**
     * Get vector dimension produced by this embedding provider.
     */
    public function getDimension(): int;

    /**
     * Get model identifier.
     */
    public function getModel(): string;
}
