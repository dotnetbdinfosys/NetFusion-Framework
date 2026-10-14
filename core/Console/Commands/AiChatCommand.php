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
use NetFusion\Core\AI\ChatMessage;
use NetFusion\Core\Console\Command;
use Throwable;

class AiChatCommand extends Command
{
    protected string $name = 'ai:chat';
    protected string $description = 'Send a prompt to configured AI LLM provider and display response';

    public function handle(): int
    {
        $prompt = (string) $this->argument(0);
        if (empty($prompt)) {
            $this->error("Usage: php netfusion ai:chat \"Your prompt here\" [--driver=mock|openai|anthropic|gemini] [--model=...]");
            return 1;
        }

        $driverName = $this->option('driver') ? (string) $this->option('driver') : null;
        $model = $this->option('model') ? (string) $this->option('model') : null;

        /** @var AiManager $ai */
        $ai = $this->app->make(AiManager::class);

        $this->info("Sending prompt to AI [Driver: " . ($driverName ?? 'default') . "]...");

        try {
            $options = [];
            if ($model !== null) {
                $options['model'] = $model;
            }

            $response = $ai->chat($prompt, $options, $driverName);

            $this->line("---------------- AI Response ----------------");
            $this->line($response->getContent());
            $this->line("---------------------------------------------");
            $this->line("<comment>Tokens: " . $response->getTotalTokens() . " (Prompt: " . $response->getPromptTokens() . ", Completion: " . $response->getCompletionTokens() . ")</comment>");

            return 0;
        } catch (Throwable $e) {
            $this->error("AI Chat Failed: " . $e->getMessage());
            return 1;
        }
    }
}
