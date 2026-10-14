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

namespace NetFusion\Core\Photonic\Mesh;

use NetFusion\Core\Photonic\Enums\MeshTopology;
use NetFusion\Core\Photonic\Exceptions\PhotonicException;
use NetFusion\Core\Photonic\Interferometer\MziCell;
use NetFusion\Core\Photonic\Physics\ComplexNumber;

class InterferometerMesh
{
    protected string $meshId;
    protected int $numPorts;
    protected MeshTopology $topology;
    protected float $insertionLossPerCell;

    /** @var array<int, array<int, array{cell: MziCell, portUpper: int, portLower: int}>> Layer -> list of MZI placements */
    protected array $layers = [];
    /** @var float[] Output phase screen (N phase shifters) */
    protected array $outputPhases = [];

    public function __construct(
        string $meshId = 'mesh_01',
        int $numPorts = 4,
        MeshTopology $topology = MeshTopology::CLEMENTS,
        float $insertionLossPerCell = 0.05
    ) {
        if ($numPorts < 2) {
            throw new PhotonicException("Optical interferometer mesh requires at least 2 ports.");
        }

        $this->meshId = $meshId;
        $this->numPorts = $numPorts;
        $this->topology = $topology;
        $this->insertionLossPerCell = $insertionLossPerCell;
        $this->outputPhases = array_fill(0, $numPorts, 0.0);

        $this->buildMesh();
    }

    public function getMeshId(): string
    {
        return $this->meshId;
    }

    public function getNumPorts(): int
    {
        return $this->numPorts;
    }

    public function getTopology(): MeshTopology
    {
        return $this->topology;
    }

    /**
     * Construct Clements rectangular MZI grid
     */
    protected function buildMesh(): void
    {
        $this->layers = [];
        $cellCount = 0;

        if ($this->topology === MeshTopology::CLEMENTS) {
            // Clements rectangular architecture has N layers
            $numLayers = $this->numPorts;

            for ($layer = 0; $layer < $numLayers; $layer++) {
                $this->layers[$layer] = [];
                $startPort = ($layer % 2 === 0) ? 0 : 1;

                for ($p = $startPort; $p + 1 < $this->numPorts; $p += 2) {
                    $cell = new MziCell("mzi_{$cellCount}", 0.0, 0.0, $this->insertionLossPerCell);
                    $this->layers[$layer][] = [
                        'cell' => $cell,
                        'portUpper' => $p,
                        'portLower' => $p + 1,
                    ];
                    $cellCount++;
                }
            }
        } else {
            // Reck triangular architecture
            $numLayers = 2 * $this->numPorts - 3;
            for ($layer = 0; $layer < $numLayers; $layer++) {
                $this->layers[$layer] = [];
            }
            for ($i = 0; $i < $this->numPorts - 1; $i++) {
                for ($j = $i + 1; $j < $this->numPorts; $j++) {
                    $layerIdx = ($i + $j - 1) % $numLayers;
                    $cell = new MziCell("mzi_{$cellCount}", 0.0, 0.0, $this->insertionLossPerCell);
                    $this->layers[$layerIdx][] = [
                        'cell' => $cell,
                        'portUpper' => $i,
                        'portLower' => $j,
                    ];
                    $cellCount++;
                }
            }
        }
    }

    /**
     * Total number of MZI cells in the mesh
     */
    public function getCellCount(): int
    {
        $count = 0;
        foreach ($this->layers as $layer) {
            $count += count($layer);
        }
        return $count;
    }

    /**
     * Retrieve all MZI cells in order
     *
     * @return MziCell[]
     */
    public function getCells(): array
    {
        $cells = [];
        foreach ($this->layers as $layer) {
            foreach ($layer as $item) {
                $cells[] = $item['cell'];
            }
        }
        return $cells;
    }

    /**
     * Set phase angles for all MZI cells
     *
     * @param array<int, array{theta: float, phi: float}> $phases
     * @param float[]|null $outputPhases
     */
    public function setMeshPhases(array $phases, ?array $outputPhases = null): void
    {
        $cells = $this->getCells();
        foreach ($phases as $idx => $p) {
            if (isset($cells[$idx])) {
                $cells[$idx]->setPhaseShifters($p['theta'] ?? 0.0, $p['phi'] ?? 0.0);
            }
        }

        if ($outputPhases !== null) {
            for ($i = 0; $i < $this->numPorts; $i++) {
                $this->outputPhases[$i] = $outputPhases[$i] ?? 0.0;
            }
        }
    }

    /**
     * Propagate input optical field vector through the interferometer mesh
     *
     * @param ComplexNumber[] $inputFields Array of N complex optical field amplitudes
     * @return ComplexNumber[] Output optical field amplitudes
     */
    public function propagate(array $inputFields): array
    {
        if (count($inputFields) !== $this->numPorts) {
            throw new PhotonicException("Input field count (" . count($inputFields) . ") must match mesh ports ({$this->numPorts}).");
        }

        $fields = $inputFields;

        // Propagate layer by layer through MZI cells
        foreach ($this->layers as $layer) {
            foreach ($layer as $item) {
                $u = $item['portUpper'];
                $l = $item['portLower'];

                /** @var MziCell $cell */
                $cell = $item['cell'];
                [$outU, $outL] = $cell->propagate($fields[$u], $fields[$l]);

                $fields[$u] = $outU;
                $fields[$l] = $outL;
            }
        }

        // Apply final output phase shifters e^(i * outputPhase)
        $outputFields = [];
        for ($i = 0; $i < $this->numPorts; $i++) {
            $expPhase = ComplexNumber::fromPolar(1.0, $this->outputPhases[$i]);
            $outputFields[] = $fields[$i]->mul($expPhase);
        }

        return $outputFields;
    }

    /**
     * Compute full N x N unitary optical transfer matrix of the mesh
     *
     * @return ComplexNumber[][]
     */
    public function computeTransferMatrix(): array
    {
        $matrix = [];
        for ($col = 0; $col < $this->numPorts; $col++) {
            // Impulse vector at port $col: [0, ..., 1, ..., 0]
            $basis = array_fill(0, $this->numPorts, ComplexNumber::zero());
            $basis[$col] = ComplexNumber::one();

            $output = $this->propagate($basis);
            for ($row = 0; $row < $this->numPorts; $row++) {
                $matrix[$row][$col] = $output[$row];
            }
        }
        return $matrix;
    }
}
