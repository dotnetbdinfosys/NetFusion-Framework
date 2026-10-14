<?php
/**
 * NetFusion Framework - Enterprise Topological Data Analysis (TDA) Engine
 *
 * Persistence visualization rendering styles.
 *
 * @package    NetFusion\Core\Analytics\Tda\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Analytics\Tda\Enums;

enum DiagramPlotMode: string
{
    case Barcode            = 'barcode';            // Horizontal persistence intervals
    case ScatterPersistence = 'scatter_persistence';// (birth, death) scatter coordinates
    case Summary            = 'summary';            // Lifespan and Betti count table
}
