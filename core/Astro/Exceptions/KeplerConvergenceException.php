<?php
/**
 * NetFusion Framework - Enterprise Astro-Informatics & Celestial Mechanics Fabric
 *
 * Exception thrown when Newton-Raphson iteration fails to converge for Kepler equation.
 *
 * @package    NetFusion\Core\Astro\Exceptions
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Astro\Exceptions;

class KeplerConvergenceException extends AstroPhysicsException
{
    public static function failedToConverge(float $meanAnomaly, float $eccentricity, int $maxIterations): self
    {
        return new self(sprintf(
            "Newton-Raphson Kepler solver failed to converge for M=%.6f, e=%.6f after %d iterations.",
            $meanAnomaly,
            $eccentricity,
            $maxIterations
        ));
    }
}
