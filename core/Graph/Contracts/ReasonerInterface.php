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

use NetFusion\Core\Graph\Models\Triple;

interface ReasonerInterface
{
    public function addTriple(Triple|string $subject, ?string $predicate = null, ?string $object = null): static;
    public function hasTriple(string $subject, string $predicate, string $object): bool;
    public function removeTriple(string $subject, string $predicate, string $object): bool;

    /**
     * Query semantic triples matching patterns with wildcards ('?' or null).
     *
     * @param string|null $subject
     * @param string|null $predicate
     * @param string|null $object
     * @return array<Triple>
     */
    public function queryTriples(?string $subject = null, ?string $predicate = null, ?string $object = null): array;

    /**
     * Run inference rules to deduct and materialize inferred triples.
     *
     * @return int Count of newly inferred triples
     */
    public function infer(): int;
}
