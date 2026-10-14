<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/GraphQLQueryCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Execute a GraphQL query or mutation directly from CLI.
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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\GraphQL\GraphQLManager;

class GraphQLQueryCommand extends Command
{
    protected string $name = 'graphql:query';
    protected string $description = 'Execute a GraphQL query string and output JSON response.';

    public function handle(): int
    {
        $query = $this->argument(0);
        $rawVars = $this->option('variables');
        $schemaName = (string) ($this->option('schema') ?? 'default');

        if (!$query) {
            $this->error('Error: GraphQL query string is required.');
            $this->line('Usage: php netfusion graphql:query "{ ping, version }" [--variables=...]');
            return 1;
        }

        $variables = [];
        if ($rawVars) {
            $decoded = json_decode((string) $rawVars, true);
            $variables = is_array($decoded) ? $decoded : [];
        }

        /** @var GraphQLManager $manager */
        $manager = $this->container->get('graphql');
        $result = $manager->execute(
            query: (string) $query,
            variables: $variables,
            schemaName: $schemaName
        );

        $json = json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->line($json);

        return $result->hasErrors() ? 1 : 0;
    }
}
