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

use NetFusion\Core\CEP\CEPManager;
use NetFusion\Core\CEP\Event;
use NetFusion\Core\Console\Command;

class CEPIngestCommand extends Command
{
    protected string $name = 'cep:ingest';
    protected string $signature = 'cep:ingest {type : Event type / stream topic} {payload? : JSON event payload}';
    protected string $description = 'Ingest an event into the Complex Event Processing (CEP) engine';

    public function handle(): int
    {
        /** @var CEPManager $manager */
        $manager = function_exists('cep') ? cep() : (function_exists('app') && app()->has('cep') ? app('cep') : new CEPManager());

        $type = (string) $this->argument('type');
        $payloadRaw = $this->argument('payload') ? (string) $this->argument('payload') : '{}';

        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            $this->error("Invalid JSON payload provided: {$payloadRaw}");
            return 1;
        }

        $event = new Event($type, $payload, microtime(true));
        $this->info("Ingesting event [{$event->getId()}] into CEP engine on stream [{$type}]...");

        $results = $manager->ingest($event);

        $this->info("Event ingested successfully. Output matches/actions generated: " . count($results));

        if (!empty($results)) {
            foreach ($results as $idx => $res) {
                $serialized = is_object($res) && method_exists($res, 'toArray') ? $res->toArray() : (array) $res;
                $this->line(sprintf("  [%d] %s", $idx + 1, json_encode($serialized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
            }
        }

        return 0;
    }
}
