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

namespace NetFusion\Core\Security\Identity\Bbs;

class BbsSignature
{
    /**
     * @param string[] $messages Array of message strings
     */
    public function __construct(
        protected string $issuerDid,
        protected array $messages,
        protected string $signatureValue,
        protected string $publicKeyHex
    ) {
    }

    public function getIssuerDid(): string
    {
        return $this->issuerDid;
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getSignatureValue(): string
    {
        return $this->signatureValue;
    }

    public function getPublicKeyHex(): string
    {
        return $this->publicKeyHex;
    }

    public function toArray(): array
    {
        return [
            'issuerDid' => $this->issuerDid,
            'messages' => $this->messages,
            'signatureValue' => $this->signatureValue,
            'publicKeyHex' => $this->publicKeyHex,
        ];
    }
}
