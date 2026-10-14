<?php
/**
 * NetFusion Framework - Enterprise Astro-Informatics CLI
 *
 * Command to calculate Keplerian orbital parameters, ephemeris, and 3D Cartesian state vectors.
 *
 * @package    NetFusion\Core\Console\Commands\Astro
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Astro;

use NetFusion\Core\Astro\Orbit\KeplerianElements;
use NetFusion\Core\Console\Command;

class AstroOrbitCommand extends Command
{
    protected string $name = 'astro:orbit';

    protected string $signature = 'astro:orbit {a? : Semi-major axis in AU} {--e=0.0167 : Eccentricity} {--i=0.0 : Inclination in deg} {--nu=0.0 : True anomaly in deg}';

    protected string $description = 'Compute Keplerian orbital mechanics and 3D Cartesian state vectors';

    public function handle(): int
    {
        $manager = astro();

        $aAu = (float) ($this->argument(0) ?: 1.0);
        $e   = (float) ($this->option('e') ?: 0.0167);
        $iDeg = (float) ($this->option('i') ?: 0.0);
        $nuDeg = (float) ($this->option('nu') ?: 0.0);

        $aMeters = $aAu * KeplerianElements::AU_METERS;
        $iRad = deg2rad($iDeg);
        $nuRad = deg2rad($nuDeg);

        $elements = new KeplerianElements(
            $aMeters,
            $e,
            $iRad,
            0.0,
            0.0,
            $nuRad,
            KeplerianElements::MU_SUN
        );

        $cartesian = $manager->getOrbitalMechanics()->elementsToCartesian($elements);
        $pos = $cartesian['position'];
        $vel = $cartesian['velocity'];

        $speed = sqrt(($vel['x'] * $vel['x']) + ($vel['y'] * $vel['y']) + ($vel['z'] * $vel['z']));
        $distAu = sqrt(($pos['x'] * $pos['x']) + ($pos['y'] * $pos['y']) + ($pos['z'] * $pos['z'])) / KeplerianElements::AU_METERS;
        $periodDays = $elements->getPeriod() / 86400.0;

        $this->info("=== Keplerian Orbit Ephemeris Analysis ===");
        $this->line(sprintf("Semi-Major Axis   : %.4f AU (%.3e m)", $aAu, $aMeters));
        $this->line(sprintf("Eccentricity (e)  : %.6f", $e));
        $this->line(sprintf("Inclination (i)   : %.2f deg (%.4f rad)", $iDeg, $iRad));
        $this->line(sprintf("Orbital Period    : %.2f days (%.3f years)", $periodDays, $periodDays / 365.25));
        $this->line(sprintf("Periapsis Distance: %.4f AU", $elements->getPeriapsis() / KeplerianElements::AU_METERS));
        $this->line(sprintf("Apoapsis Distance : %.4f AU", $elements->getApoapsis() / KeplerianElements::AU_METERS));
        $this->line("");
        $this->info("=== Instantaneous Cartesian State Vector (Heliocentric) ===");
        $this->line(sprintf("Position [x, y, z]: [%.4e, %.4e, %.4e] m (Dist: %.4f AU)", $pos['x'], $pos['y'], $pos['z'], $distAu));
        $this->line(sprintf("Velocity [vx,vy,vz]: [%.2f, %.2f, %.2f] m/s (Speed: %.2f km/s)", $vel['x'], $vel['y'], $vel['z'], $speed / 1000.0));

        return 0;
    }
}
