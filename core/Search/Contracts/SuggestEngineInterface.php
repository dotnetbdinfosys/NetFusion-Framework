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

use NetFusion\Core\Search\Models\Suggestion;

interface SuggestEngineInterface
{
    public function insert(string $phrase, float $weight = 1.0, array $payload = []): void;

    /**
     * @return array<Suggestion>
     */
    public function suggest(string $prefix, int $limit = 5): array;

    /**
     * @return array<string>
     */
    public function correct(string $word, int $maxDistance = 2): array;
}
