<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;

/**
 * CLI command to list and manage registered Micro-Frontend remotes.
 * Usage: php netfusion mfe:remotes <list|register> [name] [--entry=] [--exposes=]
 */
class MfeRemotesCommand extends Command
{
    protected string $name = 'mfe:remotes';
    protected string $description = 'Inspect and register Micro-Frontend remote containers';

    public function handle(array $args = []): int
    {
        $action = 'list';
        $remoteName = null;
        $entryUrl = '';
        $exposesRaw = '';

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string)$args[$i];
            if (str_starts_with($arg, '--entry=')) {
                $entryUrl = substr($arg, 8);
            } elseif (str_starts_with($arg, '--exposes=')) {
                $exposesRaw = substr($arg, 10);
            } elseif (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        if (!empty($positional)) {
            $action = strtolower($positional[0]);
            $remoteName = $positional[1] ?? null;
        }

        $mfe = function_exists('mfe') ? mfe() : $this->app->make(\NetFusion\Core\MFE\MfeManager::class);

        switch ($action) {
            case 'register':
                if ($remoteName === null || empty($entryUrl)) {
                    $this->error('Usage: php netfusion mfe:remotes register <name> --entry=<url> [--exposes="./Header=./src/Header.vue"]');
                    return 1;
                }

                $exposes = [];
                if (!empty($exposesRaw)) {
                    foreach (explode(',', $exposesRaw) as $item) {
                        if (str_contains($item, '=')) {
                            [$k, $v] = explode('=', trim($item), 2);
                            $exposes[$k] = $v;
                        }
                    }
                }

                $container = $mfe->registerRemote($remoteName, $entryUrl, exposes: $exposes);
                $this->info("Successfully registered remote container '{$container->getName()}' [{$container->getEntryUrl()}]");
                return 0;

            case 'list':
            default:
                $remotes = $mfe->getRegistry()->getRemotes();
                if (empty($remotes)) {
                    $this->line('No remote containers registered.');
                    return 0;
                }

                $this->info('Registered Micro-Frontend Remote Containers:');
                $this->line(sprintf('%-18s %-35s %-30s', 'NAME', 'ENTRY URL', 'EXPOSED MODULES'));
                $this->line(str_repeat('-', 85));

                foreach ($remotes as $r) {
                    $exposesList = implode(', ', array_keys($r->getExposes()));
                    $this->line(sprintf('%-18s %-35s %-30s', $r->getName(), $r->getEntryUrl(), $exposesList ?: 'none'));
                }
                return 0;
        }
    }
}
