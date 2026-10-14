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
 * Verifying Key corresponding to a circuit and trusted setup.
 */
class VerifyingKey
{
    private string $circuitName;
    private array $data;
    private array $publicSignals;

    public function __construct(string $circuitName, array $data = [], array $publicSignals = [])
    {
        $this->circuitName   = $circuitName;
        $this->data          = $data;
        $this->publicSignals = $publicSignals;
    }

    public function getCircuitName(): string
    {
        return $this->circuitName;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getPublicSignals(): array
    {
        return $this->publicSignals;
    }

    public function toArray(): array
    {
        return [
            'circuit_name'   => $this->circuitName,
            'data'           => $this->data,
            'public_signals' => $this->publicSignals,
        ];
    }

    public static function fromArray(array $arr): self
    {
        return new self(
            (string)($arr['circuit_name'] ?? ''),
            (array)($arr['data'] ?? []),
            (array)($arr['public_signals'] ?? [])
        );
    }
}
