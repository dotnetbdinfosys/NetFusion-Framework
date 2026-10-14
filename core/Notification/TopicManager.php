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

/**
 * Class TopicManager
 *
 * Topic-based pub/sub fan-out for push notifications.
 *
 * Allows grouping device tokens / user identifiers under named topics
 * and broadcasting notifications to all subscribers at once.
 *
 * Storage: in-memory by default; can be backed by a persistent store.
 */
class TopicManager
{
    /** @var array<string, string[]> topic → subscribers */
    private array $subscriptions = [];

    // ------------------------------------------------------------------
    // Subscription management
    // ------------------------------------------------------------------

    public function subscribe(string $topic, string $recipient): void
    {
        $this->subscriptions[$topic]   ??= [];
        $subscribers = &$this->subscriptions[$topic];

        if (!in_array($recipient, $subscribers, true)) {
            $subscribers[] = $recipient;
        }
    }

    public function subscribeMany(string $topic, array $recipients): void
    {
        foreach ($recipients as $r) {
            $this->subscribe($topic, $r);
        }
    }

    public function unsubscribe(string $topic, string $recipient): void
    {
        if (!isset($this->subscriptions[$topic])) return;
        $this->subscriptions[$topic] = array_values(
            array_filter($this->subscriptions[$topic], fn($r) => $r !== $recipient)
        );
    }

    public function unsubscribeFromAll(string $recipient): void
    {
        foreach ($this->subscriptions as $topic => &$subs) {
            $subs = array_values(array_filter($subs, fn($r) => $r !== $recipient));
        }
    }

    public function deleteTopic(string $topic): void
    {
        unset($this->subscriptions[$topic]);
    }

    // ------------------------------------------------------------------
    // Querying
    // ------------------------------------------------------------------

    /** @return string[] All subscribers of a topic */
    public function getSubscribers(string $topic): array
    {
        return $this->subscriptions[$topic] ?? [];
    }

    public function isSubscribed(string $topic, string $recipient): bool
    {
        return in_array($recipient, $this->subscriptions[$topic] ?? [], true);
    }

    public function countSubscribers(string $topic): int
    {
        return count($this->subscriptions[$topic] ?? []);
    }

    /** @return string[] All known topics */
    public function getTopics(): array
    {
        return array_keys($this->subscriptions);
    }

    public function toArray(): array { return $this->subscriptions; }
}
