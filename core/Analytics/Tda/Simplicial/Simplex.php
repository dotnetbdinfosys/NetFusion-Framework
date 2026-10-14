<?php
/**
 * NetFusion Framework - Enterprise Topological Data Analysis (TDA) Engine
 *
 * k-simplex defined by an ordered set of vertices and filtration diameter.
 *
 * @package    NetFusion\Core\Analytics\Tda\Simplicial
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Analytics\Tda\Simplicial;

use NetFusion\Core\Analytics\Tda\Exceptions\DegenerateSimplexException;

class Simplex
{
    /**
     * @var int[] Ordered unique vertex indices
     */
    private array $vertices;
    private int $dimension;

    /**
     * @param int[] $vertices
     * @param float $filtrationRadius Filtration birth value epsilon
     */
    public function __construct(
        array $vertices,
        private readonly float $filtrationRadius = 0.0
    ) {
        if (count($vertices) === 0) {
            throw new DegenerateSimplexException('Simplex must have at least one vertex.');
        }

        // Check for duplicates
        $unique = array_unique($vertices);
        if (count($unique) !== count($vertices)) {
            throw new DegenerateSimplexException('Simplex cannot contain duplicate vertices.');
        }

        sort($unique, SORT_NUMERIC);
        $this->vertices = array_values($unique);
        $this->dimension = count($this->vertices) - 1;
    }

    /**
     * @return int[]
     */
    public function getVertices(): array
    {
        return $this->vertices;
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }

    public function getFiltrationRadius(): float
    {
        return $this->filtrationRadius;
    }

    /**
     * Unique key identifier for the simplex (e.g. "[0,2,5]").
     */
    public function getKey(): string
    {
        return '[' . implode(',', $this->vertices) . ']';
    }

    /**
     * Generate all codimension-1 boundary faces ((k-1)-simplices).
     * For simplex [v_0, v_1, ..., v_k], each face is obtained by omitting vertex v_i.
     *
     * @return array<int, array{vertices: int[], sign: int}>
     */
    public function getBoundaryFaces(): array
    {
        if ($this->dimension === 0) {
            return [];
        }

        $faces = [];
        $k = count($this->vertices);

        for ($i = 0; $i < $k; $i++) {
            $faceVertices = [];
            for ($j = 0; $j < $k; $j++) {
                if ($i !== $j) {
                    $faceVertices[] = $this->vertices[$j];
                }
            }
            $sign = ($i % 2 === 0) ? 1 : -1;
            $faces[] = [
                'vertices' => $faceVertices,
                'sign'     => $sign,
            ];
        }

        return $faces;
    }
}
