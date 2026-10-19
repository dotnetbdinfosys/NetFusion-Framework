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
| Purpose   : RBAC User Role, Super Admin authorization, session management,
|             and user profile helper for NetFusion.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\App\Http\Helpers;

use NetFusion\App\Services\ModuleDataRepository;
use NetFusion\Core\Http\Request;
use PDO;
use Throwable;

class AuthHelper
{
    /**
     * Cache active user in memory for request lifecycle.
     *
     * @var array<string, mixed>|null
     */
    protected static ?array $cachedUser = null;

    /**
     * Get the active user's identity data.
     *
     * @return array<string, mixed>
     */
    public static function getCurrentUser(?Request $request = null): array
    {
        return self::user($request);
    }

    /**
     * Resolve the full active user record.
     *
     * @return array<string, mixed>
     */
    public static function user(?Request $request = null): array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $sessionUserId = $_SESSION['user_id'] ?? null;
        $sessionEmail = $_SESSION['user_email'] ?? null;

        // Try loading from MySQL database if session has user_id or user_email
        if ($sessionUserId !== null || $sessionEmail !== null) {
            try {
                $pdo = ModuleDataRepository::getPdo();
                $stmt = $pdo->prepare("SELECT * FROM `system_users` WHERE `id` = ? OR `email` = ? LIMIT 1");
                $stmt->execute([(string) $sessionUserId, (string) $sessionEmail]);
                $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($dbUser) {
                    $role = self::getCurrentRole($request, $dbUser['role'] ?? 'super_admin');
                    self::$cachedUser = [
                        'id'            => (string) $dbUser['id'],
                        'name'          => (string) ($dbUser['name'] ?? 'Md. Atiquzzaman Ashish'),
                        'email'         => (string) ($dbUser['email'] ?? 'ashish@dotnetbd.com'),
                        'role'          => $role,
                        'role_label_bn' => self::getRoleLabelBn($role),
                        'tenant_id'     => (string) ($dbUser['tenant_id'] ?? 'tenant_primary'),
                        'avatar_url'    => (string) ($dbUser['avatar_url'] ?? ''),
                        'phone'         => (string) ($dbUser['phone'] ?? '+880 1711-000000'),
                        'bio'           => (string) ($dbUser['bio'] ?? 'Lead Software Engineer & Systems Architect at DotNetBD'),
                        'status'        => (string) ($dbUser['status'] ?? 'Active'),
                        'last_login_at' => (string) ($dbUser['last_login_at'] ?? date('Y-m-d H:i:s')),
                        'created_at'    => (string) ($dbUser['created_at'] ?? '2026-09-01'),
                        'is_auth'       => true,
                    ];
                    return self::$cachedUser;
                }
            } catch (Throwable $e) {
                // fall back to session/defaults
            }
        }

        // Session or request fallback
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

        self::$cachedUser = [
            'id'            => $_SESSION['user_id'] ?? 'usr_001',
            'name'          => $name,
            'email'         => $email,
            'role'          => $role,
            'role_label_bn' => self::getRoleLabelBn($role),
            'tenant_id'     => $_SESSION['tenant_id'] ?? 'tenant_primary',
            'avatar_url'    => $_SESSION['avatar_url'] ?? '',
            'phone'         => '+880 1711-000000',
            'bio'           => 'Lead Software Engineer & Systems Architect at DotNetBD',
            'status'        => 'Active',
            'last_login_at' => date('Y-m-d H:i:s'),
            'created_at'    => '2026-09-01',
            'is_auth'       => !empty($_SESSION['authenticated']),
        ];

        return self::$cachedUser;
    }

    /**
     * Check if user is authenticated.
     */
    public static function check(?Request $request = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        return !empty($_SESSION['authenticated']) || !empty($_SESSION['user_id']);
    }

    /**
     * Get active user ID.
     */
    public static function id(?Request $request = null): ?string
    {
        return self::user($request)['id'] ?? null;
    }

    /**
     * Authenticate user into session.
     *
     * @param array<string, mixed> $user
     */
    public static function login(array $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = $user['id'] ?? 'usr_001';
        $_SESSION['user_name'] = $user['name'] ?? 'User';
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_role'] = $user['role'] ?? 'super_admin';
        $_SESSION['tenant_id'] = $user['tenant_id'] ?? 'tenant_primary';
        $_SESSION['avatar_url'] = $user['avatar_url'] ?? '';

        self::$cachedUser = null;
    }

    /**
     * Log user out by clearing session authentication.
     */
    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        unset(
            $_SESSION['authenticated'],
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['user_email'],
            $_SESSION['user_role'],
            $_SESSION['tenant_id'],
            $_SESSION['avatar_url']
        );

        self::$cachedUser = null;
    }

    /**
     * Resolve the active user role.
     * Hierarchy: Session -> Request Simulation (Header / Query) -> Default (super_admin for Master Portal).
     */
    public static function getCurrentRole(?Request $request = null, ?string $fallback = null): string
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

        // 3. Fallback provided or default
        return $fallback ?? 'super_admin';
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

    /**
     * Render the Topbar User Dropdown Widget.
     *
     * @param bool $isBn
     * @param Request|null $request
     * @return string
     */
    public static function renderUserDropdown(bool $isBn = false, ?Request $request = null): string
    {
        $user = self::user($request);
        $name = htmlspecialchars($user['name'] ?? 'User');
        $role = $user['role'] ?? 'super_admin';
        $roleLabel = htmlspecialchars($isBn ? self::getRoleLabelBn($role) : ucfirst(str_replace('_', ' ', $role)));
        $email = htmlspecialchars($user['email'] ?? '');
        $initial = strtoupper(mb_substr($user['name'] ?? 'A', 0, 1));
        $avatarUrl = $user['avatar_url'] ?? '';

        $avatarHtml = !empty($avatarUrl)
            ? sprintf('<img src="%s" alt="%s" class="nf-avatar-img">', htmlspecialchars($avatarUrl), $name)
            : sprintf('<div class="nf-avatar-circle">%s</div>', $initial);

        $lblProfile  = $isBn ? 'আমার প্রোফাইল' : 'My Profile';
        $lblSettings = $isBn ? 'সিস্টেম সেটিংস' : 'System Settings';
        $lblModules  = $isBn ? 'মডিউল ড্যাশবোর্ড' : 'Modules Portal';
        $lblLogout   = $isBn ? 'লগআউট' : 'Sign Out';
        $lblTenant   = $isBn ? 'টেন্যান্ট' : 'Tenant';

        return '
        <div class="nf-user-menu-wrapper" id="nfUserMenuWrapper">
            <button type="button" class="nf-user-pill-btn" onclick="nfToggleUserDropdown(event)" aria-haspopup="true" aria-expanded="false" title="' . $name . '">
                ' . $avatarHtml . '
                <div class="nf-user-info-text">
                    <span class="nf-user-name">' . $name . '</span>
                    <span class="nf-user-role-badge">' . $roleLabel . '</span>
                </div>
                <span class="nf-dropdown-arrow">▾</span>
            </button>

            <div class="nf-user-dropdown-panel" id="nfUserDropdownPanel">
                <div class="nf-dropdown-header">
                    ' . $avatarHtml . '
                    <div style="overflow:hidden;">
                        <strong class="nf-dropdown-header-name">' . $name . '</strong>
                        <div class="nf-dropdown-header-email">' . $email . '</div>
                        <div class="nf-dropdown-header-tenant">' . $lblTenant . ': <code>' . htmlspecialchars($user['tenant_id'] ?? 'primary') . '</code></div>
                    </div>
                </div>

                <div class="nf-dropdown-divider"></div>

                <a href="/profile" class="nf-dropdown-item">
                    <span class="nf-menu-icon">👤</span>
                    <span class="nf-menu-text">' . $lblProfile . '</span>
                </a>
                <a href="/admin/dashboard" class="nf-dropdown-item">
                    <span class="nf-menu-icon">⚡</span>
                    <span class="nf-menu-text">' . $lblModules . '</span>
                </a>
                <a href="/admin/dashboard#settings" class="nf-dropdown-item">
                    <span class="nf-menu-icon">⚙️</span>
                    <span class="nf-menu-text">' . $lblSettings . '</span>
                </a>

                <div class="nf-dropdown-divider"></div>

                <a href="/logout" class="nf-dropdown-item nf-item-danger">
                    <span class="nf-menu-icon">🚪</span>
                    <span class="nf-menu-text">' . $lblLogout . '</span>
                </a>
            </div>
        </div>
        ';
    }

    /**
     * Get shared CSS styling for User Dropdown Widget.
     */
    public static function renderDropdownStyles(): string
    {
        return '
        /* NetFusion Modern User Profile Dropdown Styles */
        .nf-user-menu-wrapper {
            position: relative;
            display: inline-block;
            font-family: inherit;
        }
        .nf-user-pill-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 12px 4px 6px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
            text-align: left;
            color: #1e293b;
        }
        .nf-user-pill-btn:hover, .nf-user-pill-btn:focus {
            background: #f8fafc;
            border-color: #cbd5e1;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .nf-avatar-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13.5px;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(37,99,235,0.25);
        }
        .nf-avatar-img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #cbd5e1;
            flex-shrink: 0;
        }
        .nf-user-info-text {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
            max-width: 150px;
        }
        .nf-user-name {
            font-size: 12.5px;
            font-weight: 600;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .nf-user-role-badge {
            font-size: 10.5px;
            color: #64748b;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .nf-dropdown-arrow {
            font-size: 10px;
            color: #94a3b8;
            margin-left: 2px;
        }
        .nf-user-dropdown-panel {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 250px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            padding: 8px 0;
            z-index: 1000;
            animation: nfFadeIn 0.15s ease-out;
        }
        .nf-user-dropdown-panel.show {
            display: block;
        }
        .nf-dropdown-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px 12px;
        }
        .nf-dropdown-header-name {
            display: block;
            font-size: 13.5px;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .nf-dropdown-header-email {
            font-size: 11.5px;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .nf-dropdown-header-tenant {
            font-size: 10.5px;
            color: #94a3b8;
            margin-top: 2px;
        }
        .nf-dropdown-header-tenant code {
            background: #f1f5f9;
            padding: 1px 4px;
            border-radius: 4px;
            font-size: 10px;
            color: #2563eb;
        }
        .nf-dropdown-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 6px 0;
        }
        .nf-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 16px;
            color: #334155;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .nf-dropdown-item:hover {
            background: #f8fafc;
            color: #2563eb;
        }
        .nf-dropdown-item.nf-item-danger {
            color: #dc2626;
        }
        .nf-dropdown-item.nf-item-danger:hover {
            background: #fef2f2;
            color: #b91c1c;
        }
        .nf-menu-icon {
            font-size: 15px;
            width: 18px;
            text-align: center;
        }
        @keyframes nfFadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        ';
    }

    /**
     * Get shared JavaScript for User Dropdown Widget.
     */
    public static function renderDropdownScript(): string
    {
        return '
        function nfToggleUserDropdown(event) {
            if (event) {
                event.stopPropagation();
            }
            var panel = document.getElementById("nfUserDropdownPanel");
            var btn = document.querySelector(".nf-user-pill-btn");
            if (!panel) return;
            var isShown = panel.classList.contains("show");
            if (isShown) {
                panel.classList.remove("show");
                if (btn) btn.setAttribute("aria-expanded", "false");
            } else {
                panel.classList.add("show");
                if (btn) btn.setAttribute("aria-expanded", "true");
            }
        }

        document.addEventListener("click", function(e) {
            var wrapper = document.getElementById("nfUserMenuWrapper");
            var panel = document.getElementById("nfUserDropdownPanel");
            if (wrapper && panel && !wrapper.contains(e.target)) {
                panel.classList.remove("show");
                var btn = document.querySelector(".nf-user-pill-btn");
                if (btn) btn.setAttribute("aria-expanded", "false");
            }
        });
        ';
    }
}
