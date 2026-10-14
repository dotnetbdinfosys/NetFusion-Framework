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

namespace NetFusion\Core\Spatial\Physics;

use NetFusion\Core\Spatial\Enums\PhysicsBodyType;
use NetFusion\Core\Spatial\Math\Vector3;

class RigidBody
{
    public Vector3 $velocity;
    public Vector3 $acceleration;
    public Vector3 $forceAccumulator;
    public float $inverseMass;

    public function __construct(
        protected string $id,
        public Vector3 $position,
        public PhysicsBodyType $type = PhysicsBodyType::DYNAMIC,
        public float $mass = 1.0,
        public float $restitution = 0.5,
        public float $friction = 0.2,
        public float $drag = 0.01
    ) {
        $this->velocity = Vector3::zero();
        $this->acceleration = Vector3::zero();
        $this->forceAccumulator = Vector3::zero();
        $this->inverseMass = ($this->type === PhysicsBodyType::DYNAMIC && $mass > 0.0) ? (1.0 / $mass) : 0.0;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function applyForce(Vector3 $force): void
    {
        if ($this->type !== PhysicsBodyType::DYNAMIC) {
            return;
        }
        $this->forceAccumulator = $this->forceAccumulator->add($force);
    }

    public function applyImpulse(Vector3 $impulse): void
    {
        if ($this->type !== PhysicsBodyType::DYNAMIC) {
            return;
        }
        $this->velocity = $this->velocity->add($impulse->scale($this->inverseMass));
    }

    /**
     * Semi-implicit Euler integration step
     */
    public function integrate(float $dt, Vector3 $gravity): void
    {
        if ($this->type !== PhysicsBodyType::DYNAMIC) {
            $this->forceAccumulator = Vector3::zero();
            return;
        }

        // a = gravity + (F / m)
        $appliedAcc = $this->forceAccumulator->scale($this->inverseMass);
        $totalAcc = $gravity->add($appliedAcc);

        // v += a * dt
        $this->velocity = $this->velocity->add($totalAcc->scale($dt));

        // Apply drag damping
        $damping = max(0.0, 1.0 - ($this->drag * $dt));
        $this->velocity = $this->velocity->scale($damping);

        // x += v * dt
        $this->position = $this->position->add($this->velocity->scale($dt));

        // Clear accumulated forces
        $this->forceAccumulator = Vector3::zero();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'mass' => $this->mass,
            'position' => $this->position->toArray(),
            'velocity' => $this->velocity->toArray(),
            'restitution' => $this->restitution,
            'friction' => $this->friction,
        ];
    }
}
