<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Tenant.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy
| Purpose   : Tenant entity representing isolated tenant credentials and metadata.
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

namespace NetFusion\Core\Tenancy;

use NetFusion\Core\Tenancy\Contracts\TenantInterface;

class Tenant implements TenantInterface
{
    /**
     * @param string                $id        Unique tenant identifier.
     * @param string                $name      Tenant organization name.
     * @param string|null           $subdomain Subdomain slug (e.g. 'acme').
     * @param string|null           $domain    Custom domain (e.g. 'portal.acme.com').
     * @param string|null           $database  Custom database name.
     * @param bool                  $active    Whether tenant is active.
     * @param array<string, mixed>  $data      Tenant configuration metadata.
     */
    public function __construct(
        public readonly string  $id,
        public readonly string  $name,
        public readonly ?string $subdomain = null,
        public readonly ?string $domain    = null,
        public readonly ?string $database  = null,
        public readonly bool    $active    = true,
        public readonly array   $data      = [],
    ) {
    }

    public function getTenantKey(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSubdomain(): ?string
    {
        return $this->subdomain;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function getDatabaseName(): string
    {
        if ($this->database !== null && $this->database !== '') {
            return $this->database;
        }

        $prefix = function_exists('config') ? (string)config('tenancy.database.prefix', 'tenant_') : 'tenant_';
        return $prefix . ($this->subdomain ?? $this->id);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getMetadata(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}