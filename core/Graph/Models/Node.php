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

namespace NetFusion\Core\Graph\Models;

use NetFusion\Core\Graph\Contracts\NodeInterface;

class Node implements NodeInterface
{
    /**
     * @param string $id
     * @param array<string> $labels
     * @param array<string, mixed> $properties
     */
    public function __construct(
        protected string $id,
        protected array $labels = [],
        protected array $properties = []
    ) {
    }

    public static function create(string $id, array $labels = [], array $properties = []): self
    {
        return new self($id, $labels, $properties);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabels(): array
    {
        return array_values(array_unique($this->labels));
    }

    public function hasLabel(string $label): bool
    {
        return in_array($label, $this->labels, true);
    }

    public function addLabel(string $label): static
    {
        if (!$this->hasLabel($label)) {
            $this->labels[] = $label;
        }
        return $this;
    }

    public function removeLabel(string $label): static
    {
        $this->labels = array_values(array_filter($this->labels, fn($l) => $l !== $label));
        return $this;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getProperty(string $key, mixed $default = null): mixed
    {
        return $this->properties[$key] ?? $default;
    }

    public function setProperty(string $key, mixed $value): static
    {
        $this->properties[$key] = $value;
        return $this;
    }

    public function hasProperty(string $key): bool
    {
        return array_key_exists($key, $this->properties);
    }

    public function removeProperty(string $key): static
    {
        unset($this->properties[$key]);
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'labels' => $this->getLabels(),
            'properties' => $this->properties,
        ];
    }
}
