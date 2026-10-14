<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/VcIssueCommand.php
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

class VcIssueCommand extends Command
{
    protected string $signature = 'vc:issue {subjectDid : Subject DID} {--type=MembershipCredential : Type of credential} {--claims= : JSON string of claims}';
    protected string $description = 'Issue and cryptographically sign a W3C Verifiable Credential 2.0';

    public function handle(): int
    {
        $subjectDid = (string) $this->argument(0);
        if (empty($subjectDid)) {
            $this->error("Subject DID cannot be empty.");
            return 1;
        }

        $type = (string) ($this->option('type') ?: 'MembershipCredential');
        $rawClaims = (string) ($this->option('claims') ?: '{"tier":"gold","active":true}');

        $claims = json_decode($rawClaims, true);
        if (!is_array($claims)) {
            $claims = ['tier' => 'gold'];
        }

        /** @var IdentityManager $manager */
        $manager = $this->app->has('identity')
            ? $this->app->make('identity')
            : new IdentityManager();

        // Create an issuer DID
        $issuer = $manager->createDid();
        $credentialSubject = array_merge(['id' => $subjectDid], $claims);

        $vc = $manager->issueCredential(
            issuerDid: $issuer['did'],
            credentialSubject: $credentialSubject,
            secretKeyHex: $issuer['secretKey'],
            type: ['VerifiableCredential', $type]
        );

        $this->info("=== W3C Verifiable Credential 2.0 Issued ===");
        $this->line($vc->toJson());
        return 0;
    }
}
