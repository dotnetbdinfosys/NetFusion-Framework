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

namespace NetFusion\Core\Security\Vault;

class DynamicSecretGenerator
{
    /**
     * Generate dynamic credentials based on path or template.
     *
     * @return array<string, mixed>
     */
    public static function generate(string $path, array $options = []): array
    {
        $prefix = $options['prefix'] ?? 'v_usr_';
        $randSuffix = bin2hex(random_bytes(6));
        $username = $prefix . $randSuffix;
        $password = bin2hex(random_bytes(16));

        if (str_starts_with($path, 'database/')) {
            return [
                'type' => 'database',
                'username' => $username,
                'password' => $password,
                'roles' => (array)($options['roles'] ?? ['readwrite']),
            ];
        }

        if (str_starts_with($path, 'api/')) {
            return [
                'type' => 'api_token',
                'token' => 'nvault_' . bin2hex(random_bytes(24)),
                'scope' => (string)($options['scope'] ?? 'read:all'),
            ];
        }

        return [
            'type' => 'generic_dynamic',
            'username' => $username,
            'password' => $password,
            'token' => bin2hex(random_bytes(20)),
        ];
    }
}
