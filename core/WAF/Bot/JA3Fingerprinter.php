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

namespace NetFusion\Core\WAF\Bot;

class JA3Fingerprinter
{
    /**
     * Database of known malicious JA3 hashes (e.g. Sqlmap, Mirai, Scrapers).
     * @var array<string, string>
     */
    protected array $knownMaliciousHashes = [
        'bb4f677d7de7091394a45a33c1683d3b' => 'Sqlmap Automated Attack Tool',
        'e7d705a3286e19ea42f587b344ee6865' => 'Python Requests Default Scraper',
        '6734f37431670b3ab4292b8f60f29f51' => 'Go-http-client Scanner',
        'b32309a26951912be7dba376398abc3b' => 'Cobalt Strike Beacon',
        '51c64c77e60f39ac3e179af7b3e5510f' => 'Nmap Scripting Engine (NSE)',
    ];

    /**
     * Compute JA3 fingerprint string and MD5 hash.
     *
     * @param int $sslVersion e.g. 771 (TLS 1.2) or 772 (TLS 1.3)
     * @param array<int> $ciphers List of cipher suite IDs
     * @param array<int> $extensions List of extension IDs
     * @param array<int> $curves List of supported elliptic curves
     * @param array<int> $pointFormats List of point formats
     * @return array{raw: string, hash: string, is_malicious: bool, description: ?string}
     */
    public function compute(
        int $sslVersion,
        array $ciphers,
        array $extensions = [],
        array $curves = [],
        array $pointFormats = []
    ): array {
        $raw = implode(',', [
            $sslVersion,
            implode('-', $ciphers),
            implode('-', $extensions),
            implode('-', $curves),
            implode('-', $pointFormats),
        ]);

        $hash = md5($raw);
        $isMalicious = isset($this->knownMaliciousHashes[$hash]);
        $desc = $this->knownMaliciousHashes[$hash] ?? null;

        return [
            'raw' => $raw,
            'hash' => $hash,
            'is_malicious' => $isMalicious,
            'description' => $desc,
        ];
    }

    /**
     * Register a new known malicious JA3 hash.
     */
    public function addMaliciousHash(string $hash, string $description): void
    {
        $this->knownMaliciousHashes[strtolower($hash)] = $description;
    }

    /**
     * Check if a JA3 hash is flagged as malicious.
     */
    public function isMalicious(string $hash): bool
    {
        return isset($this->knownMaliciousHashes[strtolower($hash)]);
    }

    public function getKnownHashes(): array
    {
        return $this->knownMaliciousHashes;
    }
}
