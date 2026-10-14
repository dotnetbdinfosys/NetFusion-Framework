<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
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

namespace NetFusion\Core\IaC\Ast;

use JsonSerializable;

/**
 * Abstract Syntax Tree (AST) node representing an HCL declarative configuration block.
 */
class AstBlock implements JsonSerializable
{
    /**
     * @param BlockType $blockType
     * @param string $type
     * @param string $name
     * @param array<string, mixed> $attributes
     * @param array<AstBlock> $nestedBlocks
     * @param array<string> $labels
     * @param int $line
     */
    public function __construct(
        public readonly BlockType $blockType,
        public readonly string $type,
        public readonly string $name,
        public readonly array $attributes = [],
        public readonly array $nestedBlocks = [],
        public readonly array $labels = [],
        public readonly int $line = 1
    ) {
    }

    /**
     * Compute canonical resource or object address (e.g. "aws_instance.web").
     */
    public function getAddress(): string
    {
        return match ($this->blockType) {
            BlockType::RESOURCE => "{$this->type}.{$this->name}",
            BlockType::DATA => "data.{$this->type}.{$this->name}",
            BlockType::VARIABLE => "var.{$this->name}",
            BlockType::OUTPUT => "output.{$this->name}",
            BlockType::PROVIDER => "provider.{$this->name}",
            BlockType::LOCALS => "local.{$this->name}",
            BlockType::MODULE => "module.{$this->name}",
            default => "{$this->blockType->value}.{$this->name}",
        };
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Extract all referenced resource addresses and variable dependencies from attributes.
     * Searches for patterns like `aws_vpc.main.id`, `${aws_instance.web.id}`, `var.environment`.
     *
     * @return array<string>
     */
    public function getDependencies(): array
    {
        $deps = [];
        $explicitDependsOn = $this->getAttribute('depends_on');
        if (is_array($explicitDependsOn)) {
            foreach ($explicitDependsOn as $dep) {
                if (is_string($dep)) {
                    $deps[] = trim($dep, " \t\n\r\0\x0B\"'");
                }
            }
        }

        $this->extractReferencesRecursive($this->attributes, $deps);
        return array_values(array_unique($deps));
    }

    private function extractReferencesRecursive(mixed $value, array &$deps): void
    {
        if (is_string($value)) {
            // Match patterns like aws_instance.web.id or var.env or module.vpc.vpc_id
            if (preg_match_all('/(?:(?:\$\{)?([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)+)(?:\})?)/', $value, $matches)) {
                foreach ($matches[1] as $match) {
                    $parts = explode('.', $match);
                    if (count($parts) >= 2) {
                        // Keep resource address (type.name)
                        $depAddress = $parts[0] . '.' . $parts[1];
                        if ($depAddress !== $this->getAddress()) {
                            $deps[] = $depAddress;
                        }
                    }
                }
            }
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                $this->extractReferencesRecursive($item, $deps);
            }
        }
    }

    public function toArray(): array
    {
        return [
            'block_type' => $this->blockType->value,
            'type' => $this->type,
            'name' => $this->name,
            'address' => $this->getAddress(),
            'attributes' => $this->attributes,
            'nested_blocks' => array_map(fn(AstBlock $b) => $b->toArray(), $this->nestedBlocks),
            'labels' => $this->labels,
            'line' => $this->line,
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
