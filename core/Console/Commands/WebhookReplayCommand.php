<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/WebhookReplayCommand.php
| Module    : Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Console command to replay recorded incoming webhooks through event bus.
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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Webhooks\Incoming\WebhookGateway;
use Throwable;

/**
 * Class WebhookReplayCommand
 *
 * Console command for replaying incoming webhook events.
 *
 * Usage:
 *   php netfusion webhook:replay 1
 *   php netfusion webhook:replay --id=1
 *   php netfusion webhook:replay --failed
 *
 * @package NetFusion\Core\Console\Commands
 */
class WebhookReplayCommand extends Command
{
    protected string $name = 'webhook:replay';
    protected string $description = 'Replay recorded incoming webhook events through internal event bus';

    public function handle(): int
    {
        /** @var WebhookGateway $gateway */
        $gateway = $this->app->make(WebhookGateway::class);
        $db = $this->app->make(DatabaseManager::class)->connection();

        $id = $this->argument(0) ?? $this->option('id');
        $replayFailed = $this->hasOption('failed');

        if ($id !== null) {
            $this->info("Replaying incoming webhook ID [{$id}]...");
            try {
                $result = $gateway->replay((int) $id);
                if ($result['success']) {
                    $this->success("Webhook [{$id}] replayed successfully.");
                    return 0;
                }

                $this->error("Webhook [{$id}] replay failed: " . $result['message']);
                return 1;
            } catch (Throwable $e) {
                $this->error("Failed to replay webhook: " . $e->getMessage());
                return 1;
            }
        }

        if ($replayFailed) {
            $failedRows = $db->select("SELECT id FROM incoming_webhooks WHERE status = 'failed' ORDER BY id ASC");
            if (empty($failedRows)) {
                $this->info("No failed incoming webhooks found to replay.");
                return 0;
            }

            $this->info(sprintf("Replaying %d failed incoming webhook(s)...", count($failedRows)));
            $replayed = 0;
            $success = 0;

            foreach ($failedRows as $row) {
                $replayed++;
                $wid = (int) $row['id'];
                try {
                    $res = $gateway->replay($wid);
                    if ($res['success']) {
                        $success++;
                        $this->line("  [✓] Webhook #{$wid} replayed.");
                    } else {
                        $this->warn("  [✗] Webhook #{$wid} failed again: " . $res['message']);
                    }
                } catch (Throwable $e) {
                    $this->warn("  [✗] Webhook #{$wid} error: " . $e->getMessage());
                }
            }

            $this->success("Completed replaying. {$success}/{$replayed} succeeded.");
            return 0;
        }

        $this->warn("Please specify a webhook ID or pass --failed option.");
        $this->line("Example: php netfusion webhook:replay 1");
        $this->line("Example: php netfusion webhook:replay --failed");
        return 1;
    }
}
