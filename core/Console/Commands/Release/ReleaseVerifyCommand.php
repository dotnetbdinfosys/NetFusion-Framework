<?php
/**
 * NetFusion Framework - Enterprise Golden Master & Release Fabric
 *
 * CLI Command: framework:verify
 *
 * Executes full Golden Master architectural self-inspection and prints certification summary.
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
use NetFusion\Core\Foundation\Release\GoldenMasterVerifier;

class ReleaseVerifyCommand extends Command
{
    protected string $name = 'framework:verify';
    protected string $description = 'Perform Golden Master architectural self-inspection and verification.';

    public function handle(): int
    {
        $verifier = new GoldenMasterVerifier();
        $result = $verifier->verifyAll();

        $this->line(FrameworkReleaseManifest::getBanner());
        $this->line('');
        $this->line('=== NetFusion Framework Golden Master Self-Inspection ===');
        $this->line(sprintf('Framework Version : %s (%s)', $result['version'], $result['status']));
        $this->line(sprintf('Release Codename  : %s', $result['codename']));
        $this->line(sprintf('Integrity Score   : %d / 100', $result['score']));
        $this->line(sprintf('Certification     : %s', $result['is_certified'] ? 'GOLDEN MASTER CERTIFIED [100% PASS]' : 'FAILED'));
        $this->line(str_repeat('-', 70));

        foreach ($result['checks'] as $key => $check) {
            $statusIcon = $check['passed'] ? '[PASS]' : '[FAIL]';
            $this->line(sprintf('  %-6s %-36s : %s', $statusIcon, $check['name'], $check['description']));
        }

        $this->line(str_repeat('-', 70));
        $this->line(sprintf('Golden Master Status: %s', $result['is_certified'] ? 'READY FOR PRODUCTION DEPLOYMENT' : 'ATTENTION REQUIRED'));
        $this->line('');

        return $result['is_certified'] ? 0 : 1;
    }
}
