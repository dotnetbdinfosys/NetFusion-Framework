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

namespace NetFusion\Core\Auth\WebAuthn\Data;

use InvalidArgumentException;

class AuthenticatorData
{
    public const FLAG_UP = 0x01; // User Present
    public const FLAG_UV = 0x04; // User Verified
    public const FLAG_BE = 0x08; // Backup Eligibility
    public const FLAG_BS = 0x10; // Backup State
    public const FLAG_AT = 0x40; // Attested Credential Data Present
    public const FLAG_ED = 0x80; // Extension Data Present

    public function __construct(
        public readonly string $rpIdHash,
        public readonly int $flags,
        public readonly int $signCount,
        public readonly ?string $aaguid = null,
        public readonly ?string $credentialId = null,
        public readonly ?string $credentialPublicKeyRaw = null,
        public readonly ?array $extensions = null,
        public readonly string $raw = ''
    ) {
    }

    public function isUserPresent(): bool
    {
        return ($this->flags & self::FLAG_UP) !== 0;
    }

    public function isUserVerified(): bool
    {
        return ($this->flags & self::FLAG_UV) !== 0;
    }

    public function hasAttestedCredentialData(): bool
    {
        return ($this->flags & self::FLAG_AT) !== 0;
    }

    public function hasExtensionData(): bool
    {
        return ($this->flags & self::FLAG_ED) !== 0;
    }

    public static function parse(string $binary): self
    {
        if (strlen($binary) < 37) {
            throw new InvalidArgumentException('AuthenticatorData binary length must be at least 37 bytes.');
        }

        $rpIdHash = substr($binary, 0, 32);
        $flags = ord($binary[32]);
        $signCount = unpack('N', substr($binary, 33, 4))[1];

        $offset = 37;
        $aaguid = null;
        $credentialId = null;
        $credentialPublicKeyRaw = null;

        if (($flags & self::FLAG_AT) !== 0) {
            if (strlen($binary) < $offset + 18) {
                throw new InvalidArgumentException('Attested credential data is incomplete.');
            }

            $aaguid = substr($binary, $offset, 16);
            $offset += 16;

            $credIdLen = unpack('n', substr($binary, $offset, 2))[1];
            $offset += 2;

            if (strlen($binary) < $offset + $credIdLen) {
                throw new InvalidArgumentException('Credential ID length exceeds buffer.');
            }

            $credentialId = substr($binary, $offset, $credIdLen);
            $offset += $credIdLen;

            // Remainder at this point contains the COSE public key (and optional extensions)
            $credentialPublicKeyRaw = substr($binary, $offset);
        }

        return new self(
            rpIdHash: $rpIdHash,
            flags: $flags,
            signCount: $signCount,
            aaguid: $aaguid !== null ? bin2hex($aaguid) : null,
            credentialId: $credentialId,
            credentialPublicKeyRaw: $credentialPublicKeyRaw,
            extensions: null,
            raw: $binary
        );
    }
}
