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

namespace NetFusion\Core\ZKP\Prover;

/**
 * Zero-Knowledge Proof artifact containing cryptographic group commitments / evaluations.
 */
class Proof
{
    private string $piA;
    private string $piB;
    private string $piC;
    private string $protocol;
    /** @var array<string, mixed> */
    private array $publicInputs;

    public function __construct(
        string $piA,
        string $piB,
        string $piC,
        array $publicInputs = [],
        string $protocol = 'groth16'
    ) {
        $this->piA          = $piA;
        $this->piB          = $piB;
        $this->piC          = $piC;
        $this->publicInputs = $publicInputs;
        $this->protocol     = $protocol;
    }

    public function getPiA(): string
    {
        return $this->piA;
    }

    public function getPiB(): string
    {
        return $this->piB;
    }

    public function getPiC(): string
    {
        return $this->piC;
    }

    public function getPublicInputs(): array
    {
        return $this->publicInputs;
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function toArray(): array
    {
        return [
            'pi_a'          => $this->piA,
            'pi_b'          => $this->piB,
            'pi_c'          => $this->piC,
            'public_inputs' => $this->publicInputs,
            'protocol'      => $this->protocol,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['pi_a'] ?? ''),
            (string)($data['pi_b'] ?? ''),
            (string)($data['pi_c'] ?? ''),
            (array)($data['public_inputs'] ?? []),
            (string)($data['protocol'] ?? 'groth16')
        );
    }
}
