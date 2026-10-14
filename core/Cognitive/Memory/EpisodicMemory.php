<?php
/**
 * NetFusion Framework - Enterprise Cognitive Architecture
 *
 * Chronological autobiographical episodic memory traces and similarity queries.
 *
 * @package    NetFusion\Core\Cognitive\Memory
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Cognitive\Memory;

class EpisodicMemory
{
    /**
     * @var array<int, array{id: string, timestamp: float, context: array<string, mixed>, action: string, outcome: mixed, tags: string[]}>
     */
    private array $episodes = [];

    /**
     * Record a new temporal autobiographical event.
     *
     * @param array<string, mixed> $context
     * @param string[] $tags
     */
    public function record(string $id, array $context, string $action, mixed $outcome, array $tags = [], ?float $timestamp = null): void
    {
        $this->episodes[] = [
            'id'        => $id,
            'timestamp' => $timestamp ?? microtime(true),
            'context'   => $context,
            'action'    => $action,
            'outcome'   => $outcome,
            'tags'      => $tags,
        ];
    }

    public function count(): int
    {
        return count($this->episodes);
    }

    /**
     * Retrieve the most recent N episodes.
     *
     * @return array<int, array{id: string, timestamp: float, context: array<string, mixed>, action: string, outcome: mixed, tags: string[]}>
     */
    public function getRecent(int $limit = 5): array
    {
        return array_slice(array_reverse($this->episodes), 0, $limit);
    }

    /**
     * Find episodes matching specific tags or context keys.
     *
     * @param string[] $requiredTags
     * @param array<string, mixed> $contextMatches
     * @return array<int, array{id: string, timestamp: float, context: array<string, mixed>, action: string, outcome: mixed, tags: string[]}>
     */
    public function search(array $requiredTags = [], array $contextMatches = []): array
    {
        $results = [];

        foreach ($this->episodes as $ep) {
            // Check tags
            $hasAllTags = true;
            foreach ($requiredTags as $tag) {
                if (!in_array($tag, $ep['tags'], true)) {
                    $hasAllTags = false;
                    break;
                }
            }
            if (!$hasAllTags) {
                continue;
            }

            // Check context
            $matchesContext = true;
            foreach ($contextMatches as $k => $v) {
                if (!isset($ep['context'][$k]) || $ep['context'][$k] != $v) {
                    $matchesContext = false;
                    break;
                }
            }
            if (!$matchesContext) {
                continue;
            }

            $results[] = $ep;
        }

        return $results;
    }
}
