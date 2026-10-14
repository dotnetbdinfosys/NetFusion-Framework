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

namespace NetFusion\Core\RuleEngine\Contracts;

use JsonSerializable;

interface RuleInterface extends JsonSerializable
{
    /**
     * Get unique name of the rule.
     */
    public function getName(): string;

    /**
     * Get rule description or business intent.
     */
    public function getDescription(): string;

    /**
     * Get rule salience/priority (higher integer = higher priority).
     */
    public function getSalience(): int;

    /**
     * Get condition patterns (required fact types and filters).
     *
     * @return array<array{type: string, filter: callable|null, join: callable|null}>
     */
    public function getPatterns(): array;

    /**
     * Get the action/consequence callable to execute when conditions match.
     */
    public function getAction(): ?callable;

    /**
     * Check if the rule is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Get rule metadata/tags.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;
}
