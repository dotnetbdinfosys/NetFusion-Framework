<?php
/**
 * NetFusion Framework - Enterprise Quantum Thermodynamics & Open Quantum Systems Fabric
 *
 * Density matrix rho for a 2-level (qubit) open quantum system.
 *
 * Represents the mixed state: rho = [[rho00, rho01], [rho10, rho11]]
 * with rho01 = conj(rho10), Tr(rho) = 1, rho >= 0.
 *
 * All matrix elements are stored as [real, imag] float pairs:
 *   $this->elements[row][col] = [float $re, float $im]
 *
 * @package    NetFusion\Core\Physics\QuantumThermodynamics\State
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\QuantumThermodynamics\State;

use NetFusion\Core\Physics\QuantumThermodynamics\Exceptions\QuantumThermodynamicsException;

class DensityMatrix
{
    /**
     * Boltzmann constant k_B in J/K.
     */
    public const BOLTZMANN = 1.380649e-23;

    /**
     * Density matrix elements as 2x2 complex array.
     * $elements[row][col] = [re, im]
     *
     * @var array<int, array<int, array{float,float}>>
     */
    private array $elements;

    /**
     * Create a qubit density matrix from diagonal populations and off-diagonal coherence.
     *
     * @param float $rho00   Ground state population (0 <= rho00 <= 1)
     * @param float $rho11   Excited state population (rho11 = 1 - rho00)
     * @param float $cohRe   Real part of coherence rho01
     * @param float $cohIm   Imaginary part of coherence rho01
     */
    public function __construct(
        float $rho00 = 1.0,
        float $rho11 = 0.0,
        float $cohRe = 0.0,
        float $cohIm = 0.0
    ) {
        $this->elements = [
            0 => [0 => [$rho00, 0.0], 1 => [$cohRe, $cohIm]],
            1 => [0 => [$cohRe, -$cohIm], 1 => [$rho11, 0.0]],
        ];
        $this->enforceNormalization();
    }

    /**
     * Create a thermal equilibrium (Gibbs) state rho = exp(-H/kT) / Z
     * for a qubit with energy splitting hbar_omega (in Joules) at temperature T (Kelvin).
     *
     * Populations: rho11 = exp(-E/kT) / (1 + exp(-E/kT)), rho00 = 1 - rho11.
     *
     * @param float $energySplittingJoules  hbar * omega (energy gap in Joules)
     * @param float $temperatureKelvin      Bath temperature in Kelvin (> 0)
     */
    public static function thermalState(float $energySplittingJoules, float $temperatureKelvin): self
    {
        if ($temperatureKelvin <= 0.0) {
            throw new QuantumThermodynamicsException(
                "Temperature must be positive; got T = {$temperatureKelvin} K."
            );
        }
        $beta = 1.0 / (self::BOLTZMANN * $temperatureKelvin);
        $x = exp(-$beta * $energySplittingJoules);
        $z = 1.0 + $x;
        $rho11 = $x / $z;
        $rho00 = 1.0 / $z;
        return new self($rho00, $rho11, 0.0, 0.0);
    }

    /**
     * Create the maximally mixed state rho = I / 2.
     */
    public static function maximumMixed(): self
    {
        return new self(0.5, 0.5, 0.0, 0.0);
    }

    /**
     * Create a pure state |0><0| (ground state).
     */
    public static function groundState(): self
    {
        return new self(1.0, 0.0, 0.0, 0.0);
    }

    /**
     * Create a pure state |1><1| (fully excited).
     */
    public static function excitedState(): self
    {
        return new self(0.0, 1.0, 0.0, 0.0);
    }

    // ----------------------------------------------------------------
    // Element accessors (complex [re, im] pairs)
    // ----------------------------------------------------------------

    public function element(int $row, int $col): array
    {
        return $this->elements[$row][$col];
    }

    /** Ground state population rho_00 (real, between 0 and 1). */
    public function rho00(): float
    {
        return $this->elements[0][0][0];
    }

    /** Excited state population rho_11 (real, between 0 and 1). */
    public function rho11(): float
    {
        return $this->elements[1][1][0];
    }

    /** Off-diagonal coherence magnitude |rho_01|. */
    public function coherenceMagnitude(): float
    {
        [$re, $im] = $this->elements[0][1];
        return sqrt($re * $re + $im * $im);
    }

    // ----------------------------------------------------------------
    // Physical observables
    // ----------------------------------------------------------------

    /**
     * Trace of the density matrix Tr(rho) = rho_00 + rho_11.
     * Must equal 1.0 for a valid quantum state.
     */
    public function trace(): float
    {
        return $this->elements[0][0][0] + $this->elements[1][1][0];
    }

    /**
     * Purity Tr(rho^2) = rho_00^2 + rho_11^2 + 2*|rho_01|^2.
     * Pure state = 1.0, maximally mixed = 0.5 (for qubit).
     */
    public function purity(): float
    {
        $r00 = $this->elements[0][0][0];
        $r11 = $this->elements[1][1][0];
        [$cohRe, $cohIm] = $this->elements[0][1];
        return $r00 * $r00 + $r11 * $r11 + 2.0 * ($cohRe * $cohRe + $cohIm * $cohIm);
    }

    /**
     * Von Neumann entropy S(rho) = -Tr(rho ln rho).
     *
     * For a diagonal qubit: S = -sum_i lambda_i * ln(lambda_i)
     * Eigenvalues are rho_00 and rho_11 for diagonal (incoherent) states.
     */
    public function vonNeumannEntropy(): float
    {
        $eigenvalues = $this->eigenvalues();
        $entropy = 0.0;
        foreach ($eigenvalues as $lambda) {
            if ($lambda > 1e-15) {
                $entropy -= $lambda * log($lambda);
            }
        }
        return $entropy;
    }

    /**
     * Eigenvalues of a 2x2 Hermitian matrix via analytic formula.
     * lambda = (Tr/2) +/- sqrt((Tr/2)^2 - det)
     *
     * @return float[] [lambda_1, lambda_2]
     */
    public function eigenvalues(): array
    {
        $r00 = $this->elements[0][0][0];
        $r11 = $this->elements[1][1][0];
        [$cohRe, $cohIm] = $this->elements[0][1];
        $det = $r00 * $r11 - ($cohRe * $cohRe + $cohIm * $cohIm);
        $trHalf = ($r00 + $r11) / 2.0;
        $disc = sqrt(max(0.0, $trHalf * $trHalf - $det));
        return [$trHalf + $disc, $trHalf - $disc];
    }

    /**
     * Whether the state is pure: Tr(rho^2) ≈ 1.
     */
    public function isPure(float $tol = 1e-9): bool
    {
        return abs($this->purity() - 1.0) < $tol;
    }

    // ----------------------------------------------------------------
    // State update (returns a new DensityMatrix with updated values)
    // ----------------------------------------------------------------

    /**
     * Return a new DensityMatrix with modified populations and coherences.
     *
     * @param float $rho00  New ground state population
     * @param float $rho11  New excited state population
     * @param float $cohRe  New coherence real part
     * @param float $cohIm  New coherence imaginary part
     */
    public function withElements(float $rho00, float $rho11, float $cohRe, float $cohIm): self
    {
        return new self($rho00, $rho11, $cohRe, $cohIm);
    }

    /**
     * Enforce Tr(rho) = 1 by renormalizing diagonal elements if necessary.
     */
    private function enforceNormalization(): void
    {
        $trace = $this->elements[0][0][0] + $this->elements[1][1][0];
        if (abs($trace) < 1e-15) {
            throw new QuantumThermodynamicsException('Density matrix trace is zero; invalid quantum state.');
        }
        if (abs($trace - 1.0) > 1e-6) {
            $this->elements[0][0][0] /= $trace;
            $this->elements[1][1][0] /= $trace;
        }
    }

    /**
     * Serialize density matrix to array for storage or debugging.
     */
    public function toArray(): array
    {
        return [
            'rho00'              => $this->rho00(),
            'rho11'              => $this->rho11(),
            'coherence_re'       => $this->elements[0][1][0],
            'coherence_im'       => $this->elements[0][1][1],
            'coherence_magnitude'=> $this->coherenceMagnitude(),
            'trace'              => $this->trace(),
            'purity'             => $this->purity(),
            'von_neumann_entropy'=> $this->vonNeumannEntropy(),
            'is_pure'            => $this->isPure(),
        ];
    }
}
