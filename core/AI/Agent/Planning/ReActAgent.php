<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Planning;

use Closure;
use NetFusion\Core\AI\Agent\Execution\SandboxedToolDispatcher;
use NetFusion\Core\AI\Agent\Execution\ToolCall;
use NetFusion\Core\AI\Agent\Execution\ToolRegistry;
use NetFusion\Core\AI\Agent\Memory\AgenticMemoryFabric;
use NetFusion\Core\AI\Agent\Memory\MemoryMessage;

class ReActAgent
{
    private ToolRegistry $registry;
    private SandboxedToolDispatcher $dispatcher;
    private AgenticMemoryFabric $memory;
    private Closure $llmCallable;

    public function __construct(
        ToolRegistry $registry,
        ?SandboxedToolDispatcher $dispatcher = null,
        ?AgenticMemoryFabric $memory = null,
        ?callable $llmCallable = null,
        private readonly string $name = 'NetFusionAgent',
        private readonly int $maxIterations = 15
    ) {
        $this->registry = $registry;
        $this->dispatcher = $dispatcher ?? new SandboxedToolDispatcher($registry);
        $this->memory = $memory ?? new AgenticMemoryFabric();

        $this->llmCallable = $llmCallable !== null
            ? ($llmCallable instanceof Closure ? $llmCallable : Closure::fromCallable($llmCallable))
            : fn($prompt, $history = [], $tools = []) => "Final Answer: Task completed.";
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMemory(): AgenticMemoryFabric
    {
        return $this->memory;
    }

    public function getRegistry(): ToolRegistry
    {
        return $this->registry;
    }

    public function getDispatcher(): SandboxedToolDispatcher
    {
        return $this->dispatcher;
    }

    /**
     * Run the autonomous ReAct reasoning and action execution loop.
     */
    public function run(string $task, ?int $maxIterations = null): AgentResult
    {
        $maxIter = $maxIterations ?? $this->maxIterations;
        $startTime = microtime(true);
        $thoughts = [];
        $toolCallsCount = 0;

        // Initialize session in memory
        $systemPrompt = $this->buildSystemPrompt();
        $this->memory->addMessage(MemoryMessage::system($systemPrompt));
        $this->memory->addMessage(MemoryMessage::user($task));

        $finalAnswer = '';
        $isSuccess = false;

        for ($iteration = 1; $iteration <= $maxIter; $iteration++) {
            // Context injection from working and semantic memory
            $promptContext = $this->memory->getFormattedContextForPrompt($task);

            $history = $this->memory->shortTerm()->toChatHistory();
            if ($promptContext !== '') {
                $history[] = ['role' => 'system', 'content' => $promptContext];
            }

            $toolsSchema = $this->registry->toFunctionSchemas();

            // Construct text prompt representation
            $promptText = "Task: {$task}\n";
            foreach ($history as $h) {
                $promptText .= ucfirst($h['role']) . ": " . (is_string($h['content']) ? $h['content'] : json_encode($h['content'])) . "\n";
            }

            // Invoke LLM callable with both string prompt and structured args
            $rawLlmResponse = ($this->llmCallable)($promptText, $history, $toolsSchema);

            $parsed = $this->parseReActResponse((string) $rawLlmResponse, $iteration);
            $thoughts[] = $parsed;

            if ($parsed->hasFinalAnswer()) {
                $finalAnswer = (string) $parsed->getFinalAnswer();
                $isSuccess = true;
                $this->memory->addMessage(MemoryMessage::assistant($finalAnswer));
                break;
            }

            if ($parsed->hasAction()) {
                $toolCallsCount++;
                $toolName = (string) $parsed->getAction();
                $toolArgs = (array) ($parsed->getActionInput() ?? []);

                $call = new ToolCall(uniqid('call_', true), $toolName, $toolArgs);
                $execResult = $this->dispatcher->dispatch($call);
                $observation = $execResult->toObservationString();

                $parsed->setObservation($observation);

                // Record assistant action and tool observation in short-term memory
                $this->memory->addMessage(MemoryMessage::assistant(
                    "Thought: {$parsed->getThought()}\nAction: {$toolName}\nAction Input: " . json_encode($toolArgs)
                ));
                $this->memory->addMessage(MemoryMessage::tool($observation, $call->getId(), $toolName));
            } else {
                // Thought without action or final answer: treat thought as final answer if loop ends
                $finalAnswer = $parsed->getThought();
                $isSuccess = true;
                break;
            }
        }

        if (!$isSuccess && empty($finalAnswer)) {
            $finalAnswer = "Agent stopped: reached maximum iterations ({$maxIterations}) without resolving final answer.";
        }

        $totalDurationMs = round((microtime(true) - $startTime) * 1000.0, 3);

        return new AgentResult(
            task: $task,
            finalAnswer: $finalAnswer,
            thoughts: $thoughts,
            iterations: count($thoughts),
            isSuccess: $isSuccess,
            totalDurationMs: $totalDurationMs,
            toolCallsCount: $toolCallsCount
        );
    }

    private function buildSystemPrompt(): string
    {
        $toolDescriptions = [];
        foreach ($this->registry->all() as $name => $tool) {
            $toolDescriptions[] = "- {$name}: {$tool->getDescription()}";
        }
        $toolList = implode("\n", $toolDescriptions);

        return <<<PROMPT
You are an intelligent autonomous AI Agent using the ReAct (Reasoning + Acting) framework.
Answer the user's request using the following format:

Thought: Consider what you should do next.
Action: The name of the tool to execute. Must be one of the available tools.
Action Input: A valid JSON object representing the arguments to the tool.
Observation: The result from executing the action (this will be provided to you).
... (this Thought/Action/Action Input/Observation can repeat N times)
Thought: I now know the final answer.
Final Answer: The final conclusion or response to the original task.

Available tools:
{$toolList}
PROMPT;
    }

    private function parseReActResponse(string $response, int $iteration): AgentThought
    {
        $thought = '';
        $action = null;
        $actionInput = null;
        $finalAnswer = null;

        // Check for Final Answer:
        if (preg_match('/Final Answer:\s*(.+)$/s', $response, $matches)) {
            $finalAnswer = trim($matches[1]);
            // Extract thought before Final Answer if present
            if (preg_match('/Thought:\s*(.*?)(?:Final Answer:|$)/s', $response, $tMatch)) {
                $thought = trim($tMatch[1]);
            }
            return new AgentThought($iteration, $thought ?: $finalAnswer, null, null, null, $finalAnswer);
        }

        // Extract Thought
        if (preg_match('/Thought:\s*(.*?)(?:Action:|$)/s', $response, $matches)) {
            $thought = trim($matches[1]);
        }

        // Extract Action
        if (preg_match('/Action:\s*([a-zA-Z0-9_\-]+)/', $response, $matches)) {
            $action = trim($matches[1]);
        }

        // Extract Action Input (JSON)
        if (preg_match('/Action Input:\s*(.+)$/m', $response, $matches)) {
            $rawJson = trim($matches[1]);
            $decoded = json_decode($rawJson, true);
            $actionInput = is_array($decoded) ? $decoded : ['input' => $rawJson];
        }

        if (empty($thought)) {
            $thought = trim($response);
        }

        return new AgentThought($iteration, $thought, $action, $actionInput);
    }
}
