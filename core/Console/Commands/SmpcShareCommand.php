<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/SmpcShareCommand.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Security\Homomorphic\HomomorphicManager;

class SmpcShareCommand extends Command
{
    protected string $signature = 'smpc:share {secret? : Secret integer} {--k=3 : Threshold k} {--n=5 : Total shares n}';
    protected string $description = 'Split and verify a secret using Shamir (k, n) threshold secret sharing';

    public function handle(): int
    {
        $secret = (int)($this->argument(0) ?: 12345);
        $k      = max(2, (int)($this->option('k') ?: 3));
        $n      = max($k, (int)($this->option('n') ?: 5));

        $mgr = $this->app->has('homomorphic')
            ? $this->app->make('homomorphic')
            : HomomorphicManager::getInstance();

        $shares = $mgr->splitSecret($secret, $k, $n);
        $reconstructed = $mgr->reconstructSecret(array_slice($shares, 0, $k), $k);

        $this->info("=== Shamir (k, n) Threshold Secret Sharing ===");
        $this->line("Original Secret     : {$secret}");
        $this->line("Threshold k         : {$k}");
        $this->line("Total Shares n      : {$n}");
        $this->line("Shares Generated    : " . count($shares));
        $this->line("Reconstructed Secret: {$reconstructed}");

        return 0;
    }
}
