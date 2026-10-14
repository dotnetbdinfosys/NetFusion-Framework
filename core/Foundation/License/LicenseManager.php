<?php
/**
 * NetFusion Framework - Enterprise License Fabric
 *
 * Core manager integrating DotNetBD License CMS (API Engine v5.1.8).
 * Handles license activation, remote heartbeat validation, caching,
 * server hash binding, and software update checks.
 *
 * @package    NetFusion\Core\Foundation\License
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Foundation\License;

use NetFusion\Core\Foundation\License\Exceptions\LicenseException;

class LicenseManager
{
    protected string $serverUrl;
    protected int $productId;
    protected string $salt;
    protected int $cacheTtl;
    protected string $storagePath;
    protected int $gracePeriod;

    /**
     * @param array<string, mixed>|null $config Optional custom config
     */
    public function __construct(?array $config = null)
    {
        $defaultConfig = [];
        $configFile = dirname(__DIR__, 3) . '/config/license.php';
        if (file_exists($configFile)) {
            $defaultConfig = require $configFile;
        }

        $cfg = array_merge([
            'server_url'   => 'https://lic.dotnetbd.com',
            'product_id'   => 1,
            'salt'         => 'DotNetBD_NetFusion_Secure_Salt_2026',
            'cache_ttl'    => 86400, // 24 hours
            'storage_path' => dirname(__DIR__, 3) . '/storage/framework/license.lic',
            'grace_period' => 86400 * 3, // 3 days
        ], $defaultConfig, $config ?? []);

        $this->serverUrl   = rtrim((string) $cfg['server_url'], '/');
        $this->productId   = (int) $cfg['product_id'];
        $this->salt        = (string) $cfg['salt'];
        $this->cacheTtl    = (int) $cfg['cache_ttl'];
        $this->storagePath = (string) $cfg['storage_path'];
        $this->gracePeriod = (int) $cfg['grace_period'];
    }

    /**
     * Activate software license against DotNetBD License CMS.
     *
     * @param string      $licenseKey   20-character license key
     * @param string      $companyName  Exact registered company name
     * @param string|null $domain       Optional domain (auto-detected if omitted)
     * @param string|null $ip           Optional server IP address
     * @return array<string, mixed>     Activation response
     * @throws LicenseException
     */
    public function activate(
        string $licenseKey,
        string $companyName,
        ?string $domain = null,
        ?string $ip = null
    ): array {
        $licenseKey = trim($licenseKey);
        $companyName = trim($companyName);

        if (empty($licenseKey)) {
            throw new LicenseException('License key cannot be empty.');
        }
        if (empty($companyName)) {
            throw new LicenseException('Company name cannot be empty.');
        }

        $domain = $domain ?: $this->detectDomain();
        $serverHash = md5($domain . $this->salt);

        $payload = [
            'license_key'  => $licenseKey,
            'company_name' => $companyName,
            'domain'       => $domain,
            'server_hash'  => $serverHash,
            'product_id'   => $this->productId,
        ];
        if ($ip) {
            $payload['ip'] = $ip;
        }

        $apiUrl = $this->serverUrl . '/api/activate.php';
        $response = $this->httpPost($apiUrl, $payload);

        if (!isset($response['success']) || $response['success'] !== true) {
            $errorMsg = $response['message'] ?? 'License activation failed on remote server.';
            throw new LicenseException($errorMsg);
        }

        // Save local encrypted/signed license record
        $licenseData = [
            'license_key'        => $licenseKey,
            'company_name'       => $companyName,
            'domain'             => $domain,
            'server_hash'        => $serverHash,
            'product_id'         => $this->productId,
            'max_servers'        => $response['max_servers'] ?? 1,
            'max_extensions'     => $response['max_extensions'] ?? 0,
            'expires_at'         => $response['expires_at'] ?? 'lifetime',
            'activated_at'       => date('Y-m-d H:i:s'),
            'last_validated_at'  => date('Y-m-d H:i:s'),
            'signature'          => hash_hmac('sha256', $licenseKey . '|' . $domain . '|' . ($response['expires_at'] ?? ''), $this->salt),
        ];

        $this->saveLicenseFile($licenseData);

        return [
            'success'               => true,
            'message'               => $response['message'] ?? 'License activated successfully',
            'license_key'           => $licenseKey,
            'company_name'          => $companyName,
            'domain'                => $domain,
            'max_servers'           => $response['max_servers'] ?? 1,
            'max_extensions'        => $response['max_extensions'] ?? 0,
            'extension_limit_label' => $response['extension_limit_label'] ?? 'Enterprise',
            'expires_at'            => $response['expires_at'] ?? 'lifetime',
        ];
    }

    /**
     * Validate an existing active license.
     * Uses 24-hour local response caching to prevent latency and handle network blips.
     *
     * @param bool $forceRemote Force remote server heartbeat verification
     * @return array<string, mixed>
     */
    public function validate(bool $forceRemote = false): array
    {
        $local = $this->loadLicenseFile();
        if (!$local) {
            return [
                'valid'   => false,
                'message' => 'No license installed. Please run "php netfusion license:activate <KEY> <COMPANY>".',
            ];
        }

        // 1. Verify local integrity signature
        $expectedSig = hash_hmac('sha256', $local['license_key'] . '|' . $local['domain'] . '|' . ($local['expires_at'] ?? ''), $this->salt);
        if (!hash_equals($expectedSig, $local['signature'] ?? '')) {
            return [
                'valid'   => false,
                'message' => 'License tampering detected. Local signature verification failed.',
            ];
        }

        // 2. Check local expiration
        if (!empty($local['expires_at']) && strtolower($local['expires_at']) !== 'lifetime') {
            $expTimestamp = strtotime($local['expires_at']);
            if ($expTimestamp !== false && time() > $expTimestamp) {
                return [
                    'valid'      => false,
                    'message'    => "License expired on {$local['expires_at']}.",
                    'expires_at' => $local['expires_at'],
                ];
            }
        }

        // 3. Remote heartbeat check if cache expired or forceRemote
        $lastChecked = isset($local['last_validated_at']) ? strtotime($local['last_validated_at']) : 0;
        $needsRemoteCheck = $forceRemote || ((time() - $lastChecked) >= $this->cacheTtl);

        if ($needsRemoteCheck) {
            try {
                $apiUrl = $this->serverUrl . '/api/validate.php';
                $payload = [
                    'license_key' => $local['license_key'],
                    'domain'      => $local['domain'],
                    'product_id'  => $local['product_id'] ?? $this->productId,
                ];

                $remoteRes = $this->httpPost($apiUrl, $payload);

                if (isset($remoteRes['valid']) && $remoteRes['valid'] === true) {
                    $local['last_validated_at'] = date('Y-m-d H:i:s');
                    if (isset($remoteRes['expires_at'])) {
                        $local['expires_at'] = $remoteRes['expires_at'];
                        $local['signature'] = hash_hmac('sha256', $local['license_key'] . '|' . $local['domain'] . '|' . $remoteRes['expires_at'], $this->salt);
                    }
                    $this->saveLicenseFile($local);
                } elseif (isset($remoteRes['valid']) && $remoteRes['valid'] === false) {
                    return [
                        'valid'   => false,
                        'message' => $remoteRes['message'] ?? 'License revoked or invalid on remote server.',
                    ];
                }
            } catch (\Throwable $e) {
                // If remote server unreachable, allow grace period
                $graceRemaining = $this->gracePeriod - (time() - $lastChecked);
                if ($graceRemaining <= 0) {
                    return [
                        'valid'   => false,
                        'message' => 'Remote validation server unreachable and grace period expired.',
                    ];
                }
            }
        }

        return [
            'valid'         => true,
            'message'       => 'License is active and valid.',
            'license_key'   => $local['license_key'],
            'company_name'  => $local['company_name'] ?? 'DotNetBD Client',
            'domain'        => $local['domain'],
            'expires_at'    => $local['expires_at'] ?? 'lifetime',
            'days_left'     => $this->calculateDaysRemaining($local['expires_at'] ?? null),
        ];
    }

    /**
     * Check current license status details.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $local = $this->loadLicenseFile();
        if (!$local) {
            return [
                'is_active'   => false,
                'status'      => 'UNLICENSED',
                'message'     => 'Framework is currently running unlicensed.',
            ];
        }

        $validation = $this->validate(false);

        return [
            'is_active'         => $validation['valid'] ?? false,
            'status'            => ($validation['valid'] ?? false) ? 'ACTIVE' : 'EXPIRED_OR_INVALID',
            'message'           => $validation['message'] ?? '',
            'license_key'       => $local['license_key'],
            'company_name'      => $local['company_name'] ?? 'N/A',
            'domain'            => $local['domain'] ?? 'N/A',
            'product_id'        => $local['product_id'] ?? $this->productId,
            'max_servers'       => $local['max_servers'] ?? 1,
            'expires_at'        => $local['expires_at'] ?? 'lifetime',
            'days_remaining'    => $this->calculateDaysRemaining($local['expires_at'] ?? null),
            'activated_at'      => $local['activated_at'] ?? 'N/A',
            'last_validated_at' => $local['last_validated_at'] ?? 'N/A',
        ];
    }

    /**
     * Check for software updates via DotNetBD License CMS Auto-Updater API.
     *
     * @return array<string, mixed>
     */
    public function checkForUpdate(): array
    {
        $local = $this->loadLicenseFile();
        $licenseKey = $local['license_key'] ?? '';
        $domain = $local['domain'] ?? $this->detectDomain();

        $url = sprintf(
            '%s/api/check_update.php?license_key=%s&domain=%s',
            $this->serverUrl,
            urlencode($licenseKey),
            urlencode($domain)
        );

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            return is_string($response) ? (json_decode($response, true) ?: []) : [];
        } catch (\Throwable) {
            return ['has_update' => false, 'error' => 'Could not connect to update server'];
        }
    }

    /**
     * Remove local license file (deactivate).
     */
    public function deactivate(): bool
    {
        if (file_exists($this->storagePath)) {
            return @unlink($this->storagePath);
        }
        return true;
    }

    /**
     * Auto-detect the current server hostname or domain.
     */
    public function detectDomain(): string
    {
        if (!empty($_SERVER['HTTP_HOST'])) {
            $host = (string) $_SERVER['HTTP_HOST'];
            return preg_replace('/:\d+$/', '', $host) ?: 'localhost';
        }
        if (!empty($_SERVER['SERVER_NAME'])) {
            return (string) $_SERVER['SERVER_NAME'];
        }
        $hostname = gethostname();
        return $hostname ?: 'localhost';
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    protected function saveLicenseFile(array $data): void
    {
        $dir = dirname($this->storagePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $encoded = base64_encode(json_encode($data, JSON_UNESCAPED_SLASHES));
        file_put_contents($this->storagePath, $encoded, LOCK_EX);
    }

    protected function loadLicenseFile(): ?array
    {
        if (!file_exists($this->storagePath)) {
            return null;
        }

        $raw = (string) file_get_contents($this->storagePath);
        $decoded = base64_decode($raw, true);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);
        return is_array($data) ? $data : null;
    }

    protected function calculateDaysRemaining(?string $expiresAt): ?int
    {
        if (!$expiresAt || strtolower($expiresAt) === 'lifetime') {
            return null; // Lifetime
        }
        $ts = strtotime($expiresAt);
        if ($ts === false) {
            return null;
        }
        $diff = $ts - time();
        return (int) ceil($diff / 86400);
    }

    /**
     * Send HTTP POST request via cURL.
     *
     * @param string               $url
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function httpPost(string $url, array $payload): array
    {
        if (!function_exists('curl_init')) {
            throw new LicenseException('PHP cURL extension is required for DotNetBD license verification.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: NetFusion-Framework/1.0.0 (DotNetBD Engine v5.1.8 Client)',
                'Accept: application/json',
            ],
        ]);

        $rawBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($rawBody === false || !empty($curlError)) {
            throw new LicenseException("Network communication error with License Server: {$curlError}");
        }

        $data = json_decode((string) $rawBody, true);
        if (!is_array($data)) {
            throw new LicenseException("Invalid JSON response from License Server (HTTP {$httpCode}). Raw response: " . substr((string) $rawBody, 0, 200));
        }

        return $data;
    }
}
