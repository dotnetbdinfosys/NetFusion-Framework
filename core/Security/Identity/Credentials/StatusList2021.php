<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Post-Quantum Decentralized Identity
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Security\Identity\Credentials;

use NetFusion\Core\Security\Identity\Exceptions\IdentityException;

class StatusList2021
{
    protected string $bitstring;
    protected int $length;

    /**
     * @param int $length Number of bits in status list (e.g. 100,000 or 1024)
     */
    public function __construct(int $length = 131072, ?string $encoded = null)
    {
        $this->length = $length;
        if ($encoded !== null) {
            $this->bitstring = $this->decode($encoded);
        } else {
            // Allocate zeroed byte array
            $bytes = (int) ceil($length / 8);
            $this->bitstring = str_repeat("\x00", $bytes);
        }
    }

    /**
     * Set status for a credential index (true = revoked, false = active)
     */
    public function setStatus(int $index, bool $revoked): void
    {
        if ($index < 0 || $index >= $this->length) {
            throw new IdentityException("Status list index {$index} out of bounds (max: {$this->length}).");
        }

        $bytePos = intdiv($index, 8);
        $bitPos = 7 - ($index % 8);

        $byteVal = ord($this->bitstring[$bytePos]);
        if ($revoked) {
            $byteVal |= (1 << $bitPos);
        } else {
            $byteVal &= ~(1 << $bitPos);
        }

        $this->bitstring[$bytePos] = chr($byteVal);
    }

    /**
     * Check if a credential at index is revoked
     */
    public function isRevoked(int $index): bool
    {
        if ($index < 0 || $index >= $this->length) {
            throw new IdentityException("Status list index {$index} out of bounds (max: {$this->length}).");
        }

        $bytePos = intdiv($index, 8);
        $bitPos = 7 - ($index % 8);

        $byteVal = ord($this->bitstring[$bytePos]);
        return ($byteVal & (1 << $bitPos)) !== 0;
    }

    /**
     * Export status list as base64 encoded gzip compressed payload
     */
    public function getEncodedList(): string
    {
        $compressed = gzencode($this->bitstring, 9);
        if ($compressed === false) {
            $compressed = $this->bitstring;
        }
        return base64_encode($compressed);
    }

    /**
     * Decode base64 gzip payload into raw bitstring
     */
    public function decode(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            throw new IdentityException("Invalid base64 encoding in status list.");
        }

        $decompressed = @gzdecode($raw);
        return $decompressed !== false ? $decompressed : $raw;
    }

    public function getLength(): int
    {
        return $this->length;
    }
}
