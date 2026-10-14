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

namespace NetFusion\Core\Consul\Dns;

use NetFusion\Core\Consul\Catalog\ServiceCatalog;
use NetFusion\Core\Consul\Enums\DnsRecordType;
use NetFusion\Core\Consul\Models\DnsMessage;
use NetFusion\Core\Consul\Models\ServiceInstance;

/**
 * Dynamic DNS Resolver for Consul Service Discovery.
 * Resolves standard Consul query formats:
 * - `<service>.service.consul` -> A records (IP addresses)
 * - `_<service>._<protocol>.service.consul` -> SRV records (host, port, priority, weight)
 * - `<tag>.<service>.service.consul` -> A records filtered by tag
 */
class DnsResolver
{
    protected ServiceCatalog $catalog;
    protected string $domain;
    protected int $defaultTtl;

    public function __construct(ServiceCatalog $catalog, string $domain = 'consul', int $defaultTtl = 0)
    {
        $this->catalog = $catalog;
        $this->domain = trim($domain, '.');
        $this->defaultTtl = $defaultTtl;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Resolve a query message and generate response message with answers.
     */
    public function resolve(DnsMessage $query): DnsMessage
    {
        $response = new DnsMessage($query->getId());
        $response->setIsResponse(true);
        $response->setAuthoritative(true);
        $response->setRecursionDesired($query->isRecursionDesired());
        $response->setRecursionAvailable(false);

        foreach ($query->getQuestions() as $question) {
            $qname = $question['name'];
            $qtype = $question['type'];
            $response->addQuestion($qname, $qtype, $question['class']);

            $answers = $this->resolveQuery($qname, $qtype);
            if (empty($answers)) {
                // If no matching records found, mark NXDOMAIN (RCODE = 3) if strictly domain matches
                if (str_ends_with($qname, '.' . $this->domain) || $qname === $this->domain) {
                    $response->setResponseCode(3);
                }
            } else {
                foreach ($answers as $ans) {
                    $response->addAnswer($qname, $ans['type'], $ans['ttl'], $ans['data'], $ans['class'] ?? 1);
                }
            }
        }

        return $response;
    }

    /**
     * Directly resolve a domain name string and return records.
     *
     * @return array<array{name: string, type: DnsRecordType, ttl: int, data: mixed}>
     */
    public function resolveQuery(string $qname, DnsRecordType $qtype): array
    {
        $domainSuffix = '.' . $this->domain;
        $normalized = strtolower(trim($qname, '.'));

        if (!str_ends_with($normalized, $domainSuffix)) {
            return [];
        }

        // Strip ".consul"
        $consulPart = substr($normalized, 0, -strlen($domainSuffix));
        // Expecting something like "web.service" or "v1.web.service" or "_web._tcp.service"
        $parts = explode('.', $consulPart);
        $section = end($parts); // "service" or "node"

        if ($section !== 'service') {
            return [];
        }

        array_pop($parts); // remove "service"
        if (empty($parts)) {
            return [];
        }

        $serviceName = '';
        $tagFilter = null;
        $isSrvFormat = false;

        // Check SRV RFC 2782 format: _<service>._<protocol>
        if (count($parts) >= 2 && str_starts_with($parts[0], '_') && str_starts_with($parts[1], '_')) {
            $isSrvFormat = true;
            $serviceName = ltrim($parts[0], '_');
        } elseif (count($parts) === 1) {
            $serviceName = $parts[0];
        } elseif (count($parts) === 2) {
            // tag.service e.g. production.web
            $tagFilter = $parts[0];
            $serviceName = $parts[1];
        } else {
            $serviceName = end($parts);
        }

        if (!$this->catalog->hasService($serviceName)) {
            return [];
        }

        /** @var array<ServiceInstance> $healthyInstances */
        $healthyInstances = $this->catalog->selectHealthy($serviceName, true);
        if ($tagFilter !== null) {
            $healthyInstances = array_filter($healthyInstances, fn (ServiceInstance $i) => $i->hasTag($tagFilter));
        }

        if (empty($healthyInstances)) {
            return [];
        }

        $records = [];

        // Handle SRV or ANY
        if ($qtype === DnsRecordType::SRV || $isSrvFormat || $qtype === DnsRecordType::ANY) {
            foreach ($healthyInstances as $inst) {
                $records[] = [
                    'name' => $qname,
                    'type' => DnsRecordType::SRV,
                    'ttl' => $this->defaultTtl,
                    'class' => 1,
                    'data' => [
                        'priority' => 1,
                        'weight' => $inst->getEffectiveWeight(),
                        'port' => $inst->getPort(),
                        'target' => $inst->getId() . '.node.' . $this->domain,
                    ],
                ];
            }
        }

        // Handle A or ANY
        if ($qtype === DnsRecordType::A || (!$isSrvFormat && $qtype === DnsRecordType::ANY) || ($qtype === DnsRecordType::A && !$isSrvFormat)) {
            foreach ($healthyInstances as $inst) {
                $records[] = [
                    'name' => $qname,
                    'type' => DnsRecordType::A,
                    'ttl' => $this->defaultTtl,
                    'class' => 1,
                    'data' => $inst->getAddress(),
                ];
            }
        }

        // Handle TXT (Service metadata & tags)
        if ($qtype === DnsRecordType::TXT || $qtype === DnsRecordType::ANY) {
            foreach ($healthyInstances as $inst) {
                $metaStrings = [];
                foreach ($inst->getMeta() as $k => $v) {
                    $metaStrings[] = "{$k}={$v}";
                }
                $tagsString = implode(',', $inst->getTags());
                if (!empty($tagsString)) {
                    $metaStrings[] = "tags={$tagsString}";
                }
                if (!empty($metaStrings)) {
                    $records[] = [
                        'name' => $qname,
                        'type' => DnsRecordType::TXT,
                        'ttl' => $this->defaultTtl,
                        'class' => 1,
                        'data' => implode(';', $metaStrings),
                    ];
                }
            }
        }

        return $records;
    }
}
