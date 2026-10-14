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

class Quaternion
{
    public function __construct(
        public float $x = 0.0,
        public float $y = 0.0,
        public float $z = 0.0,
        public float $w = 1.0
    ) {
    }

    public static function identity(): self
    {
        return new self(0.0, 0.0, 0.0, 1.0);
    }

    public static function fromAxisAngle(Vector3 $axis, float $angleRadians): self
    {
        $normAxis = $axis->normalize();
        $half = $angleRadians * 0.5;
        $sinHalf = sin($half);

        return (new self(
            $normAxis->x * $sinHalf,
            $normAxis->y * $sinHalf,
            $normAxis->z * $sinHalf,
            cos($half)
        ))->normalize();
    }

    public static function fromEuler(float $pitch, float $yaw, float $roll): self
    {
        $c1 = cos($yaw * 0.5);
        $c2 = cos($pitch * 0.5);
        $c3 = cos($roll * 0.5);
        $s1 = sin($yaw * 0.5);
        $s2 = sin($pitch * 0.5);
        $s3 = sin($roll * 0.5);

        return (new self(
            ($s1 * $s2 * $c3) + ($c1 * $c2 * $s3),
            ($s1 * $c2 * $c3) + ($c1 * $s2 * $s3),
            ($c1 * $s2 * $c3) - ($s1 * $c2 * $s3),
            ($c1 * $c2 * $c3) - ($s1 * $s2 * $s3)
        ))->normalize();

    }

    public function multiply(self $other): self
    {
        return new self(
            ($this->w * $other->x) + ($this->x * $other->w) + ($this->y * $other->z) - ($this->z * $other->y),
            ($this->w * $other->y) - ($this->x * $other->z) + ($this->y * $other->w) + ($this->z * $other->x),
            ($this->w * $other->z) + ($this->x * $other->y) - ($this->y * $other->x) + ($this->z * $other->w),
            ($this->w * $other->w) - ($this->x * $other->x) - ($this->y * $other->y) - ($this->z * $other->z)
        );
    }

    public function rotateVector(Vector3 $v): Vector3
    {
        $u = new Vector3($this->x, $this->y, $this->z);
        $s = $this->w;

        // 2.0 * dot(u, v) * u + (s*s - dot(u, u)) * v + 2.0 * s * cross(u, v)
        $dotUV = $u->dot($v);
        $dotUU = $u->dot($u);
        $crossUV = $u->cross($v);

        $term1 = $u->scale(2.0 * $dotUV);
        $term2 = $v->scale(($s * $s) - $dotUU);
        $term3 = $crossUV->scale(2.0 * $s);

        return $term1->add($term2)->add($term3);
    }

    public function length(): float
    {
        return sqrt(($this->x * $this->x) + ($this->y * $this->y) + ($this->z * $this->z) + ($this->w * $this->w));
    }

    public function normalize(): self
    {
        $len = $this->length();
        if ($len < 1e-9) {
            return self::identity();
        }
        return new self($this->x / $len, $this->y / $len, $this->z / $len, $this->w / $len);
    }

    /**
     * Spherical Linear Interpolation (SLERP)
     */
    public function slerp(self $target, float $t): self
    {
        $clampedT = max(0.0, min(1.0, $t));
        $cosOmega = ($this->x * $target->x) + ($this->y * $target->y) + ($this->z * $target->z) + ($this->w * $target->w);

        $targetX = $target->x;
        $targetY = $target->y;
        $targetZ = $target->z;
        $targetW = $target->w;

        if ($cosOmega < 0.0) {
            $cosOmega = -$cosOmega;
            $targetX = -$targetX;
            $targetY = -$targetY;
            $targetZ = -$targetZ;
            $targetW = -$targetW;
        }

        if ($cosOmega > 0.9995) {
            // Linear interpolation when very close
            return (new self(
                $this->x + (($targetX - $this->x) * $clampedT),
                $this->y + (($targetY - $this->y) * $clampedT),
                $this->z + (($targetZ - $this->z) * $clampedT),
                $this->w + (($targetW - $this->w) * $clampedT)
            ))->normalize();
        }

        $omega = acos($cosOmega);
        $sinOmega = sin($omega);

        $scale1 = sin((1.0 - $clampedT) * $omega) / $sinOmega;
        $scale2 = sin($clampedT * $omega) / $sinOmega;

        return new self(
            ($scale1 * $this->x) + ($scale2 * $targetX),
            ($scale1 * $this->y) + ($scale2 * $targetY),
            ($scale1 * $this->z) + ($scale2 * $targetZ),
            ($scale1 * $this->w) + ($scale2 * $targetW)
        );
    }

    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y, 'z' => $this->z, 'w' => $this->w];
    }
}
