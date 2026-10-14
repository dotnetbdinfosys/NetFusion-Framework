<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Holographic Storage & DNA Molecular Data Encoding
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Storage\Molecular\Holographic;

use JsonSerializable;
use NetFusion\Core\Storage\Molecular\Exceptions\MolecularStorageException;

class HolographicCrystal implements JsonSerializable
{
    /** @var array<string, HolographicPage> */
    protected array $pages = [];

    public function __construct(
        protected string $crystalId,
        protected float $crystalThicknessMm = 5.0,  // Physical thickness L in mm
        protected string $material = 'Lithium_Niobate_Fe', // Fe-doped LiNbO3
        protected float $angularSelectivityDeg = 0.05 // Min angle separation between pages
    ) {
    }

    public function getCrystalId(): string
    {
        return $this->crystalId;
    }

    public function getMaterial(): string
    {
        return $this->material;
    }

    public function recordPage(HolographicPage $page): void
    {
        $key = sprintf("%.3f", $page->getBraggAngle());
        $this->pages[$key] = $page;
    }

    /**
     * Optical Bragg reconstruction: reads a page if laser angle matches Bragg condition within selectivity
     */
    public function reconstructPage(float $referenceAngleDeg): ?HolographicPage
    {
        $bestPage = null;
        $maxEfficiency = 0.0;

        foreach ($this->pages as $page) {
            $deltaTheta = abs($referenceAngleDeg - $page->getBraggAngle());
            $efficiency = $this->calculateDiffractionEfficiency($deltaTheta);

            // Bragg matching condition: peak diffraction efficiency above threshold
            if ($efficiency > 0.5 && $efficiency > $maxEfficiency) {
                $maxEfficiency = $efficiency;
                $bestPage = $page;
            }
        }

        return $bestPage;
    }

    /**
     * Compute sinc^2 Bragg selectivity curve
     */
    public function calculateDiffractionEfficiency(float $deltaAngleDeg): float
    {
        if ($deltaAngleDeg < 1e-6) {
            return 1.0;
        }

        // sinc(x) = sin(pi * x) / (pi * x)
        $arg = ($deltaAngleDeg / $this->angularSelectivityDeg) * M_PI;
        $sinc = sin($arg) / $arg;
        return round($sinc * $sinc, 4);
    }

    public function getPageCount(): int
    {
        return count($this->pages);
    }

    /**
     * @return HolographicPage[]
     */
    public function getPages(): array
    {
        return array_values($this->pages);
    }

    public function toArray(): array
    {
        $pageSummary = [];
        foreach ($this->pages as $p) {
            $pageSummary[] = $p->toArray();
        }

        return [
            'crystalId' => $this->crystalId,
            'material' => $this->material,
            'thicknessMm' => $this->crystalThicknessMm,
            'angularSelectivityDeg' => $this->angularSelectivityDeg,
            'totalRecordedPages' => count($this->pages),
            'pages' => $pageSummary,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
