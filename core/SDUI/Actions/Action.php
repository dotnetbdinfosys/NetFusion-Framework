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

namespace NetFusion\Core\SDUI\Actions;

use JsonSerializable;
use NetFusion\Core\SDUI\Enums\ActionType;

/**
 * Represents a client-side executable action triggered by component events.
 */
class Action implements JsonSerializable
{
    /**
     * @param ActionType $type
     * @param array<string, mixed> $params
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        protected ActionType $type,
        protected array $params = [],
        protected array $metadata = []
    ) {
    }

    public function getType(): ActionType
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function withParam(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->params[$key] = $value;
        return $clone;
    }

    public function withMetadata(array $metadata): self
    {
        $clone = clone $this;
        $clone->metadata = array_merge($clone->metadata, $metadata);
        return $clone;
    }

    /**
     * Factory: Navigate to a client route or URL.
     */
    public static function navigate(string $url, bool $replace = false, array $params = []): self
    {
        return new self(ActionType::NAVIGATE, [
            'url'     => $url,
            'replace' => $replace,
            'params'  => $params,
        ]);
    }

    /**
     * Factory: Open a modal dialog.
     */
    public static function openModal(string $modalId, array $props = []): self
    {
        return new self(ActionType::OPEN_MODAL, [
            'modalId' => $modalId,
            'props'   => $props,
        ]);
    }

    /**
     * Factory: Close a modal dialog.
     */
    public static function closeModal(string $modalId = ''): self
    {
        return new self(ActionType::CLOSE_MODAL, [
            'modalId' => $modalId,
        ]);
    }

    /**
     * Factory: Perform an asynchronous API HTTP call from the client.
     */
    public static function apiCall(
        string $endpoint,
        string $method = 'POST',
        array $payload = [],
        ?self $onSuccess = null,
        ?self $onError = null
    ): self {
        $params = [
            'endpoint' => $endpoint,
            'method'   => strtoupper($method),
            'payload'  => $payload,
        ];

        if ($onSuccess !== null) {
            $params['onSuccess'] = $onSuccess->toArray();
        }

        if ($onError !== null) {
            $params['onError'] = $onError->toArray();
        }

        return new self(ActionType::API_CALL, $params);
    }

    /**
     * Factory: Dispatch a custom client-side event.
     */
    public static function dispatchEvent(string $eventName, array $payload = []): self
    {
        return new self(ActionType::DISPATCH_EVENT, [
            'event'   => $eventName,
            'payload' => $payload,
        ]);
    }

    /**
     * Factory: Invoke a native or mobile deep link URI.
     */
    public static function deepLink(string $uri): self
    {
        return new self(ActionType::DEEP_LINK, [
            'uri' => $uri,
        ]);
    }

    /**
     * Factory: Show a client toast or snackbar notification.
     */
    public static function snackbar(string $message, string $type = 'info', int $durationMs = 3000): self
    {
        return new self(ActionType::SNACKBAR, [
            'message'    => $message,
            'type'       => $type,
            'durationMs' => $durationMs,
        ]);
    }

    /**
     * Factory: Mutate local client state.
     */
    public static function setState(string $key, mixed $value): self
    {
        return new self(ActionType::SET_STATE, [
            'key'   => $key,
            'value' => $value,
        ]);
    }

    /**
     * Factory: Copy text to clipboard.
     */
    public static function copyClipboard(string $text): self
    {
        return new self(ActionType::COPY_CLIPBOARD, [
            'text' => $text,
        ]);
    }

    /**
     * Factory: Request client to refresh the active screen from server.
     */
    public static function refreshScreen(): self
    {
        return new self(ActionType::REFRESH_SCREEN);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'type'   => $this->type->value,
            'params' => $this->params,
        ];

        if (!empty($this->metadata)) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
