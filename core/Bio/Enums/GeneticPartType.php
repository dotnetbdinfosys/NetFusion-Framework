<?php
/**
 * NetFusion Framework - Enterprise Synthetic Biology & BioCAD Fabric
 *
 * Synthetic biology standard modular part classifications (BioBricks).
 *
 * @package    NetFusion\Core\Bio\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Bio\Enums;

enum GeneticPartType: string
{
    case PROMOTER   = 'promoter';   // Transcription initiation site (constitutive or inducible)
    case OPERATOR   = 'operator';   // Transcription factor / repressor binding site
    case RBS        = 'rbs';        // Ribosome Binding Site (Shine-Dalgarno / Kozak consensus)
    case CDS        = 'cds';        // Coding Sequence (Open Reading Frame)
    case TERMINATOR = 'terminator'; // Transcription termination hairpin loop

    /**
     * Determine if part produces functional peptide / protein product.
     */
    public function isProteinCoding(): bool
    {
        return $this === self::CDS;
    }
}
