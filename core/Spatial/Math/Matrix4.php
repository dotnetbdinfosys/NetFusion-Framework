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

class Matrix4
{
    /**
     * 4x4 matrix stored in row-major order (16 floats)
     *
     * @var float[]
     */
    public array $elements;

    public function __construct(?array $elements = null)
    {
        $this->elements = $elements ?? [
            1.0, 0.0, 0.0, 0.0,
            0.0, 1.0, 0.0, 0.0,
            0.0, 0.0, 1.0, 0.0,
            0.0, 0.0, 0.0, 1.0,
        ];
    }

    public static function identity(): self
    {
        return new self();
    }

    public static function compose(Vector3 $translation, Quaternion $rotation, Vector3 $scale): self
    {
        $x = $rotation->x;
        $y = $rotation->y;
        $z = $rotation->z;
        $w = $rotation->w;

        $x2 = $x + $x;
        $y2 = $y + $y;
        $z2 = $z + $z;
        $xx = $x * $x2;
        $xy = $x * $y2;
        $xz = $x * $z2;
        $yy = $y * $y2;
        $yz = $y * $z2;
        $zz = $z * $z2;
        $wx = $w * $x2;
        $wy = $w * $y2;
        $wz = $w * $z2;

        $sx = $scale->x;
        $sy = $scale->y;
        $sz = $scale->z;

        return new self([
            (1.0 - ($yy + $zz)) * $sx, ($xy - $wz) * $sy, ($xz + $wy) * $sz, $translation->x,
            ($xy + $wz) * $sx, (1.0 - ($xx + $zz)) * $sy, ($yz - $wx) * $sz, $translation->y,
            ($xz - $wy) * $sx, ($yz + $wx) * $sy, (1.0 - ($xx + $yy)) * $sz, $translation->z,
            0.0, 0.0, 0.0, 1.0,
        ]);
    }

    public function multiply(self $b): self
    {
        $ae = $this->elements;
        $be = $b->elements;
        $te = array_fill(0, 16, 0.0);

        for ($row = 0; $row < 4; $row++) {
            for ($col = 0; $col < 4; $col++) {
                $sum = 0.0;
                for ($k = 0; $k < 4; $k++) {
                    $sum += $ae[($row * 4) + $k] * $be[($k * 4) + $col];
                }
                $te[($row * 4) + $col] = $sum;
            }
        }

        return new self($te);
    }

    public function transformPoint(Vector3 $v): Vector3
    {
        $e = $this->elements;
        $x = $v->x;
        $y = $v->y;
        $z = $v->z;

        $w = ($e[12] * $x) + ($e[13] * $y) + ($e[14] * $z) + $e[15];
        $w = abs($w) > 1e-9 ? 1.0 / $w : 1.0;

        return new Vector3(
            (($e[0] * $x) + ($e[1] * $y) + ($e[2] * $z) + $e[3]) * $w,
            (($e[4] * $x) + ($e[5] * $y) + ($e[6] * $z) + $e[7]) * $w,
            (($e[8] * $x) + ($e[9] * $y) + ($e[10] * $z) + $e[11]) * $w
        );
    }

    public function getTranslation(): Vector3
    {
        return new Vector3($this->elements[3], $this->elements[7], $this->elements[11]);
    }

    public function toArray(): array
    {
        return $this->elements;
    }
}
