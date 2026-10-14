<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/ProjectionManager.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing
| Purpose   : Registry and dispatcher for read-model projections.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\EventSourcing;

use NetFusion\Core\EventSourcing\Contracts\DomainEventInterface;
use NetFusion\Core\EventSourcing\Contracts\ProjectionInterface;

class ProjectionManager
{
    /**
     * @var array<string, ProjectionInterface>
     */
    protected array $projections = [];

    public function register(ProjectionInterface $projection): self
    {
        $this->projections[$projection->getName()] = $projection;
        return $this;
    }

    public function get(string $name): ?ProjectionInterface
    {
        return $this->projections[$name] ?? null;
    }

    /**
     * @return array<string, ProjectionInterface>
     */
    public function all(): array
    {
        return $this->projections;
    }

    public function project(DomainEventInterface $event): void
    {
        foreach ($this->projections as $projection) {
            $projection->project($event);
        }
    }

    public function reset(?string $projectionName = null): void
    {
        if ($projectionName !== null) {
            if (isset($this->projections[$projectionName])) {
                $this->projections[$projectionName]->reset();
            }
            return;
        }

        foreach ($this->projections as $projection) {
            $projection->reset();
        }
    }
}