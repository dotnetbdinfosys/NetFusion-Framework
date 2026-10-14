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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Actors\ActorSystem;
use NetFusion\Core\Console\Command;

class ActorSendCommand extends Command
{
    protected string $signature = 'actor:send {path : Actor path or name} {message : Message payload string or JSON} {--ask : Send as ask request and await response}';
    protected string $description = 'Send a tell or ask message to an active actor in the system';

    public function handle(): int
    {
        /** @var ActorSystem $system */
        $system = function_exists('app') && app()->has('actors') ? app('actors') : new ActorSystem();

        $path = (string)$this->argument('path');
        $rawMsg = (string)$this->argument('message');

        $message = json_decode($rawMsg, true);
        if ($message === null && json_last_error() !== JSON_ERROR_NONE) {
            $message = $rawMsg;
        }

        $ref = $system->actorSelection($path);
        if ($ref === null) {
            $this->error("Actor at path '{$path}' not found.");
            return 1;
        }

        if ($this->option('ask')) {
            $this->info("Sending ask request to '{$ref->path()}'...");
            $response = $ref->ask($message);
            $this->info("=== Response Received ===");
            $this->line(is_array($response) ? json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string)$response);
        } else {
            $ref->tell($message);
            $system->runUntilIdle();
            $this->info("Message sent to '{$ref->path()}' (fire-and-forget).");
        }

        return 0;
    }
}
