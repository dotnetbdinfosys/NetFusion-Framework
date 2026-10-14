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

namespace NetFusion\Core\WAF\IP;

use NetFusion\Core\WAF\Contracts\IPReputationInterface;

class IPReputation implements IPReputationInterface
{
    /**
     * @var array<string, string> Whitelisted IP/CIDR => description
     */
    protected array $whitelist = [];

    /**
     * @var array<string, array{reason: string, expires_at: int}> Blacklisted IP/CIDR => details
     */
    protected array $blacklist = [];

    public function isWhitelisted(string $ip): bool
    {
        if (isset($this->whitelist[$ip])) {
            return true;
        }

        foreach (array_keys($this->whitelist) as $entry) {
            if (str_contains($entry, '/') && $this->matchesCidr($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    public function isBlacklisted(string $ip): bool
    {
        $now = time();

        // 1. Direct IP check
        if (isset($this->blacklist[$ip])) {
            $item = $this->blacklist[$ip];
            if ($item['expires_at'] === 0 || $item['expires_at'] > $now) {
                return true;
            }
            unset($this->blacklist[$ip]);
        }

        // 2. CIDR subnet check
        foreach ($this->blacklist as $entry => $item) {
            if (str_contains($entry, '/')) {
                if ($item['expires_at'] !== 0 && $item['expires_at'] <= $now) {
                    unset($this->blacklist[$entry]);
                    continue;
                }
                if ($this->matchesCidr($ip, $entry)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function addWhitelist(string $ipOrCidr, string $description = ''): void
    {
        $this->whitelist[trim($ipOrCidr)] = $description;
    }

    public function addBlacklist(string $ipOrCidr, string $reason = '', int $ttl = 0): void
    {
        $expiresAt = ($ttl > 0) ? (time() + $ttl) : 0;
        $this->blacklist[trim($ipOrCidr)] = [
            'reason' => $reason,
            'expires_at' => $expiresAt,
        ];
    }

    public function remove(string $ipOrCidr): bool
    {
        $key = trim($ipOrCidr);
        $removed = false;

        if (isset($this->whitelist[$key])) {
            unset($this->whitelist[$key]);
            $removed = true;
        }

        if (isset($this->blacklist[$key])) {
            unset($this->blacklist[$key]);
            $removed = true;
        }

        return $removed;
    }

    public function matchesCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);
        $mask = (int)$mask;

        // IPv4 CIDR matching
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);

            if ($ipLong === false || $subnetLong === false) {
                return false;
            }

            $maskBits = -1 << (32 - $mask);
            return ($ipLong & $maskBits) === ($subnetLong & $maskBits);
        }

        return false;
    }

    public function getWhitelist(): array
    {
        return $this->whitelist;
    }

    public function getBlacklist(): array
    {
        return $this->blacklist;
    }
}
