<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Actors\Core;

use NetFusion\Core\Actors\Contracts\ActorContextInterface;
use NetFusion\Core\Actors\Contracts\ActorRefInterface;

class ActorContext implements ActorContextInterface
{
    public function __construct(
        protected ActorCell $cell
    ) {}

    public function self(): ActorRefInterface
    {
        return $this->cell->selfRef;
    }

    public function parent(): ?ActorRefInterface
    {
        return $this->cell->parentRef;
    }

    public function sender(): ?ActorRefInterface
    {
        return $this->cell->getCurrentSender();
    }

    public function actorOf(string $actorClass, string $name = '', array $args = []): ActorRefInterface
    {
        $name = $name !== '' ? $name : 'actor_' . bin2hex(random_bytes(4));
        $childPath = $this->cell->path . '/' . $name;

        $childCell = new ActorCell(
            system: $this->cell->system,
            actorClass: $actorClass,
            path: $childPath,
            name: $name,
            args: $args,
            parentRef: $this->cell->selfRef
        );

        $childCell->start();
        $this->cell->addChild($childCell->selfRef);
        $this->cell->system->registerActor($childCell->selfRef);

        return $childCell->selfRef;
    }

    public function child(string $name): ?ActorRefInterface
    {
        return $this->cell->getChild($name);
    }

    public function children(): array
    {
        return $this->cell->getChildren();
    }

    public function stop(ActorRefInterface $child): void
    {
        if ($child instanceof LocalActorRef) {
            $child->getCell()->stop();
            $this->cell->removeChild($child->name());
            $this->cell->system->unregisterActor($child->path());
        }
    }

    public function become(callable $behavior): void
    {
        $this->cell->become($behavior);
    }

    public function unbecome(): void
    {
        $this->cell->unbecome();
    }
}
