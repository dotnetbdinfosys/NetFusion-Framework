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

use NetFusion\Core\Auth\Policy\AccessManager;
use NetFusion\Core\Auth\Policy\EvaluationContext;
use NetFusion\Core\Console\Command;
use Throwable;

class PolicyCheckCommand extends Command
{
    protected string $name = 'policy:check';
    protected string $description = 'Dry-run policy evaluation to verify access decisions';

    public function handle(): int
    {
        $subjectJson = (string)$this->argument(0);
        $action = (string)$this->argument(1);
        $resourceJson = (string)$this->argument(2);

        if (empty($subjectJson) || empty($action) || empty($resourceJson)) {
            $this->error("Usage: php netfusion policy:check <json_subject> <action> <json_resource> [--env=json]");
            return 1;
        }

        $subject = json_decode($subjectJson, true);
        if (!is_array($subject)) {
            $subject = ['id' => $subjectJson];
        }

        $resource = json_decode($resourceJson, true);
        if (!is_array($resource)) {
            $resource = ['type' => $resourceJson];
        }

        $env = [];
        if ($this->option('env')) {
            $decodedEnv = json_decode((string)$this->option('env'), true);
            if (is_array($decodedEnv)) {
                $env = $decodedEnv;
            }
        }

        /** @var AccessManager $manager */
        $manager = $this->app->make(AccessManager::class);

        try {
            $context = new EvaluationContext($subject, $action, $resource, $env);
            $decision = $manager->evaluate($context);

            $this->info("=================== Authorization Decision ===================");
            if ($decision->isAllowed()) {
                $this->success("DECISION: ALLOWED");
            } else {
                $this->error("DECISION: DENIED");
            }

            if ($decision->matchedPolicy) {
                $this->line("Matched Policy : " . $decision->matchedPolicy);
            }
            if ($decision->matchedRule) {
                $this->line("Matched Rule   : " . $decision->matchedRule);
            }
            if ($decision->reason) {
                $this->line("Reason         : " . $decision->reason);
            }
            $this->line("=============================================================");

            return 0;
        } catch (Throwable $e) {
            $this->error("Policy check failed: " . $e->getMessage());
            return 1;
        }
    }
}
