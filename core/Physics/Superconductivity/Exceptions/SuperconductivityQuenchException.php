<?php
/**
 * NetFusion Framework - Enterprise Superconductivity & Quantum Magnetics Fabric
 *
 * Exception thrown when a superconducting component transitions catastrophically to the normal resistive state.
 *
 * @package    NetFusion\Core\Physics\Superconductivity\Exceptions
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Superconductivity\Exceptions;

use RuntimeException;

class SuperconductivityQuenchException extends RuntimeException
{
    public static function criticalTemperatureExceeded(float $temperature, float $tc): self
    {
        return new self(sprintf(
            "Superconducting quench triggered: Operating temperature T=%.2f K exceeded critical temperature T_c=%.2f K.",
            $temperature,
            $tc
        ));
    }

    public static function criticalFieldExceeded(float $appliedField, float $bc): self
    {
        return new self(sprintf(
            "Superconducting quench triggered: Applied magnetic field B=%.2f T exceeded critical field B_c=%.2f T.",
            $appliedField,
            $bc
        ));
    }

    public static function criticalCurrentExceeded(float $currentDensity, float $jc): self
    {
        return new self(sprintf(
            "Superconducting quench triggered: Current density J=%.2e A/m^2 exceeded critical current density J_c=%.2e A/m^2.",
            $currentDensity,
            $jc
        ));
    }
}
