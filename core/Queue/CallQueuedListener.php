<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Queue/CallQueuedListener.php
| Module    : Core\Queue
| Namespace : NetFusion\Core\Queue
| Purpose   : Wrapper job executing queued event listeners asynchronously.
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

namespace NetFusion\Core\Queue;

/**
 * NetFusion Call Queued Listener Job
 *
 * Automatically wrapped around event listeners implementing ShouldQueue.
 *
 * @package NetFusion\Core\Queue
 * @version 1.0.0
 */
class CallQueuedListener extends Job
{
    /**
     * @param string        $listenerClass
     * @param string        $method
     * @param string|object $event
     * @param mixed         $payload
     */
    public function __construct(
        public readonly string        $listenerClass,
        public readonly string        $method,
        public readonly string|object $event,
        public readonly mixed         $payload = [],
    ) {}

    /**
     * Execute the queued listener.
     */
    public function handle(): void
    {
        $listener = app()->make($this->listenerClass);
        $method = $this->method;

        if (method_exists($listener, $method)) {
            $listener->$method($this->event, $this->payload);
        }
    }
}
