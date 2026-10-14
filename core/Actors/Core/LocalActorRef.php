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

use NetFusion\Core\Actors\Contracts\ActorRefInterface;
use RuntimeException;

class LocalActorRef implements ActorRefInterface
{
    public function __construct(
        protected ActorCell $cell
    ) {}

    public function getCell(): ActorCell
    {
        return $this->cell;
    }

    public function path(): string
    {
        return $this->cell->path;
    }

    public function name(): string
    {
        return $this->cell->name;
    }

    public function isAlive(): bool
    {
        return !$this->cell->isTerminated();
    }

    public function tell(mixed $message, ?ActorRefInterface $sender = null): void
    {
        $envelope = Envelope::create($message, $sender);
        $this->cell->enqueue($envelope);
    }

    public function ask(mixed $message, float $timeout = 5.0): mixed
    {
        if ($this->cell->isTerminated()) {
            throw new RuntimeException("Cannot ask terminated actor '{$this->path()}'.");
        }

        $correlationId = 'ask_' . bin2hex(random_bytes(6));
        $envelope = Envelope::create($message, null, 0, $correlationId);

        // Process directly synchronously through cell
        return $this->cell->processEnvelope($envelope);
    }

    public function __toString(): string
    {
        return "Actor[{$this->path()}]";
    }
}
