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
use NetFusion\Core\Edge\Enums\WorkerType;

/**
 * CLI command to deploy a serverless edge worker script.
 * Usage: php netfusion edge:deploy <name> [file] [--route=...] [--type=php|wasm] [--memory=128] [--timeout=50]
 */
class EdgeDeployCommand extends Command
{
    protected string $name = 'edge:deploy';
    protected string $description = 'Deploy a serverless edge worker script with routing configuration';

    public function handle(array $args = []): int
    {
        $name = null;
        $file = null;
        $route = null;
        $type = 'php';
        $memory = 128;
        $timeout = 50;

        $positional = [];
        foreach ($args as $arg) {
            $str = (string)$arg;
            if (str_starts_with($str, '--route=')) {
                $route = substr($str, 8);
            } elseif (str_starts_with($str, '--type=')) {
                $type = strtolower(substr($str, 7));
            } elseif (str_starts_with($str, '--memory=')) {
                $memory = (int)substr($str, 9);
            } elseif (str_starts_with($str, '--timeout=')) {
                $timeout = (int)substr($str, 10);
            } elseif (!str_starts_with($str, '-')) {
                $positional[] = $str;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        $name = $positional[0] ?? null;
        $file = $positional[1] ?? null;

        if ($name === null) {
            $this->error("Worker name is required: php netfusion edge:deploy <name> [file]");
            return 1;
        }

        /** @var EdgeWorkerManager $edge */
        $edge = function_exists('app') && app()->has('edge') ? app('edge') : new EdgeWorkerManager();

        $handler = function ($event) use ($name) {
            return [
                'status' => 'ok',
                'worker' => $name,
                'timestamp' => time(),
            ];
        };

        if ($file !== null && file_exists($file)) {
            if ($type === 'wasm') {
                $handler = file_get_contents($file);
            } else {
                $handler = require $file;
            }
        }

        $workerType = $type === 'wasm' ? WorkerType::WASM : WorkerType::PHP;
        $edge->register($name, $handler, $workerType, [], $memory, $timeout);

        if ($route !== null) {
            $edge->route($route, $name);
        }

        $this->info("Successfully deployed Edge Worker '{$name}' [Type: {$type}, Memory: {$memory}MB, Timeout: {$timeout}ms]");
        if ($route !== null) {
            $this->info("Bound to Edge Route: {$route}");
        }

        return 0;
    }
}
