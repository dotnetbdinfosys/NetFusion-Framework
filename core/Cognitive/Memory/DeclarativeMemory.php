<?php
/**
 * NetFusion Framework - Enterprise Cognitive Architecture
 *
 * Declarative memory module managing chunk storage, spreading activation, and threshold retrieval.
 *
 * @package    NetFusion\Core\Cognitive\Memory
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Cognitive\Memory;

use NetFusion\Core\Cognitive\Exceptions\ChunkRetrievalException;

class DeclarativeMemory
{
    /**
     * @var array<string, Chunk>
     */
    private array $chunks = [];

    /**
     * @param float $retrievalThreshold Minimum activation required to retrieve (tau)
     * @param float $activationNoise Transient noise standard deviation (s)
     */
    public function __construct(
        private readonly float $retrievalThreshold = -10.0,
        private readonly float $activationNoise = 0.0
    ) {
    }

    public function addChunk(Chunk $chunk): void
    {
        $this->chunks[$chunk->getId()] = $chunk;
    }

    public function getChunk(string $id): ?Chunk
    {
        return $this->chunks[$id] ?? null;
    }

    public function hasChunk(string $id): bool
    {
        return isset($this->chunks[$id]);
    }

    public function count(): int
    {
        return count($this->chunks);
    }

    /**
     * Retrieve the highest-activation chunk matching the query slots.
     *
     * @param array<string, mixed> $query
     * @param float|null $currentTime
     * @throws ChunkRetrievalException
     */
    public function retrieve(array $query = [], ?float $currentTime = null): Chunk
    {
        $bestChunk = null;
        $highestActivation = -INF;

        foreach ($this->chunks as $chunk) {
            if ($chunk->matchScore($query) > 0.0) {
                $act = $chunk->getBaseLevelActivation($currentTime);
                if ($this->activationNoise > 0.0) {
                    $noise = (mt_rand(-1000, 1000) / 1000.0) * $this->activationNoise;
                    $act += $noise;
                }

                if ($act > $highestActivation) {
                    $highestActivation = $act;
                    $bestChunk = $chunk;
                }
            }
        }

        if ($bestChunk === null) {
            throw new ChunkRetrievalException('No declarative memory chunk matched the query pattern.');
        }

        if ($highestActivation < $this->retrievalThreshold) {
            throw new ChunkRetrievalException("Best matching chunk [{$bestChunk->getId()}] activation ({$highestActivation}) is below threshold ({$this->retrievalThreshold}).");
        }

        // Rehearse/touch chunk on successful retrieval
        $bestChunk->touch($currentTime);

        return $bestChunk;
    }

    /**
     * Retrieve all chunks matching a query.
     *
     * @param array<string, mixed> $query
     * @return Chunk[]
     */
    public function queryAll(array $query = []): array
    {
        $matches = [];
        foreach ($this->chunks as $chunk) {
            if ($chunk->matchScore($query) > 0.0) {
                $matches[] = $chunk;
            }
        }
        return $matches;
    }
}
