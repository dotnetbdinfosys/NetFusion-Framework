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
use NetFusion\Core\Vector\Exceptions\DimensionMismatchException;

class Vector implements VectorInterface
{
    /**
     * @var array<float>
     */
    protected array $values;

    protected int $dimension;

    protected ?float $cachedNorm = null;

    /**
     * @param array<int|float> $values
     */
    public function __construct(array $values)
    {
        // Convert all elements to pure float
        $this->values = array_map(fn($v) => (float)$v, array_values($values));
        $this->dimension = count($this->values);
    }

    /**
     * Create vector from raw values or instance.
     *
     * @param array<int|float>|VectorInterface $values
     */
    public static function from(array|VectorInterface $values): VectorInterface
    {
        if ($values instanceof VectorInterface) {
            return $values;
        }

        return new self($values);
    }

    /**
     * Create zero vector of given dimension.
     */
    public static function zeros(int $dimension): self
    {
        return new self(array_fill(0, $dimension, 0.0));
    }

    /**
     * Create random normalized vector of given dimension.
     */
    public static function random(int $dimension): self
    {
        $vals = [];
        for ($i = 0; $i < $dimension; $i++) {
            $vals[] = (mt_rand(-1000, 1000) / 1000.0);
        }
        $vec = new self($vals);
        return $vec->normalize();
    }

    public function dimension(): int
    {
        return $this->dimension;
    }

    public function toArray(): array
    {
        return $this->values;
    }

    public function dot(VectorInterface $other): float
    {
        if ($this->dimension !== $other->dimension()) {
            throw new DimensionMismatchException($this->dimension, $other->dimension());
        }

        $otherValues = $other->toArray();
        $sum = 0.0;
        $count = $this->dimension;

        for ($i = 0; $i < $count; $i++) {
            $sum += $this->values[$i] * $otherValues[$i];
        }

        return $sum;
    }

    public function euclideanDistance(VectorInterface $other): float
    {
        if ($this->dimension !== $other->dimension()) {
            throw new DimensionMismatchException($this->dimension, $other->dimension());
        }

        $otherValues = $other->toArray();
        $sum = 0.0;
        $count = $this->dimension;

        for ($i = 0; $i < $count; $i++) {
            $diff = $this->values[$i] - $otherValues[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    public function cosineSimilarity(VectorInterface $other): float
    {
        $normA = $this->norm();
        $normB = $other->norm();

        if ($normA <= 1e-12 || $normB <= 1e-12) {
            return 0.0;
        }

        $dot = $this->dot($other);
        $sim = $dot / ($normA * $normB);

        // Bound to [-1.0, 1.0] to account for floating point inaccuracies
        return max(-1.0, min(1.0, $sim));
    }

    public function cosineDistance(VectorInterface $other): float
    {
        return 1.0 - $this->cosineSimilarity($other);
    }

    public function manhattanDistance(VectorInterface $other): float
    {
        if ($this->dimension !== $other->dimension()) {
            throw new DimensionMismatchException($this->dimension, $other->dimension());
        }

        $otherValues = $other->toArray();
        $sum = 0.0;
        $count = $this->dimension;

        for ($i = 0; $i < $count; $i++) {
            $sum += abs($this->values[$i] - $otherValues[$i]);
        }

        return $sum;
    }

    public function norm(): float
    {
        if ($this->cachedNorm !== null) {
            return $this->cachedNorm;
        }

        $sum = 0.0;
        foreach ($this->values as $val) {
            $sum += $val * $val;
        }

        $this->cachedNorm = sqrt($sum);
        return $this->cachedNorm;
    }

    public function normalize(): self
    {
        $norm = $this->norm();
        if ($norm <= 1e-12) {
            return new self($this->values);
        }

        $normalized = [];
        foreach ($this->values as $val) {
            $normalized[] = $val / $norm;
        }

        return new self($normalized);
    }

    /**
     * Add another vector.
     */
    public function add(VectorInterface $other): self
    {
        if ($this->dimension !== $other->dimension()) {
            throw new DimensionMismatchException($this->dimension, $other->dimension());
        }

        $otherValues = $other->toArray();
        $res = [];
        for ($i = 0; $i < $this->dimension; $i++) {
            $res[] = $this->values[$i] + $otherValues[$i];
        }

        return new self($res);
    }

    /**
     * Multiply vector by scalar.
     */
    public function multiply(float $scalar): self
    {
        $res = [];
        foreach ($this->values as $val) {
            $res[] = $val * $scalar;
        }

        return new self($res);
    }

    public function count(): int
    {
        return $this->dimension;
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_int($offset) && $offset >= 0 && $offset < $this->dimension;
    }

    public function offsetGet(mixed $offset): ?float
    {
        return $this->values[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // Vectors are immutable
    }

    public function offsetUnset(mixed $offset): void
    {
        // Vectors are immutable
    }

    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
