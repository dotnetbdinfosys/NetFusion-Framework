<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Application;
use NetFusion\Core\AI\Agent\Tools\ParameterType;
use NetFusion\Core\AI\Agent\Tools\ToolParameter;
use NetFusion\Core\AI\Agent\Tools\ToolDefinition;
use NetFusion\Core\AI\Agent\Tools\CallableTool;
use NetFusion\Core\AI\Agent\Tools\ReflectionToolSynthesizer;
use NetFusion\Core\AI\Agent\Execution\ToolCall;
use NetFusion\Core\AI\Agent\Execution\ToolExecutionResult;
use NetFusion\Core\AI\Agent\Execution\ToolRegistry;
use NetFusion\Core\AI\Agent\Execution\SandboxedToolDispatcher;
use NetFusion\Core\AI\Agent\Memory\MemoryMessage;
use NetFusion\Core\AI\Agent\Memory\ShortTermMemory;
use NetFusion\Core\AI\Agent\Memory\WorkingMemory;
use NetFusion\Core\AI\Agent\Memory\SemanticMemory;
use NetFusion\Core\AI\Agent\Memory\AgenticMemoryFabric;
use NetFusion\Core\AI\Agent\Planning\AgentThought;
use NetFusion\Core\AI\Agent\Planning\AgentResult;
use NetFusion\Core\AI\Agent\Planning\ReActAgent;
use NetFusion\Core\AI\Agent\AgentManager;
use NetFusion\Core\Console\Commands\AgentRunCommand;
use NetFusion\Core\Console\Commands\AgentToolsCommand;
use NetFusion\Core\Console\Commands\AgentMemoryCommand;

$app = new Application(realpath(__DIR__ . '/..'));

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $testName): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [\033[32mPASS\033[0m] {$testName}\n";
    } else {
        $failed++;
        echo "  [\033[31mFAIL\033[0m] {$testName}\n";
    }
}

echo "\n\033[1;36m=== NetFusion Phase 75: Enterprise AI Function Calling & Agentic Memory Fabrics Test Suite ===\033[0m\n\n";

// ==========================================
// 1. Tool Parameters and Definitions
// ==========================================
echo "\033[1;33m[1. Tool Parameters & Definitions]\033[0m\n";

$param1 = new ToolParameter('city', ParameterType::STRING, 'The city name', true, ['London', 'Dhaka', 'Tokyo']);
$param2 = new ToolParameter('units', ParameterType::STRING, 'Temperature unit', false, ['celsius', 'fahrenheit'], 'celsius');

$schema1 = $param1->toJsonSchema();
assertTest($schema1['type'] === 'string' && count($schema1['enum']) === 3, 'ToolParameter generates valid JSON Schema with enums');

$toolDef = new ToolDefinition('get_weather', 'Fetch weather information for a given city.', [
    'city' => $param1,
    'units' => $param2,
]);

$openAiSchema = $toolDef->toOpenAiSchema();
assertTest($openAiSchema['type'] === 'function' && $openAiSchema['function']['name'] === 'get_weather', 'ToolDefinition exports standard OpenAI function calling format');
assertTest($openAiSchema['function']['parameters']['required'] === ['city'], 'ToolDefinition correctly identifies required parameters');

$geminiSchema = $toolDef->toGeminiSchema();
assertTest($geminiSchema['name'] === 'get_weather' && isset($geminiSchema['parameters']['properties']['city']), 'ToolDefinition exports Google Gemini function declaration format');

$anthropicSchema = $toolDef->toAnthropicSchema();
assertTest($anthropicSchema['name'] === 'get_weather' && isset($anthropicSchema['input_schema']), 'ToolDefinition exports Anthropic Claude tool schema format');

// ==========================================
// 2. CallableTool & Dynamic Execution
// ==========================================
echo "\n\033[1;33m[2. CallableTool & Sandboxed Execution]\033[0m\n";

$weatherTool = new CallableTool(
    'get_weather',
    'Get weather for a city',
    function (string $city, string $units = 'celsius'): string {
        return "Weather in {$city}: 28° {$units}, Sunny";
    },
    [
        new ToolParameter('city', ParameterType::STRING, 'Target city', true),
        new ToolParameter('units', ParameterType::STRING, 'Unit', false, null, 'celsius'),
    ]
);

$res = $weatherTool->execute(['city' => 'Dhaka']);
assertTest(str_contains($res, '28° celsius, Sunny'), 'CallableTool executes successfully with default optional parameters');

// ==========================================
// 3. Reflection Tool Synthesizer
// ==========================================
echo "\n\033[1;33m[3. Reflection Tool Synthesizer]\033[0m\n";

class SampleOrderService {
    /**
     * Calculate tax for an order total.
     * @param float $subtotal The net order subtotal
     * @param float $taxRate The tax percentage rate
     * @return float
     */
    public function calculateTax(float $subtotal, float $taxRate = 0.15): float {
        return $subtotal * $taxRate;
    }

    /**
     * Check warehouse inventory.
     */
    public function checkStock(int $productId, string $warehouseCode = 'WH-MAIN'): bool {
        return $productId > 0;
    }
}

$synthesizer = new ReflectionToolSynthesizer();
$synthesizedTools = $synthesizer->synthesizeFromClass(SampleOrderService::class, 'order');

assertTest(count($synthesizedTools) === 2, 'ReflectionToolSynthesizer discovers and synthesizes 2 public methods');
assertTest(isset($synthesizedTools['order_calculateTax']), 'Synthesizer applies method prefix correctly');
assertTest(isset($synthesizedTools['order_checkStock']), 'Synthesizer synthesizes second method correctly');

$taxTool = $synthesizedTools['order_calculateTax'];
$taxResult = $taxTool->execute(['subtotal' => 100.0, 'taxRate' => 0.05]);
assertTest((float) $taxResult === 5.0, 'Synthesized tool executes underlying reflected method accurately');

// ==========================================
// 4. Tool Registry & Sandboxed Dispatcher
// ==========================================
echo "\n\033[1;33m[4. Tool Registry & Sandboxed Dispatcher]\033[0m\n";

$registry = new ToolRegistry();
$registry->register($weatherTool);
$registry->registerAll($synthesizedTools);

assertTest($registry->count() === 3, 'ToolRegistry registers all tools correctly');
assertTest($registry->has('get_weather') && $registry->has('order_checkStock'), 'ToolRegistry has() correctly checks tool existence');

$dispatcher = new SandboxedToolDispatcher($registry);

// Dispatch valid call
$validCall = new ToolCall('call_01', 'get_weather', ['city' => 'Tokyo', 'units' => 'celsius']);
$execRes = $dispatcher->dispatch($validCall);
assertTest(!$execRes->isError && str_contains($execRes->result, 'Tokyo'), 'SandboxedToolDispatcher dispatches valid tool call');

// Dispatch missing required parameter
$missingParamCall = new ToolCall('call_02', 'get_weather', []);
$errRes1 = $dispatcher->dispatch($missingParamCall);
assertTest($errRes1->isError && str_contains($errRes1->errorMessage, 'Missing required parameter'), 'SandboxedToolDispatcher validates required parameters');

// Dispatch unknown tool call
$unknownCall = new ToolCall('call_03', 'non_existing_tool', ['foo' => 'bar']);
$errRes2 = $dispatcher->dispatch($unknownCall);
assertTest($errRes2->isError && str_contains($errRes2->errorMessage, 'not found in registry'), 'SandboxedToolDispatcher safely catches unknown tools');

// Dispatch throwing tool
$throwingTool = new CallableTool('crash_tool', 'A tool that fails', function() {
    throw new \RuntimeException("Database connection timed out");
});
$registry->register($throwingTool);
$crashCall = new ToolCall('call_04', 'crash_tool', []);
$errRes3 = $dispatcher->dispatch($crashCall);
assertTest($errRes3->isError && str_contains($errRes3->errorMessage, 'Database connection timed out'), 'SandboxedToolDispatcher shields exceptions into error observations');

// ==========================================
// 5. Memory Message & Short-Term Memory
// ==========================================
echo "\n\033[1;33m[5. Short-Term Memory Sliding Buffer]\033[0m\n";

$shortMem = new ShortTermMemory(4);
$shortMem->addSystemMessage("You are NetFusion AI.");
$shortMem->addUserMessage("Hello 1");
$shortMem->addAssistantMessage("Reply 1");
$shortMem->addUserMessage("Hello 2");
$shortMem->addAssistantMessage("Reply 2");
$shortMem->addUserMessage("Hello 3");

$messages = $shortMem->getMessages();
assertTest(count($messages) <= 4, 'ShortTermMemory trims older messages to stay within window size');
assertTest($messages[0]->role === 'system', 'ShortTermMemory preserves initial system prompt during buffer sliding');

// ==========================================
// 6. Working Memory (Scratchpad & Checklist)
// ==========================================
echo "\n\033[1;33m[6. Working Memory (Scratchpad & Checklist)]\033[0m\n";

$workingMem = new WorkingMemory();
$workingMem->set('user_budget', 5000);
$workingMem->set('destination', 'Paris');
assertTest($workingMem->get('user_budget') === 5000 && $workingMem->has('destination'), 'WorkingMemory stores and retrieves key-value variables');

$workingMem->addChecklistItem('Search flights');
$workingMem->addChecklistItem('Book hotel');
$workingMem->markChecklistItemDone(0);

$items = $workingMem->getChecklist();
assertTest($items[0]['done'] === true && $items[1]['done'] === false, 'WorkingMemory manages step-by-step checklist status');

$workingContext = $workingMem->toContextPrompt();
assertTest(str_contains($workingContext, '[x] Search flights') && str_contains($workingContext, 'user_budget: 5000'), 'WorkingMemory compiles structured context prompt');

// ==========================================
// 7. Semantic Long-Term Vector Memory
// ==========================================
echo "\n\033[1;33m[7. Semantic Long-Term Vector Memory]\033[0m\n";

$semanticMem = new SemanticMemory();
$semanticMem->remember("The database credentials host is db.internal.net on port 5432.", ['category' => 'infra']);
$semanticMem->remember("The API rate limit is 1000 requests per minute for authenticated tenants.", ['category' => 'api']);
$semanticMem->remember("Customers can request refunds within 14 days of purchase.", ['category' => 'policy']);

$recall1 = $semanticMem->recall("What is the database host?", 2);
assertTest(count($recall1) > 0 && str_contains($recall1[0]['content'], 'db.internal.net'), 'SemanticMemory ranks and recalls most relevant document using cosine similarity');

$recall2 = $semanticMem->recall("How many days for refund?", 1);
assertTest(count($recall2) === 1 && str_contains($recall2[0]['content'], '14 days'), 'SemanticMemory correctly recalls refund policy document');

// ==========================================
// 8. Agentic Memory Fabric (Unified Coordinator)
// ==========================================
echo "\n\033[1;33m[8. Unified Agentic Memory Fabric]\033[0m\n";

$fabric = new AgenticMemoryFabric(['window_size' => 10, 'compact_observations' => true, 'observation_char_limit' => 50]);
$longObservation = str_repeat("Very long API response telemetry data string. ", 10);
$compacted = $fabric->compactObservation($longObservation);
assertTest(strlen($compacted) < strlen($longObservation) && str_contains($compacted, '... [truncated'), 'AgenticMemoryFabric compacts verbose observation data');

$fabric->rememberFact('Weather in Dhaka is currently 28C Sunny with mild breeze.');
$fabric->recordObservation('get_weather', 'Dhaka is 28C Sunny');
$fabricPrompt = $fabric->compileContextPrompt('weather');
assertTest(str_contains($fabricPrompt, 'Agent Memory Context') && str_contains($fabricPrompt, 'Dhaka is currently 28C'), 'AgenticMemoryFabric compiles unified memory prompt');

// ==========================================
// 9. ReAct Autonomous Agent Loop
// ==========================================
echo "\n\033[1;33m[9. ReAct Autonomous Agent Loop]\033[0m\n";

$stepCounter = 0;
$mockLLM = function (string $prompt) use (&$stepCounter): string {
    $stepCounter++;
    if ($stepCounter === 1) {
        return "Thought: I need to check the stock of product 42 first.\n" .
               "Action: order_checkStock\n" .
               "Action Input: {\"productId\": 42}";
    } elseif ($stepCounter === 2) {
        return "Thought: Product 42 is in stock. Now I should calculate tax for subtotal 200.\n" .
               "Action: order_calculateTax\n" .
               "Action Input: {\"subtotal\": 200.0, \"taxRate\": 0.10}";
    } else {
        return "Thought: I have checked the stock and calculated tax.\n" .
               "Final Answer: Product 42 is available in stock and the calculated tax is $20.00.";
    }
};

$agent = new ReActAgent(
    registry: $registry,
    dispatcher: $dispatcher,
    memory: $fabric,
    llmCallable: $mockLLM,
    name: 'OrderAssistant',
    maxIterations: 5
);

$agentResult = $agent->run("Check stock for product 42 and calculate tax for $200");
assertTest($agentResult->isSuccess === true, 'ReActAgent completes multi-step autonomous loop successfully');
assertTest($agentResult->toolCallsCount === 2, 'ReActAgent calls exactly 2 tools during execution');
assertTest(str_contains($agentResult->finalAnswer, 'Product 42 is available in stock'), 'ReActAgent produces accurate final answer');
assertTest(count($agentResult->thoughts) === 3, 'ReActAgent logs all iteration thoughts and observations');

// Test agent exceeding max iterations
$infiniteLLM = function (string $prompt): string {
    return "Thought: I am looping.\nAction: order_checkStock\nAction Input: {\"productId\": 1}";
};
$loopAgent = new ReActAgent($registry, $dispatcher, $fabric, $infiniteLLM, 'Looper', 2);
$loopRes = $loopAgent->run("Loop forever");
assertTest($loopRes->isSuccess === false && str_contains($loopRes->finalAnswer, 'maximum iterations'), 'ReActAgent respects maxIterations execution guard');

// ==========================================
// 10. AgentManager Master Facade & Global Helpers
// ==========================================
echo "\n\033[1;33m[10. AgentManager Facade & Global Helpers]\033[0m\n";

$manager = agent_manager();
assertTest($manager instanceof AgentManager, 'agent_manager() helper returns AgentManager instance');

$createdTool = agent_tool('ping_service', 'Ping test', fn(): string => 'pong');
assertTest($manager->getToolRegistry()->has('ping_service'), 'agent_tool() registers callable tool directly');

$memFabric = agent_memory('TestAgent');
assertTest($memFabric instanceof AgenticMemoryFabric, 'agent_memory() returns AgenticMemoryFabric');

$agentInstance = agent(fn($p) => "Final Answer: Done", 'QuickAgent');
assertTest($agentInstance instanceof ReActAgent, 'agent() factory returns configured ReActAgent instance');

// ==========================================
// 11. CLI Commands Verification
// ==========================================
echo "\n\033[1;33m[11. CLI Commands Execution]\033[0m\n";

$toolsCmd = new AgentToolsCommand($app);
ob_start();
$toolsExit = $toolsCmd->handle(['--format=json']);
$toolsOut = ob_get_clean();
assertTest($toolsExit === 0 && str_contains($toolsOut, 'calculator'), 'AgentToolsCommand outputs valid tool JSON schema definitions');

$memCmd = new AgentMemoryCommand($app);
ob_start();
$memCmd->handle(['remember', 'speed_note', 'NetFusion is blazing fast']);
$memRecallExit = $memCmd->handle(['recall', 'blazing fast']);
$memOut = ob_get_clean();
assertTest($memRecallExit === 0 && str_contains($memOut, 'speed_note'), 'AgentMemoryCommand stores and recalls semantic memory');

$runCmd = new AgentRunCommand($app);
ob_start();
$runExit = $runCmd->handle(['Calculate 42 * 10']);
$runOut = ob_get_clean();
assertTest($runExit === 0 && str_contains($runOut, 'Final Answer'), 'AgentRunCommand executes autonomous ReAct loop from CLI');

// ==========================================
// Test Results Summary
// ==========================================
echo "\n\033[1;36m=================================================================\033[0m\n";
echo "Total Tests Run: " . ($passed + $failed) . "\n";
echo "\033[32mPassed: {$passed}\033[0m\n";
if ($failed > 0) {
    echo "\033[31mFailed: {$failed}\033[0m\n";
}
echo "\033[1;36m=================================================================\033[0m\n";

exit($failed > 0 ? 1 : 0);
