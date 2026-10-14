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

namespace NetFusion\Core\Kube\Enums;

/**
 * Represents the current execution phase of a Kubernetes Pod.
 */
enum PodPhase: string
{
    case PENDING   = 'Pending';
    case RUNNING   = 'Running';
    case SUCCEEDED = 'Succeeded';
    case FAILED    = 'Failed';
    case UNKNOWN   = 'Unknown';

    /**
     * Determine if this phase represents a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this === self::SUCCEEDED || $this === self::FAILED;
    }
}
