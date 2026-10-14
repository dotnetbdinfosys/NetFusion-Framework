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

namespace NetFusion\Core\IaC\Providers\Aws;

use NetFusion\Core\IaC\Providers\Contracts\ResourceProviderInterface;

/**
 * Amazon Web Services (AWS) infrastructure provider driver.
 */
class AwsProvider implements ResourceProviderInterface
{
    private string $region;

    /**
     * @var array<string, array<string, mixed>> In-memory resource registry: [id => attributes]
     */
    private array $liveResources = [];

    public function __construct(string $region = 'us-east-1')
    {
        $this->region = $region;
    }

    public function getName(): string
    {
        return 'aws';
    }

    public function supports(string $resourceType): bool
    {
        return str_starts_with($resourceType, 'aws_');
    }

    public function create(string $type, string $name, array $attributes): array
    {
        $id = match ($type) {
            'aws_vpc' => 'vpc-' . substr(md5($name . microtime()), 0, 17),
            'aws_subnet' => 'subnet-' . substr(md5($name . microtime()), 0, 17),
            'aws_security_group' => 'sg-' . substr(md5($name . microtime()), 0, 17),
            'aws_instance' => 'i-' . substr(md5($name . microtime()), 0, 17),
            'aws_s3_bucket' => $attributes['bucket'] ?? ('bucket-' . strtolower($name)),
            default => 'res-' . substr(md5($name . microtime()), 0, 12),
        };

        $arn = "arn:aws:{$type}:{$this->region}:123456789012:{$id}";
        $computed = array_merge($attributes, [
            'id' => $id,
            'arn' => $arn,
            'region' => $this->region,
            'created_at' => time(),
        ]);

        if ($type === 'aws_instance') {
            $computed['public_ip'] = '54.' . rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254);
            $computed['private_ip'] = '10.0.' . rand(1, 254) . '.' . rand(1, 254);
            $computed['instance_state'] = 'running';
        } elseif ($type === 'aws_vpc') {
            $computed['default_route_table_id'] = 'rtb-' . substr(md5('rtb' . $id), 0, 17);
            $computed['default_network_acl_id'] = 'acl-' . substr(md5('acl' . $id), 0, 17);
        }

        $this->liveResources[$id] = $computed;
        return $computed;
    }

    public function read(string $type, string $id): ?array
    {
        return $this->liveResources[$id] ?? null;
    }

    public function update(string $type, string $id, array $attributes): array
    {
        $existing = $this->liveResources[$id] ?? ['id' => $id];
        $updated = array_merge($existing, $attributes, ['updated_at' => time()]);
        $this->liveResources[$id] = $updated;
        return $updated;
    }

    public function delete(string $type, string $id): bool
    {
        unset($this->liveResources[$id]);
        return true;
    }
}
