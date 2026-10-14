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

namespace NetFusion\Core\IaC\Plan;

use JsonSerializable;

/**
 * Proposed mutation diff for a single resource address.
 */
class ResourceChange implements JsonSerializable
{
    /**
     * @param string $address Canonical resource address (e.g. "aws_instance.web")
     * @param ActionType $action Planned action
     * @param array<string, mixed>|null $before Current state attributes (or null if creating)
     * @param array<string, mixed>|null $after Target state attributes (or null if destroying)
     * @param array<string> $changedAttributes Keys of attributes modified
     */
    public function __construct(
        public readonly string $address,
        public readonly ActionType $action,
        public readonly ?array $before = null,
        public readonly ?array $after = null,
        public readonly array $changedAttributes = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'action' => $this->action->value,
            'before' => $this->before,
            'after' => $this->after,
            'changed_attributes' => $this->changedAttributes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            address: (string) ($data['address'] ?? ''),
            action: ActionType::from((string) ($data['action'] ?? 'noop')),
            before: isset($data['before']) && is_array($data['before']) ? $data['before'] : null,
            after: isset($data['after']) && is_array($data['after']) ? $data['after'] : null,
            changedAttributes: (array) ($data['changed_attributes'] ?? [])
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
