<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Collaboration\WebRTC;

/**
 * Class PeerMesh
 *
 * Tracks full-mesh WebRTC P2P DataChannel topology, latency, and connection states.
 */
class PeerMesh
{
    /** @var array<string, bool> */
    private array $peers = [];

    /** @var array<string, array{state: string, rttMs: float, updatedAt: float}> */
    private array $channels = [];

    public function addPeer(string $peerId): void
    {
        $this->peers[$peerId] = true;
    }

    public function removePeer(string $peerId): void
    {
        unset($this->peers[$peerId]);

        foreach (array_keys($this->channels) as $edgeKey) {
            [$a, $b] = explode('::', $edgeKey);
            if ($a === $peerId || $b === $peerId) {
                unset($this->channels[$edgeKey]);
            }
        }
    }

    public function setChannelState(string $peerA, string $peerB, string $state, float $rttMs = 0.0): void
    {
        $this->addPeer($peerA);
        $this->addPeer($peerB);

        $edge = $this->makeEdgeKey($peerA, $peerB);
        $this->channels[$edge] = [
            'state' => $state,
            'rttMs' => $rttMs,
            'updatedAt' => microtime(true),
        ];
    }

    public function getChannelState(string $peerA, string $peerB): ?string
    {
        $edge = $this->makeEdgeKey($peerA, $peerB);
        return $this->channels[$edge]['state'] ?? null;
    }

    public function getConnectedPeersFor(string $peerId): array
    {
        $connected = [];
        foreach ($this->channels as $edge => $info) {
            if ($info['state'] === 'connected') {
                [$a, $b] = explode('::', $edge);
                if ($a === $peerId) {
                    $connected[] = $b;
                } elseif ($b === $peerId) {
                    $connected[] = $a;
                }
            }
        }
        return array_values(array_unique($connected));
    }

    public function getMeshMetrics(): array
    {
        $peerCount = count($this->peers);
        $theoreticalEdges = $peerCount > 1 ? ($peerCount * ($peerCount - 1)) / 2 : 0;
        $activeChannels = 0;
        $totalRtt = 0.0;

        foreach ($this->channels as $chan) {
            if ($chan['state'] === 'connected') {
                $activeChannels++;
                $totalRtt += $chan['rttMs'];
            }
        }

        $density = $theoreticalEdges > 0 ? round($activeChannels / $theoreticalEdges, 3) : 1.0;
        $avgRtt = $activeChannels > 0 ? round($totalRtt / $activeChannels, 2) : 0.0;

        return [
            'peerCount' => $peerCount,
            'theoreticalMaxChannels' => $theoreticalEdges,
            'activeConnectedChannels' => $activeChannels,
            'meshDensity' => $density,
            'averageRttMs' => $avgRtt,
        ];
    }

    private function makeEdgeKey(string $a, string $b): string
    {
        return strcmp($a, $b) <= 0 ? "{$a}::{$b}" : "{$b}::{$a}";
    }
}
