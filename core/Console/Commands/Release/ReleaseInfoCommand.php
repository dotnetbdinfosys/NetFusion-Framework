<?php
/**
 * NetFusion Framework - Enterprise Golden Master & Release Fabric
 *
 * CLI Command: framework:info
 *
 * Displays framework version metadata, release manifest, and module category inventory.
 *
 * @package    NetFusion\Core\Console\Commands\Release
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Release;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Foundation\Release\FrameworkReleaseManifest;

class ReleaseInfoCommand extends Command
{
    protected string $name = 'framework:info';
    protected string $description = 'Display NetFusion Framework version metadata and module catalog.';

    public function handle(): int
    {
        $info = FrameworkReleaseManifest::getReleaseInfo();

        $this->line(FrameworkReleaseManifest::getBanner());
        $this->line('');
        $this->line('=== Framework Release Metadata ===');
        $this->line(sprintf('Framework Name   : %s', $info['name']));
        $this->line(sprintf('Official Version : %s (%s)', $info['version'], $info['status']));
        $this->line(sprintf('Release Codename : %s', $info['codename']));
        $this->line(sprintf('Release Date     : %s', $info['release_date']));
        $this->line(sprintf('Author & Company : %s | %s', $info['author'], $info['company']));
        $this->line(sprintf('License Type     : %s License', $info['license']));
        $this->line(sprintf('PHP Specification: Host PHP %s (Minimum %s)', $info['current_php'], $info['minimum_php']));
        $this->line(sprintf('External Runtime : %d Dependencies (Pure Sovereign PHP Architecture)', $info['runtime_external_dependencies']));
        $this->line(sprintf('Total Milestones : %d Completed Phases', $info['total_phases']));
        $this->line(sprintf('Database Schemas : %d Migration Files', $info['expected_migrations']));
        $this->line(str_repeat('-', 70));
        $this->line('=== Module Categories (All 120 Phases) ===');

        foreach ($info['module_catalog'] as $category => $phases) {
            $this->line(sprintf('  [%s]', $category));
            foreach ($phases as $phaseRange => $desc) {
                $this->line(sprintf('    * %-14s: %s', $phaseRange, $desc));
            }
        }

        $this->line(str_repeat('-', 70));
        $this->line('');

        return 0;
    }
}
