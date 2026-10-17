<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Http/Helpers/AuthHelper.php
| Module    : Helpers
| Namespace : NetFusion\App\Http\Helpers;
| Purpose   : RBAC User Role and Super Admin authorization helper for NetFusion.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\App\Http\Helpers;

use NetFusion\Core\Http\Request;

class AuthHelper
{
    /**
     * Get the active user's identity data.
     *
     * @return array<string, mixed>
     */
    public static function getCurrentUser(?Request $request = null): array
    {
        $role = self::getCurrentRole($request);
        $name = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Md. Atiquzzaman Ashish';
        $email = $_SESSION['user_email'] ?? 'ashish@dotnetbd.com';

        if ($request !== null) {
            $inputName = $request->input('user_name');
            if ($inputName) {
                $name = (string) $inputName;
            }
            $inputEmail = $request->input('user_email');
            if ($inputEmail) {
                $email = (string) $inputEmail;
            }
        }

        return [
            'name'  => $name,
            'email' => $email,
            'role'  => $role,
        ];
    }

    /**
     * Resolve the active user role.
     * Hierarchy: Session -> Request Simulation (Header / Query) -> Default (super_admin for Master Portal).
     */
    public static function getCurrentRole(?Request $request = null): string
    {
        // 1. Check Request override / simulation (useful for testing or persona switching)
        if ($request !== null) {
            $headerRole = $request->header('X-User-Role');
            if ($headerRole !== null && $headerRole !== '') {
                return strtolower(trim((string) $headerRole));
            }

            $queryRole = $request->input('as_role') ?? $request->input('user_role');
            if ($queryRole !== null && $queryRole !== '') {
                return strtolower(trim((string) $queryRole));
            }
        }

        // 2. Check Session
        if (isset($_SESSION['user_role']) && !empty($_SESSION['user_role'])) {
            return strtolower(trim((string) $_SESSION['user_role']));
        }
        if (isset($_SESSION['role']) && !empty($_SESSION['role'])) {
            return strtolower(trim((string) $_SESSION['role']));
        }

        // 3. Master admin portal fallback
        return 'super_admin';
    }

    /**
     * Check if the current user is Super Admin.
     */
    public static function isSuperAdmin(?Request $request = null): bool
    {
        return self::getCurrentRole($request) === 'super_admin';
    }

    /**
     * Get human-readable Bengali label for role.
     */
    public static function getRoleLabelBn(string $role): string
    {
        return match (strtolower(trim($role))) {
            'super_admin'  => 'সুপার অ্যাডমিন (Super Admin)',
            'tenant_admin' => 'টেন্যান্ট অ্যাডমিন (Tenant Admin)',
            'manager'      => 'ম্যানেজার (Manager)',
            'staff'        => 'স্টাফ / অফিসার (Staff)',
            'client'       => 'ক্লায়েন্ট / গ্রাহক (Client)',
            default        => 'ব্যবহারকারী (' . htmlspecialchars($role) . ')',
        };
    }
}
