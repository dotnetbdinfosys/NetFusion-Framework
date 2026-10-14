<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/GraphQLManager.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL
| Purpose   : Central GraphQL coordinator, schema registry & query executor facade.
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

namespace NetFusion\Core\GraphQL;

use NetFusion\Core\Application;
use NetFusion\Core\GraphQL\Execution\ExecutionResult;
use NetFusion\Core\GraphQL\Execution\Executor;
use NetFusion\Core\GraphQL\Parser\Parser;
use NetFusion\Core\GraphQL\Types\GraphQLType;
use NetFusion\Core\GraphQL\Types\ObjectType;
use NetFusion\Core\GraphQL\Types\Schema;
use NetFusion\Core\GraphQL\Types\SchemaBuilder;
use RuntimeException;
use Throwable;

class GraphQLManager
{
    /** @var array<string, Schema> */
    protected array $schemas = [];
    protected string $defaultSchema = 'default';

    public function __construct(
        protected Application $app
    ) {
        $this->registerDefaultSchema();
    }

    /**
     * Create or retrieve default starter schema.
     */
    protected function registerDefaultSchema(): void
    {
        $queryType = new ObjectType('Query', [
            'ping' => [
                'type'        => GraphQLType::string(),
                'description' => 'System heartbeat check.',
                'resolve'     => fn() => 'pong',
            ],
            'version' => [
                'type'        => GraphQLType::string(),
                'description' => 'NetFusion Framework version.',
                'resolve'     => fn() => $this->app->version(),
            ],
        ]);

        $this->schemas['default'] = new Schema($queryType);
    }

    /**
     * Get a Schema by name.
     */
    public function schema(?string $name = null): Schema
    {
        $name = $name ?? $this->defaultSchema;
        if (!isset($this->schemas[$name])) {
            throw new RuntimeException("GraphQL Schema [{$name}] is not registered.");
        }
        return $this->schemas[$name];
    }

    /**
     * Register a new Schema.
     */
    public function registerSchema(string $name, Schema $schema): self
    {
        $this->schemas[$name] = $schema;
        return $this;
    }

    /**
     * Create a new SchemaBuilder instance.
     */
    public function builder(): SchemaBuilder
    {
        return new SchemaBuilder();
    }

    /**
     * Build Schema from SDL string and resolvers.
     */
    public function fromSDL(string $sdl, array $resolvers = [], string $name = 'default'): Schema
    {
        $schema = SchemaBuilder::fromSDL($sdl, $resolvers);
        $this->registerSchema($name, $schema);
        return $schema;
    }

    /**
     * Execute a GraphQL query string.
     *
     * @param string               $query
     * @param array<string, mixed> $variables
     * @param mixed                $context
     * @param mixed                $rootValue
     * @param string|null          $operationName
     * @param string|null          $schemaName
     * @return ExecutionResult
     */
    public function execute(
        string $query,
        array $variables = [],
        mixed $context = null,
        mixed $rootValue = null,
        ?string $operationName = null,
        ?string $schemaName = null
    ): ExecutionResult {
        $schema = $this->schema($schemaName);

        try {
            $document = Parser::parse($query);
            $contextValue = $context ?? $this->app;
            return Executor::execute($schema, $document, $rootValue, $contextValue, $variables, $operationName);
        } catch (Throwable $e) {
            return new ExecutionResult(null, [['message' => $e->getMessage()]]);
        }
    }

    /**
     * Render GraphiQL interactive playground HTML page.
     */
    public function renderPlayground(string $endpoint = '/graphql'): string
    {
        $title = 'NetFusion GraphiQL Playground';
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <link rel="stylesheet" href="https://unpkg.com/graphiql/graphiql.min.css" />
    <style>
        body { height: 100vh; margin: 0; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        #splash { display: flex; align-items: center; justify-content: center; height: 100vh; background: #0f172a; color: #38bdf8; font-size: 20px; font-weight: 600; }
    </style>
</head>
<body>
    <div id="splash">Loading NetFusion GraphQL Explorer...</div>
    <script src="https://unpkg.com/react/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/graphiql/graphiql.min.js"></script>
    <script>
        const fetcher = GraphiQL.createFetcher({ url: '{$endpoint}' });
        const root = document.getElementById('splash');
        root.remove();
        ReactDOM.render(React.createElement(GraphiQL, { fetcher: fetcher, defaultQuery: '{ ping, version }' }), document.body);
    </script>
</body>
</html>
HTML;
    }
}
