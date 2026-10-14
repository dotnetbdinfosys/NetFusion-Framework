<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Webhooks/WebhookPayload.php
| Module    : Core\Webhooks
| Namespace : NetFusion\Core\Webhooks
| Purpose   : Encapsulates outgoing webhook event data, metadata, and serialization.
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

namespace NetFusion\Core\Webhooks;

use JsonSerializable;

/**
 * Class WebhookPayload
 *
 * Immutable data transfer object representing a dispatched webhook event.
 *
 * @package NetFusion\Core\Webhooks
 */
class WebhookPayload implements JsonSerializable
{
    protected string $id;
    protected int $timestamp;

    /**
     * @param string $event Event topic name (e.g., 'payroll.processed').
     * @param array<string, mixed> $data Event payload data.
     * @param int|null $attempt Delivery attempt counter.
     * @param string|null $id Unique delivery ID.
     * @param int|null $timestamp Event timestamp.
     */
    public function __construct(
        protected string $event,
        protected array $data,
        protected int $attempt = 1,
        ?string $id = null,
        ?int $timestamp = null
    ) {
        $this->id = $id ?? bin2hex(random_bytes(16));
        $this->timestamp = $timestamp ?? time();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Convert to standard structured array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'event'     => $this->event,
            'timestamp' => $this->timestamp,
            'attempt'   => $this->attempt,
            'data'      => $this->data,
        ];
    }

    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
