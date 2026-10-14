<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS Engine
 *
 * Complex channel matrix operations for MIMO and cascaded RIS links.
 *
 * @package    NetFusion\Wireless\Ris\Physics
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Wireless\Ris\Physics;

use NetFusion\Core\Photonic\Physics\ComplexNumber;
use NetFusion\Core\Wireless\Ris\Exceptions\TerahertzChannelException;

class ChannelMatrix
{
    /**
     * @var array<int, array<int, ComplexNumber>> $elements [rows][cols]
     */
    private array $elements;
    private int $rows;
    private int $cols;

    /**
     * @param array<int, array<int, ComplexNumber>> $elements
     */
    public function __construct(array $elements)
    {
        $this->rows = count($elements);
        if ($this->rows === 0) {
            throw new TerahertzChannelException('Channel matrix cannot be empty.');
        }

        $this->cols = count($elements[0]);
        if ($this->cols === 0) {
            throw new TerahertzChannelException('Channel matrix columns cannot be empty.');
        }

        foreach ($elements as $row) {
            if (count($row) !== $this->cols) {
                throw new TerahertzChannelException('All rows in channel matrix must have equal length.');
            }
        }

        $this->elements = $elements;
    }

    public static function zeros(int $rows, int $cols): self
    {
        $matrix = [];
        for ($i = 0; $i < $rows; $i++) {
            $row = [];
            for ($j = 0; $j < $cols; $j++) {
                $row[] = ComplexNumber::zero();
            }
            $matrix[] = $row;
        }
        return new self($matrix);
    }

    public static function identity(int $size): self
    {
        $matrix = [];
        for ($i = 0; $i < $size; $i++) {
            $row = [];
            for ($j = 0; $j < $size; $j++) {
                $row[] = ($i === $j) ? ComplexNumber::one() : ComplexNumber::zero();
            }
            $matrix[] = $row;
        }
        return new self($matrix);
    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function getCols(): int
    {
        return $this->cols;
    }

    public function get(int $row, int $col): ComplexNumber
    {
        if ($row < 0 || $row >= $this->rows || $col < 0 || $col >= $this->cols) {
            throw new TerahertzChannelException("Index out of bounds: [$row, $col]");
        }
        return $this->elements[$row][$col];
    }

    public function set(int $row, int $col, ComplexNumber $val): void
    {
        if ($row < 0 || $row >= $this->rows || $col < 0 || $col >= $this->cols) {
            throw new TerahertzChannelException("Index out of bounds: [$row, $col]");
        }
        $this->elements[$row][$col] = $val;
    }

    /**
     * Matrix addition: A + B
     */
    public function add(self $other): self
    {
        if ($this->rows !== $other->rows || $this->cols !== $other->cols) {
            throw new TerahertzChannelException('Matrices must have matching dimensions for addition.');
        }

        $result = [];
        for ($i = 0; $i < $this->rows; $i++) {
            $row = [];
            for ($j = 0; $j < $this->cols; $j++) {
                $row[] = $this->elements[$i][$j]->add($other->elements[$i][$j]);
            }
            $result[] = $row;
        }
        return new self($result);
    }

    /**
     * Matrix multiplication: A * B
     */
    public function multiply(self $other): self
    {
        if ($this->cols !== $other->rows) {
            throw new TerahertzChannelException("Dimension mismatch for multiplication: [{$this->rows}x{$this->cols}] * [{$other->rows}x{$other->cols}]");
        }

        $result = [];
        for ($i = 0; $i < $this->rows; $i++) {
            $row = [];
            for ($j = 0; $j < $other->cols; $j++) {
                $sum = ComplexNumber::zero();
                for ($k = 0; $k < $this->cols; $k++) {
                    $term = $this->elements[$i][$k]->multiply($other->elements[$k][$j]);
                    $sum = $sum->add($term);
                }
                $row[] = $sum;
            }
            $result[] = $row;
        }
        return new self($result);
    }

    /**
     * Scalar multiplication.
     */
    public function scale(float $scalar): self
    {
        $result = [];
        for ($i = 0; $i < $this->rows; $i++) {
            $row = [];
            for ($j = 0; $j < $this->cols; $j++) {
                $row[] = $this->elements[$i][$j]->scale($scalar);
            }
            $result[] = $row;
        }
        return new self($result);
    }

    /**
     * Conjugate Transpose (Hermitian): A^H
     */
    public function conjugateTranspose(): self
    {
        $result = [];
        for ($j = 0; $j < $this->cols; $j++) {
            $row = [];
            for ($i = 0; $i < $this->rows; $i++) {
                $row[] = $this->elements[$i][$j]->conjugate();
            }
            $result[] = $row;
        }
        return new self($result);
    }

    /**
     * Frobenius norm squared: ||A||_F^2 = sum |a_{i,j}|^2
     */
    public function frobeniusNormSquared(): float
    {
        $sum = 0.0;
        for ($i = 0; $i < $this->rows; $i++) {
            for ($j = 0; $j < $this->cols; $j++) {
                $mag = $this->elements[$i][$j]->magnitude();
                $sum += ($mag * $mag);
            }
        }
        return $sum;
    }

    /**
     * Frobenius norm: sqrt(sum |a_{i,j}|^2)
     */
    public function frobeniusNorm(): float
    {
        return sqrt($this->frobeniusNormSquared());
    }

    /**
     * Estimate Shannon capacity (bits/s/Hz) given SNR in linear scale.
     * C = log2(1 + SNR * ||H||_F^2 / rows)
     */
    public function estimateCapacity(float $snrLinear): float
    {
        $normSq = $this->frobeniusNormSquared();
        $normalizedPower = ($normSq / max(1, $this->rows));
        $effSnr = $snrLinear * $normalizedPower;
        return log(1.0 + max(0.0, $effSnr), 2.0);
    }

    /**
     * @return array<int, array<int, array{real: float, imag: float}>>
     */
    public function toArray(): array
    {
        $out = [];
        for ($i = 0; $i < $this->rows; $i++) {
            $row = [];
            for ($j = 0; $j < $this->cols; $j++) {
                $row[] = [
                    'real' => $this->elements[$i][$j]->getReal(),
                    'imag' => $this->elements[$i][$j]->getImaginary(),
                ];
            }
            $out[] = $row;
        }
        return $out;
    }
}

