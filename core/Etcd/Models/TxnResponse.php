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

namespace NetFusion\Core\Etcd\Models;

/**
 * Represents the execution response of an atomic etcd multi-operation transaction.
 */
class TxnResponse
{
    public function __construct(
        public readonly bool $succeeded,
        public readonly array $responses = [],
        public readonly int $revision = 0
    ) {
    }

    /**
     * Determine if comparison preconditions passed.
     */
    public function isSuccess(): bool
    {
        return $this->succeeded;
    }

    /**
     * Get branch execution responses.
     */
    public function getResponses(): array
    {
        return $this->responses;
    }

    /**
     * Get the revision after transaction execution.
     */
    public function getRevision(): int
    {
        return $this->revision;
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'succeeded' => $this->succeeded,
            'revision'  => $this->revision,
            'responses' => array_map(function ($resp) {
                if ($resp instanceof KeyRevision) {
                    return $resp->toArray();
                }
                return $resp;
            }, $this->responses),
        ];
    }
}
