<?php
/**
 * NetFusion Framework - Enterprise Astro-Informatics & Celestial Mechanics Fabric
 *
 * Astronomical celestial body classifications.
 *
 * @package    NetFusion\Core\Astro\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Astro\Enums;

enum CelestialBodyType: string
{
    case STAR       = 'star';
    case PLANET     = 'planet';
    case MOON       = 'moon';
    case ASTEROID   = 'asteroid';
    case COMET      = 'comet';
    case SPACECRAFT = 'spacecraft';

    public function isMajorGravitationalSource(): bool
    {
        return match ($this) {
            self::STAR, self::PLANET => true,
            default => false,
        };
    }
}
