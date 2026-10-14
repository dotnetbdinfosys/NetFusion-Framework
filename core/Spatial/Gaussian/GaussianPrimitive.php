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

namespace NetFusion\Core\Spatial\Gaussian;

use NetFusion\Core\Spatial\Math\Quaternion;
use NetFusion\Core\Spatial\Math\Vector3;

class GaussianPrimitive
{
    /**
     * @param float[] $colorRgb RGB in range [0.0, 1.0]
     * @param float[] $shCoefficients Spherical Harmonics bands
     */
    public function __construct(
        public Vector3 $position,
        public Vector3 $scale,
        public Quaternion $rotation,
        public float $opacity = 1.0,
        public array $colorRgb = [1.0, 1.0, 1.0],
        public array $shCoefficients = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'position' => $this->position->toArray(),
            'scale' => $this->scale->toArray(),
            'rotation' => $this->rotation->toArray(),
            'opacity' => $this->opacity,
            'color' => $this->colorRgb,
            'sh' => $this->shCoefficients,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            position: Vector3::fromArray($data['position'] ?? []),
            scale: Vector3::fromArray($data['scale'] ?? ['x' => 1.0, 'y' => 1.0, 'z' => 1.0]),
            rotation: isset($data['rotation']) ? new Quaternion(
                (float) ($data['rotation']['x'] ?? 0.0),
                (float) ($data['rotation']['y'] ?? 0.0),
                (float) ($data['rotation']['z'] ?? 0.0),
                (float) ($data['rotation']['w'] ?? 1.0)
            ) : Quaternion::identity(),
            opacity: (float) ($data['opacity'] ?? 1.0),
            colorRgb: (array) ($data['color'] ?? [1.0, 1.0, 1.0]),
            shCoefficients: (array) ($data['sh'] ?? [])
        );
    }
}
