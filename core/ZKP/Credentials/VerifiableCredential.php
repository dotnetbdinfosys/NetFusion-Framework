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

namespace NetFusion\Core\ZKP\Credentials;

/**
 * Privacy-Preserving Verifiable Credential container with cryptographic attribute commitments.
 */
class VerifiableCredential
{
    private string $id;
    private string $issuer;
    private string $subject;
    /** @var array<string, mixed> */
    private array $claims;
    /** @var array<string, string> attributeName => commitment */
    private array $attributeCommitments = [];
    private float $issuedAt;

    public function __construct(
        string $id,
        string $issuer,
        string $subject,
        array $claims = [],
        ?float $issuedAt = null
    ) {
        $this->id       = $id;
        $this->issuer   = $issuer;
        $this->subject  = $subject;
        $this->claims   = $claims;
        $this->issuedAt = $issuedAt ?? microtime(true);

        $this->computeCommitments();
    }

    private function computeCommitments(): void
    {
        foreach ($this->claims as $key => $val) {
            $salt = hash('sha256', $this->id . ':' . $key);
            $valStr = is_scalar($val) ? (string)$val : json_encode($val);
            $this->attributeCommitments[$key] = hash('sha256', $salt . ':' . $valStr);
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getIssuer(): string
    {
        return $this->issuer;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getClaims(): array
    {
        return $this->claims;
    }

    public function getCommitment(string $attribute): ?string
    {
        return $this->attributeCommitments[$attribute] ?? null;
    }

    public function getAttributeCommitments(): array
    {
        return $this->attributeCommitments;
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'issuer'      => $this->issuer,
            'subject'     => $this->subject,
            'claims'      => $this->claims,
            'commitments' => $this->attributeCommitments,
            'issued_at'   => $this->issuedAt,
        ];
    }
}
