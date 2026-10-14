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

namespace NetFusion\Core\ServiceMesh\Policy;

/**
 * Class AccessDecision
 *
 * Encapsulates policy authorization result with rule traceability.
 */
class AccessDecision
{
    public function __construct(
        private readonly bool $allowed,
        private readonly string $matchedRule = '',
        private readonly string $reason = '',
        private readonly array $auditMetadata = []
    ) {}

    public static function allow(string $matchedRule, string $reason = 'Matched allow rule'): self
    {
        return new self(true, $matchedRule, $reason);
    }

    public static function deny(string $matchedRule = '', string $reason = 'Denied by zero-trust policy'): self
    {
        return new self(false, $matchedRule, $reason);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function isDenied(): bool
    {
        return !$this->allowed;
    }

    public function getMatchedRule(): string
    {
        return $this->matchedRule;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getAuditMetadata(): array
    {
        return $this->auditMetadata;
    }

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'matched_rule' => $this->matchedRule,
            'reason' => $this->reason,
            'metadata' => $this->auditMetadata,
        ];
    }
}
