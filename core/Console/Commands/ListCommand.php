<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/ListCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Displays the NetFusion ASCII banner and the list of available CLI commands.
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
use NetFusion\Core\Console\Kernel;

/**
 * NetFusion List Console Command
 *
 * Displays all available framework CLI commands and usage information.
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class ListCommand extends Command
{
    protected string $name = 'list';
    protected string $description = 'List all available NetFusion CLI commands';

    public function handle(): int
    {
        $version = $this->app->version();

        $this->line();
        $this->info("   _   _      _   _____           _             ");
        $this->info("  | \ | | ___| |_|  ___|   _ ___(_) ___  _ __   ");
        $this->info("  |  \| |/ _ \ __| |_ | | | / __| |/ _ \| '_ \  ");
        $this->info("  | |\  |  __/ |_|  _|| |_| \__ \ | (_) | | | | ");
        $this->info("  |_| \_|\___|\__|_|   \__,_|___/_|\___/|_| |_| ");
        $this->line();
        $this->line("  \033[33mNetFusion Framework CLI Tool\033[0m — Version \033[32m{$version}\033[0m");
        $this->line("  Author: \033[36mMd. Atiquzzaman Ashish (DotNetBD)\033[0m");
        $this->line();

        $this->line("\033[33mUsage:\033[0m");
        $this->line("  php netfusion <command> [arguments] [options]");
        $this->line();

        $this->line("\033[33mAvailable Commands:\033[0m");

        /** @var Kernel $kernel */
        $kernel = $this->app->make(Kernel::class);
        $commands = $kernel->getCommands();

        foreach ($commands as $cmdName => $cmdClass) {
            /** @var Command $cmdInstance */
            $cmdInstance = $this->app->make($cmdClass);
            $padding = str_repeat(' ', max(2, 24 - strlen($cmdName)));
            $this->line("  \033[32m{$cmdName}\033[0m{$padding}{$cmdInstance->getDescription()}");
        }

        $this->line();
        return 0;
    }
}
