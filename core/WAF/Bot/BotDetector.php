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

use NetFusion\Core\WAF\IP\ThreatContext;

class BotDetector
{
    /**
     * Regex patterns for identifying known automated vulnerability scanners & aggressive scrapers.
     * @var array<string, string>
     */
    protected array $badScannerSignatures = [
        'sqlmap' => '/\bsqlmap\b/i',
        'nikto' => '/\bnikto\b/i',
        'acunetix' => '/\bacunetix\b/i',
        'gobuster' => '/\bgobuster\b/i',
        'dirbuster' => '/\bdirbuster\b/i',
        'nmap' => '/\bnmap\b/i',
        'masscan' => '/\bmasscan\b/i',
        'nessus' => '/\bnessus\b/i',
        'metasploit' => '/\bmetasploit\b/i',
        'wpscan' => '/\bwpscan\b/i',
        'openvas' => '/\bopenvas\b/i',
        'havij' => '/\bhavij\b/i',
    ];

    /**
     * Regex patterns for automated headless browsers and automation frameworks.
     * @var array<string, string>
     */
    protected array $headlessSignatures = [
        'headless_chrome' => '/\bHeadlessChrome\b/i',
        'phantomjs' => '/\bPhantomJS\b/i',
        'puppeteer' => '/\bpuppeteer\b/i',
        'selenium' => '/\bselenium\b/i',
        'playwright' => '/\bplaywright\b/i',
    ];

    /**
     * @param JA3Fingerprinter|null $ja3Fingerprinter
     */
    public function __construct(
        protected ?JA3Fingerprinter $ja3Fingerprinter = null
    ) {
        $this->ja3Fingerprinter = $this->ja3Fingerprinter ?? new JA3Fingerprinter();
    }

    /**
     * Inspect request context for bot characteristics.
     *
     * @return array{is_bot: bool, is_bad_bot: bool, bot_type: ?string, description: ?string, score: float}
     */
    public function detect(ThreatContext $context): array
    {
        $headers = $context->getHeaders();
        $userAgent = (string)($headers['user-agent'] ?? $headers['User-Agent'] ?? '');
        $ja3Hash = $context->getJa3Hash();

        // 1. Check known malicious JA3 TLS fingerprint
        if ($ja3Hash !== null && $this->ja3Fingerprinter->isMalicious($ja3Hash)) {
            return [
                'is_bot' => true,
                'is_bad_bot' => true,
                'bot_type' => 'malicious_ja3',
                'description' => "Known malicious JA3 TLS fingerprint: [{$ja3Hash}]",
                'score' => 5.0,
            ];
        }

        // 2. Check aggressive vulnerability scanners
        foreach ($this->badScannerSignatures as $name => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return [
                    'is_bot' => true,
                    'is_bad_bot' => true,
                    'bot_type' => 'security_scanner',
                    'description' => "Malicious automated vulnerability scanner: [{$name}]",
                    'score' => 5.0,
                ];
            }
        }

        // 3. Check headless browser frameworks
        foreach ($this->headlessSignatures as $name => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return [
                    'is_bot' => true,
                    'is_bad_bot' => false,
                    'bot_type' => 'headless_browser',
                    'description' => "Automated headless browser detected: [{$name}]",
                    'score' => 3.0,
                ];
            }
        }

        // 4. Missing User-Agent check (Informational only, not flagged as bot by default)
        if (trim($userAgent) === '') {
            return [
                'is_bot' => false,
                'is_bad_bot' => false,
                'bot_type' => null,
                'description' => null,
                'score' => 0.0,
            ];
        }

        return [
            'is_bot' => false,
            'is_bad_bot' => false,
            'bot_type' => null,
            'description' => null,
            'score' => 0.0,
        ];
    }
}
