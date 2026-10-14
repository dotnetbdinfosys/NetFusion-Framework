<?php
/**
 * NetFusion Framework - Enterprise Topological Data Analysis (TDA) Engine
 *
 * Persistence interval [birth, death) representing lifespan of a topological feature.
 *
 * @package    NetFusion\Core\Analytics\Tda\Homology
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Analytics\Tda\Homology;

use NetFusion\Core\Analytics\Tda\Enums\HomologyDimension;

class PersistenceInterval
{
    /**
     * @param float $birth Filtration radius at which feature appears
     * @param float|null $death Filtration radius at which feature disappears (null = infinite)
     */
    public function __construct(
        private readonly HomologyDimension $dimension,
        private readonly float $birth,
        private readonly ?float $death = null
    ) {
    }

    public function getDimension(): HomologyDimension
    {
        return $this->dimension;
    }

    public function getBirth(): float
    {
        return $this->birth;
    }

    public function getDeath(): ?float
    {
        return $this->death;
    }

    public function isInfinite(): bool
    {
        return $this->death === null;
    }

    /**
     * Lifespan / persistence length (death - birth). Returns INF if infinite.
     */
    public function getLifespan(): float
    {
        if ($this->death === null) {
            return INF;
        }
        return max(0.0, $this->death - $this->birth);
    }

    /**
     * Checks if the feature is alive at filtration radius epsilon.
     */
    public function isAliveAt(float $epsilon): bool
    {
        if ($epsilon < $this->birth) {
            return false;
        }
        if ($this->death === null) {
            return true;
        }
        return $epsilon < $this->death;
    }

    /**
     * @return array{dimension: int, birth: float, death: float|null, lifespan: float, infinite: bool}
     */
    public function toArray(): array
    {
        return [
            'dimension' => $this->dimension->value,
            'birth'     => round($this->birth, 4),
            'death'     => ($this->death !== null) ? round($this->death, 4) : null,
            'lifespan'  => is_infinite($this->getLifespan()) ? INF : round($this->getLifespan(), 4),
            'infinite'  => $this->isInfinite(),
        ];
    }
}
