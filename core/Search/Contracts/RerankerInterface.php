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

namespace NetFusion\Core\Search\Contracts;

use NetFusion\Core\Search\Models\Document;
use NetFusion\Core\Search\Models\SearchQuery;

interface RerankerInterface
{
    /**
     * Rerank retrieved candidate documents using hybrid scoring formulas (BM25 + Dense Vector + Boosting).
     *
     * @param array<array{document: Document, score: float, lexical_score?: float, vector_score?: float}> $candidates
     * @param SearchQuery $query
     * @return array<array{document: Document, score: float, lexical_score?: float, vector_score?: float}>
     */
    public function rerank(array $candidates, SearchQuery $query): array;
}
