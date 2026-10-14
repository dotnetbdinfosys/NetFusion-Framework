<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/WebhookRetryCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Console command to retry failed webhook deliveries.
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
use NetFusion\Core\Webhooks\WebhookManager;
use NetFusion\Core\Webhooks\WebhookPayload;

/**
 * NetFusion Webhook Retry Command
 *
 * Retries failed webhook delivery attempts.
 * Usage:
 *   php netfusion webhook:retry
 *   php netfusion webhook:retry --id=5
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class WebhookRetryCommand extends Command
{
    protected string $name = 'webhook:retry';
    protected string $description = 'Retry failed webhook deliveries';

    public function handle(): int
    {
        /** @var WebhookManager $webhookManager */
        $webhookManager = $this->app->make('webhook');
        $db = $this->app->make(DatabaseManager::class)->connection();

        $deliveryIdOption = $this->option('id');

        if ($deliveryIdOption) {
            $failedDeliveries = $db->select(
                "SELECT d.*, s.target_url, s.secret 
                 FROM netfusion_webhook_deliveries d
                 JOIN netfusion_webhook_subscriptions s ON d.subscription_id = s.id
                 WHERE d.id = :id",
                [':id' => (int) $deliveryIdOption]
            );
        } else {
            $failedDeliveries = $db->select(
                "SELECT d.*, s.target_url, s.secret 
                 FROM netfusion_webhook_deliveries d
                 JOIN netfusion_webhook_subscriptions s ON d.subscription_id = s.id
                 WHERE (d.status_code < 200 OR d.status_code >= 300)
                 ORDER BY d.id DESC LIMIT 20"
            );
        }

        if (empty($failedDeliveries)) {
            $this->info("No failed webhook deliveries to retry.");
            return 0;
        }

        $this->info(sprintf("Retrying %d failed webhook delivery(ies)...", count($failedDeliveries)));

        $retried = 0;
        $success = 0;

        foreach ($failedDeliveries as $row) {
            $retried++;
            $rawPayload = json_decode((string) $row['payload'], true);
            $data = is_array($rawPayload) ? ($rawPayload['data'] ?? []) : [];
            $attempt = ((int) $row['attempt']) + 1;

            $payload = new WebhookPayload(
                event: (string) $row['event'],
                data: $data,
                attempt: $attempt,
                id: (string) $row['delivery_id']
            );

            $res = $webhookManager->getClient()->send(
                (string) $row['target_url'],
                $payload,
                (string) $row['secret']
            );

            $webhookManager->logDelivery((int) $row['subscription_id'], $payload, $res);

            if ($res['status'] >= 200 && $res['status'] < 300) {
                $success++;
                $this->success(sprintf("Delivery [%s] to [%s] succeeded (HTTP %d).", $row['delivery_id'], $row['target_url'], $res['status']));
            } else {
                $this->warn(sprintf("Delivery [%s] to [%s] failed again (HTTP %d).", $row['delivery_id'], $row['target_url'], $res['status']));
            }
        }

        $this->info(sprintf("Done. %d/%d deliveries succeeded.", $success, $retried));
        return 0;
    }
}
