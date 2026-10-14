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

namespace NetFusion\Core\Search\Suggest;

use NetFusion\Core\Search\Contracts\SuggestEngineInterface;
use NetFusion\Core\Search\Models\Suggestion;

class TrieSuggestEngine implements SuggestEngineInterface
{
    /**
     * Trie Root Node
     * Node structure: [
     *   'children' => [char => Node],
     *   'is_terminal' => bool,
     *   'phrase' => string|null,
     *   'weight' => float,
     *   'payload' => array
     * ]
     */
    protected array $root = [
        'children' => [],
        'is_terminal' => false,
        'phrase' => null,
        'weight' => 0.0,
        'payload' => [],
    ];

    /**
     * @var array<string, float> Dictionary for spell checking: word => frequency
     */
    protected array $vocabulary = [];

    public function insert(string $phrase, float $weight = 1.0, array $payload = []): void
    {
        $phrase = trim($phrase);
        if ($phrase === '') {
            return;
        }

        $lower = mb_strtolower($phrase, 'UTF-8');
        $this->vocabulary[$lower] = ($this->vocabulary[$lower] ?? 0.0) + $weight;

        $node = &$this->root;
        $len = mb_strlen($lower, 'UTF-8');

        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($lower, $i, 1, 'UTF-8');
            if (!isset($node['children'][$char])) {
                $node['children'][$char] = [
                    'children' => [],
                    'is_terminal' => false,
                    'phrase' => null,
                    'weight' => 0.0,
                    'payload' => [],
                ];
            }
            $node = &$node['children'][$char];
        }

        $node['is_terminal'] = true;
        $node['phrase'] = $phrase;
        $node['weight'] = max($node['weight'], $weight);
        $node['payload'] = array_merge($node['payload'], $payload);
    }

    /**
     * Suggest completions matching the given prefix.
     *
     * @return array<Suggestion>
     */
    public function suggest(string $prefix, int $limit = 5): array
    {
        $prefix = trim($prefix);
        if ($prefix === '') {
            return [];
        }

        $lower = mb_strtolower($prefix, 'UTF-8');
        $node = $this->root;
        $len = mb_strlen($lower, 'UTF-8');

        // Traverse to the prefix node
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($lower, $i, 1, 'UTF-8');
            if (!isset($node['children'][$char])) {
                // If exact prefix not found, try fuzzy spell fallback
                $corrections = $this->correct($lower, 2);
                if (!empty($corrections)) {
                    return $this->suggest($corrections[0], $limit);
                }
                return [];
            }
            $node = $node['children'][$char];
        }

        // Collect all terminal phrases from this sub-trie
        $candidates = [];
        $this->collectTerminals($node, $candidates);

        // Sort by weight descending
        usort($candidates, fn($a, $b) => $b['weight'] <=> $a['weight']);

        $suggestions = [];
        $sliced = array_slice($candidates, 0, $limit);

        foreach ($sliced as $c) {
            $phrase = $c['phrase'];
            $highlighted = $this->highlightPrefix($phrase, $prefix);
            $suggestions[] = new Suggestion(
                text: $phrase,
                score: $c['weight'],
                payload: $c['payload'],
                highlighted: $highlighted
            );
        }

        return $suggestions;
    }

    /**
     * Find nearest dictionary terms within Levenshtein edit distance.
     *
     * @return array<string>
     */
    public function correct(string $word, int $maxDistance = 2): array
    {
        $word = mb_strtolower(trim($word), 'UTF-8');
        if ($word === '' || empty($this->vocabulary)) {
            return [];
        }

        $candidates = [];
        foreach ($this->vocabulary as $dictWord => $freq) {
            $dist = levenshtein($word, $dictWord);
            if ($dist <= $maxDistance) {
                $candidates[$dictWord] = [
                    'distance' => $dist,
                    'frequency' => $freq,
                ];
            }
        }

        // Sort primarily by lowest distance, then highest frequency
        uasort($candidates, function ($a, $b) {
            if ($a['distance'] !== $b['distance']) {
                return $a['distance'] <=> $b['distance'];
            }
            return $b['frequency'] <=> $a['frequency'];
        });

        return array_keys($candidates);
    }

    protected function collectTerminals(array $node, array &$results): void
    {
        if ($node['is_terminal'] && $node['phrase'] !== null) {
            $results[] = [
                'phrase' => $node['phrase'],
                'weight' => $node['weight'],
                'payload' => $node['payload'],
            ];
        }

        foreach ($node['children'] as $child) {
            $this->collectTerminals($child, $results);
        }
    }

    protected function highlightPrefix(string $phrase, string $prefix): string
    {
        $pLen = mb_strlen($prefix, 'UTF-8');
        if (mb_stripos($phrase, $prefix, 0, 'UTF-8') === 0) {
            $matchPart = mb_substr($phrase, 0, $pLen, 'UTF-8');
            $rest = mb_substr($phrase, $pLen, null, 'UTF-8');
            return "<strong>" . htmlspecialchars($matchPart) . "</strong>" . htmlspecialchars($rest);
        }
        return htmlspecialchars($phrase);
    }
}
