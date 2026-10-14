<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/DidResolveCommand.php
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
use NetFusion\Core\Security\Identity\IdentityManager;

class DidResolveCommand extends Command
{
    protected string $signature = 'did:resolve {did : Decentralized Identifier to resolve}';
    protected string $description = 'Resolve a Decentralized Identifier (DID) to its W3C DID Document';

    public function handle(): int
    {
        $did = (string) $this->argument(0);
        if (empty($did)) {
            $this->error("DID argument cannot be empty.");
            return 1;
        }

        /** @var IdentityManager $manager */
        $manager = $this->app->has('identity')
            ? $this->app->make('identity')
            : new IdentityManager();

        try {
            $doc = $manager->resolveDid($did);
            $this->info("=== W3C DID Document Resolved ===");
            $this->line($doc->toJson());
            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to resolve DID: " . $e->getMessage());
            return 1;
        }
    }
}
