<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\IaC\IacManager;

/**
 * Class IacStateCommand
 *
 * CLI: php netfusion iac:state [address]
 */
class IacStateCommand extends Command
{
    protected string $name = 'iac:state';
    protected string $description = 'Inspect and list resources currently tracked in the Terraform state store';

    public function handle(array $args = []): int
    {
        $address = null;
        foreach ($args as $arg) {
            if (is_string($arg) && !str_starts_with($arg, '--')) {
                $address = $arg;
                break;
            }
        }

        /** @var IacManager $iac */
        $iac = app(IacManager::class);
        $state = $iac->getState();

        $this->info("=== Terraform State File (v{$state->version}) ===");
        $this->line("Serial   : {$state->serial}");
        $this->line("Lineage  : {$state->lineage}");
        $this->line("Checksum : " . $state->computeChecksum());
        $this->line("--------------------------------------------------");

        if ($address !== null) {
            $res = $state->getResource($address);
            if ($res === null) {
                $this->error("Resource '{$address}' not found in state.");
                return 1;
            }
            $this->line(json_encode($res->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info("Tracked Resources (" . count($state->resources) . "):");
            foreach ($state->resources as $addr => $res) {
                $id = $res->getPrimaryInstance()?->getAttribute('id') ?? 'unknown';
                $this->line("- {$addr} (ID: {$id})");
            }
        }

        return 0;
    }
}
