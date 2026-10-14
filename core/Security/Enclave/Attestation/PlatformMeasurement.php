<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Enclave/Attestation/PlatformMeasurement.php
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

namespace NetFusion\Core\Security\Enclave\Attestation;

/**
 * Platform Configuration Register (PCR) and Enclave Measurement Profile.
 */
class PlatformMeasurement
{
    /**
     * @param array<int, string> $pcrs PCR index => SHA-384 hex hash
     * @param string $mrEnclave SHA-384 measurement of initial code & data pages
     * @param string $mrSigner SHA-384 measurement of signing authority
     * @param int $isvProdId ISV Product ID
     * @param int $isvSvn ISV Security Version Number
     */
    public function __construct(
        private array $pcrs,
        private string $mrEnclave,
        private string $mrSigner,
        private int $isvProdId = 1,
        private int $isvSvn = 1
    ) {
    }

    public static function createDefault(string $codePayload = 'netfusion-core-enclave-v1.0'): self
    {
        $mrEnclave = hash('sha384', $codePayload);
        $mrSigner  = hash('sha384', 'DotNetBD-Master-Root-Authority');

        $pcrs = [];
        for ($i = 0; $i < 8; $i++) {
            $pcrs[$i] = hash('sha384', "pcr-{$i}-stage-{$codePayload}");
        }

        return new self($pcrs, $mrEnclave, $mrSigner, 1, 1);
    }

    /**
     * @return array<int, string>
     */
    public function getPcrs(): array
    {
        return $this->pcrs;
    }

    public function getMrEnclave(): string
    {
        return $this->mrEnclave;
    }

    public function getMrSigner(): string
    {
        return $this->mrSigner;
    }

    public function getIsvProdId(): int
    {
        return $this->isvProdId;
    }

    public function getIsvSvn(): int
    {
        return $this->isvSvn;
    }

    /**
     * Derive a unique composite digest representing the whole hardware measurement.
     */
    public function getDigest(): string
    {
        $payload = $this->mrEnclave . '|' . $this->mrSigner . '|' . $this->isvProdId . '|' . $this->isvSvn;
        foreach ($this->pcrs as $idx => $val) {
            $payload .= "|{$idx}:{$val}";
        }
        return hash('sha384', $payload);
    }

    public function matches(PlatformMeasurement $other): bool
    {
        return hash_equals($this->getDigest(), $other->getDigest());
    }

    public function toArray(): array
    {
        return [
            'mr_enclave' => $this->mrEnclave,
            'mr_signer'  => $this->mrSigner,
            'isv_prod_id'=> $this->isvProdId,
            'isv_svn'    => $this->isvSvn,
            'pcrs_count' => count($this->pcrs),
            'digest'     => $this->getDigest(),
        ];
    }
}
