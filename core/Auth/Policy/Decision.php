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

namespace NetFusion\Core\Auth\Policy;

use JsonSerializable;

class Decision implements JsonSerializable
{
    /**
     * @param Effect $effect
     * @param string|null $matchedPolicy
     * @param string|null $matchedRule
     * @param string|null $reason
     * @param array<string, mixed> $diagnostics
     */
    public function __construct(
        public Effect $effect,
        public ?string $matchedPolicy = null,
        public ?string $matchedRule = null,
        public ?string $reason = null,
        public array $diagnostics = []
    ) {
    }

    public static function allow(?string $policy = null, ?string $rule = null, ?string $reason = null): self
    {
        return new self(Effect::ALLOW, $policy, $rule, $reason);
    }

    public static function deny(?string $policy = null, ?string $rule = null, ?string $reason = null): self
    {
        return new self(Effect::DENY, $policy, $rule, $reason);
    }

    public function isAllowed(): bool
    {
        return $this->effect === Effect::ALLOW;
    }

    public function isDenied(): bool
    {
        return $this->effect === Effect::DENY;
    }

    public function getRuleId(): ?string
    {
        return $this->matchedRule;
    }

    public function getPolicyId(): ?string
    {
        return $this->matchedPolicy;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getEffect(): Effect
    {
        return $this->effect;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->isAllowed(),
            'effect' => $this->effect->value,
            'matched_policy' => $this->matchedPolicy,
            'matched_rule' => $this->matchedRule,
            'reason' => $this->reason,
            'diagnostics' => $this->diagnostics,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
