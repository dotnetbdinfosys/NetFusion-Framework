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

namespace NetFusion\Core\Vector\Embedding;

use NetFusion\Core\Vector\Contracts\EmbeddingInterface;
use NetFusion\Core\Vector\Contracts\VectorInterface;
use NetFusion\Core\Vector\Math\Vector;

class MockEmbedding implements EmbeddingInterface
{
    /**
     * @param int $dimension Vector dimension (default 64)
     * @param string $model Model name
     */
    public function __construct(
        protected int $dimension = 64,
        protected string $model = 'mock-embedding-v1'
    ) {
    }

    public function embedText(string $text): VectorInterface
    {
        $clean = mb_strtolower(trim($text), 'UTF-8');
        $words = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $values = array_fill(0, $this->dimension, 0.0);

        if (empty($words)) {
            $vec = new Vector($values);
            return $vec;
        }

        foreach ($words as $word) {
            // Compute deterministic pseudo-random hash projections for each word
            $hash = crc32($word);
            $seed = abs($hash);
            
            for ($d = 0; $d < $this->dimension; $d++) {
                // Linear congruential projection
                $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
                $val = (($seed / 0x7FFFFFFF) * 2.0) - 1.0;
                $values[$d] += $val;
            }
        }

        // Apply L2 normalization
        $vector = new Vector($values);
        return $vector->normalize();
    }

    public function embedBatch(array $texts): array
    {
        $result = [];
        foreach ($texts as $text) {
            $result[] = $this->embedText((string)$text);
        }
        return $result;
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
