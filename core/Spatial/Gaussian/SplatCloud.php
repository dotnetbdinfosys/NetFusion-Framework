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

use JsonSerializable;
use NetFusion\Core\Spatial\Math\Vector3;

class SplatCloud implements JsonSerializable
{
    /** @var GaussianPrimitive[] */
    protected array $splats = [];

    public function __construct(protected string $id, protected string $name = 'Gaussian Splat Cloud')
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addSplat(GaussianPrimitive $splat): void
    {
        $this->splats[] = $splat;
    }

    /**
     * @return GaussianPrimitive[]
     */
    public function getSplats(): array
    {
        return $this->splats;
    }

    public function count(): int
    {
        return count($this->splats);
    }

    /**
     * Depth-sort splats relative to camera position for alpha compositing
     *
     * @return GaussianPrimitive[]
     */
    public function sortByDepth(Vector3 $cameraPosition): array
    {
        $sorted = $this->splats;
        usort($sorted, function (GaussianPrimitive $a, GaussianPrimitive $b) use ($cameraPosition) {
            $distA = $a->position->distanceTo($cameraPosition);
            $distB = $b->position->distanceTo($cameraPosition);
            // Back-to-front rendering for transparency
            return $distB <=> $distA;
        });
        return $sorted;
    }

    /**
     * Compute Axis-Aligned Bounding Box (AABB) of the splat cloud
     *
     * @return array{min: Vector3, max: Vector3, center: Vector3}
     */
    public function computeBounds(): array
    {
        if (empty($this->splats)) {
            return [
                'min' => Vector3::zero(),
                'max' => Vector3::zero(),
                'center' => Vector3::zero(),
            ];
        }

        $minX = $maxX = $this->splats[0]->position->x;
        $minY = $maxY = $this->splats[0]->position->y;
        $minZ = $maxZ = $this->splats[0]->position->z;

        foreach ($this->splats as $s) {
            $p = $s->position;
            if ($p->x < $minX) $minX = $p->x;
            if ($p->x > $maxX) $maxX = $p->x;
            if ($p->y < $minY) $minY = $p->y;
            if ($p->y > $maxY) $maxY = $p->y;
            if ($p->z < $minZ) $minZ = $p->z;
            if ($p->z > $maxZ) $maxZ = $p->z;
        }

        $min = new Vector3($minX, $minY, $minZ);
        $max = new Vector3($maxX, $maxY, $maxZ);
        $center = $min->add($max)->scale(0.5);

        return ['min' => $min, 'max' => $max, 'center' => $center];
    }

    public function toArray(): array
    {
        $items = [];
        foreach ($this->splats as $s) {
            $items[] = $s->toArray();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'totalSplats' => count($this->splats),
            'bounds' => [
                'min' => $this->computeBounds()['min']->toArray(),
                'max' => $this->computeBounds()['max']->toArray(),
                'center' => $this->computeBounds()['center']->toArray(),
            ],
            'splats' => $items,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
