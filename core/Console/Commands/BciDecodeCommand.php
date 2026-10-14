<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/BciDecodeCommand.php
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
use NetFusion\Core\Neural\Bci\Decoder\MotorImageryDecoder;

class BciDecodeCommand extends Command
{
    protected string $signature = 'bci:decode {--trials=10 : Number of synthetic calibration trials}';
    protected string $description = 'Calibrate CSP spatial filters and decode Motor Imagery cognitive intent';

    public function handle(): int
    {
        $numTrials = (int) ($this->option('trials') ?: 10);
        $decoder = new MotorImageryDecoder(250.0, 8.0, 30.0, 'left_hand', 'right_hand', 1);

        // Generate synthetic training trials for Left Hand vs Right Hand
        // Left hand imagery: higher Mu desynchronization (ERD) on C4 than C3
        // Right hand imagery: higher Mu desynchronization (ERD) on C3 than C4
        $leftTrials = [];
        $rightTrials = [];

        for ($tr = 0; $tr < $numTrials; $tr++) {
            $tL = [[], []]; // C3, C4
            $tR = [[], []];
            for ($s = 0; $s < 128; $s++) {
                $t = $s / 250.0;
                // Left hand trial: C3 has strong 12Hz, C4 has attenuated 12Hz
                $tL[0][] = 15.0 * sin(2 * M_PI * 12.0 * $t) + (mt_rand(-50, 50) / 10.0);
                $tL[1][] = 3.0 * sin(2 * M_PI * 12.0 * $t) + (mt_rand(-50, 50) / 10.0);

                // Right hand trial: C3 has attenuated 12Hz, C4 has strong 12Hz
                $tR[0][] = 3.0 * sin(2 * M_PI * 12.0 * $t) + (mt_rand(-50, 50) / 10.0);
                $tR[1][] = 15.0 * sin(2 * M_PI * 12.0 * $t) + (mt_rand(-50, 50) / 10.0);
            }
            $leftTrials[] = $tL;
            $rightTrials[] = $tR;
        }

        $decoder->train($leftTrials, $rightTrials);

        // Test prediction
        $testTrial = [[], []];
        for ($s = 0; $s < 128; $s++) {
            $t = $s / 250.0;
            $testTrial[0][] = 14.5 * sin(2 * M_PI * 12.0 * $t) + (mt_rand(-50, 50) / 10.0);
            $testTrial[1][] = 2.8 * sin(2 * M_PI * 12.0 * $t) + (mt_rand(-50, 50) / 10.0);
        }

        $prediction = $decoder->predict($testTrial);

        $this->info("=== Motor Imagery Neural Intent Decoding ===");
        $this->line("Calibration Trials : {$numTrials} per class");
        $this->line("Spatial Filter     : Common Spatial Pattern (CSP)");
        $this->line("Classifier Model   : Linear Discriminant Analysis (LDA)");
        $this->line("Predicted Intent   : " . strtoupper($prediction['intent']));
        $this->line("Confidence         : " . ($prediction['confidence'] * 100) . "%");
        $this->line("Decision Score     : " . $prediction['score']);

        return 0;
    }
}
