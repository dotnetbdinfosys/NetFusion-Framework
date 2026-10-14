<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;

/**
 * Class KafkaTopicCommand
 *
 * CLI: php netfusion kafka:topic <create|list|describe|delete> [topic_name] [--partitions=N]
 */
class KafkaTopicCommand extends Command
{
    protected string $name = 'kafka:topic';
    protected string $description = 'Manage Kafka topics (create, list, describe, delete)';

    public function handle(array $args = []): int
    {
        $action = 'list';
        $topicName = null;
        $partitions = 1;
        $replication = 1;

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string) $args[$i];
            if (str_starts_with($arg, '--partitions=')) {
                $partitions = (int) substr($arg, 13);
            } elseif ($arg === '--partitions' && isset($args[$i + 1])) {
                $partitions = (int) $args[++$i];
            } elseif (str_starts_with($arg, '--replication=')) {
                $replication = (int) substr($arg, 14);
            } elseif (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        if (!empty($positional)) {
            $action = strtolower($positional[0]);
            $topicName = $positional[1] ?? null;
        }

        $kafka = function_exists('kafka') ? kafka() : $this->app->make(\NetFusion\Core\Kafka\KafkaManager::class);

        switch ($action) {
            case 'create':
                if ($topicName === null) {
                    $this->error('Usage: php netfusion kafka:topic create <name> [--partitions=N]');
                    return 1;
                }
                try {
                    $meta = $kafka->createTopic($topicName, $partitions, $replication);
                    $this->info("Created topic '{$meta->name}' with {$meta->partitionsCount} partition(s).");
                    return 0;
                } catch (\Throwable $e) {
                    $this->error($e->getMessage());
                    return 1;
                }

            case 'describe':
                if ($topicName === null) {
                    $this->error('Usage: php netfusion kafka:topic describe <name>');
                    return 1;
                }
                $meta = $kafka->describeTopic($topicName);
                if ($meta === null) {
                    $this->error("Topic '{$topicName}' does not exist.");
                    return 1;
                }
                $this->info("Topic: {$meta->name}");
                $this->line("Partitions: {$meta->partitionsCount}");
                $this->line("Replication Factor: {$meta->replicationFactor}");
                $this->line("Cleanup Policy: {$meta->cleanupPolicy()->value}");
                return 0;

            case 'delete':
                if ($topicName === null) {
                    $this->error('Usage: php netfusion kafka:topic delete <name>');
                    return 1;
                }
                $ok = $kafka->deleteTopic($topicName);
                if ($ok) {
                    $this->info("Deleted topic '{$topicName}'.");
                } else {
                    $this->error("Topic '{$topicName}' not found.");
                }
                return $ok ? 0 : 1;

            case 'list':
            default:
                $topics = $kafka->listTopics();
                if (empty($topics)) {
                    $this->line('No topics found.');
                    return 0;
                }
                $this->line(sprintf('%-30s %-15s %-15s', 'TOPIC NAME', 'PARTITIONS', 'REPLICATION'));
                $this->line(str_repeat('-', 60));
                foreach ($topics as $name => $meta) {
                    $this->line(sprintf('%-30s %-15d %-15d', $name, $meta->partitionsCount, $meta->replicationFactor));
                }
                return 0;
        }
    }
}
