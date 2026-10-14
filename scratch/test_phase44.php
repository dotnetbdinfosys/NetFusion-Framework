<?php

/**
 * NetFusion Framework - Phase 44 Comprehensive Test Suite
 * Enterprise AI Agent Engine, LLM Tool Calling & Vector Memory Pipeline
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\AI\Agents\Agent;
use NetFusion\Core\AI\Agents\AgentResult;
use NetFusion\Core\AI\AiException;
use NetFusion\Core\AI\AiManager;
use NetFusion\Core\AI\ChatMessage;
use NetFusion\Core\AI\ChatResponse;
use NetFusion\Core\AI\Drivers\MockAiDriver;
use NetFusion\Core\AI\Drivers\OpenAiDriver;
use NetFusion\Core\AI\Memory\BufferMemory;
use NetFusion\Core\AI\Memory\VectorMemory;
use NetFusion\Core\AI\Memory\WindowedMemory;
use NetFusion\Core\AI\ToolCall;
use NetFusion\Core\AI\Tools\CalculatorTool;
use NetFusion\Core\AI\Tools\CustomTool;
use NetFusion\Core\AI\Tools\ToolRegistry;
use NetFusion\Core\AI\Vector\ArrayVectorStore;
use NetFusion\Core\AI\Vector\DatabaseVectorStore;
use NetFusion\Core\AI\Vector\VectorMath;
use NetFusion\Core\Application;
use NetFusion\Core\Console\Kernel;
use NetFusion\Core\Database\Connection;

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name}" . ($detail ? " - {$detail}" : '') . "\n";
    }
}

echo "\n=======================================================\n";
echo " NETFUSION PHASE 44 TEST SUITE: ENTERPRISE AI AGENT ENGINE\n";
echo "=======================================================\n\n";

// -----------------------------------------------------------------------------
// 1. ChatMessage, ChatResponse & ToolCall Value Objects
// -----------------------------------------------------------------------------
echo "1. Testing AI Value Objects (ChatMessage, ChatResponse, ToolCall)...\n";

$msg = ChatMessage::user('Hello World', metadata: ['user_id' => 101]);
assert_test("ChatMessage::user role is 'user'", $msg->getRole() === 'user');
assert_test("ChatMessage content matches", $msg->getContent() === 'Hello World');
assert_test("ChatMessage metadata matches", ($msg->getMetadata()['user_id'] ?? null) === 101);

$sysMsg = ChatMessage::system('You are an assistant.');
assert_test("ChatMessage::system role is 'system'", $sysMsg->getRole() === 'system');

$toolCall = new ToolCall('call_123', 'calculator', ['expression' => '10 + 20']);
assert_test("ToolCall id and name", $toolCall->getId() === 'call_123' && $toolCall->getName() === 'calculator');
assert_test("ToolCall argument lookup", $toolCall->getArgument('expression') === '10 + 20');
assert_test("ToolCall toArray format", ($toolCall->toArray()['function']['name'] ?? '') === 'calculator');

$astMsg = ChatMessage::assistant('Let me calculate that.', [$toolCall]);
assert_test("ChatMessage assistant hasToolCalls is true", $astMsg->hasToolCalls());
assert_test("ChatMessage toolCalls count", count($astMsg->getToolCalls()) === 1);

$toolMsg = ChatMessage::tool('30', 'call_123', 'calculator');
assert_test("ChatMessage::tool role and toolCallId", $toolMsg->getRole() === 'tool' && $toolMsg->getToolCallId() === 'call_123');

$chatResponse = new ChatResponse(
    content: 'Result is 30',
    role: 'assistant',
    toolCalls: [$toolCall],
    usage: ['prompt_tokens' => 20, 'completion_tokens' => 10, 'total_tokens' => 30],
    model: 'mock-model'
);
assert_test("ChatResponse usage getters", $chatResponse->getPromptTokens() === 20 && $chatResponse->getCompletionTokens() === 10 && $chatResponse->getTotalTokens() === 30);
assert_test("ChatResponse model and content", $chatResponse->getModel() === 'mock-model' && $chatResponse->getContent() === 'Result is 30');
assert_test("ChatResponse toChatMessage conversion", $chatResponse->toChatMessage()->getRole() === 'assistant');


// -----------------------------------------------------------------------------
// 2. VectorMath & Similarity Metrics
// -----------------------------------------------------------------------------
echo "\n2. Testing VectorMath & Metrics...\n";

$v1 = [1.0, 0.0, 0.0];
$v2 = [1.0, 0.0, 0.0];
$v3 = [0.0, 1.0, 0.0];
$v4 = [-1.0, 0.0, 0.0];

assert_test("VectorMath::dotProduct identical", VectorMath::dotProduct($v1, $v2) == 1.0);
assert_test("VectorMath::dotProduct orthogonal", VectorMath::dotProduct($v1, $v3) == 0.0);
assert_test("VectorMath::magnitude unit vector", abs(VectorMath::magnitude($v1) - 1.0) < 0.0001);
assert_test("VectorMath::magnitude 3-4-5 triangle", abs(VectorMath::magnitude([3.0, 4.0]) - 5.0) < 0.0001);
assert_test("VectorMath::cosineSimilarity identical vectors = 1.0", abs(VectorMath::cosineSimilarity($v1, $v2) - 1.0) < 0.0001);
assert_test("VectorMath::cosineSimilarity orthogonal vectors = 0.0", abs(VectorMath::cosineSimilarity($v1, $v3) - 0.0) < 0.0001);
assert_test("VectorMath::cosineSimilarity opposite vectors = -1.0", abs(VectorMath::cosineSimilarity($v1, $v4) - (-1.0)) < 0.0001);
assert_test("VectorMath::euclideanDistance identical = 0.0", abs(VectorMath::euclideanDistance($v1, $v2) - 0.0) < 0.0001);
assert_test("VectorMath::euclideanDistance [0,0] to [3,4] = 5.0", abs(VectorMath::euclideanDistance([0.0, 0.0], [3.0, 4.0]) - 5.0) < 0.0001);

$unnorm = [3.0, 4.0];
$normed = VectorMath::normalize($unnorm);
assert_test("VectorMath::normalize length is 1.0", abs(VectorMath::magnitude($normed) - 1.0) < 0.0001);

$tfidf = VectorMath::simpleTfIdf("machine learning algorithms", ["machine", "learning", "pizza", "algorithms"]);
assert_test("VectorMath::simpleTfIdf dimension matches vocabulary", count($tfidf) === 4);
assert_test("VectorMath::simpleTfIdf pizza index is 0.0", $tfidf[2] == 0.0 && $tfidf[0] > 0.0);


// -----------------------------------------------------------------------------
// 3. ArrayVectorStore & DatabaseVectorStore
// -----------------------------------------------------------------------------
echo "\n3. Testing Vector Stores (Array & Database)...\n";

$arrayStore = new ArrayVectorStore();
$arrayStore->add('doc1', [1.0, 0.0, 0.0], 'Introduction to PHP 8.2', ['category' => 'tech']);
$arrayStore->add('doc2', [0.95, 0.1, 0.0], 'PHP Architecture and Design Patterns', ['category' => 'tech']);
$arrayStore->add('doc3', [0.0, 1.0, 0.0], 'Delicious Italian Pizza Recipes', ['category' => 'food']);

assert_test("ArrayVectorStore count is 3", $arrayStore->count() === 3);

$searchRes = $arrayStore->search([1.0, 0.0, 0.0], limit: 2);
assert_test("ArrayVectorStore search returns top 2 matches", count($searchRes) === 2);
assert_test("ArrayVectorStore top match is doc1 (score ~1.0)", $searchRes[0]['id'] === 'doc1' && $searchRes[0]['score'] >= 0.99);
assert_test("ArrayVectorStore second match is doc2", $searchRes[1]['id'] === 'doc2' && $searchRes[1]['score'] >= 0.9);

$arrayStore->delete('doc3');
assert_test("ArrayVectorStore delete decrements count", $arrayStore->count() === 2);
$arrayStore->clear();
assert_test("ArrayVectorStore clear empties collection", $arrayStore->count() === 0);

// Database Vector Store
$dbPath = sys_get_temp_dir() . '/netfusion_ai_test_' . uniqid() . '.sqlite';
$conn = new Connection(['driver' => 'sqlite', 'database' => $dbPath]);

// Run migration
$migration = require __DIR__ . '/../database/migrations/2026_09_20_000024_create_ai_vector_and_chat_tables.php';
$migration->up($conn);

$dbStore = new DatabaseVectorStore($conn->getPdo());
$dbStore->add('kb1', [0.8, 0.6, 0.0], 'NetFusion Framework Kernel', ['author' => 'DotNetBD'], 'kb');
$dbStore->add('kb2', [0.0, 0.0, 1.0], 'Cooking Masterclass', ['author' => 'Chef'], 'kb');
$dbStore->add('other', [1.0, 0.0, 0.0], 'Unrelated topic', [], 'other_collection');

assert_test("DatabaseVectorStore count in 'kb' collection", $dbStore->count('kb') === 2);
assert_test("DatabaseVectorStore count in 'other_collection'", $dbStore->count('other_collection') === 1);

$dbSearch = $dbStore->search([0.8, 0.6, 0.0], limit: 1, collection: 'kb');
assert_test("DatabaseVectorStore search top match is kb1", count($dbSearch) === 1 && $dbSearch[0]['id'] === 'kb1');
assert_test("DatabaseVectorStore search metadata retained", ($dbSearch[0]['metadata']['author'] ?? '') === 'DotNetBD');

$dbStore->delete('kb2', 'kb');
assert_test("DatabaseVectorStore delete removes item", $dbStore->count('kb') === 1);
$dbStore->clear('kb');
assert_test("DatabaseVectorStore clear cleans collection", $dbStore->count('kb') === 0);


// -----------------------------------------------------------------------------
// 4. Memory Implementations (Buffer, Windowed, Vector)
// -----------------------------------------------------------------------------
echo "\n4. Testing Conversation Memory Modules...\n";

// Buffer Memory
$buffer = new BufferMemory();
$buffer->add('user', 'Hello there');
$buffer->add('assistant', 'How can I assist you today?');
assert_test("BufferMemory count is 2", $buffer->count() === 2);
assert_test("BufferMemory getMessages roles", $buffer->getMessages()[0]->getRole() === 'user' && $buffer->getMessages()[1]->getRole() === 'assistant');
assert_test("BufferMemory getRecent returns last 1", count($buffer->getRecent(1)) === 1 && $buffer->getRecent(1)[0]->getRole() === 'assistant');

// Windowed Memory
$window = new WindowedMemory(windowSize: 2);
$window->add('system', 'System instructions');
$window->add('user', 'Message 1');
$window->add('assistant', 'Reply 1');
$window->add('user', 'Message 2');
$window->add('assistant', 'Reply 2');

$winMsgs = $window->getMessages();
assert_test("WindowedMemory keeps system prompt + last 2 messages", count($winMsgs) === 3);
assert_test("WindowedMemory first message is system", $winMsgs[0]->getRole() === 'system');
assert_test("WindowedMemory second message is Message 2", $winMsgs[1]->getContent() === 'Message 2');
assert_test("WindowedMemory third message is Reply 2", $winMsgs[2]->getContent() === 'Reply 2');

// Vector Memory
$mockDriver = new MockAiDriver();
$vectorMem = new VectorMemory(new ArrayVectorStore(), $mockDriver, 'test_mem');
$vectorMem->add('user', 'My favorite programming language is PHP 8.2');
$vectorMem->add('user', 'I live in Dhaka, Bangladesh');

assert_test("VectorMemory messages buffer", count($vectorMem->getMessages()) === 2);
$relSearch = $vectorMem->searchRelevant('programming language', limit: 2, minScore: 0.0);
assert_test("VectorMemory searchRelevant returns items", !empty($relSearch));


// -----------------------------------------------------------------------------
// 5. Tools & ToolRegistry
// -----------------------------------------------------------------------------
echo "\n5. Testing Tools & ToolRegistry...\n";

$calc = new CalculatorTool();
assert_test("CalculatorTool name is 'calculator'", $calc->getName() === 'calculator');
assert_test("CalculatorTool basic addition", $calc->execute(['expression' => '25 + 75']) == 100);
assert_test("CalculatorTool multiplication & division", $calc->execute(['expression' => '10 * 5 / 2']) == 25);
assert_test("CalculatorTool sqrt and pow", $calc->execute(['expression' => 'sqrt(144) + pow(2, 3)']) == 20);

$calcFailed = false;
try {
    $calc->execute(['expression' => 'system("rm -rf /")']);
} catch (\InvalidArgumentException) {
    $calcFailed = true;
}
assert_test("CalculatorTool blocks dangerous code/functions", $calcFailed);

$customTool = CustomTool::make(
    'greet',
    'Greets a user by name',
    ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
    fn(array $args) => "Hello, " . ($args['name'] ?? 'Friend') . "!"
);
assert_test("CustomTool execution", $customTool->execute(['name' => 'Ashish']) === 'Hello, Ashish!');

$registry = new ToolRegistry([$calc, $customTool]);
assert_test("ToolRegistry has tools", $registry->has('calculator') && $registry->has('greet'));
assert_test("ToolRegistry execute", $registry->execute('greet', ['name' => 'NetFusion']) === 'Hello, NetFusion!');

$fnDefs = $registry->toFunctionDefinitions();
assert_test("ToolRegistry toFunctionDefinitions format", count($fnDefs) === 2 && ($fnDefs[0]['function']['name'] ?? '') === 'calculator');


// -----------------------------------------------------------------------------
// 6. Autonomous ReAct Agent Loop
// -----------------------------------------------------------------------------
echo "\n6. Testing Autonomous AI Agent Loop...\n";

// Scenario A: Direct answer without tools
$agentDriver = new MockAiDriver();
$agentDriver->queueResponse(new ChatResponse(content: "The capital of France is Paris."));

$agent = new Agent($agentDriver, $registry, new BufferMemory(), "You are a helpful assistant.");
$resultA = $agent->run("What is the capital of France?");

assert_test("Agent single-step direct completion", $resultA instanceof AgentResult);
assert_test("Agent result content matches", $resultA->getContent() === "The capital of France is Paris.");
assert_test("Agent steps count is 1", $resultA->getSteps() === 1);
assert_test("Agent executedTools is empty", empty($resultA->getExecutedTools()));

// Scenario B: Multi-step ReAct Tool Calling
// Step 1: LLM outputs tool call -> calculator(expression: '45 * 89')
// Step 2: Agent executes calculator, feeds result 4005 back to LLM -> LLM outputs "45 * 89 is 4005."
$agentDriver->queueResponse(new ChatResponse(
    content: '',
    role: 'assistant',
    toolCalls: [new ToolCall('calc_call_1', 'calculator', ['expression' => '45 * 89'])],
    model: 'mock-gpt'
));
$agentDriver->queueResponse(new ChatResponse(
    content: "The result of 45 multiplied by 89 is 4005.",
    role: 'assistant',
    toolCalls: [],
    model: 'mock-gpt'
));

$stepEvents = [];
$agent->onStep(function (int $step, string $type, mixed $data) use (&$stepEvents) {
    $stepEvents[] = ['step' => $step, 'type' => $type];
});

$resultB = $agent->run("Calculate 45 * 89 for me.");

assert_test("Agent multi-step tool calling succeeded", $resultB->getContent() === "The result of 45 multiplied by 89 is 4005.");
assert_test("Agent took 2 steps", $resultB->getSteps() === 2);
assert_test("Agent executed tools recorded", count($resultB->getExecutedTools()) === 1);
assert_test("Agent recorded executed tool details", $resultB->getExecutedTools()[0]['tool'] === 'calculator' && $resultB->getExecutedTools()[0]['result'] == 4005);
assert_test("Agent memory persisted user prompt and assistant final response", count($agent->getMemory()->getMessages()) === 4); // User1, Assistant1, User2, Assistant2


// -----------------------------------------------------------------------------
// 7. AiManager Coordination & Global Helpers
// -----------------------------------------------------------------------------
echo "\n7. Testing AiManager & Global Helpers...\n";

$app = new Application(dirname(__DIR__));
$app->instance(\NetFusion\Core\Database\DatabaseManager::class, new class($conn) {
    public function __construct(protected $conn) {}
    public function connection() { return $this->conn; }
});

/** @var AiManager $ai */
$ai = $app->make(AiManager::class);

assert_test("AiManager default driver is MockAiDriver", $ai->driver() instanceof MockAiDriver);
assert_test("AiManager vectorStore is ArrayVectorStore", $ai->vectorStore() instanceof ArrayVectorStore);
assert_test("AiManager database vectorStore resolution", $ai->vectorStore('database') instanceof DatabaseVectorStore);
assert_test("AiManager tools registry", $ai->tools() instanceof ToolRegistry && $ai->tools()->has('calculator'));

// Test chat() via AiManager
$chatRes = $ai->chat("Hello AI");
assert_test("AiManager::chat() returns ChatResponse", $chatRes instanceof ChatResponse && str_contains($chatRes->getContent(), 'Mock response'));

// Test embed() via AiManager
$emb = $ai->embed("DotNetBD Enterprise AI");
assert_test("AiManager::embed() returns vector array", is_array($emb) && count($emb) === 16);

// Test agent() factory via AiManager
$mgrAgent = $ai->agent("You are a specialized agent", ['calculator']);
assert_test("AiManager::agent() returns configured Agent", $mgrAgent instanceof Agent);
assert_test("AiManager agent has calculator tool", $mgrAgent->getToolRegistry()->has('calculator'));

// Global helpers test
assert_test("ai() helper returns AiManager", ai() instanceof AiManager);
assert_test("ai('Prompt') helper returns ChatResponse", ai("Testing helper") instanceof ChatResponse);
assert_test("ai_vector() helper returns VectorStoreInterface", ai_vector() instanceof ArrayVectorStore);
assert_test("ai_agent() helper returns Agent instance", ai_agent() instanceof Agent);


// -----------------------------------------------------------------------------
// 8. Console CLI Commands (ai:chat, ai:agent, ai:embed)
// -----------------------------------------------------------------------------
echo "\n8. Testing AI Console Commands...\n";

$kernel = $app->make(Kernel::class);

// Test ai:chat command
ob_start();
$exitChat = $kernel->handle(['netfusion', 'ai:chat', 'Hello AI CLI']);
$chatOutput = ob_get_clean();
assert_test("CLI ai:chat exit code 0", $exitChat === 0);
assert_test("CLI ai:chat output contains response", str_contains($chatOutput, 'AI Response'));

// Test ai:agent command
ob_start();
$exitAgent = $kernel->handle(['netfusion', 'ai:agent', 'What is 10 + 20?']);
$agentOutput = ob_get_clean();
assert_test("CLI ai:agent exit code 0", $exitAgent === 0);
assert_test("CLI ai:agent output contains Agent Result", str_contains($agentOutput, 'Agent Result'));

// Test ai:embed command
ob_start();
$exitEmbed = $kernel->handle(['netfusion', 'ai:embed', 'Embedding Test', '--store', '--collection=cli_docs']);
$embedOutput = ob_get_clean();
assert_test("CLI ai:embed exit code 0", $exitEmbed === 0);
assert_test("CLI ai:embed output contains Vector Dimensions", str_contains($embedOutput, 'Embedding Vector Dimensions: 16'));


// -----------------------------------------------------------------------------
// Clean up
// -----------------------------------------------------------------------------
if (file_exists($dbPath)) {
    @unlink($dbPath);
}

echo "\n=======================================================\n";
echo " PHASE 44 TESTS FINISHED\n";
echo " Passed: {$passed} | Failed: {$failed}\n";
echo "=======================================================\n\n";

exit($failed > 0 ? 1 : 0);
