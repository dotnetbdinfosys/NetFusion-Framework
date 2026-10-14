<?php
/**
 * NetFusion Framework - Enterprise Quantum Thermodynamics & Open Quantum Systems Fabric
 *
 * Enum of Lindblad dissipation channels modeling environment-induced
 * quantum decoherence and thermalization processes.
 *
 * @package    NetFusion\Core\Physics\QuantumThermodynamics\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\QuantumThermodynamics\Enums;

enum DissipationChannel: string
{
    case AMPLITUDE_DAMPING = 'amplitude_damping'; // Spontaneous emission: |1> -> |0> energy decay
    case DEPHASING         = 'dephasing';          // Pure phase decoherence: sigma_z collapse
    case THERMAL_PUMPING   = 'thermal_pumping';    // Thermal excitation: |0> -> |1> from hot bath

    /**
     * Physical description of the Lindblad channel.
     */
    public function description(): string
    {
        return match ($this) {
            self::AMPLITUDE_DAMPING => 'Amplitude damping (spontaneous emission, T1 decay, sigma_- jump operator)',
            self::DEPHASING         => 'Pure dephasing (T2 decoherence, sigma_z/2 jump operator)',
            self::THERMAL_PUMPING   => 'Thermal excitation from hot reservoir (sigma_+ jump operator)',
        };
    }

    /**
     * Lindblad jump operator label in Dirac notation.
     */
    public function jumpOperatorLabel(): string
    {
        return match ($this) {
            self::AMPLITUDE_DAMPING => 'L = sigma_- = |0><1|',
            self::DEPHASING         => 'L = sigma_z / 2',
            self::THERMAL_PUMPING   => 'L = sigma_+ = |1><0|',
        };
    }
}
