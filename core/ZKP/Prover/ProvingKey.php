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
 * Proving Key generated during circuit setup.
 */
class ProvingKey
{
    private string $circuitName;
    private array $data;

    public function __construct(string $circuitName, array $data = [])
    {
        $this->circuitName = $circuitName;
        $this->data        = $data;
    }

    public function getCircuitName(): string
    {
        return $this->circuitName;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function toArray(): array
    {
        return [
            'circuit_name' => $this->circuitName,
            'data'         => $this->data,
        ];
    }

    public static function fromArray(array $arr): self
    {
        return new self((string)($arr['circuit_name'] ?? ''), (array)($arr['data'] ?? []));
    }
}
