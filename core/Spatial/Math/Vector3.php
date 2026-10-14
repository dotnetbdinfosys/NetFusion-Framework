<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Spatial Computing & WebXR Hyper-Spatial Digital Twin
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Spatial\Math;

class Vector3
{
    public function __construct(
        public float $x = 0.0,
        public float $y = 0.0,
        public float $z = 0.0
    ) {
    }

    public static function zero(): self
    {
        return new self(0.0, 0.0, 0.0);
    }

    public static function one(): self
    {
        return new self(1.0, 1.0, 1.0);
    }

    public static function up(): self
    {
        return new self(0.0, 1.0, 0.0);
    }

    public function add(self $other): self
    {
        return new self($this->x + $other->x, $this->y + $other->y, $this->z + $other->z);
    }

    public function subtract(self $other): self
    {
        return new self($this->x - $other->x, $this->y - $other->y, $this->z - $other->z);
    }

    public function scale(float $scalar): self
    {
        return new self($this->x * $scalar, $this->y * $scalar, $this->z * $scalar);
    }

    public function dot(self $other): float
    {
        return ($this->x * $other->x) + ($this->y * $other->y) + ($this->z * $other->z);
    }

    public function cross(self $other): self
    {
        return new self(
            ($this->y * $other->z) - ($this->z * $other->y),
            ($this->z * $other->x) - ($this->x * $other->z),
            ($this->x * $other->y) - ($this->y * $other->x)
        );
    }

    public function length(): float
    {
        return sqrt(($this->x * $this->x) + ($this->y * $this->y) + ($this->z * $this->z));
    }

    public function distanceTo(self $other): float
    {
        return $this->subtract($other)->length();
    }

    public function normalize(): self
    {
        $len = $this->length();
        if ($len < 1e-9) {
            return self::zero();
        }
        return new self($this->x / $len, $this->y / $len, $this->z / $len);
    }

    public function lerp(self $target, float $t): self
    {
        $clampedT = max(0.0, min(1.0, $t));
        return new self(
            $this->x + (($target->x - $this->x) * $clampedT),
            $this->y + (($target->y - $this->y) * $clampedT),
            $this->z + (($target->z - $this->z) * $clampedT)
        );
    }

    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y, 'z' => $this->z];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            x: (float) ($data['x'] ?? $data[0] ?? 0.0),
            y: (float) ($data['y'] ?? $data[1] ?? 0.0),
            z: (float) ($data['z'] ?? $data[2] ?? 0.0)
        );
    }
}
