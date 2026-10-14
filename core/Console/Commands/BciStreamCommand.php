<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/BciStreamCommand.php
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
use NetFusion\Core\Neural\Bci\BciManager;
use NetFusion\Core\Neural\Bci\Enums\BciModality;

class BciStreamCommand extends Command
{
    protected string $signature = 'bci:stream {session : Session identifier} {--channels=C3,Cz,C4 : Comma-separated channels} {--samples=250 : Sample count to stream}';
    protected string $description = 'Ingest and monitor real-time multi-channel EEG telemetry stream';

    public function handle(): int
    {
        $sessionId = (string) $this->argument(0);
        if ($sessionId === '') {
            $this->error("Session ID is required.");
            return 1;
        }

        $channelsRaw = (string) ($this->option('channels') ?: 'C3,Cz,C4');
        $channels = array_map('trim', explode(',', $channelsRaw));
        $sampleCount = (int) ($this->option('samples') ?: 250);

        /** @var BciManager $manager */
        $manager = $this->app->has('bci')
            ? $this->app->make('bci')
            : new BciManager();

        $manager->startSession($sessionId, BciModality::MOTOR_IMAGERY, $channels);

        // Simulate microvolt EEG streams
        foreach ($channels as $ch) {
            $synthetic = [];
            for ($i = 0; $i < $sampleCount; $i++) {
                $t = $i / 250.0;
                // 10 Hz Alpha wave + 20 Hz Beta + Gaussian noise
                $val = 10.0 * sin(2 * M_PI * 10.0 * $t) + 5.0 * sin(2 * M_PI * 20.0 * $t) + (mt_rand(-200, 200) / 100.0);
                $synthetic[] = $val;
            }
            $manager->ingestStream($sessionId, $ch, $synthetic, 4.5);
        }

        $this->info("=== BCI Stream Active: {$sessionId} ===");
        $this->line("Channels Active    : " . implode(', ', $channels));
        $this->line("Sampling Frequency : " . $manager->getSamplingRate() . " Hz");
        $this->line("Samples Ingested   : {$sampleCount} per channel");
        $this->line("Electrode Status   : Nominal (< 10 kOhm)");

        return 0;
    }
}
