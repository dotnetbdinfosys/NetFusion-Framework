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

namespace NetFusion\Core\Collaboration\Session;

/**
 * Class CursorPresence
 *
 * Real-time awareness and collaborative presence state for a connected peer.
 * Tracks live cursor position, selection range, user handle, and heartbeat.
 */
class CursorPresence
{
    public function __construct(
        public readonly string $peerId,
        public readonly string $userName,
        public int $cursorIndex = 0,
        public int $selectionEnd = 0,
        public readonly string $color = '#3b82f6',
        public float $lastHeartbeat = 0.0
    ) {
        if ($this->lastHeartbeat <= 0.0) {
            $this->lastHeartbeat = microtime(true);
        }
    }

    public function updateCursor(int $cursorIndex, ?int $selectionEnd = null): void
    {
        $this->cursorIndex = $cursorIndex;
        $this->selectionEnd = $selectionEnd ?? $cursorIndex;
        $this->lastHeartbeat = microtime(true);
    }

    public function heartbeat(): void
    {
        $this->lastHeartbeat = microtime(true);
    }

    public function isAlive(float $timeoutSeconds = 30.0): bool
    {
        return (microtime(true) - $this->lastHeartbeat) <= $timeoutSeconds;
    }

    public function toArray(): array
    {
        return [
            'peerId' => $this->peerId,
            'userName' => $this->userName,
            'cursorIndex' => $this->cursorIndex,
            'selectionEnd' => $this->selectionEnd,
            'color' => $this->color,
            'lastHeartbeat' => $this->lastHeartbeat,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['peerId'] ?? 'anon'),
            (string) ($data['userName'] ?? 'Anonymous'),
            (int) ($data['cursorIndex'] ?? 0),
            (int) ($data['selectionEnd'] ?? 0),
            (string) ($data['color'] ?? '#3b82f6'),
            (float) ($data['lastHeartbeat'] ?? microtime(true))
        );
    }
}
