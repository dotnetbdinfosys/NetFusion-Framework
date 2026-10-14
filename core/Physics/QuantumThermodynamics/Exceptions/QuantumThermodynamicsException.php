<?php
/**
 * NetFusion Framework - Enterprise Quantum Thermodynamics & Open Quantum Systems Fabric
 *
 * Exception thrown when a quantum thermodynamic process violates physical
 * constraints (e.g., temperature inversion, negative entropy, trace > 1).
 *
 * @package    NetFusion\Core\Physics\QuantumThermodynamics\Exceptions
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\QuantumThermodynamics\Exceptions;

class QuantumThermodynamicsException extends \RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct('[QuantumThermodynamics] ' . $message, $code, $previous);
    }
}
