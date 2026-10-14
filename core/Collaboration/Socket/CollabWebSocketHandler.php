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

namespace NetFusion\Core\Collaboration\Socket;

use NetFusion\Core\Collaboration\CollabManager;
use NetFusion\Core\Collaboration\Ot\Selection;
use NetFusion\Core\Collaboration\Ot\TextOperation;
use NetFusion\Core\Collaboration\WebRtc\SignalingMessage;

class CollabWebSocketHandler
{
    protected CollabManager $collab;

    public function __construct(?CollabManager $collab = null)
    {
        $this->collab = $collab ?? (function_exists('app') && app()->has('collab') ? app('collab') : new CollabManager());
    }

    /**
     * Process an incoming WebSocket frame payload.
     *
     * @param  array<string, mixed> $payload Decoded JSON WebSocket message
     * @return array<string, mixed> Response or broadcast payload
     */
    public function handle(array $payload): array
    {
        $action = (string)($payload['action'] ?? $payload['type'] ?? '');

        return match ($action) {
            'ot:operation' => $this->handleOtOperation($payload),
            'ot:cursor' => $this->handleOtCursor($payload),
            'webrtc:signal' => $this->handleWebRtcSignal($payload),
            default => ['error' => 'Unknown collaboration action.'],
        };
    }

    protected function handleOtOperation(array $payload): array
    {
        $docId = (string)($payload['document_id'] ?? '');
        $revision = (int)($payload['revision'] ?? 0);
        $userId = (string)($payload['user_id'] ?? 'anonymous');
        $opData = (array)($payload['operation'] ?? []);

        $session = $this->collab->session($docId);
        $op = TextOperation::fromArray($opData);

        $result = $session->applyClientOperation($revision, $op, $userId);

        return [
            'event' => 'ot:operation_applied',
            'document_id' => $docId,
            'revision' => $result['revision'],
            'operation' => $result['operation']->toArray(),
            'user_id' => $userId,
        ];
    }

    protected function handleOtCursor(array $payload): array
    {
        $docId = (string)($payload['document_id'] ?? '');
        $userId = (string)($payload['user_id'] ?? 'anonymous');
        $username = (string)($payload['username'] ?? 'Anonymous');
        $color = (string)($payload['color'] ?? '#3b82f6');
        $anchor = (int)($payload['anchor'] ?? 0);
        $head = (int)($payload['head'] ?? $anchor);

        $session = $this->collab->session($docId);
        $cursor = $session->updateCursor($userId, $username, $color, new Selection($anchor, $head));

        return [
            'event' => 'ot:cursor_updated',
            'document_id' => $docId,
            'cursor' => $cursor->toArray(),
        ];
    }

    protected function handleWebRtcSignal(array $payload): array
    {
        $msg = SignalingMessage::fromArray((array)($payload['signal'] ?? $payload));
        $outbound = $this->collab->signaling()->handleMessage($msg);

        return [
            'event' => 'webrtc:signal_processed',
            'messages' => array_map(fn(SignalingMessage $m) => $m->toArray(), $outbound),
        ];
    }
}
