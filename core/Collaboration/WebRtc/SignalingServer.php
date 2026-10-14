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

use Closure;

class SignalingServer
{
    protected RoomManager $rooms;

    /**
     * Outbound broadcast message dispatcher closure.
     * signature: function(string $targetPeerIdOrRoomId, SignalingMessage $message, bool $isRoomBroadcast = false): void
     */
    protected ?Closure $dispatcher = null;

    public function __construct(?RoomManager $rooms = null)
    {
        $this->rooms = $rooms ?? new RoomManager();
    }

    public function getRoomManager(): RoomManager
    {
        return $this->rooms;
    }

    public function setDispatcher(Closure $dispatcher): self
    {
        $this->dispatcher = $dispatcher;
        return $this;
    }

    /**
     * Process an incoming signaling message and route responses.
     *
     * @return SignalingMessage[] Array of generated outbound messages.
     */
    public function handleMessage(SignalingMessage $msg): array
    {
        $outbound = [];

        switch ($msg->type) {
            case SignalingMessage::TYPE_JOIN:
                $existingPeers = $this->rooms->join($msg->roomId, $msg->senderId, $msg->payload);

                // 1. Reply to joining peer with list of existing peers
                $peersMsg = SignalingMessage::peers($msg->roomId, $existingPeers);
                $outbound[] = $peersMsg;
                $this->dispatch($msg->senderId, $peersMsg, false);

                // 2. Notify all existing peers that a new peer joined
                $joinedNotice = SignalingMessage::create(
                    type: SignalingMessage::TYPE_JOIN,
                    roomId: $msg->roomId,
                    senderId: $msg->senderId,
                    payload: $msg->payload
                );
                foreach ($existingPeers as $peerId) {
                    $outbound[] = $joinedNotice;
                    $this->dispatch($peerId, $joinedNotice, false);
                }
                break;

            case SignalingMessage::TYPE_LEAVE:
                if ($this->rooms->leave($msg->roomId, $msg->senderId)) {
                    $remainingPeers = $this->rooms->getPeers($msg->roomId);
                    $leaveNotice = SignalingMessage::create(
                        type: SignalingMessage::TYPE_LEAVE,
                        roomId: $msg->roomId,
                        senderId: $msg->senderId
                    );
                    foreach ($remainingPeers as $peerId) {
                        $outbound[] = $leaveNotice;
                        $this->dispatch($peerId, $leaveNotice, false);
                    }
                }
                break;

            case SignalingMessage::TYPE_OFFER:
            case SignalingMessage::TYPE_ANSWER:
            case SignalingMessage::TYPE_ICE_CANDIDATE:
                // Direct peer-to-peer forwarded signaling
                if (!empty($msg->targetId)) {
                    $outbound[] = $msg;
                    $this->dispatch($msg->targetId, $msg, false);
                }
                break;
        }

        return $outbound;
    }

    protected function dispatch(string $targetId, SignalingMessage $message, bool $isRoom = false): void
    {
        if ($this->dispatcher !== null) {
            ($this->dispatcher)($targetId, $message, $isRoom);
        }
    }
}
