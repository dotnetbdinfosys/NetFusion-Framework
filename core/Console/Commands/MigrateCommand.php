<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/MigrateCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command to execute pending migrations or rollback previous batches across modules.
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
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Database\Migrations\Migrator;
use NetFusion\Core\Modules\ModuleRepository;

/**
 * NetFusion Migrate Command
 *
 * Discovers and applies all pending database schema migrations across the
 * core framework and active HMVC modules.
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class MigrateCommand extends Command
{
    protected string $name = 'migrate';
    protected string $description = 'Run pending database migrations (use --rollback to revert last batch)';

    public function handle(): int
    {
        /** @var DatabaseManager $dbManager */
        $dbManager = $this->app->make(DatabaseManager::class);

        $connName   = $this->option('connection');
        $connection = $dbManager->connection($connName);

        $migrator = new Migrator($connection);

        $paths = $this->resolveMigrationPaths();

        if (empty($paths)) {
            $this->warn('No migration paths found.');
            return 0;
        }

        // Handle rollback flag
        if ($this->hasOption('rollback')) {
            $this->info('Rolling back last migration batch...');
            $rolledBack = $migrator->rollback($paths);

            if (empty($rolledBack)) {
                $this->line('Nothing to rollback.');
                return 0;
            }

            foreach ($rolledBack as $migration) {
                $this->line("  \033[33mRolled back:\033[0m {$migration}");
            }

            $this->success('Rollback completed.');
            return 0;
        }

        $this->info('Running database migrations...');
        $migrated = $migrator->run($paths);

        if (empty($migrated)) {
            $this->line('Nothing to migrate. Database is already up to date.');
            return 0;
        }

        foreach ($migrated as $migration) {
            $this->line("  \033[32mMigrated:\033[0m {$migration}");
        }

        $this->success('Database migrations completed successfully!');
        return 0;
    }

    /**
     * Resolve directories containing migrations to execute.
     *
     * @return string[]
     */
    protected function resolveMigrationPaths(): array
    {
        $paths = [];

        $specificModule = $this->option('module');

        if ($specificModule) {
            $modPath = $this->app->modulesPath($specificModule . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations');
            if (is_dir($modPath)) {
                $paths[] = $modPath;
            }
            return $paths;
        }

        // 1. Global storage migrations
        $globalPath = $this->app->storagePath('migrations');
        if (is_dir($globalPath)) {
            $paths[] = $globalPath;
        }

        // 2. Discover module migrations
        /** @var ModuleRepository $repo */
        $repo = $this->app->make(ModuleRepository::class);
        foreach ($repo->enabled() as $module) {
            $modMigrationPath = $module->migrationsPath();
            if (is_dir($modMigrationPath)) {
                $paths[] = $modMigrationPath;
            }
        }

        return $paths;
    }
}
