<?php
/**
 * NetFusion Framework - Enterprise Synthetic Biology & BioCAD Fabric
 *
 * Biological host organism classifications for codon bias adaptation.
 *
 * @package    NetFusion\Core\Bio\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Bio\Enums;

enum OrganismHost: string
{
    case E_COLI       = 'e_coli';        // Escherichia coli (bacterial expression)
    case S_CEREVISIAE = 's_cerevisiae';  // Saccharomyces cerevisiae (yeast model)
    case H_SAPIENS    = 'h_sapiens';     // Homo sapiens (human therapeutic cell)
    case CHO_CELLS    = 'cho_cells';     // Chinese Hamster Ovary (biopharmaceutical monoclonal antibodies)

    /**
     * Standard optimal growth temperature in Celsius.
     */
    public function optimalTemperature(): float
    {
        return match ($this) {
            self::E_COLI, self::H_SAPIENS, self::CHO_CELLS => 37.0,
            self::S_CEREVISIAE => 30.0,
        };
    }
}
