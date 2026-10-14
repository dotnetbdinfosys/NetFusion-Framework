<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Edge\Http;

use NetFusion\Core\Edge\Isolate\ExecutionContext;

/**
 * W3C ServiceWorker / Cloudflare Workers FetchEvent.
 * Dispatched to edge worker scripts when an HTTP request enters the gateway.
 */
class FetchEvent
{
    protected EdgeRequest $request;
    protected ExecutionContext $executionContext;
    protected ?EdgeResponse $response = null;

    public function __construct(EdgeRequest $request, ?ExecutionContext $executionContext = null)
    {
        $this->request = $request;
        $this->executionContext = $executionContext ?? new ExecutionContext();
    }

    public function getRequest(): EdgeRequest
    {
        return $this->request;
    }

    public function getExecutionContext(): ExecutionContext
    {
        return $this->executionContext;
    }

    /**
     * Set the HTTP response for this event.
     */
    public function respondWith(EdgeResponse $response): void
    {
        $this->response = $response;
    }

    public function getResponse(): ?EdgeResponse
    {
        return $this->response;
    }

    /**
     * Delegate async background task to ExecutionContext.
     */
    public function waitUntil(callable $task): void
    {
        $this->executionContext->waitUntil($task);
    }
}
