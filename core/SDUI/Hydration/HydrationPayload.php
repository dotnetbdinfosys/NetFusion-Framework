<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\SDUI\Hydration;

use JsonSerializable;
use NetFusion\Core\SDUI\Layout\Screen;

/**
 * Standardized client hydration response payload for mobile and web clients.
 */
class HydrationPayload implements JsonSerializable
{
    /** @var array<string, mixed> */
    protected array $data;

    protected string $checksum;
    protected int $timestamp;

    public function __construct(
        Screen|array $screen,
        array $context = [],
        ?string $appSecret = null
    ) {
        $this->timestamp = time();
        $this->data = ($screen instanceof Screen) ? $screen->render($context) : $screen;

        $json = json_encode($this->data);
        $this->checksum = hash('sha256', (string) $json);

        if ($appSecret !== null) {
            $this->data['signature'] = hash_hmac('sha256', $this->checksum, $appSecret);
        }
    }

    public static function create(Screen|array $screen, array $context = [], ?string $appSecret = null): self
    {
        return new self($screen, $context, $appSecret);
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status'    => 'success',
            'timestamp' => $this->timestamp,
            'checksum'  => $this->checksum,
            'payload'   => $this->data,
        ];
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
