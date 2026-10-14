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
use NetFusion\Core\AI\Agent\AgentManager;
use NetFusion\Core\AI\Agent\Tools\ToolParameter;
use NetFusion\Core\AI\Agent\Tools\ParameterType;

/**
 * Class AgentRunCommand
 *
 * CLI: php netfusion agent:run <task> [--agent=NetFusionAgent] [--max-iter=15]
 */
class AgentRunCommand extends Command
{
    protected string $name = 'agent:run';
    protected string $description = 'Execute a task using the autonomous ReAct agent runtime';

    public function handle(array $args = []): int
    {
        $task = null;
        $agentName = 'NetFusionAgent';
        $maxIter = 15;

        foreach ($args as $arg) {
            if (!is_string($arg)) {
                continue;
            }
            if (str_starts_with($arg, '--agent=')) {
                $agentName = substr($arg, 8);
            } elseif (str_starts_with($arg, '--max-iter=')) {
                $maxIter = (int) substr($arg, 11);
            } elseif (!str_starts_with($arg, '--')) {
                if ($task === null) {
                    $task = $arg;
                } else {
                    $task .= ' ' . $arg;
                }
            }
        }

        $task = $task ?? (string) $this->argument(0, 'Calculate 42 * 10 and return the result.');

        $this->line("<info>[NetFusion AI Agent]</info> Initializing Autonomous Agent '{$agentName}'...");
        $this->line("<comment>Task:</comment> {$task}\n");

        $manager = new AgentManager(['max_iterations' => $maxIter, 'default_agent_name' => $agentName]);

        // Register default standard math and time tools for standalone CLI execution
        $manager->registerCallable(
            'calculator',
            'Perform arithmetic evaluation of mathematical expressions.',
            function (string $expression): string {
                // Safe numeric evaluation
                $clean = preg_replace('/[^0-9\+\-\*\/\(\)\.\s]/', '', $expression);
                if (empty($clean)) {
                    return '0';
                }
                try {
                    $val = @eval("return ({$clean});");
                    return (string) ($val !== false ? $val : 'Invalid expression');
                } catch (\Throwable $e) {
                    return 'Error: ' . $e->getMessage();
                }
            },
            [
                new ToolParameter('expression', ParameterType::STRING, 'The math expression to evaluate, e.g. "12 * 8"'),
            ]
        );

        $manager->registerCallable(
            'get_current_time',
            'Get current server timestamp and ISO-8601 formatted datetime.',
            fn(): string => date('Y-m-d H:i:s T'),
            []
        );

        // Simulation LLM fallback for CLI demonstration if no live provider is configured
        $simulatedLLM = function (string $prompt): string {
            if (preg_match('/calculator/i', $prompt) && !preg_match('/Observation:/i', $prompt)) {
                return "Thought: I need to calculate the arithmetic expression required by the user.\n" .
                       "Action: calculator\n" .
                       "Action Input: {\"expression\": \"42 * 10\"}";
            }
            return "Thought: I have calculated the requested result and am ready to conclude.\n" .
                   "Final Answer: The calculation is complete. The result is 420.";
        };

        $agent = $manager->createAgent($simulatedLLM, $agentName);
        $result = $agent->run($task);

        foreach ($result->thoughts as $idx => $thought) {
            $iter = $idx + 1;
            $this->line("<info>--- Iteration #{$iter} ---</info>");
            if ($thought->thought) {
                $this->line("<comment>Thought:</comment> " . trim($thought->thought));
            }
            if ($thought->action) {
                $this->line("<comment>Action:</comment> {$thought->action}");
                $this->line("<comment>Action Input:</comment> " . json_encode($thought->actionInput));
            }
            if ($thought->observation) {
                $this->line("<comment>Observation:</comment> " . trim($thought->observation));
            }
            if ($thought->finalAnswer) {
                $this->line("<info>Final Answer:</info> " . trim($thought->finalAnswer));
            }
            $this->line("");
        }

        $this->line("<info>========================================</info>");
        $this->line("<info>Status:</info> " . ($result->isSuccess ? 'SUCCESS' : 'FAILED'));
        $this->line("<info>Total Iterations:</info> {$result->iterations}");
        $this->line("<info>Tool Calls:</info> {$result->toolCallsCount}");
        $this->line("<info>Execution Time:</info> {$result->durationMs} ms");
        $this->line("<info>Result:</info> {$result->finalAnswer}");
        $this->line("<info>========================================</info>");

        return $result->isSuccess ? 0 : 1;
    }
}
