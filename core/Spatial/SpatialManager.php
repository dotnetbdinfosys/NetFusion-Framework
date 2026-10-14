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

namespace NetFusion\Core\Spatial;

use NetFusion\Core\Spatial\DigitalTwin\TwinSynchronizer;
use NetFusion\Core\Spatial\Gaussian\SplatCloud;
use NetFusion\Core\Spatial\Physics\PhysicsWorld;
use NetFusion\Core\Spatial\SceneGraph\SceneGraph;
use NetFusion\Core\Spatial\WebXR\XrSession;
use NetFusion\Core\Spatial\Enums\XrSessionMode;

class SpatialManager
{
    /** @var array<string, SceneGraph> */
    protected array $scenes = [];
    /** @var array<string, SplatCloud> */
    protected array $splatClouds = [];
    /** @var array<string, PhysicsWorld> */
    protected array $physicsWorlds = [];
    /** @var array<string, XrSession> */
    protected array $xrSessions = [];
    /** @var array<string, TwinSynchronizer> */
    protected array $synchronizers = [];

    public function createScene(string $id, string $name = 'Scene'): SceneGraph
    {
        $scene = new SceneGraph($id, $name);
        $this->scenes[$id] = $scene;
        $this->physicsWorlds[$id] = new PhysicsWorld();
        $this->synchronizers[$id] = new TwinSynchronizer($scene);
        return $scene;
    }

    public function getScene(string $id): ?SceneGraph
    {
        return $this->scenes[$id] ?? null;
    }

    public function createSplatCloud(string $id, string $name = 'Splat Cloud'): SplatCloud
    {
        $cloud = new SplatCloud($id, $name);
        $this->splatClouds[$id] = $cloud;
        return $cloud;
    }

    public function getSplatCloud(string $id): ?SplatCloud
    {
        return $this->splatClouds[$id] ?? null;
    }

    public function getPhysicsWorld(string $sceneId): ?PhysicsWorld
    {
        return $this->physicsWorlds[$sceneId] ?? null;
    }

    public function getSynchronizer(string $sceneId): ?TwinSynchronizer
    {
        return $this->synchronizers[$sceneId] ?? null;
    }

    public function startXrSession(string $sessionId, XrSessionMode $mode = XrSessionMode::IMMERSIVE_VR): XrSession
    {
        $session = new XrSession($sessionId, $mode);
        $this->xrSessions[$sessionId] = $session;
        return $session;
    }

    public function getXrSession(string $sessionId): ?XrSession
    {
        return $this->xrSessions[$sessionId] ?? null;
    }
}
