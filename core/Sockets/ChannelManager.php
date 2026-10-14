<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Sockets/ChannelManager.php
| Module    : Sockets
| Namespace : NetFusion\Core\Sockets
| Purpose   : Channel rooms, Presence data, and Pub/Sub broadcasting engine.
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

namespace NetFusion\Core\Sockets;

use NetFusion\Core\Sockets\Contracts\ConnectionInterface;

/**
 * Class ChannelManager
 *
 * Manages public, private, and presence channel room memberships and broadcasting.
 *
 * @package NetFusion\Core\Sockets
 */
class ChannelManager
{
    /**
     * Map of channel names to connections: channel => [connId => Connection].
     *
     * @var array<string, array<string, ConnectionInterface>>
     */
    protected array $channels = [];

    /**
     * Map of presence channel metadata: channel => [connId => memberData].
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected array $presenceData = [];

    /**
     * Join a client connection to a channel.
     *
     * @param string              $channel
     * @param ConnectionInterface $conn
     * @param array<string, mixed> $memberData Optional user info for presence channels.
     */
    public function join(string $channel, ConnectionInterface $conn, array $memberData = []): void
    {
        $connId = $conn->getId();
        $this->channels[$channel][$connId] = $conn;
        $conn->subscribe($channel);

        if (!empty($memberData) || str_starts_with($channel, 'presence-')) {
            $this->presenceData[$channel][$connId] = $memberData;

            // Notify existing members about new member in presence channel
            $this->broadcast($channel, 'presence:join', [
                'channel' => $channel,
                'user'    => $memberData,
                'id'      => $connId,
            ], except: $conn);
        }
    }

    /**
     * Leave a channel.
     */
    public function leave(string $channel, ConnectionInterface $conn): void
    {
        $connId = $conn->getId();
        if (isset($this->channels[$channel][$connId])) {
            unset($this->channels[$channel][$connId]);
            $conn->unsubscribe($channel);

            if (isset($this->presenceData[$channel][$connId])) {
                $userData = $this->presenceData[$channel][$connId];
                unset($this->presenceData[$channel][$connId]);

                // Notify remaining members
                $this->broadcast($channel, 'presence:leave', [
                    'channel' => $channel,
                    'user'    => $userData,
                    'id'      => $connId,
                ]);
            }

            if (empty($this->channels[$channel])) {
                unset($this->channels[$channel]);
                unset($this->presenceData[$channel]);
            }
        }
    }

    /**
     * Remove a connection from all channels it is subscribed to.
     */
    public function leaveAll(ConnectionInterface $conn): void
    {
        foreach ($conn->getChannels() as $channel) {
            $this->leave($channel, $conn);
        }
    }

    /**
     * Broadcast a structured JSON event to all subscribers in a channel.
     *
     * @param string                   $channel
     * @param string                   $event
     * @param array<string, mixed>     $data
     * @param ConnectionInterface|null $except
     * @return int Number of clients the message was delivered to.
     */
    public function broadcast(string $channel, string $event, array $data = [], ?ConnectionInterface $except = null): int
    {
        $payload = json_encode([
            'event'   => $event,
            'channel' => $channel,
            'data'    => $data,
            'time'    => microtime(true),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->broadcastRaw($channel, (string) $payload, $except);
    }

    /**
     * Broadcast raw text string to all subscribers in a channel.
     *
     * @return int
     */
    public function broadcastRaw(string $channel, string $rawMessage, ?ConnectionInterface $except = null): int
    {
        if (!isset($this->channels[$channel])) {
            return 0;
        }

        $delivered = 0;
        $exceptId = $except?->getId();

        foreach ($this->channels[$channel] as $connId => $conn) {
            if ($exceptId !== null && $connId === $exceptId) {
                continue;
            }

            if ($conn->send($rawMessage)) {
                $delivered++;
            }
        }

        return $delivered;
    }

    /**
     * Broadcast to all connected clients across the entire server.
     */
    public function broadcastAll(string $event, array $data = [], ?ConnectionInterface $except = null): int
    {
        $allConns = [];
        foreach ($this->channels as $conns) {
            foreach ($conns as $id => $conn) {
                $allConns[$id] = $conn;
            }
        }

        $delivered = 0;
        $exceptId = $except?->getId();
        $payload = json_encode([
            'event' => $event,
            'data'  => $data,
            'time'  => microtime(true),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($allConns as $id => $conn) {
            if ($exceptId !== null && $id === $exceptId) {
                continue;
            }
            if ($conn->send((string) $payload)) {
                $delivered++;
            }
        }

        return $delivered;
    }

    /**
     * Check if a client is subscribed to a channel.
     */
    public function isSubscribed(string $channel, ConnectionInterface $conn): bool
    {
        return isset($this->channels[$channel][$conn->getId()]);
    }

    /**
     * Get number of active subscribers in a channel.
     */
    public function getChannelCount(string $channel): int
    {
        return isset($this->channels[$channel]) ? count($this->channels[$channel]) : 0;
    }

    /**
     * Get all active channel names.
     *
     * @return array<string>
     */
    public function getChannels(): array
    {
        return array_keys($this->channels);
    }

    /**
     * Get presence members in a presence channel.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPresenceData(string $channel): array
    {
        return $this->presenceData[$channel] ?? [];
    }

    /**
     * Get connections for a channel.
     *
     * @return array<string, ConnectionInterface>
     */
    public function getChannelConnections(string $channel): array
    {
        return $this->channels[$channel] ?? [];
    }
}
