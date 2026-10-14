<?php
/**
 * NetFusion Framework - Enterprise Plasma Physics & Fusion Reactor Fabric
 *
 * Exception thrown on MHD disruptions, Kruskal-Shafranov limit violations, or loss of equilibrium.
 *
 * @package    NetFusion\Core\Physics\Plasma\Exceptions
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Plasma\Exceptions;

use NetFusion\Core\Physics\Plasma\Enums\InstabilityMode;
use RuntimeException;

class PlasmaInstabilityException extends RuntimeException
{
    public static function kruskalShafranovViolation(float $qEdge, float $limit = 1.0): self
    {
        return new self(sprintf(
            "Kruskal-Shafranov safety factor threshold violated (q=%.4f < %.1f): immediate external kink disruption imminent.",
            $qEdge,
            $limit
        ));
    }

    public static function troyonBetaLimitExceeded(float $beta, float $betaTroyon): self
    {
        return new self(sprintf(
            "Plasma beta factor exceeded Troyon operational limit (beta=%.4f%% > limit=%.4f%%): high-beta ballooning instability.",
            $beta * 100.0,
            $betaTroyon * 100.0
        ));
    }
}
