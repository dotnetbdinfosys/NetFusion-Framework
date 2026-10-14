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

namespace NetFusion\Core\Graph\Contracts;

interface EdgeInterface
{
    public function getId(): string;
    public function getSourceId(): string;
    public function getTargetId(): string;
    public function getType(): string;
    public function isDirected(): bool;
    public function getWeight(): float;
    public function setWeight(float $weight): static;
    public function getProperties(): array;
    public function getProperty(string $key, mixed $default = null): mixed;
    public function setProperty(string $key, mixed $value): static;
    public function hasProperty(string $key): bool;
    public function removeProperty(string $key): static;
    public function toArray(): array;
}
