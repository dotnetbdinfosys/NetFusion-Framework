<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/HeComputeCommand.php
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

class HeComputeCommand extends Command
{
    protected string $signature = 'he:compute {a? : First integer operand} {b? : Second integer operand} {--op=add : Operation (add/sub/mul)}';
    protected string $description = 'Perform homomorphic computations directly over encrypted ciphertexts';

    public function handle(): int
    {
        $a = (int)($this->argument(0) ?: 5);
        $b = (int)($this->argument(1) ?: 7);
        $op = strtolower((string)($this->option('op') ?: 'add'));

        $mgr = $this->app->has('homomorphic')
            ? $this->app->make('homomorphic')
            : HomomorphicManager::getInstance();

        $keyPair = $mgr->generateBfvKeys();
        $cipherA = $mgr->encryptInt($a, $keyPair);

        if ($op === 'mul') {
            $cipherRes = $mgr->bfv()->multiplyPlain($cipherA, $b);
        } elseif ($op === 'sub') {
            $cipherB = $mgr->encryptInt($b, $keyPair);
            $cipherRes = $mgr->bfv()->sub($cipherA, $cipherB);
        } else {
            $cipherB = $mgr->encryptInt($b, $keyPair);
            $cipherRes = $mgr->bfv()->add($cipherA, $cipherB);
        }

        $decrypted = $mgr->decryptInt($cipherRes, $keyPair);

        $this->info("=== Encrypted Compute Result ===");
        $this->line("Operation : {$a} " . strtoupper($op) . " {$b}");
        $this->line("Decrypted : {$decrypted}");

        return 0;
    }
}
