<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;

/**
 * Class MeshCertCommand
 *
 * CLI: php netfusion mesh:cert [--service=<service>] [--rotate]
 */
class MeshCertCommand extends Command
{
    protected string $name        = 'mesh:cert';
    protected string $description = 'Inspect, issue, or rotate mTLS X.509 SVID certificates for mesh workloads';

    public function handle(array $args = []): int
    {
        $serviceName = $this->getOption($args, 'service', 'app');
        $shouldRotate = in_array('--rotate', $args, true);

        try {
            $mesh = service_mesh();
        } catch (\Throwable $e) {
            $this->error("Service mesh unavailable: " . $e->getMessage());
            return 1;
        }

        if ($shouldRotate) {
            $newCert = $mesh->getRotator()->checkAndRotate($serviceName);
            echo "Certificate for service '{$serviceName}' rotated successfully.\n";
            echo "SPIFFE ID: " . $newCert->getSpiffeId() . "\n";
            echo "Expires At: " . date('Y-m-d H:i:s', $newCert->getExpiresAt()) . "\n";
            return 0;
        }

        $cert = $mesh->getRotator()->getCertificate($serviceName);
        if ($cert === null) {
            $cert = $mesh->registerService($serviceName);
            echo "New X.509 SVID certificate issued for '{$serviceName}'.\n";
        }

        echo "\n=== Mesh Workload Certificate ({$serviceName}) ===\n";
        echo "SPIFFE ID: " . $cert->getSpiffeId() . "\n";
        echo "Expires: " . date('Y-m-d H:i:s', $cert->getExpiresAt()) . "\n";
        echo "Status: " . ($cert->isExpired() ? "EXPIRED" : "VALID") . "\n\n";

        return 0;
    }

    private function getOption(array $args, string $key, string $default = ''): string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$key}=")) {
                return substr($arg, strlen("--{$key}="));
            }
        }
        return $default;
    }
}
