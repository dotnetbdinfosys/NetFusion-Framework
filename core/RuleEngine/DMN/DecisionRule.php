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

namespace NetFusion\Core\RuleEngine\DMN;

use JsonSerializable;

class DecisionRule implements JsonSerializable
{
    /** @var array<string> Array of FEEL input condition entries (one per InputClause) */
    protected array $inputEntries;
    /** @var array<mixed> Array of output values/expressions (one per OutputClause) */
    protected array $outputEntries;
    protected ?string $description;
    protected int $index = 0;

    /**
     * @param array<string> $inputEntries
     * @param array<mixed> $outputEntries
     * @param string|null $description
     */
    public function __construct(array $inputEntries, array $outputEntries, ?string $description = null)
    {
        $this->inputEntries = array_values($inputEntries);
        $this->outputEntries = array_values($outputEntries);
        $this->description = $description;
    }

    public static function make(array $inputEntries, array $outputEntries, ?string $description = null): self
    {
        return new self($inputEntries, $outputEntries, $description);
    }

    public function setIndex(int $index): self
    {
        $this->index = $index;
        return $this;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * @return array<string>
     */
    public function getInputEntries(): array
    {
        return $this->inputEntries;
    }

    /**
     * @return array<mixed>
     */
    public function getOutputEntries(): array
    {
        return $this->outputEntries;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function jsonSerialize(): array
    {
        return [
            'index' => $this->index,
            'inputEntries' => $this->inputEntries,
            'outputEntries' => $this->outputEntries,
            'description' => $this->description,
        ];
    }
}
