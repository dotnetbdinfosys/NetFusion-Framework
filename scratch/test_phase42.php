<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Phase 42 Test Suite
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase42.php
| Module    : GraphQL
| Purpose   : Comprehensive tests for GraphQL Lexer, AST Parser, Type System,
|             SDL SchemaBuilder, Query & Mutation Execution, GraphiQL Playground,
|             and Global Helpers.
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

namespace NetFusion\Tests\GraphQL;

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\App\Http\Controllers\GraphQLController;
use NetFusion\Core\Application;
use NetFusion\Core\GraphQL\Execution\ExecutionResult;
use NetFusion\Core\GraphQL\Execution\Executor;
use NetFusion\Core\GraphQL\GraphQLManager;
use NetFusion\Core\GraphQL\Parser\AST;
use NetFusion\Core\GraphQL\Parser\DocumentNode;
use NetFusion\Core\GraphQL\Parser\Lexer;
use NetFusion\Core\GraphQL\Parser\OperationDefinitionNode;
use NetFusion\Core\GraphQL\Parser\Parser;
use NetFusion\Core\GraphQL\Parser\Token;
use NetFusion\Core\GraphQL\Types\EnumType;
use NetFusion\Core\GraphQL\Types\Field;
use NetFusion\Core\GraphQL\Types\GraphQLType;
use NetFusion\Core\GraphQL\Types\InputObjectType;
use NetFusion\Core\GraphQL\Types\ListType;
use NetFusion\Core\GraphQL\Types\NonNullType;
use NetFusion\Core\GraphQL\Types\ObjectType;
use NetFusion\Core\GraphQL\Types\ScalarType;
use NetFusion\Core\GraphQL\Types\Schema;
use NetFusion\Core\GraphQL\Types\SchemaBuilder;
use NetFusion\Core\Http\Request;

$basePath = realpath(__DIR__ . '/..');
$app      = new Application($basePath);

$passed = 0;
$failed = 0;
$total  = 0;

function t(bool $cond, string $name): void
{
    global $passed, $failed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo "  \033[32m✔\033[0m {$name}\n";
    } else {
        $failed++;
        echo "  \033[31m✖\033[0m {$name}\n";
    }
}

echo "\n=================================================================\n";
echo "  NetFusion Phase 42 — Enterprise GraphQL Engine Tests\n";
echo "=================================================================\n\n";

// =============================================================================
// Section 1: Lexer & Tokenizer
// =============================================================================
echo "\033[36m[Section 1: Lexer & Tokenizer]\033[0m\n";

$querySample = 'query GetUser($id: ID! = "100") { user(id: $id) { name, age, balance, active } }';
$lexer = new Lexer($querySample);

$tokens = [];
while ($lexer->getToken()->kind !== Token::EOF) {
    $tokens[] = $lexer->advance();
}

t($tokens[0]->kind === Token::NAME && $tokens[0]->value === 'query', 'Lexer tokenizes operation keyword "query"');
t($tokens[1]->kind === Token::NAME && $tokens[1]->value === 'GetUser', 'Lexer tokenizes operation name "GetUser"');
t($tokens[2]->kind === Token::PAREN_L, 'Lexer tokenizes opening parenthesis');
t($tokens[3]->kind === Token::DOLLAR, 'Lexer tokenizes variable prefix "$"');
t($tokens[4]->kind === Token::NAME && $tokens[4]->value === 'id', 'Lexer tokenizes variable name "id"');
t($tokens[5]->kind === Token::COLON, 'Lexer tokenizes type colon ":"');
t($tokens[6]->kind === Token::NAME && $tokens[6]->value === 'ID', 'Lexer tokenizes type name "ID"');
t($tokens[7]->kind === Token::BANG, 'Lexer tokenizes NonNull exclamation "!"');

// Test block string and escape sequence
$blockLexer = new Lexer('"""Hello\nWorld""" "Escaped \"Quote\""');
$t1 = $blockLexer->advance();
$t2 = $blockLexer->advance();
t($t1->kind === Token::BLOCK_STRING && str_contains($t1->value, 'Hello'), 'Lexer parses triple-quote block string');
t($t2->kind === Token::STRING && $t2->value === 'Escaped "Quote"', 'Lexer parses escaped quotes in strings');

// =============================================================================
// Section 2: Recursive Descent Parser & AST Hierarchy
// =============================================================================
echo "\033[36m[Section 2: Recursive Descent Parser & AST Hierarchy]\033[0m\n";

$ast = Parser::parse($querySample);
t($ast instanceof DocumentNode, 'Parser returns DocumentNode AST root');
t(count($ast->definitions) === 1, 'Document contains 1 operation definition');

/** @var OperationDefinitionNode $opDef */
$opDef = $ast->definitions[0];
t($opDef instanceof OperationDefinitionNode, 'OperationDefinitionNode extracted');
t($opDef->operation === 'query', 'Operation type is "query"');
t($opDef->name === 'GetUser', 'Operation name is "GetUser"');
t(count($opDef->variableDefinitions) === 1, 'Variable definitions count is 1');
t($opDef->variableDefinitions[0]->variableName === 'id', 'Variable name is "id"');

// Selection Set
$selections = $opDef->selectionSet->selections;
t(count($selections) === 1, 'Top level selection count is 1');
t($selections[0]->name === 'user', 'Field name is "user"');
t(count($selections[0]->selectionSet->selections) === 4, 'Sub-field selection count is 4 (name, age, balance, active)');

// Mutation with Input Object AST
$mutationStr = 'mutation CreateUser($input: UserInput!) { createUser(input: $input) { id, name } }';
$mutAst = Parser::parse($mutationStr);
/** @var OperationDefinitionNode $mutDef */
$mutDef = $mutAst->definitions[0];
t($mutDef->operation === 'mutation', 'Parser recognizes "mutation" operation');
t($mutDef->name === 'CreateUser', 'Mutation name is "CreateUser"');

// =============================================================================
// Section 3: Schema & Type System Definition
// =============================================================================
echo "\033[36m[Section 3: Schema & Type System Definition]\033[0m\n";

// Scalars
$strType = GraphQLType::string();
$intType = GraphQLType::int();
$floatType = GraphQLType::float();
$boolType = GraphQLType::boolean();
$idType = GraphQLType::id();
$dateTimeType = GraphQLType::dateTime();
$jsonType = GraphQLType::json();

t($strType->serialize('Fusion') === 'Fusion', 'ScalarType String serializes string');
t($intType->serialize('42') === 42, 'ScalarType Int serializes integer');
t($floatType->serialize('19.95') === 19.95, 'ScalarType Float serializes float');
t($boolType->serialize(1) === true, 'ScalarType Boolean serializes boolean');
t($idType->serialize(100) === '100', 'ScalarType ID serializes string ID');

// Enum Type
$roleEnum = new EnumType('UserRole', ['ADMIN', 'MANAGER', 'USER'], 'User authority role');
t($roleEnum->isValidName('ADMIN') === true, 'EnumType validates member ADMIN');
t($roleEnum->isValidName('GUEST') === false, 'EnumType rejects invalid member GUEST');

// Object Types
$profileType = new ObjectType('Profile', [
    'bio'     => ['type' => GraphQLType::string()],
    'website' => ['type' => GraphQLType::string()],
]);

$userType = new ObjectType('User', [
    'id'      => ['type' => GraphQLType::nonNull(GraphQLType::id())],
    'name'    => ['type' => GraphQLType::nonNull(GraphQLType::string())],
    'email'   => ['type' => GraphQLType::string()],
    'role'    => ['type' => $roleEnum],
    'profile' => ['type' => $profileType],
]);

// Query & Mutation Types
$queryType = new ObjectType('Query', [
    'user' => [
        'type' => $userType,
        'args' => [
            'id' => ['type' => GraphQLType::nonNull(GraphQLType::id())],
        ],
        'resolve' => function ($source, $args) {
            return [
                'id'      => $args['id'],
                'name'    => 'Ashish',
                'email'   => 'ashish@dotnetbd.com',
                'role'    => 'ADMIN',
                'profile' => ['bio' => 'Lead Software Engineer', 'website' => 'https://dotnetbd.com'],
            ];
        },
    ],
    'users' => [
        'type' => GraphQLType::listOf($userType),
        'args' => [
            'limit' => ['type' => GraphQLType::int(), 'defaultValue' => 10],
        ],
        'resolve' => function ($source, $args) {
            return [
                ['id' => '1', 'name' => 'Ashish', 'email' => 'ashish@dotnetbd.com', 'role' => 'ADMIN', 'profile' => ['bio' => 'Lead', 'website' => 'https://dotnetbd.com']],
                ['id' => '2', 'name' => 'John Doe', 'email' => 'john@example.com', 'role' => 'USER', 'profile' => ['bio' => 'Dev', 'website' => 'https://example.com']],
            ];
        },
    ],
]);

$userInputType = new InputObjectType('UserInput', [
    'name'  => ['type' => GraphQLType::nonNull(GraphQLType::string())],
    'email' => ['type' => GraphQLType::nonNull(GraphQLType::string())],
]);

$mutationType = new ObjectType('Mutation', [
    'createUser' => [
        'type' => $userType,
        'args' => [
            'input' => ['type' => GraphQLType::nonNull($userInputType)],
        ],
        'resolve' => function ($source, $args) {
            $input = $args['input'];
            return [
                'id'      => '99',
                'name'    => $input['name'],
                'email'   => $input['email'],
                'role'    => 'USER',
                'profile' => null,
            ];
        },
    ],
]);

$schema = new Schema(
    queryType: $queryType,
    mutationType: $mutationType,
    additionalTypes: [$userType, $profileType, $roleEnum, $userInputType]
);

t($schema->getQueryType()->getName() === 'Query', 'Schema has Query type');
t($schema->getMutationType()->getName() === 'Mutation', 'Schema has Mutation type');
t($schema->getType('User') instanceof ObjectType, 'Schema typeMap contains User type');
t(str_contains($schema->printSDL(), 'type User {'), 'Schema::printSDL() generates standard SDL');

// =============================================================================
// Section 4: Query Execution & Field Resolution
// =============================================================================
echo "\033[36m[Section 4: Query Execution & Field Resolution]\033[0m\n";

// 1. Simple Nested Query
$query1 = '
query {
    user(id: "1") {
        id
        name
        email
        role
        profile {
            bio
            website
        }
    }
}';

$doc1 = Parser::parse($query1);
$res1 = Executor::execute($schema, $doc1);

t(!$res1->hasErrors(), 'Query 1 executed without errors');
$userData = $res1->getData()['user'];
t($userData['id'] === '1' && $userData['name'] === 'Ashish', 'Query 1 returned correct scalar fields');
t($userData['role'] === 'ADMIN', 'Query 1 resolved enum UserRole');
t($userData['profile']['bio'] === 'Lead Software Engineer', 'Query 1 resolved nested Profile object');

// 2. List Query with Default Arguments
$query2 = '{ users { id, name } }';
$doc2 = Parser::parse($query2);
$res2 = Executor::execute($schema, $doc2);
t(count($res2->getData()['users']) === 2, 'List query returned 2 user records');

// 3. Field Alias
$query3 = '{ u1: user(id: "1") { name }, u2: user(id: "2") { name } }';
$doc3 = Parser::parse($query3);
$res3 = Executor::execute($schema, $doc3);
t(isset($res3->getData()['u1']) && isset($res3->getData()['u2']), 'Field aliasing (u1, u2) resolved correctly');

// 4. Variables & Mutation
$mutationQuery = '
mutation AddNewUser($input: UserInput!) {
    createUser(input: $input) {
        id
        name
        email
    }
}';
$mutDoc = Parser::parse($mutationQuery);
$mutRes = Executor::execute($schema, $mutDoc, variables: [
    'input' => [
        'name'  => 'Rahim',
        'email' => 'rahim@dotnetbd.com',
    ],
]);

t(!$mutRes->hasErrors(), 'Mutation executed without errors');
t($mutRes->getData()['createUser']['id'] === '99', 'Mutation returned generated ID');
t($mutRes->getData()['createUser']['name'] === 'Rahim', 'Mutation returned created name');

// 5. Directives (@include & @skip)
$queryDirectives = '
query TestDirectives($showEmail: Boolean!, $hideRole: Boolean!) {
    user(id: "1") {
        name
        email @include(if: $showEmail)
        role @skip(if: $hideRole)
    }
}';
$dirDoc = Parser::parse($queryDirectives);
$dirRes = Executor::execute($schema, $dirDoc, variables: ['showEmail' => false, 'hideRole' => true]);
t(isset($dirRes->getData()['user']['name']), 'Directives: name included');
t(!isset($dirRes->getData()['user']['email']), 'Directives: email skipped via @include(if: false)');
t(!isset($dirRes->getData()['user']['role']), 'Directives: role skipped via @skip(if: true)');

// 6. Introspection (__typename)
$introQuery = '{ user(id: "1") { __typename, id, name } }';
$introRes = Executor::execute($schema, Parser::parse($introQuery));
t($introRes->getData()['user']['__typename'] === 'User', '__typename introspection returned "User"');

// =============================================================================
// Section 5: SchemaBuilder from SDL String
// =============================================================================
echo "\033[36m[Section 5: SchemaBuilder from SDL String]\033[0m\n";

$sampleSdl = '
type Product {
    id: ID!
    title: String!
    price: Float!
}

type Query {
    product(id: ID!): Product
    allProducts: [Product]
}

type Mutation {
    createProduct(title: String!, price: Float!): Product
}
';

$resolvers = [
    'Query' => [
        'product' => fn($src, $args) => ['id' => $args['id'], 'title' => 'Gaming Mouse', 'price' => 49.99],
        'allProducts' => fn() => [
            ['id' => '1', 'title' => 'Keyboard', 'price' => 79.99],
            ['id' => '2', 'title' => 'Mouse', 'price' => 49.99],
        ],
    ],
    'Mutation' => [
        'createProduct' => fn($src, $args) => ['id' => '10', 'title' => $args['title'], 'price' => $args['price']],
    ],
];

$sdlSchema = SchemaBuilder::fromSDL($sampleSdl, $resolvers);
t($sdlSchema->getQueryType() instanceof ObjectType, 'SchemaBuilder::fromSDL constructed Query ObjectType');
t($sdlSchema->getMutationType() instanceof ObjectType, 'SchemaBuilder::fromSDL constructed Mutation ObjectType');

$sdlExecRes = Executor::execute($sdlSchema, Parser::parse('{ product(id: "5") { id, title, price } }'));
t(!$sdlExecRes->hasErrors(), 'Query against SDL-built schema executed successfully');
t($sdlExecRes->getData()['product']['title'] === 'Gaming Mouse', 'SDL schema resolved field value');

// =============================================================================
// Section 6: GraphQL HTTP Controller & GraphiQL Playground
// =============================================================================
echo "\033[36m[Section 6: GraphQL HTTP Controller & GraphiQL Playground]\033[0m\n";

/** @var GraphQLManager $gqlManager */
$gqlManager = $app->make(GraphQLManager::class);
$gqlManager->registerSchema('custom', $schema);

$controller = new GraphQLController($gqlManager);

// 1. POST JSON Query Request
$postBody = json_encode([
    'query' => '{ ping, version }',
]);
$reqPost = new Request(
    method: 'POST',
    uri: '/graphql',
    server: ['CONTENT_TYPE' => 'application/json', 'REQUEST_METHOD' => 'POST'],
    content: $postBody
);

$httpRes = $controller->handle($reqPost);
t($httpRes->getStatusCode() === 200, 'GraphQLController returns HTTP 200 for valid POST JSON query');
$jsonOutput = json_decode($httpRes->getContent(), true);
t(isset($jsonOutput['data']['ping']) && $jsonOutput['data']['ping'] === 'pong', 'GraphQLController executes ping query and returns pong');

// 2. GET Browser Request with text/html Accept header (GraphiQL Playground)
$reqGetHtml = new Request(
    method: 'GET',
    uri: '/graphql',
    server: ['HTTP_ACCEPT' => 'text/html,application/xhtml+xml', 'REQUEST_METHOD' => 'GET']
);

$htmlRes = $controller->handle($reqGetHtml);
t($htmlRes->getStatusCode() === 200, 'GraphQLController returns HTTP 200 for GraphiQL playground');
t(str_contains($htmlRes->getContent(), 'GraphiQL'), 'GraphiQL HTML contains GraphiQL assets and editor');

// =============================================================================
// Section 7: Global Helpers & Facade
// =============================================================================
echo "\033[36m[Section 7: Global Helpers & Facade]\033[0m\n";

t(function_exists('graphql'), 'Global helper graphql() exists');
t(graphql() instanceof GraphQLManager, 'graphql() returns GraphQLManager singleton');

$helperExec = graphql('{ ping }');
t($helperExec instanceof ExecutionResult, 'graphql("{ ping }") executes query and returns ExecutionResult');
t($helperExec->getData()['ping'] === 'pong', 'graphql() helper executed query correctly');

echo "\n=================================================================\n";
echo "  Results: {$passed}/{$total} Passed (" . round(($passed / $total) * 100) . "%)\n";
echo "=================================================================\n\n";

if ($failed > 0) {
    exit(1);
}
