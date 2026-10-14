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

interface WorkingMemoryInterface
{
    /**
     * Assert a new fact into working memory.
     */
    public function assertFact(FactInterface $fact): FactInterface;

    /**
     * Retract an existing fact from working memory.
     */
    public function retractFact(FactInterface|string $factOrId): bool;

    /**
     * Modify an existing fact in working memory.
     *
     * @param array<string, mixed> $newAttributes
     */
    public function modifyFact(FactInterface|string $factOrId, array $newAttributes): FactInterface;

    /**
     * Get all active facts of a given type, or all facts if type is null.
     *
     * @return array<FactInterface>
     */
    public function getFacts(?string $type = null): array;

    /**
     * Find a single fact by ID.
     */
    public function getFactById(string $id): ?FactInterface;

    /**
     * Clear all facts from working memory.
     */
    public function clear(): void;

    /**
     * Get total count of facts in working memory.
     */
    public function count(): int;
}
