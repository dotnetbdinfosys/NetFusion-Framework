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

namespace NetFusion\Core\RuleEngine\DSL;

use NetFusion\Core\RuleEngine\Contracts\RuleInterface;

class RuleBuilder implements RuleInterface
{
    protected string $name;
    protected string $description = '';
    protected int $salience = 0;
    /** @var array<array{type: string, filter: callable|null, join: callable|null}> */
    protected array $patterns = [];
    /** @var callable|null */
    protected $action = null;
    protected bool $enabled = true;
    /** @var array<string, mixed> */
    protected array $metadata = [];

    public function __construct(string $name)
    {
        $this->name = trim($name);
    }

    public static function name(string $name): self
    {
        return new self($name);
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function salience(int $salience): self
    {
        $this->salience = $salience;
        return $this;
    }

    public function priority(int $priority): self
    {
        return $this->salience($priority);
    }

    public function enabled(bool $enabled = true): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function metadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    /**
     * Define the first pattern requirement for this rule.
     *
     * @param string $factType Fact entity type (e.g. 'Order', 'User')
     * @param callable|null $filter Intra-fact condition predicate: fn(FactInterface $fact): bool
     */
    public function when(string $factType, ?callable $filter = null): self
    {
        $this->patterns[] = [
            'type' => trim($factType),
            'filter' => $filter,
            'join' => null,
        ];
        return $this;
    }

    /**
     * Add a subsequent pattern requirement with optional join condition against previously matched facts.
     *
     * @param string $factType Fact entity type
     * @param callable|null $joinOrFilter If 2+ args, acts as join condition fn(Fact $current, Fact ...$previous): bool
     */
    public function and(string $factType, ?callable $joinOrFilter = null): self
    {
        $this->patterns[] = [
            'type' => trim($factType),
            'filter' => null,
            'join' => $joinOrFilter,
        ];
        return $this;
    }

    /**
     * Define the consequence/action callable to execute when conditions match.
     *
     * @param callable $action fn(Fact ...$facts, WorkingMemoryInterface $workingMemory, array $context): mixed
     */
    public function then(callable $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getSalience(): int
    {
        return $this->salience;
    }

    public function getPatterns(): array
    {
        return $this->patterns;
    }

    public function getAction(): ?callable
    {
        return $this->action;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'salience' => $this->salience,
            'patterns' => array_map(fn($p) => ['type' => $p['type']], $this->patterns),
            'enabled' => $this->enabled,
            'metadata' => $this->metadata,
        ];
    }
}
