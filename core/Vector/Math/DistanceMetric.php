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

namespace NetFusion\Core\Vector\Math;

use NetFusion\Core\Vector\Contracts\VectorInterface;

enum DistanceMetric: string
{
    case COSINE = 'cosine';
    case EUCLIDEAN = 'euclidean';
    case DOT_PRODUCT = 'dot_product';
    case MANHATTAN = 'manhattan';

    /**
     * Compute distance between two vectors according to metric.
     * Lower distance = more similar.
     */
    public function calculateDistance(VectorInterface $a, VectorInterface $b): float
    {
        return match ($this) {
            self::COSINE => $a->cosineDistance($b),
            self::EUCLIDEAN => $a->euclideanDistance($b),
            self::DOT_PRODUCT => -1.0 * $a->dot($b), // inverted so lower is more similar
            self::MANHATTAN => $a->manhattanDistance($b),
        };
    }

    /**
     * Compute similarity score between two vectors according to metric.
     * Higher score = more similar.
     */
    public function calculateSimilarity(VectorInterface $a, VectorInterface $b): float
    {
        return match ($this) {
            self::COSINE => $a->cosineSimilarity($b),
            self::EUCLIDEAN => 1.0 / (1.0 + $a->euclideanDistance($b)),
            self::DOT_PRODUCT => $a->dot($b),
            self::MANHATTAN => 1.0 / (1.0 + $a->manhattanDistance($b)),
        };
    }
}
