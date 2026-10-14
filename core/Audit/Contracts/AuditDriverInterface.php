<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Audit/Contracts/AuditDriverInterface.php
| Module    : Core\Audit
| Namespace : NetFusion\Core\Audit\Contracts
| Purpose   : Interface defining standard storage contract for enterprise audit drivers.
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

namespace NetFusion\Core\Audit\Contracts;

interface AuditDriverInterface
{
    /**
     * Record an audit event entry.
     *
     * @param  array<string, mixed> $data
     * @return bool
     */
    public function record(array $data): bool;

    /**
     * Prune audit records older than the given number of days.
     *
     * @param  int $days
     * @return int Number of pruned records
     */
    public function prune(int $days): int;
}
