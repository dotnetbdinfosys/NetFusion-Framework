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

namespace NetFusion\Core\Security\Vault\Encryption;

use InvalidArgumentException;
use NetFusion\Core\Security\Vault\Encryption\Contracts\KeyProviderInterface;

class LocalKeyProvider implements KeyProviderInterface
{
    /** @var array<int, string> Version => 32-byte Binary Master Key */
    protected array $keys = [];

    protected int $activeVersion = 1;

    public function __construct(array $keys = [], ?int $activeVersion = null)
    {
        if (empty($keys)) {
            // Generate initial version 1 key
            $this->keys[1] = random_bytes(32);
            $this->activeVersion = 1;
        } else {
            foreach ($keys as $ver => $k) {
                $v = (int)$ver;
                $this->keys[$v] = strlen($k) === 32 ? $k : hash('sha256', (string)$k, true);
            }
            $this->activeVersion = $activeVersion ?? max(array_keys($this->keys));
        }
    }

    public function getMasterKey(int $version): string
    {
        if (!isset($this->keys[$version])) {
            throw new InvalidArgumentException("Master Key version {$version} does not exist in key provider.");
        }
        return $this->keys[$version];
    }

    public function getActiveVersion(): int
    {
        return $this->activeVersion;
    }

    public function rotateMasterKey(): int
    {
        $newVersion = max(array_keys($this->keys)) + 1;
        $this->keys[$newVersion] = random_bytes(32);
        $this->activeVersion = $newVersion;
        return $newVersion;
    }

    public function hasVersion(int $version): bool
    {
        return isset($this->keys[$version]);
    }

    public function setKey(int $version, string $key, bool $makeActive = false): self
    {
        $this->keys[$version] = strlen($key) === 32 ? $key : hash('sha256', $key, true);
        if ($makeActive) {
            $this->activeVersion = $version;
        }
        return $this;
    }

    /**
     * @return int[]
     */
    public function getVersions(): array
    {
        return array_keys($this->keys);
    }
}
