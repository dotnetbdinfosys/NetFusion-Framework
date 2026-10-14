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
 * Class Notification
 *
 * Immutable notification payload builder.
 *
 * Supports title, body, data, badge, sound, image URL,
 * channel-specific overrides, and TTL.
 */
final class Notification
{
    private string $id;
    private string $title       = '';
    private string $body        = '';
    private array  $data        = [];
    private int    $badge       = 0;
    private string $sound       = 'default';
    private string $imageUrl    = '';
    private string $clickAction = '';
    private int    $ttl         = 3600;
    private string $priority    = 'high';   // high | normal
    private array  $channelOverrides = [];

    private function __construct()
    {
        $this->id = uniqid('notif_', true);
    }

    // ------------------------------------------------------------------
    // Builder
    // ------------------------------------------------------------------

    public static function create(): self
    {
        return new self();
    }

    public function withTitle(string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;
        return $clone;
    }

    public function withBody(string $body): self
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    public function withData(array $data): self
    {
        $clone = clone $this;
        $clone->data = $data;
        return $clone;
    }

    public function withBadge(int $badge): self
    {
        $clone = clone $this;
        $clone->badge = $badge;
        return $clone;
    }

    public function withSound(string $sound): self
    {
        $clone = clone $this;
        $clone->sound = $sound;
        return $clone;
    }

    public function withImageUrl(string $url): self
    {
        $clone = clone $this;
        $clone->imageUrl = $url;
        return $clone;
    }

    public function withClickAction(string $action): self
    {
        $clone = clone $this;
        $clone->clickAction = $action;
        return $clone;
    }

    public function withTtl(int $seconds): self
    {
        $clone = clone $this;
        $clone->ttl = $seconds;
        return $clone;
    }

    public function withPriority(string $priority): self
    {
        $clone = clone $this;
        $clone->priority = $priority;
        return $clone;
    }

    /**
     * Add channel-specific payload overrides (e.g., APNs-specific options).
     */
    public function withChannelOverride(string $channel, array $options): self
    {
        $clone = clone $this;
        $clone->channelOverrides[$channel] = $options;
        return $clone;
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getId(): string          { return $this->id; }
    public function getTitle(): string       { return $this->title; }
    public function getBody(): string        { return $this->body; }
    public function getData(): array         { return $this->data; }
    public function getBadge(): int          { return $this->badge; }
    public function getSound(): string       { return $this->sound; }
    public function getImageUrl(): string    { return $this->imageUrl; }
    public function getClickAction(): string { return $this->clickAction; }
    public function getTtl(): int            { return $this->ttl; }
    public function getPriority(): string    { return $this->priority; }

    public function getChannelOverride(string $channel): array
    {
        return $this->channelOverrides[$channel] ?? [];
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'body'         => $this->body,
            'data'         => $this->data,
            'badge'        => $this->badge,
            'sound'        => $this->sound,
            'image_url'    => $this->imageUrl,
            'click_action' => $this->clickAction,
            'ttl'          => $this->ttl,
            'priority'     => $this->priority,
        ];
    }

    /**
     * Build the FCM v1 API message payload.
     */
    public function toFcmPayload(string $token): array
    {
        $overrides = $this->getChannelOverride('fcm');
        $payload = [
            'message' => [
                'token'        => $token,
                'notification' => [
                    'title' => $this->title,
                    'body'  => $this->body,
                ],
                'android' => array_merge([
                    'ttl'      => $this->ttl . 's',
                    'priority' => strtoupper($this->priority),
                    'notification' => [
                        'sound' => $this->sound,
                        'badge' => (string) $this->badge,
                    ],
                ], $overrides['android'] ?? []),
            ],
        ];
        if (!empty($this->data)) {
            $payload['message']['data'] = array_map('strval', $this->data);
        }
        if (!empty($this->imageUrl)) {
            $payload['message']['notification']['image'] = $this->imageUrl;
        }
        return $payload;
    }

    /**
     * Build an APNs payload (aps dictionary).
     */
    public function toApnsPayload(): array
    {
        $overrides = $this->getChannelOverride('apns');
        $aps = [
            'alert'            => ['title' => $this->title, 'body' => $this->body],
            'sound'            => $this->sound,
            'badge'            => $this->badge,
            'content-available'=> 1,
        ];
        if (!empty($this->clickAction)) {
            $aps['category'] = $this->clickAction;
        }
        return array_merge(['aps' => $aps], $this->data, $overrides);
    }
}
