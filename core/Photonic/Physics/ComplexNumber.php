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

namespace NetFusion\Core\Photonic\Physics;

use NetFusion\Core\Photonic\Exceptions\PhotonicException;

class ComplexNumber
{
    protected float $real;
    protected float $imag;

    public function __construct(float $real, float $imag = 0.0)
    {
        $this->real = $real;
        $this->imag = $imag;
    }

    public static function fromPolar(float $r, float $theta): self
    {
        return new self($r * cos($theta), $r * sin($theta));
    }

    public static function zero(): self
    {
        return new self(0.0, 0.0);
    }

    public static function one(): self
    {
        return new self(1.0, 0.0);
    }

    public static function i(): self
    {
        return new self(0.0, 1.0);
    }

    public function real(): float
    {
        return $this->real;
    }

    public function imag(): float
    {
        return $this->imag;
    }

    public function getReal(): float
    {
        return $this->real;
    }

    public function getImaginary(): float
    {
        return $this->imag;
    }

    public function add(self|float $other): self
    {
        if (is_float($other)) {
            return new self($this->real + $other, $this->imag);
        }
        return new self($this->real + $other->real, $this->imag + $other->imag);
    }

    public function sub(self|float $other): self
    {
        if (is_float($other)) {
            return new self($this->real - $other, $this->imag);
        }
        return new self($this->real - $other->real, $this->imag - $other->imag);
    }

    public function mul(self|float $other): self
    {
        if (is_float($other)) {
            return new self($this->real * $other, $this->imag * $other);
        }
        // (a + bi)(c + di) = (ac - bd) + (ad + bc)i
        $r = $this->real * $other->real - $this->imag * $other->imag;
        $i = $this->real * $other->imag + $this->imag * $other->real;
        return new self($r, $i);
    }

    public function multiply(self|float $other): self
    {
        return $this->mul($other);
    }

    public function scale(float $scalar): self
    {
        return new self($this->real * $scalar, $this->imag * $scalar);
    }

    public function div(self|float $other): self
    {
        if (is_float($other)) {
            if (abs($other) < 1e-15) {
                throw new PhotonicException("Division by zero in complex scalar division.");
            }
            return new self($this->real / $other, $this->imag / $other);
        }

        $denom = $other->real * $other->real + $other->imag * $other->imag;
        if ($denom < 1e-15) {
            throw new PhotonicException("Division by zero in complex division.");
        }

        $r = ($this->real * $other->real + $this->imag * $other->imag) / $denom;
        $i = ($this->imag * $other->real - $this->real * $other->imag) / $denom;
        return new self($r, $i);
    }

    public function conjugate(): self
    {
        return new self($this->real, -$this->imag);
    }

    public function magnitude(): float
    {
        return hypot($this->real, $this->imag);
    }

    /**
     * Optical Intensity I = |E|^2
     */
    public function magnitudeSquared(): float
    {
        return $this->real * $this->real + $this->imag * $this->imag;
    }

    public function phase(): float
    {
        return atan2($this->imag, $this->real);
    }

    public function argument(): float
    {
        return $this->phase();
    }

    public function __toString(): string
    {
        $sign = ($this->imag >= 0) ? '+' : '-';
        return sprintf("%.4f %s %.4fi", $this->real, $sign, abs($this->imag));
    }
}

