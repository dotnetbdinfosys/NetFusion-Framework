<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/HeEncryptCommand.php
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

class HeEncryptCommand extends Command
{
    protected string $signature = 'he:encrypt {value? : Integer or float value} {--scheme=bfv : Encryption scheme (bfv/ckks)}';
    protected string $description = 'Encrypt a value into a homomorphic ciphertext';

    public function handle(): int
    {
        $val    = (int)($this->argument(0) ?: 42);
        $scheme = strtolower((string)($this->option('scheme') ?: 'bfv'));

        $mgr = $this->app->has('homomorphic')
            ? $this->app->make('homomorphic')
            : HomomorphicManager::getInstance();

        $keyPair = $mgr->generateBfvKeys();
        $cipher  = $mgr->encryptInt($val, $keyPair);

        $this->info("=== Homomorphic Encryption Succeeded ===");
        $this->line("Scheme      : " . strtoupper($scheme));
        $this->line("Input Value : {$val}");
        $this->line("Degree      : " . $cipher->getDegree());
        $this->line("C0 Elements : " . count($cipher->getC0()));
        $this->line("C1 Elements : " . count($cipher->getC1()));

        return 0;
    }
}
