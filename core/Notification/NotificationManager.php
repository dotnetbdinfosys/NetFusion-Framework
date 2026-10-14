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

namespace NetFusion\Core\Notification;

use NetFusion\Core\Notification\Contracts\ChannelInterface;

/**
 * Class NotificationManager
 *
 * Central facade for the Push Notification Gateway.
 *
 * Features:
 *   - Multi-channel routing with fallback chain
 *   - Topic-based fan-out broadcasting
 *   - Delivery log tracking
 *   - Batch sending with chunk support
 *
 * Usage:
 *   $mgr = NotificationManager::create();
 *   $mgr->addChannel(new FcmChannel(simulate: true))
 *       ->setDefaultChannel('fcm');
 *
 *   $notif = Notification::create()
 *       ->withTitle('Hello')
 *       ->withBody('World');
 *
 *   $mgr->send('device_token_123', $notif);
 *   $mgr->broadcastToTopic('news', $notif);
 */
class NotificationManager
{
    /** @var ChannelInterface[] keyed by channel name */
    private array  $channels       = [];
    private string $defaultChannel = 'database';
    private TopicManager    $topics;
    private NotificationLog $log;

    public function __construct()
    {
        $this->topics = new TopicManager();
        $this->log    = new NotificationLog();
    }

    // ------------------------------------------------------------------
    // Factory / singleton
    // ------------------------------------------------------------------

    public static function create(): self
    {
        return new self();
    }

    // ------------------------------------------------------------------
    // Channel management
    // ------------------------------------------------------------------

    public function addChannel(ChannelInterface $channel): self
    {
        $this->channels[$channel->getName()] = $channel;
        return $this;
    }

    public function setDefaultChannel(string $name): self
    {
        $this->defaultChannel = $name;
        return $this;
    }

    public function getChannel(string $name): ?ChannelInterface
    {
        return $this->channels[$name] ?? null;
    }

    public function getChannelNames(): array
    {
        return array_keys($this->channels);
    }

    // ------------------------------------------------------------------
    // Sending
    // ------------------------------------------------------------------

    /**
     * Send via a specific channel (or default channel if not specified).
     */
    public function send(
        string       $recipient,
        Notification $notification,
        ?string      $channelName = null
    ): array {
        $name    = $channelName ?? $this->defaultChannel;
        $channel = $this->channels[$name] ?? null;

        if ($channel === null || !$channel->isAvailable()) {
            // Fallback to first available channel
            $channel = $this->firstAvailableChannel();
        }

        if ($channel === null) {
            $result = ['success' => false, 'message_id' => null, 'error' => 'No channel available'];
        } else {
            $result = $channel->send($recipient, $notification);
            $name   = $channel->getName();
        }

        $this->log->record(
            $name,
            $recipient,
            $notification->getId(),
            $result['success'],
            $result['message_id'],
            $result['error']
        );

        return $result;
    }

    /**
     * Send to all subscribers of a topic.
     *
     * @return array<string, array> Results keyed by recipient
     */
    public function broadcastToTopic(
        string       $topic,
        Notification $notification,
        ?string      $channelName = null
    ): array {
        $subscribers = $this->topics->getSubscribers($topic);
        $results     = [];

        foreach ($subscribers as $recipient) {
            $results[$recipient] = $this->send($recipient, $notification, $channelName);
        }

        return $results;
    }

    /**
     * Send to multiple recipients in batch (chunked at 500 per call).
     *
     * @param  string[]     $recipients
     * @return array<string, array>
     */
    public function sendBatch(
        array        $recipients,
        Notification $notification,
        ?string      $channelName = null,
        int          $chunkSize   = 500
    ): array {
        $results = [];
        foreach (array_chunk($recipients, $chunkSize) as $chunk) {
            foreach ($chunk as $recipient) {
                $results[$recipient] = $this->send($recipient, $notification, $channelName);
            }
        }
        return $results;
    }

    // ------------------------------------------------------------------
    // Topics
    // ------------------------------------------------------------------

    public function getTopics(): TopicManager { return $this->topics; }

    public function subscribe(string $topic, string $recipient): void
    {
        $this->topics->subscribe($topic, $recipient);
    }

    public function unsubscribe(string $topic, string $recipient): void
    {
        $this->topics->unsubscribe($topic, $recipient);
    }

    // ------------------------------------------------------------------
    // Logging
    // ------------------------------------------------------------------

    public function getLog(): NotificationLog { return $this->log; }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function firstAvailableChannel(): ?ChannelInterface
    {
        foreach ($this->channels as $channel) {
            if ($channel->isAvailable()) return $channel;
        }
        return null;
    }
}
