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

namespace NetFusion\Core\Gateway\Models;

use NetFusion\Core\Gateway\Contracts\UpstreamInterface;

/**
 * Pool of upstream servers associated with a route.
 * Provides filtering of healthy members.
 */
class UpstreamPool
{
    /** @var Upstream[] */
    private array $upstreams = [];

    public function __construct(private readonly string $name = 'default') {}

    public function getName(): string
    {
        return $this->name;
    }

    public function add(Upstream $upstream): self
    {
        $this->upstreams[$upstream->getId()] = $upstream;
        return $this;
    }

    /**
     * Returns all upstreams (healthy and unhealthy).
     *
     * @return Upstream[]
     */
    public function all(): array
    {
        return array_values($this->upstreams);
    }

    /**
     * Returns only healthy upstreams.
     *
     * @return Upstream[]
     */
    public function healthy(): array
    {
        return array_values(
            array_filter($this->upstreams, fn(Upstream $u) => $u->isHealthy())
        );
    }

    /**
     * Returns true if at least one upstream is healthy.
     */
    public function hasHealthy(): bool
    {
        return !empty($this->healthy());
    }

    public function count(): int
    {
        return count($this->upstreams);
    }

    public function healthyCount(): int
    {
        return count($this->healthy());
    }

    /**
     * Finds an upstream by its ID.
     */
    public function findById(string $id): ?Upstream
    {
        return $this->upstreams[$id] ?? null;
    }
}
