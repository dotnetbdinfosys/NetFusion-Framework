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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Edge\EdgeWorkerManager;
use NetFusion\Core\Edge\Http\EdgeRequest;

/**
 * CLI command to invoke a serverless edge worker directly.
 * Usage: php netfusion edge:invoke <name> [--url=/] [--method=GET] [--payload=...]
 */
class EdgeInvokeCommand extends Command
{
    protected string $name = 'edge:invoke';
    protected string $description = 'Directly test-invoke an edge worker within an isolated sandbox';

    public function handle(array $args = []): int
    {
        $workerName = null;
        $url = 'https://edge.local/api/test';
        $method = 'GET';
        $payload = '';

        $positional = [];
        foreach ($args as $arg) {
            $str = (string)$arg;
            if (str_starts_with($str, '--url=')) {
                $url = substr($str, 6);
            } elseif (str_starts_with($str, '--method=')) {
                $method = strtoupper(substr($str, 9));
            } elseif (str_starts_with($str, '--payload=')) {
                $payload = substr($str, 10);
            } elseif (!str_starts_with($str, '-')) {
                $positional[] = $str;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        $workerName = $positional[0] ?? null;

        if ($workerName === null) {
            $this->error("Worker name is required: php netfusion edge:invoke <name>");
            return 1;
        }

        /** @var EdgeWorkerManager $edge */
        $edge = function_exists('app') && app()->has('edge') ? app('edge') : new EdgeWorkerManager();

        if (!$edge->hasWorker($workerName)) {
            // Auto-provision a mock worker if invoking for ad-hoc testing
            $edge->register($workerName, function ($event) use ($workerName) {
                return [
                    'invoked' => $workerName,
                    'method' => $event->getRequest()->getMethod(),
                    'path' => $event->getRequest()->getPath(),
                    'echo' => $event->getRequest()->json() ?? $event->getRequest()->getBody(),
                ];
            });
        }

        $request = new EdgeRequest($method, $url, ['content-type' => 'application/json'], $payload);

        $start = microtime(true);
        $response = $edge->invoke($workerName, $request);
        $duration = round((microtime(true) - $start) * 1000, 2);

        $this->info("Edge Worker Response [{$response->getStatus()} {$response->getStatusText()}] in {$duration}ms:");
        $this->line($response->getBody());

        return 0;
    }
}
