<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Media\Rtp;

use InvalidArgumentException;
use RuntimeException;

/**
 * RFC 3711 Secure Real-time Transport Protocol (SRTP) Cryptographic Context.
 * Implements AES-CTR encryption and HMAC-SHA1-80 authentication tag protection.
 */
class SrtpContext
{
    public const AUTH_TAG_LENGTH = 10; // 80-bit auth tag

    protected string $masterKey;
    protected string $masterSalt;
    protected string $cipher;

    public function __construct(string $masterKey, string $masterSalt, string $cipher = 'aes-128-ctr')
    {
        if (strlen($masterKey) < 16) {
            throw new InvalidArgumentException("SRTP master key must be at least 16 bytes.");
        }
        if (strlen($masterSalt) < 14) {
            throw new InvalidArgumentException("SRTP master salt must be at least 14 bytes.");
        }

        $this->masterKey = substr($masterKey, 0, 16);
        $this->masterSalt = substr($masterSalt, 0, 14);
        $this->cipher = $cipher;
    }

    /**
     * Protect (encrypt & sign) an outgoing RTP packet.
     */
    public function protectRtp(RtpPacket $packet): string
    {
        $raw = $packet->serialize();
        $headerLen = 12 + ($packet->getCsrcCount() * 4) + ($packet->hasExtension() && $packet->getExtensionHeader() !== null ? strlen($packet->getExtensionHeader()) : 0);

        $header = substr($raw, 0, $headerLen);
        $payload = substr($raw, $headerLen);

        // Compute IV using SSRC, Sequence Number, and Master Salt
        $iv = $this->computeIv($packet->getSsrc(), $packet->getSequenceNumber());

        // Encrypt payload via AES-128-CTR
        $ciphertext = openssl_encrypt($payload, $this->cipher, $this->masterKey, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new RuntimeException("Failed to encrypt RTP payload via AES-CTR.");
        }

        $authenticatedPortion = $header . $ciphertext;

        // Compute 80-bit HMAC-SHA1 authentication tag
        $fullHmac = hash_hmac('sha1', $authenticatedPortion, $this->masterKey, true);
        $authTag = substr($fullHmac, 0, self::AUTH_TAG_LENGTH);

        return $authenticatedPortion . $authTag;
    }

    /**
     * Unprotect (verify & decrypt) an incoming SRTP packet buffer.
     *
     * @throws RuntimeException
     */
    public function unprotectRtp(string $srtpBuffer): RtpPacket
    {
        $bufferLen = strlen($srtpBuffer);
        if ($bufferLen < 12 + self::AUTH_TAG_LENGTH) {
            throw new InvalidArgumentException("SRTP buffer too short.");
        }

        $authenticatedLen = $bufferLen - self::AUTH_TAG_LENGTH;
        $authenticatedPortion = substr($srtpBuffer, 0, $authenticatedLen);
        $receivedTag = substr($srtpBuffer, $authenticatedLen);

        // Verify HMAC-SHA1 auth tag
        $expectedHmac = hash_hmac('sha1', $authenticatedPortion, $this->masterKey, true);
        $expectedTag = substr($expectedHmac, 0, self::AUTH_TAG_LENGTH);

        if (!hash_equals($expectedTag, $receivedTag)) {
            throw new RuntimeException("SRTP authentication tag verification failed (tampered packet).");
        }

        // Unpack header to obtain SSRC and sequence number
        $parsedHeader = unpack('Cbyte0/Cbyte1/nseq/Ntimestamp/Nssrc', substr($authenticatedPortion, 0, 12));
        $csrcCount = $parsedHeader['byte0'] & 0x0F;
        $hasExt = (bool)(($parsedHeader['byte0'] >> 4) & 0x01);

        $headerLen = 12 + ($csrcCount * 4);
        if ($hasExt && $authenticatedLen > $headerLen + 4) {
            $extMeta = unpack('nprofile/nlength', substr($authenticatedPortion, $headerLen, 4));
            $headerLen += 4 + ($extMeta['length'] * 4);
        }

        $header = substr($authenticatedPortion, 0, $headerLen);
        $ciphertext = substr($authenticatedPortion, $headerLen);

        $iv = $this->computeIv($parsedHeader['ssrc'], $parsedHeader['seq']);
        $plaintext = openssl_decrypt($ciphertext, $this->cipher, $this->masterKey, OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            throw new RuntimeException("Failed to decrypt SRTP payload.");
        }

        return RtpPacket::parse($header . $plaintext);
    }

    /**
     * Alias for protectRtp().
     */
    public function encryptRtp(RtpPacket $packet): string
    {
        return $this->protectRtp($packet);
    }

    /**
     * Alias for unprotectRtp().
     */
    public function decryptRtp(string $srtpBuffer): RtpPacket
    {
        return $this->unprotectRtp($srtpBuffer);
    }

    /**
     * Compute 16-byte initialization vector (IV) for AES-CTR.
     */
    protected function computeIv(int $ssrc, int $seq): string
    {
        // 16-byte IV = salt XOR (ssrc padded | seq padded)
        $iv = str_pad($this->masterSalt, 16, "\x00");
        $modifier = pack('NNNN', 0, $ssrc, 0, $seq & 0xFFFF);

        return $iv ^ $modifier;
    }
}
