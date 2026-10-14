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

namespace NetFusion\Core\Collaboration\WebRtc;

class RoomManager
{
    /**
     * Map: roomId => [peerId => ['metadata' => array, 'joined_at' => float]]
     *
     * @var array<string, array<string, array{metadata: array, joined_at: float}>>
     */
    protected array $rooms = [];

    /**
     * Join peer to a room.
     *
     * @return string[] List of other active peer IDs in the room.
     */
    public function join(string $roomId, string $peerId, array $metadata = []): array
    {
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [];
        }

        $existingPeers = array_keys($this->rooms[$roomId]);

        $this->rooms[$roomId][$peerId] = [
            'metadata' => $metadata,
            'joined_at' => microtime(true),
        ];

        return $existingPeers;
    }

    /**
     * Leave a room.
     *
     * @return bool True if peer was present and removed.
     */
    public function leave(string $roomId, string $peerId): bool
    {
        if (!isset($this->rooms[$roomId][$peerId])) {
            return false;
        }

        unset($this->rooms[$roomId][$peerId]);

        if (empty($this->rooms[$roomId])) {
            unset($this->rooms[$roomId]);
        }

        return true;
    }

    /**
     * Get all active peers in a room.
     *
     * @return string[]
     */
    public function getPeers(string $roomId): array
    {
        return isset($this->rooms[$roomId]) ? array_keys($this->rooms[$roomId]) : [];
    }

    /**
     * Check if a peer is in a room.
     */
    public function hasPeer(string $roomId, string $peerId): bool
    {
        return isset($this->rooms[$roomId][$peerId]);
    }

    /**
     * Get room member count.
     */
    public function count(string $roomId): int
    {
        return isset($this->rooms[$roomId]) ? count($this->rooms[$roomId]) : 0;
    }

    /**
     * Get list of all active room IDs.
     *
     * @return string[]
     */
    public function getRooms(): array
    {
        return array_keys($this->rooms);
    }
}
