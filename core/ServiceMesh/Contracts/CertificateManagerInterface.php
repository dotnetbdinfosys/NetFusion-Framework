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

namespace NetFusion\Core\ServiceMesh\Contracts;

use NetFusion\Core\ServiceMesh\Security\SpiffeId;

interface CertificateManagerInterface
{
    /**
     * Issue or retrieve an mTLS certificate for a given SPIFFE identity.
     *
     * @return array{cert: string, key: string, ca: string, expires_at: int, spiffe_id: string}
     */
    public function issueCertificate(SpiffeId $spiffeId, int $validitySeconds = 86400): array;

    /**
     * Verify an X.509 client certificate against the Mesh CA.
     *
     * @return array{valid: bool, spiffe_id: ?string, error: ?string}
     */
    public function verifyCertificate(string $pemCert): array;

    /**
     * Rotate certificate for an identity.
     */
    public function rotateCertificate(SpiffeId $spiffeId): array;

    /**
     * Get Mesh Root CA certificate PEM.
     */
    public function getRootCaPem(): string;
}
