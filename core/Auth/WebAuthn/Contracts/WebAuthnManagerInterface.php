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

namespace NetFusion\Core\Auth\WebAuthn\Contracts;

use NetFusion\Core\Auth\WebAuthn\Data\PublicKeyCredentialCreationOptions;
use NetFusion\Core\Auth\WebAuthn\Data\PublicKeyCredentialRequestOptions;
use NetFusion\Core\Auth\WebAuthn\Data\WebAuthnCredential;

interface WebAuthnManagerInterface
{
    /**
     * Generate registration challenge and creation options for a user.
     *
     * @param string|int $userId
     * @param string     $username
     * @param string     $displayName
     * @param array      $options
     * @return PublicKeyCredentialCreationOptions
     */
    public function generateRegisterOptions(
        string|int $userId,
        string $username,
        string $displayName,
        array $options = []
    ): PublicKeyCredentialCreationOptions;

    /**
     * Verify client registration response and store credential.
     *
     * @param array  $response Array containing clientDataJSON, attestationObject, etc.
     * @param string $expectedChallenge Base64url challenge string
     * @param array  $metadata Additional metadata (e.g. device name)
     * @return WebAuthnCredential
     */
    public function verifyRegistration(
        array $response,
        string $expectedChallenge,
        array $metadata = []
    ): WebAuthnCredential;

    /**
     * Generate authentication options / assertion challenge.
     *
     * @param string|int|null $userId If null, allows discoverable credentials (resident keys)
     * @param array           $options
     * @return PublicKeyCredentialRequestOptions
     */
    public function generateLoginOptions(
        string|int|null $userId = null,
        array $options = []
    ): PublicKeyCredentialRequestOptions;

    /**
     * Verify authentication response and update signature counter.
     *
     * @param array  $response Array containing id, clientDataJSON, authenticatorData, signature, userHandle
     * @param string $expectedChallenge
     * @return WebAuthnCredential
     */
    public function verifyAuthentication(
        array $response,
        string $expectedChallenge
    ): WebAuthnCredential;
}
