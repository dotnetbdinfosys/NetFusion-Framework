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

namespace NetFusion\Core\Workflow\Definition;

class SequenceFlow
{
    protected string $id;
    protected string $sourceRef;
    protected string $targetRef;
    protected ?string $condition;
    protected ?string $name;
    protected bool $isDefault;

    public function __construct(
        string $id,
        string $sourceRef,
        string $targetRef,
        ?string $condition = null,
        ?string $name = null,
        bool $isDefault = false
    ) {
        $this->id = $id;
        $this->sourceRef = $sourceRef;
        $this->targetRef = $targetRef;
        $this->condition = $condition;
        $this->name = $name;
        $this->isDefault = $isDefault;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSourceRef(): string
    {
        return $this->sourceRef;
    }

    public function getTargetRef(): string
    {
        return $this->targetRef;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    /**
     * Evaluate condition against context variables.
     *
     * @param array<string, mixed> $variables
     * @return bool
     */
    public function evaluateCondition(array $variables): bool
    {
        if ($this->condition === null || trim($this->condition) === '') {
            return true;
        }

        // Support simple expression syntax e.g. "total > 100", "status == 'approved'", "is_vip == true"
        $expr = trim($this->condition);

        // Simple variable substitution and evaluation in a safe scope
        try {
            // Check basic comparison operators: ==, !=, >, <, >=, <=, in, ===
            foreach ($variables as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $jsonVal = json_encode($value);
                    $pattern = '/\b' . preg_quote($key, '/') . '\b/';
                    $expr = (string) preg_replace($pattern, (string) $jsonVal, $expr);
                }
            }

            // Quick safe boolean evaluation
            $result = @eval('return (' . $expr . ');');
            return (bool) $result;
        } catch (\Throwable) {
            return false;
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sourceRef' => $this->sourceRef,
            'targetRef' => $this->targetRef,
            'condition' => $this->condition,
            'name' => $this->name,
            'isDefault' => $this->isDefault,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? uniqid('flow_', true),
            $data['sourceRef'] ?? '',
            $data['targetRef'] ?? '',
            $data['condition'] ?? null,
            $data['name'] ?? null,
            (bool) ($data['isDefault'] ?? false)
        );
    }
}
