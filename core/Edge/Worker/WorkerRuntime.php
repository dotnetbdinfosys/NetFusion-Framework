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

namespace NetFusion\Core\Edge\Worker;

use NetFusion\Core\Edge\Exceptions\EdgeException;
use NetFusion\Core\Edge\Http\EdgeRequest;
use NetFusion\Core\Edge\Http\EdgeResponse;
use NetFusion\Core\Edge\Http\FetchEvent;
use NetFusion\Core\Edge\Isolate\ExecutionContext;
use NetFusion\Core\Edge\Isolate\IsolatePool;
use NetFusion\Core\Wasm\Binary\BinaryReader;
use NetFusion\Core\Wasm\Binary\ModuleParser;
use NetFusion\Core\Wasm\Vm\VirtualMachine;

/**
 * Serverless Worker Execution Runtime Sandbox.
 * Orchestrates isolate allocation, Polyglot execution (PHP & Wasm), and ExecutionContext draining.
 */
class WorkerRuntime
{
    protected IsolatePool $isolatePool;

    public function __construct(?IsolatePool $isolatePool = null)
    {
        $this->isolatePool = $isolatePool ?? new IsolatePool();
    }

    public function getIsolatePool(): IsolatePool
    {
        return $this->isolatePool;
    }

    /**
     * Dispatch an EdgeRequest to a WorkerScript.
     */
    public function dispatch(
        WorkerScript $script,
        EdgeRequest $request,
        ?ExecutionContext $context = null
    ): EdgeResponse {
        $ctx = $context ?? new ExecutionContext();
        $isolate = $this->isolatePool->acquire($script->getMemoryLimitMb(), $script->getCpuTimeoutMs());

        try {
            $response = $isolate->execute(function () use ($script, $request, $ctx) {
                if ($script->isWasm()) {
                    return $this->executeWasm($script, $request, $ctx);
                }
                return $this->executePhp($script, $request, $ctx);
            });

            // Post-dispatch: drain background waitUntil tasks
            $ctx->drain();

            return $response;
        } catch (\Throwable $e) {
            if ($ctx->shouldPassThroughOnException()) {
                return new EdgeResponse("<!-- Edge Pass-Through -->", 502, ['x-edge-passthrough' => 'true']);
            }
            return new EdgeResponse(
                sprintf("Edge Worker Error in '%s': %s", $script->getName(), $e->getMessage()),
                500,
                ['content-type' => 'text/plain', 'x-edge-error' => 'true']
            );
        } finally {
            $this->isolatePool->release($isolate);
        }
    }

    /**
     * Execute PHP callable worker logic.
     */
    protected function executePhp(WorkerScript $script, EdgeRequest $request, ExecutionContext $ctx): EdgeResponse
    {
        $handler = $script->getHandler();
        if (!is_callable($handler)) {
            throw new EdgeException("Worker script handler is not callable.");
        }

        // Support both (FetchEvent $event) signature and (EdgeRequest $req, array $env, ExecutionContext $ctx)
        $fetchEvent = new FetchEvent($request, $ctx);
        $result = $handler($fetchEvent, $script->getEnv(), $ctx);

        if ($fetchEvent->getResponse() instanceof EdgeResponse) {
            return $fetchEvent->getResponse();
        }

        if ($result instanceof EdgeResponse) {
            return $result;
        }

        if (is_array($result)) {
            return EdgeResponse::json($result);
        }

        if (is_string($result)) {
            return EdgeResponse::html($result);
        }

        return new EdgeResponse('', 204);
    }

    /**
     * Execute compiled WebAssembly bytecode module.
     */
    protected function executeWasm(WorkerScript $script, EdgeRequest $request, ExecutionContext $ctx): EdgeResponse
    {
        $binary = $script->getHandler();
        if (!is_string($binary)) {
            throw new EdgeException("Wasm worker handler must be a binary byte string.");
        }

        $parser = new ModuleParser();
        $module = $parser->parse($binary);

        $vm = new VirtualMachine($module);

        // Check for exported request handler
        $exportedFn = $module->getExport('handle_request') ?? $module->getExport('_start') ?? $module->getExport('main');

        $output = '';
        if ($exportedFn !== null) {
            $res = $vm->invoke($exportedFn->name);
            $output = (string)($res ?? 'WASM OK');
        } else {
            $output = 'WASM Loaded (no exported entrypoint)';
        }

        return new EdgeResponse($output, 200, [
            'content-type' => 'text/plain',
            'x-edge-runtime' => 'wasm-v1',
            'x-wasm-worker' => $script->getName(),
        ]);
    }
}
