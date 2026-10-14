<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/MolecularHolographCommand.php
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
use NetFusion\Core\Storage\Molecular\MolecularManager;

class MolecularHolographCommand extends Command
{
    protected string $signature = 'molecular:holograph {crystalId? : Holographic crystal identifier} {--payload=NetFusionHologram2026 : Text to record onto page} {--angle=12.5 : Bragg reference angle in degrees}';
    protected string $description = 'Record and optically reconstruct 2D page memory in 3D holographic crystal';

    public function handle(): int
    {
        $crystalId = (string) ($this->argument(0) ?: 'crystal_alpha');
        $payload = (string) ($this->option('payload') ?: 'NetFusionHologram2026');
        $angle = (float) ($this->option('angle') ?: 12.5);

        /** @var MolecularManager $manager */
        $manager = $this->app->has('molecular')
            ? $this->app->make('molecular')
            : MolecularManager::getInstance();

        $page = $manager->storeHolographicPage($crystalId, $payload, 1, $angle);
        $crystal = $manager->getCrystal($crystalId);

        $reconstructed = $manager->readHolographicPage($crystalId, $angle, strlen($payload));

        $this->info("=== Volumetric Holographic Page Recording ===");
        $this->line("Crystal ID       : " . $crystal->getCrystalId());
        $this->line("Material         : " . $crystal->getMaterial());
        $this->line("Bragg Angle      : " . $page->getBraggAngle() . "°");
        $this->line("Page Dimensions  : " . $page->getWidth() . "x" . $page->getHeight() . " pixels");
        $this->line("Recorded Payload : " . $payload);
        $this->line("Read Reconstructed: " . ($reconstructed ?? 'FAILED'));

        return ($reconstructed === $payload) ? 0 : 1;
    }
}
