<?php
/**
 * NetFusion Framework - Enterprise Quantum Thermodynamics & Open Quantum Systems Fabric
 *
 * Enum of quantum heat engine cycle types supported by the framework.
 *
 * @package    NetFusion\Core\Physics\QuantumThermodynamics\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\QuantumThermodynamics\Enums;

enum ThermalCycleType: string
{
    case OTTO    = 'otto';    // Quantum Otto cycle: two isentropic + two isochoric strokes
    case CARNOT  = 'carnot';  // Quantum Carnot cycle: two isothermal + two adiabatic strokes
    case STIRLING = 'stirling'; // Quantum Stirling cycle: two isothermal + two isochoric strokes

    /**
     * Human-readable description of the cycle.
     */
    public function description(): string
    {
        return match ($this) {
            self::OTTO     => 'Quantum Otto Cycle (isentropic compression/expansion + isochoric heat exchange)',
            self::CARNOT   => 'Quantum Carnot Cycle (maximal Carnot efficiency bound)',
            self::STIRLING => 'Quantum Stirling Cycle (isothermal + isochoric strokes with regenerator)',
        };
    }

    /**
     * Theoretical efficiency formula label.
     */
    public function efficiencyFormula(): string
    {
        return match ($this) {
            self::OTTO     => 'eta = 1 - omega_c / omega_h',
            self::CARNOT   => 'eta = 1 - T_c / T_h',
            self::STIRLING => 'eta = 1 - T_c / T_h (with ideal regenerator)',
        };
    }
}
