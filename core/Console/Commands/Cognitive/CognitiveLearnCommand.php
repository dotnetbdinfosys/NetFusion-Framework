<?php
/**
 * NetFusion Framework - Enterprise Cognitive Architecture CLI
 *
 * Command to learn facts and synthesize new procedural rules from experiences.
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

class CognitiveLearnCommand extends Command
{
    protected string $signature = 'cognitive:learn
        {--action=deploy_service : Episodic experience action}
        {--outcome=success : Outcome result}';

    protected string $description = 'Ingest autobiographical episodic experiences and synthesize declarative knowledge';

    public function handle(): int
    {
        $manager = cognitive();
        $action = (string) $this->option('action');

        $outcome = (string) $this->option('outcome');

        $manager->episodic()->record(
            'ep_' . uniqid(),
            ['env' => 'production', 'cluster' => 'k8s_primary'],
            $action,
            $outcome,
            ['deployment', 'ops']
        );

        $recent = $manager->episodic()->getRecent(1)[0];

        $this->info("=== Cognitive Architecture: Episodic Memory Learning ===");
        $this->line("Recorded Episode ID: {$recent['id']}");
        $this->line("Action Executed    : {$recent['action']}");
        $this->line("Outcome Result     : " . json_encode($recent['outcome']));
        $this->line("Total Episodes     : {$manager->episodic()->count()} events");

        return 0;
    }
}
