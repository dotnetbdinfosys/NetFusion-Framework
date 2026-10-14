<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/BciFilterCommand.php
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
use NetFusion\Core\Neural\Bci\Dsp\DspFilter;
use NetFusion\Core\Neural\Bci\Dsp\FftEngine;

class BciFilterCommand extends Command
{
    protected string $signature = 'bci:filter {--low=8.0 : Bandpass low cutoff in Hz} {--high=30.0 : Bandpass high cutoff in Hz} {--rate=250 : Sampling frequency}';
    protected string $description = 'Apply Notch, Butterworth bandpass and CAR spatial filter to test EEG data';

    public function handle(): int
    {
        $low = (float) ($this->option('low') ?: 8.0);
        $high = (float) ($this->option('high') ?: 30.0);
        $rate = (float) ($this->option('rate') ?: 250.0);

        // Synthetic 2-channel signal with 50Hz noise and 10Hz Alpha signal
        $signals = ['C3' => [], 'C4' => []];
        for ($i = 0; $i < 250; $i++) {
            $t = $i / $rate;
            $noise = 25.0 * sin(2 * M_PI * 50.0 * $t); // 50Hz powerline
            $signals['C3'][] = 12.0 * sin(2 * M_PI * 10.0 * $t) + $noise;
            $signals['C4'][] = 8.0 * sin(2 * M_PI * 10.0 * $t) + $noise;
        }

        $notch = DspFilter::designNotch(50.0, $rate);
        $bandpass = DspFilter::designBandpass($low, $high, $rate);

        $filtered = [];
        foreach ($signals as $ch => $raw) {
            $n = DspFilter::filtfilt($raw, $notch);
            $bp = DspFilter::filtfilt($n, $bandpass);
            $filtered[$ch] = $bp;
        }

        $car = DspFilter::commonAverageReference($filtered);
        $powers = FftEngine::extractBandPowers($car['C3'], $rate);

        $this->info("=== BCI Digital Signal Processing Pipeline ===");
        $this->line("Notch Filter       : 50.0 Hz Powerline Rejection");
        $this->line("Bandpass Filter    : {$low} Hz - {$high} Hz (Mu/Beta)");
        $this->line("Spatial Filter     : Common Average Reference (CAR)");
        $this->line("C3 Alpha Power     : " . round($powers['absolute']['alpha'], 4) . " uV^2");
        $this->line("C3 Relative Alpha  : " . round($powers['relative']['alpha'] * 100, 2) . "%");

        return 0;
    }
}
