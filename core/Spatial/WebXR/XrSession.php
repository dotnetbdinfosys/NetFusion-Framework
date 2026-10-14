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

namespace NetFusion\Core\Spatial\WebXR;

use JsonSerializable;
use NetFusion\Core\Spatial\Enums\XrSessionMode;
use NetFusion\Core\Spatial\Math\Quaternion;
use NetFusion\Core\Spatial\Math\Vector3;

class XrSession implements JsonSerializable
{
    protected bool $isActive = true;
    protected Vector3 $viewerPosition;
    protected Quaternion $viewerOrientation;
    /** @var array<string, array{position: Vector3, orientation: Quaternion}> */
    protected array $spatialAnchors = [];

    public function __construct(
        protected string $sessionId,
        protected XrSessionMode $mode = XrSessionMode::IMMERSIVE_VR,
        protected string $referenceSpace = 'local-floor'
    ) {
        $this->viewerPosition = Vector3::zero();
        $this->viewerOrientation = Quaternion::identity();
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getMode(): XrSessionMode
    {
        return $this->mode;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function end(): void
    {
        $this->isActive = false;
    }

    public function updateViewerPose(Vector3 $position, Quaternion $orientation): void
    {
        $this->viewerPosition = $position;
        $this->viewerOrientation = $orientation;
    }

    public function getViewerPosition(): Vector3
    {
        return $this->viewerPosition;
    }

    public function getViewerOrientation(): Quaternion
    {
        return $this->viewerOrientation;
    }

    public function createAnchor(string $anchorId, Vector3 $position, ?Quaternion $orientation = null): void
    {
        $this->spatialAnchors[$anchorId] = [
            'position' => $position,
            'orientation' => $orientation ?? Quaternion::identity(),
        ];
    }

    public function getAnchor(string $anchorId): ?array
    {
        return $this->spatialAnchors[$anchorId] ?? null;
    }

    public function toArray(): array
    {
        $anchors = [];
        foreach ($this->spatialAnchors as $id => $data) {
            $anchors[$id] = [
                'position' => $data['position']->toArray(),
                'orientation' => $data['orientation']->toArray(),
            ];
        }

        return [
            'sessionId' => $this->sessionId,
            'mode' => $this->mode->value,
            'referenceSpace' => $this->referenceSpace,
            'isActive' => $this->isActive,
            'viewer' => [
                'position' => $this->viewerPosition->toArray(),
                'orientation' => $this->viewerOrientation->toArray(),
            ],
            'anchors' => $anchors,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
