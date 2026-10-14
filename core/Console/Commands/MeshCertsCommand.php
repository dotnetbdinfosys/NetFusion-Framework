<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\ServiceMesh\Security\SpiffeId;
use NetFusion\Core\ServiceMesh\ServiceMeshManager;

class MeshCertsCommand extends Command
{
    protected string $name = 'mesh:certs';
    protected string $signature = 'mesh:certs {--service= : Issue or inspect certificate for service} {--issue : Force issue new certificate}';
    protected string $description = 'Inspect, issue, and verify mTLS X.509 certificates and SPIFFE identities for mesh workloads';

    public function handle(): int
    {
        /** @var ServiceMeshManager $manager */
        $manager = function_exists('mesh') ? mesh() : (function_exists('app') && app()->has('mesh') ? app('mesh') : new ServiceMeshManager());
        $certMgr = $manager->getCertificateManager();

        $service = $this->option('service') ? (string) $this->option('service') : 'api-gateway';
        $forceIssue = (bool) $this->option('issue');

        $spiffeId = SpiffeId::of($service, 'default', $manager->getTrustDomain());

        $this->info("==========================================================================================");
        $this->info("               Zero-Trust Mesh: mTLS X.509 Certificate & SPIFFE Identity");
        $this->info("==========================================================================================");

        $certData = $forceIssue
            ? $certMgr->rotateCertificate($spiffeId)
            : $certMgr->issueCertificate($spiffeId);

        $verified = $certMgr->verifyCertificate($certData['cert']);

        $this->line("SPIFFE ID       : <info>{$spiffeId->toString()}</info>");
        $this->line("Trust Domain    : {$spiffeId->getTrustDomain()}");
        $this->line("Namespace       : {$spiffeId->getNamespace()}");
        $this->line("Service Account : {$spiffeId->getServiceAccount()}");
        $this->line("Status          : " . ($verified['valid'] ? '<info>VALID & TRUSTED</info>' : '<error>INVALID</error>'));
        $this->line("Expires At      : " . date('Y-m-d H:i:s', $certData['expires_at']));
        $this->line("------------------------------------------------------------------------------------------");
        $this->line("Public Certificate PEM:");
        $this->line(trim($certData['cert']));

        return 0;
    }
}
