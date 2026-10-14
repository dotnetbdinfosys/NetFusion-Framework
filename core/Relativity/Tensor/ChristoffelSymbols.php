<?php
/**
 * NetFusion Framework - Enterprise Numerical Relativity Engine
 *
 * Second kind Christoffel symbols (affine connection coefficients) Gamma^sigma_mu_nu.
 *
 * @package    NetFusion\Core\Relativity\Tensor
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Relativity\Tensor;

use NetFusion\Core\Relativity\Exceptions\RelativityComputationException;

class ChristoffelSymbols
{
    /**
     * Gamma[sigma][mu][nu] of size [4][4][4]
     *
     * @var array<int, array<int, array<int, float>>>
     */
    private array $symbols;

    /**
     * @param array<int, array<int, array<int, float>>> $symbols
     */
    public function __construct(array $symbols)
    {
        $this->symbols = $symbols;
    }

    public static function zeros(): self
    {
        $sym = [];
        for ($s = 0; $s < 4; $s++) {
            $row = [];
            for ($m = 0; $m < 4; $m++) {
                $col = [];
                for ($n = 0; $n < 4; $n++) {
                    $col[] = 0.0;
                }
                $row[] = $col;
            }
            $sym[] = $row;
        }
        return new self($sym);
    }

    /**
     * Analytical Christoffel symbols for Schwarzschild metric in coordinates (t, r, theta, phi).
     * Indices: 0=t, 1=r, 2=theta, 3=phi.
     */
    public static function forSchwarzschild(float $massSolarMasses, float $rMeters, float $thetaRad = M_PI_2): self
    {
        $sym = self::zeros();
        $rs = MetricTensor::schwarzschildRadiusMeters($massSolarMasses);

        $f = 1.0 - ($rs / $rMeters);
        $sinTh = sin($thetaRad);
        $cosTh = cos($thetaRad);

        // Gamma^t_tr = Gamma^t_rt = rs / (2 * r * (r - rs))
        $gammaTtr = $rs / (2.0 * $rMeters * ($rMeters - $rs));
        $sym->set(0, 0, 1, $gammaTtr);
        $sym->set(0, 1, 0, $gammaTtr);

        // Gamma^r_tt = (rs * (r - rs)) / (2 * r^3)
        $gammaRtt = ($rs * ($rMeters - $rs)) / (2.0 * pow($rMeters, 3));
        $sym->set(1, 0, 0, $gammaRtt);

        // Gamma^r_rr = -rs / (2 * r * (r - rs))
        $gammaRrr = -$rs / (2.0 * $rMeters * ($rMeters - $rs));
        $sym->set(1, 1, 1, $gammaRrr);

        // Gamma^r_thth = -(r - rs)
        $gammaRthth = -($rMeters - $rs);
        $sym->set(1, 2, 2, $gammaRthth);

        // Gamma^r_phph = -(r - rs) * sin^2(theta)
        $gammaRphph = -($rMeters - $rs) * $sinTh * $sinTh;
        $sym->set(1, 3, 3, $gammaRphph);

        // Gamma^th_rth = Gamma^th_thr = 1 / r
        $gammaThrth = 1.0 / $rMeters;
        $sym->set(2, 1, 2, $gammaThrth);
        $sym->set(2, 2, 1, $gammaThrth);

        // Gamma^th_phph = -sin(theta) * cos(theta)
        $gammaThphph = -$sinTh * $cosTh;
        $sym->set(2, 3, 3, $gammaThphph);

        // Gamma^ph_rph = Gamma^ph_phr = 1 / r
        $gammaPhrph = 1.0 / $rMeters;
        $sym->set(3, 1, 3, $gammaPhrph);
        $sym->set(3, 3, 1, $gammaPhrph);

        // Gamma^ph_thph = Gamma^ph_phth = cos(theta) / sin(theta) = cot(theta)
        $gammaPhthph = (abs($sinTh) > 1e-12) ? ($cosTh / $sinTh) : 0.0;
        $sym->set(3, 2, 3, $gammaPhthph);
        $sym->set(3, 3, 2, $gammaPhthph);

        return $sym;
    }

    public function get(int $sigma, int $mu, int $nu): float
    {
        if ($sigma < 0 || $sigma >= 4 || $mu < 0 || $mu >= 4 || $nu < 0 || $nu >= 4) {
            throw new RelativityComputationException("Christoffel symbol indices out of bounds: ($sigma, $mu, $nu)");
        }
        return $this->symbols[$sigma][$mu][$nu];
    }

    public function set(int $sigma, int $mu, int $nu, float $val): void
    {
        if ($sigma < 0 || $sigma >= 4 || $mu < 0 || $mu >= 4 || $nu < 0 || $nu >= 4) {
            throw new RelativityComputationException("Christoffel symbol indices out of bounds: ($sigma, $mu, $nu)");
        }
        $this->symbols[$sigma][$mu][$nu] = $val;
    }

    /**
     * Compute geodesic acceleration d^2 x^sigma / dtau^2 = - Gamma^sigma_mu_nu (dx^mu/dtau) (dx^nu/dtau)
     *
     * @param array<int, float> $fourVelocity u^mu = dx^mu / dtau
     * @return array<int, float> four-acceleration a^sigma
     */
    public function geodesicAcceleration(array $fourVelocity): array
    {
        if (count($fourVelocity) !== 4) {
            throw new RelativityComputationException('Four-velocity must have 4 components.');
        }

        $acc = [];
        for ($s = 0; $s < 4; $s++) {
            $sum = 0.0;
            for ($m = 0; $m < 4; $m++) {
                for ($n = 0; $n < 4; $n++) {
                    $sum += $this->symbols[$s][$m][$n] * $fourVelocity[$m] * $fourVelocity[$n];
                }
            }
            $acc[] = -$sum;
        }

        return $acc;
    }
}
