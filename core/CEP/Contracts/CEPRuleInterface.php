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

namespace NetFusion\Core\CEP\Contracts;

interface CEPRuleInterface
{
    /**
     * Get unique rule name / identifier.
     */
    public function getName(): string;

    /**
     * Get human-readable rule description.
     */
    public function getDescription(): string;

    /**
     * Get source stream topic / event type.
     */
    public function getStreamName(): string;

    /**
     * Determine if rule is active.
     */
    public function isEnabled(): bool;

    /**
     * Enable or disable rule.
     */
    public function setEnabled(bool $enabled): self;

    /**
     * Ingest an incoming event into this rule pipeline.
     *
     * @return array<mixed> Generated actions/matches
     */
    public function process(EventInterface $event): array;

    /**
     * Get rule execution statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array;
}
