<?php
/**
 * NetFusion Framework - Enterprise Cognitive Architecture CLI
 *
 * Command to execute ACT-R cognitive Recognize-Act cycles on active buffers.
 *
 * @package    NetFusion\Core\Console\Commands\Cognitive
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Cognitive;

use NetFusion\Core\Cognitive\CognitiveManager;
use NetFusion\Core\Console\Command;

class CognitiveCycleCommand extends Command
{
    protected string $signature = 'cognitive:cycle
        {--cycles=3 : Number of cognitive recognize-act cycles to step}';

    protected string $description = 'Step through cognitive Recognize-Act rule firings and buffer modifications';

    public function handle(): int
    {
        $manager = cognitive();
        $cycles = (int) $this->option('cycles');


        // Setup goal buffer and production rules
        $manager->setBufferSlot('goal', 'task', 'retrieve_customer');
        $manager->setBufferSlot('goal', 'status', 'start');

        $manager->learnFact('cust_101', 'customer', ['name' => 'Alice', 'plan' => 'enterprise']);

        $manager->defineRule('Rule_QueryCustomer', [
            'goal' => ['task' => 'retrieve_customer', 'status' => 'start'],
        ], function (array &$buffers, $dm, $em) {
            $chunk = $dm->retrieve(['type' => 'customer']);
            $buffers['retrieval'] = $chunk->getSlots();
            $buffers['goal']['status'] = 'customer_found';
            return 'Customer found: ' . $chunk->getSlot('name');
        }, 10.0);

        $manager->defineRule('Rule_FinishTask', [
            'goal' => ['status' => 'customer_found'],
        ], function (array &$buffers, $dm, $em) {
            $buffers['action']['notify'] = 'Customer ' . ($buffers['retrieval']['name'] ?? '') . ' active.';
            $buffers['goal']['status'] = 'done';
            return 'Task completed.';
        }, 5.0);

        $history = $manager->runCycles($cycles);

        $this->info("=== Cognitive Architecture: Recognize-Act Cycles ===");
        foreach ($history as $step) {
            $this->line(sprintf("Cycle #%d | Fired: %-22s | Result: %s", $step['cycle'], $step['fired_rule'], (string) $step['result']));
        }

        $this->line("Final Goal Status : " . $manager->getBufferSlot('goal', 'status'));

        return 0;
    }
}
