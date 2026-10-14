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

namespace NetFusion\Core\AI\Agents;

use JsonSerializable;
use NetFusion\Core\AI\ChatMessage;
use NetFusion\Core\AI\ChatResponse;
use NetFusion\Core\AI\Contracts\LlmDriverInterface;
use NetFusion\Core\AI\Contracts\MemoryInterface;
use NetFusion\Core\AI\Memory\BufferMemory;
use NetFusion\Core\AI\Tools\ToolRegistry;

class Agent
{
    protected string $name;
    protected string $systemPrompt;
    protected LlmDriverInterface $driver;
    protected ToolRegistry $toolRegistry;
    protected MemoryInterface $memory;
    protected int $maxSteps = 10;
    protected ?string $model = null;
    protected ?float $temperature = null;
    /** @var array<callable> */
    protected array $stepListeners = [];

    public function __construct(
        LlmDriverInterface $driver,
        ?ToolRegistry $toolRegistry = null,
        ?MemoryInterface $memory = null,
        string $systemPrompt = 'You are a helpful and intelligent AI assistant.',
        string $name = 'DefaultAgent'
    ) {
        $this->driver = $driver;
        $this->toolRegistry = $toolRegistry ?? new ToolRegistry();
        $this->memory = $memory ?? new BufferMemory();
        $this->systemPrompt = $systemPrompt;
        $this->name = $name;
    }

    public static function create(
        LlmDriverInterface $driver,
        ?ToolRegistry $toolRegistry = null,
        ?MemoryInterface $memory = null,
        string $systemPrompt = 'You are a helpful and intelligent AI assistant.',
        string $name = 'DefaultAgent'
    ): self {
        return new self($driver, $toolRegistry, $memory, $systemPrompt, $name);
    }

    public function setSystemPrompt(string $prompt): self
    {
        $this->systemPrompt = $prompt;
        return $this;
    }

    public function getSystemPrompt(): string
    {
        return $this->systemPrompt;
    }

    public function setMaxSteps(int $steps): self
    {
        $this->maxSteps = max(1, $steps);
        return $this;
    }

    public function setModel(?string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function setTemperature(?float $temperature): self
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function getToolRegistry(): ToolRegistry
    {
        return $this->toolRegistry;
    }

    public function getMemory(): MemoryInterface
    {
        return $this->memory;
    }

    public function getDriver(): LlmDriverInterface
    {
        return $this->driver;
    }

    /**
     * Add a listener callback triggered at each step of the ReAct loop: function(int $step, string $type, mixed $data)
     */
    public function onStep(callable $callback): self
    {
        $this->stepListeners[] = $callback;
        return $this;
    }

    /**
     * Run the autonomous Agent ReAct execution loop on a user prompt.
     *
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return AgentResult
     */
    public function run(string $prompt, array $options = []): AgentResult
    {
        $startTime = microtime(true);
        $userMessage = ChatMessage::user($prompt);
        
        // Build initial conversation messages
        $messages = [];
        if (!empty(trim($this->systemPrompt))) {
            $messages[] = ChatMessage::system($this->systemPrompt);
        }

        // Add history from memory
        foreach ($this->memory->getMessages() as $memMsg) {
            $messages[] = $memMsg;
        }

        // Add current user prompt
        $messages[] = $userMessage;

        $step = 0;
        $totalPromptTokens = 0;
        $totalCompletionTokens = 0;
        $executedTools = [];
        $finalContent = '';
        $finalResponse = null;

        $tools = $this->toolRegistry->toFunctionDefinitions();

        while ($step < $this->maxSteps) {
            $step++;

            $driverOptions = [
                'model' => $options['model'] ?? $this->model,
                'temperature' => $options['temperature'] ?? $this->temperature,
            ];
            if (!empty($tools)) {
                $driverOptions['tools'] = $tools;
            }

            $this->notifyStep($step, 'thinking', ['messages_count' => count($messages)]);

            $response = $this->driver->chat($messages, $driverOptions);
            $finalResponse = $response;

            $totalPromptTokens += $response->getPromptTokens();
            $totalCompletionTokens += $response->getCompletionTokens();

            if ($response->hasToolCalls()) {
                // LLM wants to call one or more tools
                $messages[] = $response->toChatMessage();

                foreach ($response->getToolCalls() as $toolCall) {
                    $toolName = $toolCall->getName();
                    $toolArgs = $toolCall->getArguments();

                    $this->notifyStep($step, 'tool_call', [
                        'name' => $toolName,
                        'arguments' => $toolArgs,
                    ]);

                    try {
                        $toolResult = $this->toolRegistry->execute($toolName, $toolArgs);
                        $formattedResult = is_string($toolResult) ? $toolResult : json_encode($toolResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    } catch (\Throwable $e) {
                        $formattedResult = 'Error executing tool ' . $toolName . ': ' . $e->getMessage();
                    }

                    $executedTools[] = [
                        'step' => $step,
                        'tool' => $toolName,
                        'arguments' => $toolArgs,
                        'result' => $formattedResult,
                    ];

                    $this->notifyStep($step, 'tool_result', [
                        'name' => $toolName,
                        'result' => $formattedResult,
                    ]);

                    // Add tool response to conversation
                    $messages[] = ChatMessage::tool(
                        content: (string)$formattedResult,
                        toolCallId: $toolCall->getId(),
                        name: $toolName
                    );
                }

                // Continue loop with tool results fed back to LLM
                continue;
            }

            // No tool calls — final answer from LLM
            $finalContent = $response->getContent();
            $this->notifyStep($step, 'completed', ['content' => $finalContent]);
            break;
        }

        if ($step >= $this->maxSteps && empty($finalContent)) {
            $finalContent = "Agent reached maximum step limit ({$this->maxSteps}) before concluding.";
        }

        // Update memory with user prompt and assistant final answer
        $this->memory->add($userMessage);
        $this->memory->add(ChatMessage::assistant($finalContent));

        $executionTime = round(microtime(true) - $startTime, 4);

        return new AgentResult(
            content: $finalContent,
            steps: $step,
            executedTools: $executedTools,
            executionTime: $executionTime,
            usage: [
                'prompt_tokens' => $totalPromptTokens,
                'completion_tokens' => $totalCompletionTokens,
                'total_tokens' => $totalPromptTokens + $totalCompletionTokens,
            ],
            lastResponse: $finalResponse
        );
    }

    protected function notifyStep(int $step, string $type, mixed $data): void
    {
        foreach ($this->stepListeners as $listener) {
            $listener($step, $type, $data);
        }
    }
}
