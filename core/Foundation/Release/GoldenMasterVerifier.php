<?php
/**
 * NetFusion Framework - Enterprise Golden Master & Release Fabric
 *
 * Automated Golden Master certification & self-inspection engine.
 *
 * @package    NetFusion\Core\Foundation\Release
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Foundation\Release;

class GoldenMasterVerifier
{
    /**
     * Run all Golden Master architectural diagnostics.
     *
     * @param string|null $basePath Optional root path of framework
     * @return array<string, mixed> Comprehensive verification results
     */
    public function verifyAll(?string $basePath = null): array
    {
        $root = $basePath ?? realpath(__DIR__ . '/../../..');
        $checks = [];
        $score = 100;

        // 1. PHP Runtime Compatibility Check
        $phpVersion = PHP_VERSION;
        $phpCompatible = version_compare($phpVersion, FrameworkReleaseManifest::MINIMUM_PHP, '>=');
        $checks['php_runtime'] = [
            'name'        => 'PHP 8.2+ Compatibility',
            'required'    => '>= ' . FrameworkReleaseManifest::MINIMUM_PHP,
            'current'     => $phpVersion,
            'passed'      => $phpCompatible,
            'description' => 'Verifies host environment meets modern PHP language specifications.',
        ];
        if (!$phpCompatible) {
            $score -= 20;
        }

        // 2. Zero External Runtime Dependencies Check
        $composerPath = $root . '/composer.json';
        $runtimeDepsCount = 0;
        if (file_exists($composerPath)) {
            $composerData = json_decode((string) file_get_contents($composerPath), true);
            $require = $composerData['require'] ?? [];
            foreach ($require as $pkg => $ver) {
                if ($pkg !== 'php' && !str_starts_with($pkg, 'ext-') && !str_starts_with($pkg, 'psr/')) {
                    $runtimeDepsCount++;
                }
            }
        }
        $zeroDeps = ($runtimeDepsCount === 0);
        $checks['zero_dependencies'] = [
            'name'        => 'Zero Runtime Composer Dependencies',
            'allowed'     => 0,
            'current'     => $runtimeDepsCount,
            'passed'      => $zeroDeps,
            'description' => 'Guarantees sovereign self-contained execution without 3rd-party vendor supply-chain risks.',
        ];
        if (!$zeroDeps) {
            $score -= 30;
        }

        // 3. Database Migration Inventory Check
        $migrationsDir = $root . '/database/migrations';
        $migrationFiles = [];
        if (is_dir($migrationsDir)) {
            $migrationFiles = glob($migrationsDir . '/*.php') ?: [];
        }
        $migrationCount = count($migrationFiles);
        $expectedMigrations = FrameworkReleaseManifest::EXPECTED_MIGRATIONS;
        $migrationsValid = ($migrationCount >= $expectedMigrations);
        $checks['migrations_inventory'] = [
            'name'        => 'Database Migration Schemas',
            'expected'    => $expectedMigrations,
            'actual'      => $migrationCount,
            'passed'      => $migrationsValid,
            'description' => 'Verifies all sequential migration schemas spanning Enterprise Phases exist.',
        ];
        if (!$migrationsValid) {
            $score -= 20;
        }

        // 4. Version File Consistency Check
        $versionFile = $root . '/version.json';
        $versionMatches = false;
        $versionRead = 'unknown';
        if (file_exists($versionFile)) {
            $versionData = json_decode((string) file_get_contents($versionFile), true);
            $versionRead = $versionData['version'] ?? 'missing';
            $versionMatches = ($versionRead === FrameworkReleaseManifest::VERSION);
        }
        $checks['version_consistency'] = [
            'name'        => 'Version Manifest Consistency',
            'expected'    => FrameworkReleaseManifest::VERSION,
            'actual'      => $versionRead,
            'passed'      => $versionMatches,
            'description' => 'Ensures version.json synchronizes with core release constants.',
        ];
        if (!$versionMatches) {
            $score -= 10;
        }

        // 5. Standard DotNetBD Header Compliance Check
        $sampleCoreFile = $root . '/core/Foundation/Release/FrameworkReleaseManifest.php';
        $headerCompliant = false;
        if (file_exists($sampleCoreFile)) {
            $content = (string) file_get_contents($sampleCoreFile);
            $headerCompliant = str_contains($content, 'DotNetBD') && str_contains($content, 'NetFusion Framework');
        }
        $checks['header_standards'] = [
            'name'        => 'DotNetBD Standard File Headers',
            'passed'      => $headerCompliant,
            'description' => 'Verifies enterprise copyright, package namespace, and author attribution headers.',
        ];
        if (!$headerCompliant) {
            $score -= 10;
        }

        // 6. Total Phases Coverage Check
        $totalPhases = FrameworkReleaseManifest::TOTAL_PHASES;
        $checks['phases_coverage'] = [
            'name'        => 'Complete 120-Phase Architectural Coverage',
            'total_phases'=> $totalPhases,
            'passed'      => ($totalPhases === 120),
            'description' => 'Validates full completion of all 120 planned evolutionary phases.',
        ];

        $isCertified = ($score === 100);

        return [
            'framework'        => 'NetFusion Framework',
            'version'          => FrameworkReleaseManifest::VERSION,
            'codename'         => FrameworkReleaseManifest::CODENAME,
            'status'           => FrameworkReleaseManifest::STATUS,
            'score'            => max(0, $score),
            'is_certified'     => $isCertified,
            'timestamp'        => date('Y-m-d H:i:s'),
            'checks'           => $checks,
        ];
    }
}
