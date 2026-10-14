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

use JsonSerializable;
use ArrayAccess;
use Countable;

interface VectorInterface extends JsonSerializable, ArrayAccess, Countable
{
    /**
     * Get vector dimension.
     */
    public function dimension(): int;

    /**
     * Get underlying float array values.
     *
     * @return array<float>
     */
    public function toArray(): array;

    /**
     * Compute dot product with another vector.
     */
    public function dot(VectorInterface $other): float;

    /**
     * Compute Euclidean (L2) distance to another vector.
     */
    public function euclideanDistance(VectorInterface $other): float;

    /**
     * Compute Cosine similarity to another vector (-1.0 to 1.0).
     */
    public function cosineSimilarity(VectorInterface $other): float;

    /**
     * Compute Cosine distance to another vector (0.0 to 2.0).
     */
    public function cosineDistance(VectorInterface $other): float;

    /**
     * Compute Manhattan (L1) distance to another vector.
     */
    public function manhattanDistance(VectorInterface $other): float;

    /**
     * Calculate L2 norm (magnitude).
     */
    public function norm(): float;

    /**
     * Return a normalized unit vector (L2 norm = 1.0).
     */
    public function normalize(): VectorInterface;
}
