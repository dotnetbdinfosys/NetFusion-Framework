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

namespace NetFusion\Core\Spatial\DigitalTwin;

use NetFusion\Core\Spatial\Math\Quaternion;
use NetFusion\Core\Spatial\Math\Vector3;
use NetFusion\Core\Spatial\SceneGraph\SceneGraph;
use NetFusion\Core\Spatial\SceneGraph\SpatialNode;

class TwinSynchronizer
{
    /** @var array<string, array{twinId: string, nodeId: string, lastTelemetry: array, timestamp: float}> */
    protected array $twins = [];

    public function __construct(protected SceneGraph $scene)
    {
    }

    public function registerTwin(string $twinId, string $targetNodeId): void
    {
        $this->twins[$twinId] = [
            'twinId' => $twinId,
            'nodeId' => $targetNodeId,
            'lastTelemetry' => [],
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Ingest physical IoT sensor telemetry and update corresponding Spatial Node in scene
     *
     * @param array<string, mixed> $telemetry e.g. ['position' => [x, y, z], 'rotation' => [pitch, yaw, roll], 'temperature' => 45.2]
     */
    public function ingestTelemetry(string $twinId, array $telemetry): bool
    {
        if (!isset($this->twins[$twinId])) {
            return false;
        }

        $nodeId = $this->twins[$twinId]['nodeId'];
        $node = $this->scene->getNode($nodeId);
        if ($node === null) {
            return false;
        }

        if (isset($telemetry['position'])) {
            $p = $telemetry['position'];
            $vec = is_array($p) ? Vector3::fromArray($p) : Vector3::zero();
            $node->getTransform()->setPosition($vec);
        }

        if (isset($telemetry['rotation'])) {
            $r = $telemetry['rotation'];
            if (is_array($r)) {
                if (isset($r['w'])) {
                    $node->getTransform()->setRotation(new Quaternion((float)$r['x'], (float)$r['y'], (float)$r['z'], (float)$r['w']));
                } elseif (isset($r['pitch'], $r['yaw'], $r['roll'])) {
                    $node->getTransform()->setRotation(Quaternion::fromEuler((float)$r['pitch'], (float)$r['yaw'], (float)$r['roll']));
                }
            }
        }

        // Store arbitrary IoT sensor metadata
        foreach ($telemetry as $k => $v) {
            if ($k !== 'position' && $k !== 'rotation') {
                $node->setMetadata($k, $v);
            }
        }

        $this->twins[$twinId]['lastTelemetry'] = $telemetry;
        $this->twins[$twinId]['timestamp'] = microtime(true);
        $this->scene->update();

        return true;
    }

    public function getTwinStatus(string $twinId): ?array
    {
        if (!isset($this->twins[$twinId])) {
            return null;
        }

        $nodeId = $this->twins[$twinId]['nodeId'];
        $node = $this->scene->getNode($nodeId);

        return [
            'twinId' => $twinId,
            'nodeId' => $nodeId,
            'worldPosition' => $node?->getTransform()->getWorldPosition()->toArray(),
            'metadata' => $node?->getMetadata(),
            'lastUpdated' => $this->twins[$twinId]['timestamp'],
        ];
    }
}
