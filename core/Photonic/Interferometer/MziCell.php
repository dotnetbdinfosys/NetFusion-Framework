<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Optical Photonic Computing & Tensor Light-Interferometer
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Photonic\Interferometer;

use NetFusion\Core\Photonic\Physics\ComplexNumber;

class MziCell
{
    protected string $cellId;
    protected float $theta; // Internal phase shifter [0, pi]
    protected float $phi;   // External phase shifter [0, 2*pi]
    protected float $insertionLossDb; // Optical waveguide insertion loss in dB

    public function __construct(
        string $cellId = 'mzi_0',
        float $theta = 0.0,
        float $phi = 0.0,
        float $insertionLossDb = 0.05
    ) {
        $this->cellId = $cellId;
        $this->theta = $theta;
        $this->phi = $phi;
        $this->insertionLossDb = $insertionLossDb;
    }

    public function getCellId(): string
    {
        return $this->cellId;
    }

    public function getTheta(): float
    {
        return $this->theta;
    }

    public function getPhi(): float
    {
        return $this->phi;
    }

    public function setPhaseShifters(float $theta, float $phi): void
    {
        $this->theta = $theta;
        $this->phi = $phi;
    }

    /**
     * Compute 2x2 unitary optical transfer matrix
     *
     * @return ComplexNumber[][] 2x2 matrix
     */
    public function getTransferMatrix(): array
    {
        $cosTheta = cos($this->theta);
        $sinTheta = sin($this->theta);

        // e^(i * phi)
        $expIphi = ComplexNumber::fromPolar(1.0, $this->phi);

        // Amplitude attenuation due to insertion loss
        $attenuation = 10.0 ** (-$this->insertionLossDb / 20.0);

        // U_00 = e^(i*phi) * cos(theta) * att
        $u00 = $expIphi->mul($cosTheta * $attenuation);
        // U_01 = -e^(i*phi) * sin(theta) * att
        $u01 = $expIphi->mul(-$sinTheta * $attenuation);
        // U_10 = sin(theta) * att
        $u10 = new ComplexNumber($sinTheta * $attenuation, 0.0);
        // U_11 = cos(theta) * att
        $u11 = new ComplexNumber($cosTheta * $attenuation, 0.0);

        return [
            [$u00, $u01],
            [$u10, $u11],
        ];
    }

    /**
     * Propagate 2-port optical fields through this MZI cell
     *
     * @param ComplexNumber $in1 Optical input field 1
     * @param ComplexNumber $in2 Optical input field 2
     * @return array{0: ComplexNumber, 1: ComplexNumber} [out1, out2]
     */
    public function propagate(ComplexNumber $in1, ComplexNumber $in2): array
    {
        $matrix = $this->getTransferMatrix();

        $out1 = $matrix[0][0]->mul($in1)->add($matrix[0][1]->mul($in2));
        $out2 = $matrix[1][0]->mul($in1)->add($matrix[1][1]->mul($in2));

        return [$out1, $out2];
    }

    /**
     * Set internal splitting ratio directly: 50:50, Bar (100% through), or Cross (100% crossed)
     */
    public function setCoupling(string $state): void
    {
        match ($state) {
            'bar'   => $this->theta = 0.0,           // cos(0) = 1, sin(0) = 0
            'cross' => $this->theta = M_PI / 2.0,    // cos(pi/2) = 0, sin(pi/2) = 1
            '50:50' => $this->theta = M_PI / 4.0,    // cos(pi/4) = sin(pi/4) = 1/sqrt(2)
            default => null,
        };
    }
}
