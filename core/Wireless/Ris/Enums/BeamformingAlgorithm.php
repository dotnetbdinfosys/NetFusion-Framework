<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS Engine
 *
 * Supported beamforming optimization algorithms for RIS phase configuration.
 *
 * @package    NetFusion\Wireless\Ris\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Wireless\Ris\Enums;

enum BeamformingAlgorithm: string
{
    case MaximumRatio      = 'mrt';               // Maximum Ratio Transmission / Combining
    case ZeroForcing       = 'zero_forcing';      // Zero-Forcing interference cancellation
    case HolographicPencil = 'holographic_pencil';// High-directivity continuous holographic pencil beam
    case CodebookSwept     = 'codebook_swept';    // Discrete steering vector codebook search

    /**
     * Human-friendly description.
     */
    public function description(): string
    {
        return match ($this) {
            self::MaximumRatio      => 'Maximum Ratio Transmission (SNR maximization)',
            self::ZeroForcing       => 'Zero-Forcing (Inter-user interference cancellation)',
            self::HolographicPencil => 'Holographic Metasurface High-Directivity Pencil Beam',
            self::CodebookSwept     => 'Hierarchical Discrete Codebook Swept Beamforming',
        };
    }
}

