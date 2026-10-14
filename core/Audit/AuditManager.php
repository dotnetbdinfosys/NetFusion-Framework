<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Audit/AuditManager.php
| Module    : Core\Audit
| Namespace : NetFusion\Core\Audit
| Purpose   : Central manager and event recorder for enterprise audit trails and revisions.
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

namespace NetFusion\Core\Audit;

use NetFusion\Core\Audit\Contracts\AuditDriverInterface;
use NetFusion\Core\Audit\Drivers\DatabaseAuditDriver;
use NetFusion\Core\Database\DatabaseManager;

class AuditManager
{
    protected ?AuditDriverInterface $driver = null;

    /**
     * @var string[]
     */
    protected array $globalExcludes = [
        'password',
        'remember_token',
        'secret',
        'api_key',
        'auth_token',
        'credit_card',
    ];

    /**
     * Optional custom user resolver.
     *
     * @var callable|null
     */
    protected $userResolver = null;

    public function __construct(?AuditDriverInterface $driver = null)
    {
        $this->driver = $driver;
    }

    /**
     * Get or lazy-load the audit driver.
     */
    public function getDriver(): AuditDriverInterface
    {
        if ($this->driver === null) {
            $db = app(DatabaseManager::class);
            $this->driver = new DatabaseAuditDriver($db);
        }

        return $this->driver;
    }

    /**
     * Set a custom audit driver.
     */
    public function setDriver(AuditDriverInterface $driver): static
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Set callback to resolve current authenticated user ID.
     */
    public function setUserResolver(callable $resolver): static
    {
        $this->userResolver = $resolver;
        return $this;
    }

    /**
     * Record an audit event.
     *
     * @param  string $event created|updated|deleted|custom
     * @param  string $auditableType
     * @param  int|string $auditableId
     * @param  array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null $newValues
     * @param  int|null $userId
     * @param  string|null $tags
     * @param  int|null $tenantId
     * @return bool
     */
    public function record(
        string $event,
        string $auditableType,
        int|string $auditableId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?string $tags = null,
        ?int $tenantId = null
    ): bool {
        // Sanitize sensitive attributes
        $filteredOld = $oldValues !== null ? $this->filterSensitive($oldValues) : null;
        $filteredNew = $newValues !== null ? $this->filterSensitive($newValues) : null;

        // Resolve current user ID if not explicitly passed
        if ($userId === null && $this->userResolver !== null) {
            $userId = ($this->userResolver)();
        }

        // Gather request metadata if available
        $ip = $this->resolveClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $url = $this->resolveRequestUrl();

        $entry = [
            'user_id'        => $userId,
            'tenant_id'      => $tenantId,
            'event'          => strtolower($event),
            'auditable_type' => $auditableType,
            'auditable_id'   => (int) $auditableId,
            'old_values'     => $filteredOld,
            'new_values'     => $filteredNew,
            'ip_address'     => $ip,
            'user_agent'     => $userAgent,
            'url'            => $url,
            'tags'           => $tags,
        ];

        return $this->getDriver()->record($entry);
    }

    /**
     * Filter sensitive keys from logged data.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function filterSensitive(array $data): array
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $this->globalExcludes, true)) {
                $filtered[$key] = '********';
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Resolve client IP address.
     */
    protected function resolveClientIp(): ?string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Resolve current request URL.
     */
    protected function resolveRequestUrl(): ?string
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            return "{$scheme}://{$host}" . $_SERVER['REQUEST_URI'];
        }

        return null;
    }

    /**
     * Prune audit records older than the given days.
     */
    public function prune(int $days = 90): int
    {
        return $this->getDriver()->prune($days);
    }
}
