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

use NetFusion\Core\AI\AiManager;
use NetFusion\Core\Console\Command;
use Throwable;

class AiAgentCommand extends Command
{
    protected string $name = 'ai:agent';
    protected string $description = 'Execute an autonomous AI agent ReAct loop with tools and memory';

    public function handle(): int
    {
        $prompt = (string) $this->argument(0);
        if (empty($prompt)) {
            $this->error("Usage: php netfusion ai:agent \"What is 45 * 89?\" [--driver=mock|openai|gemini] [--steps=10]");
            return 1;
        }

        $driverName = $this->option('driver') ? (string) $this->option('driver') : null;
        $maxSteps = $this->option('steps') ? (int) $this->option('steps') : 10;
        $system = $this->option('system') ? (string) $this->option('system') : 'You are an intelligent AI agent equipped with tools.';

        /** @var AiManager $ai */
        $ai = $this->app->make(AiManager::class);

        $agent = $ai->agent(systemPrompt: $system, driver: $driverName);
        $agent->setMaxSteps($maxSteps);

        $this->info("Initializing Autonomous AI Agent [Driver: " . ($driverName ?? 'default') . "]...");

        $agent->onStep(function (int $step, string $type, mixed $data) {
            if ($type === 'tool_call') {
                $this->line("<comment>[Step {$step}] Tool Call: {$data['name']}(" . json_encode($data['arguments']) . ")</comment>");
            } elseif ($type === 'tool_result') {
                $this->line("<info>[Step {$step}] Tool Result: {$data['result']}</info>");
            }
        });

        try {
            $result = $agent->run($prompt);

            $this->line("\n================ Agent Result ================");
            $this->line($result->getContent());
            $this->line("==============================================");
            $this->line("Steps Taken: " . $result->getSteps() . " | Execution Time: " . $result->getExecutionTime() . "s");
            $this->line("Total Tokens: " . ($result->getUsage()['total_tokens'] ?? 0));

            return 0;
        } catch (Throwable $e) {
            $this->error("Agent Execution Failed: " . $e->getMessage());
            return 1;
        }
    }
}
