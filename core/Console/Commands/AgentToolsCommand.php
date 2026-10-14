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
 * Class AgentToolsCommand
 *
 * CLI: php netfusion agent:tools [--format=table|json]
 */
class AgentToolsCommand extends Command
{
    protected string $name = 'agent:tools';
    protected string $description = 'List all registered and synthesized agent tools with their JSON Schema parameters';

    public function handle(array $args = []): int
    {
        $format = 'table';
        foreach ($args as $arg) {
            if (is_string($arg) && str_starts_with($arg, '--format=')) {
                $format = strtolower(substr($arg, 9));
            }
        }

        $manager = new AgentManager();

        // Sample registered tools
        $manager->registerCallable(
            'calculator',
            'Evaluate arithmetic expression.',
            fn(string $expression): string => 'evaluated',
            [new ToolParameter('expression', ParameterType::STRING, 'The math expression to evaluate')]
        );
        $manager->registerCallable(
            'current_timestamp',
            'Returns the current server ISO-8601 timestamp.',
            fn(): string => date('c'),
            []
        );

        $registry = $manager->getToolRegistry();
        $tools = $registry->all();

        if ($format === 'json') {
            $schemas = $registry->toFunctionSchemas();
            $this->line(json_encode($schemas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        $this->line("<info>[NetFusion AI Tools Registry]</info>");
        $this->line(str_repeat('-', 70));
        $this->line(sprintf("%-20s | %-45s", "Tool Name", "Description"));
        $this->line(str_repeat('-', 70));

        foreach ($tools as $tool) {
            $this->line(sprintf("%-20s | %-45s", $tool->getName(), substr($tool->getDescription(), 0, 45)));
            $def = $tool->getDefinition();
            if (!empty($def->getParameters())) {
                $params = array_keys($def->getParameters());
                $this->line("  <comment>Parameters:</comment> " . implode(', ', $params));
            }
        }
        $this->line(str_repeat('-', 70));
        $this->line("Total Tools: " . count($tools));

        return 0;
    }
}
