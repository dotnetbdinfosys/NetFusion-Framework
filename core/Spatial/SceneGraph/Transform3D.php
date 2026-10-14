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

namespace NetFusion\Core\Spatial\SceneGraph;

use NetFusion\Core\Spatial\Math\Matrix4;
use NetFusion\Core\Spatial\Math\Quaternion;
use NetFusion\Core\Spatial\Math\Vector3;

class Transform3D
{
    protected Matrix4 $localMatrix;
    protected Matrix4 $worldMatrix;
    protected bool $isDirty = true;

    public function __construct(
        public Vector3 $position = new Vector3(),
        public Quaternion $rotation = new Quaternion(),
        public Vector3 $scale = new Vector3(1.0, 1.0, 1.0)
    ) {
        $this->localMatrix = Matrix4::identity();
        $this->worldMatrix = Matrix4::identity();
        $this->updateMatrices();
    }

    public function setPosition(Vector3 $position): self
    {
        $this->position = $position;
        $this->isDirty = true;
        return $this;
    }

    public function setRotation(Quaternion $rotation): self
    {
        $this->rotation = $rotation;
        $this->isDirty = true;
        return $this;
    }

    public function setScale(Vector3 $scale): self
    {
        $this->scale = $scale;
        $this->isDirty = true;
        return $this;
    }

    public function updateMatrices(?Matrix4 $parentWorldMatrix = null): void
    {
        if ($this->isDirty) {
            $this->localMatrix = Matrix4::compose($this->position, $this->rotation, $this->scale);
            $this->isDirty = false;
        }

        if ($parentWorldMatrix !== null) {
            $this->worldMatrix = $parentWorldMatrix->multiply($this->localMatrix);
        } else {
            $this->worldMatrix = $this->localMatrix;
        }
    }

    public function getLocalMatrix(): Matrix4
    {
        if ($this->isDirty) {
            $this->updateMatrices();
        }
        return $this->localMatrix;
    }

    public function getWorldMatrix(): Matrix4
    {
        return $this->worldMatrix;
    }

    public function getWorldPosition(): Vector3
    {
        return $this->worldMatrix->getTranslation();
    }

    public function toArray(): array
    {
        return [
            'position' => $this->position->toArray(),
            'rotation' => $this->rotation->toArray(),
            'scale' => $this->scale->toArray(),
            'worldPosition' => $this->getWorldPosition()->toArray(),
        ];
    }
}
