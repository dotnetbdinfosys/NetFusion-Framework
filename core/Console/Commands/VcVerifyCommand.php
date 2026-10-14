<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/VcVerifyCommand.php
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
use NetFusion\Core\Security\Identity\Credentials\VerifiableCredential;
use NetFusion\Core\Security\Identity\IdentityManager;

class VcVerifyCommand extends Command
{
    protected string $signature = 'vc:verify {credentialJson : JSON string or path to credential file}';
    protected string $description = 'Verify cryptographic integrity and revocation status of a Verifiable Credential';

    public function handle(): int
    {
        $input = (string) $this->argument(0);
        if (empty($input)) {
            $this->error("Credential input cannot be empty.");
            return 1;
        }

        $json = file_exists($input) ? (string) file_get_contents($input) : $input;

        try {
            $vc = VerifiableCredential::fromJson($json);
        } catch (\Throwable $e) {
            $this->error("Failed to parse Verifiable Credential: " . $e->getMessage());
            return 1;
        }

        /** @var IdentityManager $manager */
        $manager = $this->app->has('identity')
            ? $this->app->make('identity')
            : new IdentityManager();

        $res = $manager->verifyCredential($vc);
        if ($res['valid']) {
            $this->info("✓ Credential is Cryptographically VALID and Active.");
            $this->line("Issuer  : " . $vc->getIssuer());
            $this->line("Subject : " . ($vc->getCredentialSubject()['id'] ?? 'unknown'));
            $this->line("Types   : " . implode(', ', $vc->getType()));
            return 0;
        }

        $this->error("✗ Credential Verification FAILED: " . ($res['reason'] ?? 'Unknown reason'));
        return 1;
    }
}
