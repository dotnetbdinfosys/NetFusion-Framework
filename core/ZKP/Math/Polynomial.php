<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\ZKP\Math;

/**
 * Univariate polynomial representation and operations over finite field F_p.
 * Coefficients: [a_0, a_1, a_2, ...] where P(x) = a_0 + a_1*x + a_2*x^2 + ...
 */
class Polynomial
{
    /** @var FieldElement[] */
    private array $coeffs;
    private FiniteField $field;

    public function __construct(array $coeffs, ?FiniteField $field = null)
    {
        if ($field === null) {
            foreach ($coeffs as $c) {
                if ($c instanceof FieldElement) {
                    $field = $c->getField();
                    break;
                }
            }
            $field ??= FiniteField::bn254();
        }
        $this->field = $field;
        $this->coeffs = [];
        foreach ($coeffs as $c) {
            $this->coeffs[] = $c instanceof FieldElement ? $c : $field->element($c);
        }
        $this->trim();
    }

    private function trim(): void
    {
        while (count($this->coeffs) > 1 && end($this->coeffs)->isZero()) {
            array_pop($this->coeffs);
        }
        if (empty($this->coeffs)) {
            $this->coeffs = [$this->field->zero()];
        }
    }

    public function degree(): int
    {
        return count($this->coeffs) - 1;
    }

    public function getCoefficients(): array
    {
        return $this->coeffs;
    }

    /**
     * Evaluate polynomial at point x using Horner's method:
     * P(x) = a_0 + x * (a_1 + x * (a_2 + ...))
     */
    public function evaluate(FieldElement|int|string $x): FieldElement
    {
        $xElem = $x instanceof FieldElement ? $x : $this->field->element($x);
        $result = $this->field->zero();

        for ($i = count($this->coeffs) - 1; $i >= 0; $i--) {
            $result = $result->mul($xElem)->add($this->coeffs[$i]);
        }

        return $result;
    }

    public function add(Polynomial $other): self
    {
        $maxLen = max(count($this->coeffs), count($other->coeffs));
        $resCoeffs = [];

        for ($i = 0; $i < $maxLen; $i++) {
            $c1 = $this->coeffs[$i] ?? $this->field->zero();
            $c2 = $other->coeffs[$i] ?? $this->field->zero();
            $resCoeffs[] = $c1->add($c2);
        }

        return new self($resCoeffs, $this->field);
    }

    public function mul(Polynomial $other): self
    {
        $deg1 = count($this->coeffs);
        $deg2 = count($other->coeffs);
        $resLen = $deg1 + $deg2 - 1;
        $resCoeffs = array_fill(0, $resLen, $this->field->zero());

        for ($i = 0; $i < $deg1; $i++) {
            for ($j = 0; $j < $deg2; $j++) {
                $term = $this->coeffs[$i]->mul($other->coeffs[$j]);
                $resCoeffs[$i + $j] = $resCoeffs[$i + $j]->add($term);
            }
        }

        return new self($resCoeffs, $this->field);
    }

    /**
     * Compute Lagrange Interpolation polynomial passing through given points (x_i, y_i).
     *
     * @param array<array{x: FieldElement|int|string, y: FieldElement|int|string}> $points
     */
    public static function interpolate(array $points, FiniteField $field): self
    {
        $result = new self([$field->zero()], $field);

        foreach ($points as $i => $ptI) {
            $xi = $ptI['x'] instanceof FieldElement ? $ptI['x'] : $field->element($ptI['x']);
            $yi = $ptI['y'] instanceof FieldElement ? $ptI['y'] : $field->element($ptI['y']);

            // Basis polynomial l_i(x)
            $li = new self([$field->one()], $field);
            $denom = $field->one();

            foreach ($points as $j => $ptJ) {
                if ($i === $j) {
                    continue;
                }
                $xj = $ptJ['x'] instanceof FieldElement ? $ptJ['x'] : $field->element($ptJ['x']);

                // Term (x - xj)
                $term = new self([$field->zero()->sub($xj), $field->one()], $field);
                $li = $li->mul($term);
                $denom = $denom->mul($xi->sub($xj));
            }

            // Scale by y_i / denom
            $scaleFactor = $yi->div($denom);
            $termPoly = $li->mul(new self([$scaleFactor], $field));
            $result = $result->add($termPoly);
        }

        return $result;
    }
}
