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

interface PatternInterface
{
    /**
     * Get pattern root state name.
     */
    public function getName(): string;

    /**
     * Define strict contiguity (immediate successor event).
     */
    public function followedBy(string $name): self;

    /**
     * Define relaxed contiguity (non-immediate successor event, ignoring intermediate noise).
     */
    public function followedByAny(string $name): self;

    /**
     * Define negative match (event must NOT occur within the time window).
     */
    public function notFollowedBy(string $name): self;

    /**
     * Define predicate condition for current pattern state.
     */
    public function where(callable $condition): self;

    /**
     * Define OR predicate condition for current pattern state.
     */
    public function orWhere(callable $condition): self;

    /**
     * Specify occurrence count for current state.
     */
    public function times(int $count): self;

    /**
     * Specify one or more occurrences.
     */
    public function oneOrMore(): self;

    /**
     * Set max time window constraint for the pattern sequence in seconds.
     */
    public function within(float $seconds): self;

    /**
     * Get maximum window time in seconds.
     */
    public function getWindowSeconds(): ?float;

    /**
     * Convert pattern to state transition definition.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
