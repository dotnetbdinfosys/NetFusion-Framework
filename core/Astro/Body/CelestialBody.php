<?php
/**
 * NetFusion Framework - Enterprise Astro-Informatics & Celestial Mechanics Fabric
 *
 * Physical celestial body entity representing stars, planets, and spacecraft in 3D space.
 *
 * @package    NetFusion\Core\Astro\Body
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Astro\Body;

use NetFusion\Core\Astro\Enums\CelestialBodyType;

class CelestialBody
{
    /**
     * @param array{x: float, y: float, z: float} $position
     * @param array{x: float, y: float, z: float} $velocity
     */
    public function __construct(
        protected readonly string $bodyId,
        protected readonly string $name,
        protected readonly CelestialBodyType $type,
        protected readonly float $mass,       // Kilograms
        protected readonly float $radius,     // Meters
        protected array $position = ['x' => 0.0, 'y' => 0.0, 'z' => 0.0],
        protected array $velocity = ['x' => 0.0, 'y' => 0.0, 'z' => 0.0],
        protected readonly array $metadata = []
    ) {
    }

    public function getBodyId(): string
    {
        return $this->bodyId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): CelestialBodyType
    {
        return $this->type;
    }

    public function getMass(): float
    {
        return $this->mass;
    }

    public function getRadius(): float
    {
        return $this->radius;
    }

    /**
     * @return array{x: float, y: float, z: float}
     */
    public function getPosition(): array
    {
        return $this->position;
    }

    /**
     * @param array{x: float, y: float, z: float} $position
     */
    public function setPosition(array $position): void
    {
        $this->position = $position;
    }

    /**
     * @return array{x: float, y: float, z: float}
     */
    public function getVelocity(): array
    {
        return $this->velocity;
    }

    /**
     * @param array{x: float, y: float, z: float} $velocity
     */
    public function setVelocity(array $velocity): void
    {
        $this->velocity = $velocity;
    }

    public function distanceTo(CelestialBody $other): float
    {
        $p1 = $this->position;
        $p2 = $other->getPosition();

        $dx = $p1['x'] - $p2['x'];
        $dy = $p1['y'] - $p2['y'];
        $dz = $p1['z'] - $p2['z'];

        return sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz));
    }

    public function speed(): float
    {
        $v = $this->velocity;
        return sqrt(($v['x'] * $v['x']) + ($v['y'] * $v['y']) + ($v['z'] * $v['z']));
    }

    /**
     * Kinetic Energy: E_k = 0.5 * m * v^2
     */
    public function kineticEnergy(): float
    {
        $s = $this->speed();
        return 0.5 * $this->mass * ($s * $s);
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'body_id'  => $this->bodyId,
            'name'     => $this->name,
            'type'     => $this->type->value,
            'mass'     => $this->mass,
            'radius'   => $this->radius,
            'position' => $this->position,
            'velocity' => $this->velocity,
            'speed'    => $this->speed(),
            'metadata' => $this->metadata,
        ];
    }
}
