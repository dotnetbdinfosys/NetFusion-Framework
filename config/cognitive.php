<?php
/**
 * NetFusion Framework - Enterprise Cognitive Architecture Configuration
 *
 * Configuration for ACT-R / SOAR cognitive cycles, decay rates, and retrieval thresholds.
 *
 * @package    NetFusion\Config
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

return [
    'retrieval_threshold' => (float) env('COGNITIVE_RETRIEVAL_THRESHOLD', -10.0),

    'activation_noise' => (float) env('COGNITIVE_ACTIVATION_NOISE', 0.0),

    'decay_rate' => (float) env('COGNITIVE_DECAY_RATE', 0.5), // Standard power-law decay d=0.5

    'max_cycles_per_step' => (int) env('COGNITIVE_MAX_CYCLES', 10),
];
