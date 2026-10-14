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

namespace NetFusion\Core\Vector\Contracts;

use NetFusion\Core\Vector\RAG\RAGResponse;

interface RAGPipelineInterface
{
    /**
     * Answer a natural language query using retrieval augmented generation.
     *
     * @param string $query User natural language query
     * @param string $collectionName Knowledge collection to query
     * @param array<string, mixed> $options Pipeline options (top_k, hybrid, alpha, filter, temperature)
     */
    public function query(string $query, string $collectionName, array $options = []): RAGResponse;
}
