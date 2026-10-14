<?php
/**
 * NetFusion Framework - Enterprise Synthetic Biology & BioCAD Fabric
 *
 * CRISPR-Cas endonuclease variant classifications and PAM motif rules.
 *
 * @package    NetFusion\Core\Bio\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Bio\Enums;

enum CasVariant: string
{
    case SpCas9         = 'SpCas9';         // Streptococcus pyogenes Cas9 (PAM: NGG, 3' end)
    case SaCas9         = 'SaCas9';         // Staphylococcus aureus Cas9 (PAM: NNGRRT, 3' end)
    case Cas12a_AsCpf1  = 'Cas12a_AsCpf1';  // Acidaminococcus sp. Cas12a/Cpf1 (PAM: TTTV, 5' end)
    case Cas13a         = 'Cas13a';         // Leptotrichia wadei Cas13a (RNA targeting, PFS motif)

    /**
     * Standard spacer length in nucleotides.
     */
    public function spacerLength(): int
    {
        return match ($this) {
            self::SpCas9 => 20,
            self::SaCas9 => 21,
            self::Cas12a_AsCpf1 => 23,
            self::Cas13a => 28,
        };
    }

    /**
     * Canonical Protospacer Adjacent Motif (PAM).
     */
    public function pamPattern(): string
    {
        return match ($this) {
            self::SpCas9 => '[ACGT]GG',
            self::SaCas9 => '[ACGT]{2}G[AG]{2}T',
            self::Cas12a_AsCpf1 => 'TTT[ACG]',
            self::Cas13a => '[ACG]', // Non-G PFS
        };
    }

    /**
     * Whether PAM is located at 3' end (true for Cas9) or 5' end (true for Cas12a).
     */
    public function isPamAtThreePrime(): bool
    {
        return match ($this) {
            self::SpCas9, self::SaCas9 => true,
            self::Cas12a_AsCpf1, self::Cas13a => false,
        };
    }
}
