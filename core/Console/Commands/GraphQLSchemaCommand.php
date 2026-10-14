<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/GraphQLSchemaCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Export or display GraphQL Schema Definition Language (SDL).
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

class GraphQLSchemaCommand extends Command
{
    protected string $name = 'graphql:schema';
    protected string $description = 'Print or export GraphQL Schema Definition Language (SDL).';

    public function handle(): int
    {
        $schemaName = (string) ($this->option('schema') ?? 'default');
        $outputFile = $this->option('output');

        /** @var GraphQLManager $manager */
        $manager = $this->container->get('graphql');
        $schema = $manager->schema($schemaName);
        $sdl = $schema->printSDL();

        if ($outputFile) {
            file_put_contents((string) $outputFile, $sdl);
            $this->info("✔ GraphQL Schema [{$schemaName}] exported to: {$outputFile}");
            return 0;
        }

        $this->info("======================================================================");
        $this->info("  GraphQL Schema SDL [{$schemaName}]");
        $this->info("======================================================================\n");
        $this->line($sdl);
        $this->line("\n");

        return 0;
    }
}
