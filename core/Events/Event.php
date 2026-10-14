<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Events/Event.php
| Module    : Core\Events
| Namespace : NetFusion\Core\Events
| Purpose   : Abstract base event class for framework and application domain events.
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

namespace NetFusion\Core\Events;

/**
 * NetFusion Base Event
 *
 * Base class for all domain and framework events.
 *
 * @package NetFusion\Core\Events
 * @version 1.0.0
 */
abstract class Event
{
    /**
     * Whether event propagation should be stopped.
     */
    protected bool $propagationStopped = false;

    /**
     * Stop further listeners from executing for this event.
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * Check if propagation has been stopped.
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
