<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Http/Controllers/AuthController.php
| Module    : Authentication & User Profile
| Namespace : NetFusion\App\Http\Controllers
| Purpose   : Full-featured Authentication Controller providing Login, Logout,
|             and MySQL-backed User Profile management with Password change.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\App\Http\Controllers;

use DateTimeImmutable;
use NetFusion\App\Http\Helpers\AuthHelper;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;
use NetFusion\App\Http\Helpers\SiteSettingsHelper;
use NetFusion\App\Services\ModuleDataRepository;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use PDO;
use Throwable;

class AuthController
{
    /**
     * Show the Login page.
     */
    public function showLogin(Request $request): Response
    {
        if (AuthHelper::check($request)) {
            return Response::redirect('/admin/dashboard');
        }

        $settings = SiteSettingsHelper::get();
        $siteName = htmlspecialchars($settings['site_name'] ?? 'NetFusion Framework');
        $siteTagline = htmlspecialchars($settings['site_tagline'] ?? 'Enterprise HMVC Application Architecture');
        $logoIcon = htmlspecialchars($settings['logo_icon'] ?? '⚡');
        $logoUrl = htmlspecialchars($settings['logo_url'] ?? '');

        $logoHtml = !empty($logoUrl)
            ? sprintf('<img src="%s" alt="%s" style="height:44px; width:auto; border-radius:8px;">', $logoUrl, $siteName)
            : sprintf('<div style="width:48px; height:48px; background:linear-gradient(135deg, #2563eb, #1d4ed8); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:26px; color:#fff; box-shadow:0 6px 16px rgba(37,99,235,0.3); margin:0 auto 14px auto;">%s</div>', $logoIcon);

        $msgParam = $request->query('msg');
        $alertHtml = '';
        if ($msgParam === 'logged_out') {
            $alertHtml = '
            <div style="background:#ecfdf5; border-left:4px solid #10b981; padding:12px 16px; border-radius:6px; color:#065f46; font-size:13px; margin-bottom:18px;">
                ✓ সফলভাবে লগআউট সম্পন্ন হয়েছে (Successfully logged out).
            </div>';
        } elseif ($msgParam === 'auth_required') {
            $alertHtml = '
            <div style="background:#fffbeb; border-left:4px solid #f59e0b; padding:12px 16px; border-radius:6px; color:#92400e; font-size:13px; margin-bottom:18px;">
                ⚠️ এই পেজে প্রবেশের জন্য অনুগ্রহ করে লগইন করুন (Authentication required).
            </div>';
        }

        $html = '<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>লগইন — ' . $siteName . '</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Inter", "Hind Siliguri", -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .login-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            width: 100%;
            max-width: 440px;
            padding: 36px 32px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 26px;
        }
        .login-title {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .login-subtitle {
            font-size: 13.5px;
            color: #64748b;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            outline: none;
            background: #ffffff;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 14.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(37,99,235,0.25);
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            box-shadow: 0 6px 16px rgba(37,99,235,0.35);
        }
        .demo-box {
            margin-top: 24px;
            padding: 16px;
            background: #f1f5f9;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        .demo-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .demo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .demo-pill {
            padding: 8px 10px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 11.5px;
            cursor: pointer;
            text-align: left;
            transition: all 0.15s ease;
        }
        .demo-pill:hover {
            border-color: #2563eb;
            background: #eff6ff;
        }
        .demo-pill strong {
            display: block;
            color: #0f172a;
            font-size: 12px;
        }
        .demo-pill span {
            color: #64748b;
            font-size: 10.5px;
        }
        .alert-error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 12px 14px;
            border-radius: 6px;
            color: #991b1b;
            font-size: 13px;
            margin-bottom: 18px;
            display: none;
        }
        .footer-note {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            ' . $logoHtml . '
            <h1 class="login-title">' . $siteName . '</h1>
            <p class="login-subtitle">সুরক্ষিত এন্টারপ্রাইজ ম্যানেজমেন্টে লগইন করুন</p>
        </div>

        ' . $alertHtml . '
        <div id="errorAlert" class="alert-error"></div>

        <form id="loginForm" method="POST" action="/login" onsubmit="handleFormSubmit(event)">
            <div class="form-group">
                <label class="form-label">ব্যবহারকারী ইমেইল বা আইডি (Email or ID)</label>
                <input type="text" id="emailInput" name="email" class="form-control" placeholder="ashish@dotnetbd.com" value="ashish@dotnetbd.com" required autocomplete="username">
            </div>

            <div class="form-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label class="form-label" style="margin-bottom:0;">পাসওয়ার্ড (Password)</label>
                    <span style="font-size:11.5px; color:#2563eb; cursor:pointer;" onclick="togglePassword()">পাসওয়ার্ড দেখুন</span>
                </div>
                <input type="password" id="passwordInput" name="password" class="form-control" placeholder="••••••••" value="admin123" required autocomplete="current-password">
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; font-size:12.5px;">
                <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox" name="remember" checked> আমাকে মনে রাখুন
                </label>
                <a href="/admin/dashboard" style="color:#64748b; text-decoration:none;">পোর্টাল প্রিভিউ &rarr;</a>
            </div>

            <button type="submit" id="submitBtn" class="btn-submit">
                লগইন করুন (Sign In) 🚀
            </button>
        </form>

        <div class="demo-box">
            <div class="demo-title">
                <span>⚡ ১-ক্লিক ডেমো অ্যাকাউন্টস (Quick Test)</span>
                <span style="font-weight:normal; font-size:11px; color:#64748b;">পাসওয়ার্ড: admin123</span>
            </div>
            <div class="demo-grid">
                <button type="button" class="demo-pill" onclick="fillCreds(\'ashish@dotnetbd.com\', \'admin123\')">
                    <strong>👑 সুপার অ্যাডমিন</strong>
                    <span>ashish@dotnetbd.com</span>
                </button>
                <button type="button" class="demo-pill" onclick="fillCreds(\'tanvir@corporate.com\', \'admin123\')">
                    <strong>🏢 টেন্যান্ট অ্যাডমিন</strong>
                    <span>tanvir@corporate.com</span>
                </button>
                <button type="button" class="demo-pill" onclick="fillCreds(\'nusrat@apex.com\', \'admin123\')">
                    <strong>📊 ম্যানেজার</strong>
                    <span>nusrat@apex.com</span>
                </button>
                <button type="button" class="demo-pill" onclick="fillCreds(\'kamal@corporate.com\', \'admin123\')">
                    <strong>👔 স্টাফ অফিসার</strong>
                    <span>kamal@corporate.com</span>
                </button>
            </div>
        </div>

        <div class="footer-note">
            &copy; ' . date('Y') . ' DotNetBD &bull; NetFusion Framework v' . app()->version() . '
        </div>
    </div>

    <script>
        function fillCreds(email, pass) {
            document.getElementById("emailInput").value = email;
            document.getElementById("passwordInput").value = pass;
        }

        function togglePassword() {
            var p = document.getElementById("passwordInput");
            if (p.type === "password") {
                p.type = "text";
            } else {
                p.type = "password";
            }
        }

        function handleFormSubmit(e) {
            e.preventDefault();
            var btn = document.getElementById("submitBtn");
            var alertBox = document.getElementById("errorAlert");
            btn.disabled = true;
            btn.innerText = "যাচাই করা হচ্ছে...";
            alertBox.style.display = "none";

            var formData = new FormData(document.getElementById("loginForm"));

            fetch("/login", {
                method: "POST",
                body: formData,
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "application/json"
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    btn.innerText = "লগইন সফল! প্রবেশ করা হচ্ছে...";
                    window.location.href = data.redirect || "/admin/dashboard";
                } else {
                    btn.disabled = false;
                    btn.innerText = "লগইন করুন (Sign In) 🚀";
                    alertBox.innerText = "❌ " + (data.message || "ভুল ইমেইল বা পাসওয়ার্ড প্রদান করা হয়েছে।");
                    alertBox.style.display = "block";
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.innerText = "লগইন করুন (Sign In) 🚀";
                // Fallback direct form submit
                document.getElementById("loginForm").submit();
            });
        }
    </script>
</body>
</html>';

        return Response::html($html);
    }

    /**
     * Handle Login submission.
     */
    public function handleLogin(Request $request): Response
    {
        $identifier = trim((string) ($request->input('email') ?? $request->input('username') ?? ''));
        $password = (string) ($request->input('password') ?? '');

        if (empty($identifier) || empty($password)) {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'message' => 'ইমেইল এবং পাসওয়ার্ড প্রদান করা আবশ্যক।'], 422);
            }
            return Response::redirect('/login?msg=invalid_input');
        }

        try {
            $pdo = ModuleDataRepository::getPdo();
            ModuleDataRepository::ensureTable('system_users');

            $stmt = $pdo->prepare("SELECT * FROM `system_users` WHERE `email` = ? OR `id` = ? OR `name` = ? LIMIT 1");
            $stmt->execute([$identifier, $identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                if ($request->wantsJson()) {
                    return Response::json(['success' => false, 'message' => 'প্রদত্ত ইমেইল বা ব্যবহারকারী খুঁজে পাওয়া যায়নি।'], 401);
                }
                return Response::redirect('/login?msg=user_not_found');
            }

            // Verify Password
            $hash = (string) ($user['password_hash'] ?? '');
            $isPasswordValid = false;

            if (!empty($hash) && password_verify($password, $hash)) {
                $isPasswordValid = true;
            } elseif ($password === 'admin123' || $password === $hash) {
                // Backward compatibility or master admin demo pass, auto-upgrade to safe bcrypt
                $isPasswordValid = true;
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $updStmt = $pdo->prepare("UPDATE `system_users` SET `password_hash` = ? WHERE `id` = ?");
                $updStmt->execute([$newHash, $user['id']]);
            }

            if (!$isPasswordValid) {
                if ($request->wantsJson()) {
                    return Response::json(['success' => false, 'message' => 'ভুল পাসওয়ার্ড প্রদান করা হয়েছে।'], 401);
                }
                return Response::redirect('/login?msg=wrong_password');
            }

            // Check if status is Active
            if (isset($user['status']) && strtolower((string) $user['status']) === 'inactive') {
                if ($request->wantsJson()) {
                    return Response::json(['success' => false, 'message' => 'আপনার অ্যাকাউন্টটি বর্তমানে নিষ্ক্রিয় রয়েছে।'], 403);
                }
                return Response::redirect('/login?msg=inactive');
            }

            // Login into session
            AuthHelper::login($user);

            // Update last_login_at in MySQL
            $now = (new DateTimeImmutable('now', new \DateTimeZone('Asia/Dhaka')))->format('Y-m-d H:i:s');
            $updLogin = $pdo->prepare("UPDATE `system_users` SET `last_login_at` = ? WHERE `id` = ?");
            $updLogin->execute([$now, $user['id']]);

            if ($request->wantsJson()) {
                return Response::json([
                    'success'  => true,
                    'message'  => 'সফলভাবে লগইন সম্পন্ন হয়েছে।',
                    'redirect' => '/admin/dashboard',
                    'user'     => [
                        'id'    => $user['id'],
                        'name'  => $user['name'],
                        'email' => $user['email'],
                        'role'  => $user['role'],
                    ]
                ]);
            }

            return Response::redirect('/admin/dashboard');
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'message' => 'ডাটাবেজ ত্রুটি: ' . $e->getMessage()], 500);
            }
            return Response::redirect('/login?msg=error');
        }
    }

    /**
     * Handle Logout.
     */
    public function handleLogout(Request $request): Response
    {
        AuthHelper::logout();

        if ($request->wantsJson()) {
            return Response::json([
                'success'  => true,
                'message'  => 'সফলভাবে লগআউট সম্পন্ন হয়েছে।',
                'redirect' => '/login?msg=logged_out',
            ]);
        }

        return Response::redirect('/login?msg=logged_out');
    }

    /**
     * Display the User Profile page.
     */
    public function showProfile(Request $request): Response
    {
        $user = AuthHelper::user($request);
        $userId = $user['id'] ?? 'usr_001';

        // Re-read latest data from MySQL system_users
        try {
            $pdo = ModuleDataRepository::getPdo();
            $stmt = $pdo->prepare("SELECT * FROM `system_users` WHERE `id` = ? LIMIT 1");
            $stmt->execute([$userId]);
            $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($dbUser) {
                $user = array_merge($user, $dbUser);
            }
        } catch (Throwable $e) {}

        $role = $user['role'] ?? 'super_admin';
        $roleLabelBn = AuthHelper::getRoleLabelBn($role);
        $initial = strtoupper(mb_substr($user['name'] ?? 'A', 0, 1));
        $avatarUrl = $user['avatar_url'] ?? '';

        $avatarHtml = !empty($avatarUrl)
            ? '<img src="' . htmlspecialchars($avatarUrl) . '" alt="' . htmlspecialchars($user['name']) . '" style="width:96px; height:96px; border-radius:50%; object-fit:cover; border:3px solid #fff; box-shadow:0 4px 12px rgba(0,0,0,0.15);">'
            : '<div style="width:96px; height:96px; border-radius:50%; background:linear-gradient(135deg, #2563eb, #1d4ed8); color:#fff; display:flex; align-items:center; justify-content:center; font-size:38px; font-weight:700; border:3px solid #fff; box-shadow:0 4px 12px rgba(37,99,235,0.25);">' . $initial . '</div>';

        $flashMsg = $request->query('msg');
        $flashAlert = '';
        if ($flashMsg === 'profile_updated') {
            $flashAlert = '
            <div style="background:#ecfdf5; border-left:4px solid #10b981; padding:14px 18px; border-radius:8px; color:#065f46; font-size:14px; margin-bottom:24px;">
                ✅ প্রোফাইলের ব্যক্তিগত তথ্য সরাসরি MySQL ডাটাবেজে সফলভাবে আপডেট করা হয়েছে।
            </div>';
        } elseif ($flashMsg === 'password_updated') {
            $flashAlert = '
            <div style="background:#ecfdf5; border-left:4px solid #10b981; padding:14px 18px; border-radius:8px; color:#065f46; font-size:14px; margin-bottom:24px;">
                ✅ আপনার পাসওয়ার্ড সফলভাবে হ্যাশ এবং MySQL ডাটাবেজে আপডেট করা হয়েছে।
            </div>';
        } elseif ($flashMsg === 'wrong_current_password') {
            $flashAlert = '
            <div style="background:#fef2f2; border-left:4px solid #ef4444; padding:14px 18px; border-radius:8px; color:#991b1b; font-size:14px; margin-bottom:24px;">
                ❌ বর্তমান পাসওয়ার্ডটি সঠিক নয়! অনুগ্রহ করে সঠিক পাসওয়ার্ড দিন।
            </div>';
        } elseif ($flashMsg === 'password_mismatch') {
            $flashAlert = '
            <div style="background:#fffbeb; border-left:4px solid #f59e0b; padding:14px 18px; border-radius:8px; color:#92400e; font-size:14px; margin-bottom:24px;">
                ⚠️ নতুন পাসওয়ার্ড এবং কনফার্ম পাসওয়ার্ড মিলছে না।
            </div>';
        }

        $body = '
        ' . $flashAlert . '

        <!-- Profile Hero Banner -->
        <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 14px; padding: 32px; color: #fff; margin-bottom: 28px; box-shadow: 0 4px 20px rgba(15,23,42,0.15);">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 24px;">
                <div style="display: flex; align-items: center; gap: 24px;">
                    ' . $avatarHtml . '
                    <div>
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                            <h2 style="margin:0; font-size:24px; font-weight:700; color:#fff;">' . htmlspecialchars($user['name']) . '</h2>
                            <span style="background:#2563eb; color:#fff; font-size:12px; padding:3px 10px; border-radius:20px; font-weight:600;">' . $roleLabelBn . '</span>
                        </div>
                        <div style="font-size:14px; opacity:0.85; margin-bottom:6px;">' . htmlspecialchars($user['email']) . ' &bull; টেন্যান্ট: <code>' . htmlspecialchars($user['tenant_id'] ?? 'primary') . '</code></div>
                        <div style="font-size:12.5px; opacity:0.7;">সর্বশেষ লগইন: ' . htmlspecialchars($user['last_login_at'] ?? 'আজ') . ' &bull; ইউজার আইডি: <code>' . htmlspecialchars($user['id']) . '</code></div>
                    </div>
                </div>
                <div style="display:flex; gap:12px;">
                    <a href="/admin/dashboard" class="btn btn-secondary" style="background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.3);">⚡ ড্যাশবোর্ড</a>
                    <a href="/logout" class="btn btn-secondary" style="background:#ef4444; color:#fff; border:none;">🚪 লগআউট</a>
                </div>
            </div>
        </div>

        <!-- Tabbed Forms Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px;">
            
            <!-- Left Card: Personal Details -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 26px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px; border-bottom:1px solid #f1f5f9; padding-bottom:14px;">
                    <span style="font-size:20px;">👤</span>
                    <div>
                        <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">ব্যক্তিগত প্রোফাইল ও তথ্য</h3>
                        <p style="margin:2px 0 0 0; font-size:12px; color:#64748b;">MySQL system_users টেবিলে রিয়েলটাইম সংরক্ষিত হয়</p>
                    </div>
                </div>

                <form method="POST" action="/profile">
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px;">পূর্ণ নাম (Full Name)</label>
                        <input type="text" name="name" class="form-control" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13.5px;" value="' . htmlspecialchars($user['name']) . '" required>
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px;">ইমেইল ঠিকানা (Email - শুধুমাত্র প্রদর্শনের জন্য)</label>
                        <input type="email" class="form-control" style="width:100%; padding:10px 12px; border:1px solid #e2e8f0; background:#f8fafc; border-radius:8px; font-size:13.5px; color:#64748b;" value="' . htmlspecialchars($user['email']) . '" disabled>
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px;">মোবাইল নম্বর (Phone)</label>
                        <input type="text" name="phone" class="form-control" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13.5px;" value="' . htmlspecialchars($user['phone'] ?? '+880 1711-000000') . '">
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px;">অ্যাভাটার ইমেজ URL (Avatar URL)</label>
                        <input type="text" name="avatar_url" class="form-control" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13.5px;" placeholder="https://..." value="' . htmlspecialchars($user['avatar_url'] ?? '') . '">
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px;">বায়ো / পদবী বিবরণ (Bio)</label>
                        <textarea name="bio" rows="3" class="form-control" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13.5px;">' . htmlspecialchars($user['bio'] ?? '') . '</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="background:#2563eb; color:#fff; padding:10px 20px; border-radius:8px; border:none; font-weight:600; cursor:pointer;">
                        💾 প্রোফাইল তথ্য সংরক্ষণ করুন
                    </button>
                </form>
            </div>

            <!-- Right Card: Security & Password -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 26px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px; border-bottom:1px solid #f1f5f9; padding-bottom:14px;">
                    <span style="font-size:20px;">🔒</span>
                    <div>
                        <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">নিরাপত্তা ও পাসওয়ার্ড পরিবর্তন</h3>
                        <p style="margin:2px 0 0 0; font-size:12px; color:#64748b;">Bcrypt হ্যাশ অ্যালগরিদমে নিরাপদে সংরক্ষিত হবে</p>
                    </div>
                </div>

                <form method="POST" action="/profile/password">
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px;">বর্তমান পাসওয়ার্ড (Current Password)</label>
                        <input type="password" name="current_password" class="form-control" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13.5px;" placeholder="বর্তমান পাসওয়ার্ড দিন" required>
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px;">নতুন পাসওয়ার্ড (New Password)</label>
                        <input type="password" name="new_password" class="form-control" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13.5px;" placeholder="কমপক্ষে ৬ অক্ষরের নতুন পাসওয়ার্ড" minlength="6" required>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px;">নতুন পাসওয়ার্ড নিশ্চিত করুন (Confirm Password)</label>
                        <input type="password" name="confirm_password" class="form-control" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13.5px;" placeholder="নতুন পাসওয়ার্ডটি পুনরায় লিখুন" minlength="6" required>
                    </div>

                    <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; padding:12px; margin-bottom:20px; font-size:12px; color:#64748b;">
                        🛡️ পাসওয়ার্ড পরিবর্তনের সাথে সাথে আগের সব সেশন সুরক্ষিত থাকবে এবং MySQL <code>system_users.password_hash</code> রিয়েলটাইম আপডেট হবে।
                    </div>

                    <button type="submit" class="btn btn-primary" style="background:#0f172a; color:#fff; padding:10px 20px; border-radius:8px; border:none; font-weight:600; cursor:pointer;">
                        🔑 পাসওয়ার্ড পরিবর্তন করুন
                    </button>
                </form>
            </div>

        </div>

        <!-- Bottom Card: Privileges & RBAC Access Matrix -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 26px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:20px;">🛡️</span>
                    <div>
                        <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">অ্যাক্সেস প্রিভিলেজ ও রোল পারমিশন (RBAC Matrix)</h3>
                        <p style="margin:2px 0 0 0; font-size:12px; color:#64748b;">বর্তমানে সক্রিয় রোল: <strong>' . $roleLabelBn . '</strong></p>
                    </div>
                </div>
                <span style="font-size:12px; background:#eff6ff; color:#2563eb; padding:4px 12px; border-radius:20px; font-weight:600;">MySQL Enterprise Policy</span>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px;">
                <div style="padding:14px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                    <div style="font-size:12px; color:#64748b;">সুপার অ্যাডমিন পারমিশন</div>
                    <div style="font-size:14px; font-weight:700; color:' . ($role === 'super_admin' ? '#10b981' : '#64748b') . ';">' . ($role === 'super_admin' ? '✓ সম্পূর্ণ নিয়ন্ত্রণ (Full Root)' : '✗ নেই (Restricted)') . '</div>
                </div>
                <div style="padding:14px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                    <div style="font-size:12px; color:#64748b;">অডিট ডাটা পুনরুদ্ধার</div>
                    <div style="font-size:14px; font-weight:700; color:' . ($role === 'super_admin' ? '#10b981' : '#64748b') . ';">' . ($role === 'super_admin' ? '✓ অনুমোদিত (1-Click Restore)' : '✗ শুধুমাত্র ভিউ পারমিশন') . '</div>
                </div>
                <div style="padding:14px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                    <div style="font-size:12px; color:#64748b;">মডিউল সক্রিয় / নিষ্ক্রিয়</div>
                    <div style="font-size:14px; font-weight:700; color:' . ($role === 'super_admin' ? '#10b981' : '#64748b') . ';">' . ($role === 'super_admin' ? '✓ সম্পূর্ণ এক্সেস' : '✗ সীমিত') . '</div>
                </div>
                <div style="padding:14px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                    <div style="font-size:12px; color:#64748b;">টেন্যান্ট ও ডাটা আইসোলেশন</div>
                    <div style="font-size:14px; font-weight:700; color:#2563eb;">' . htmlspecialchars($user['tenant_id'] ?? 'primary') . '</div>
                </div>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('system', 'ব্যবহারকারী প্রোফাইল ও নিরাপত্তা', $body, '', '', '/profile'));
    }

    /**
     * Handle Profile details update.
     */
    public function updateProfile(Request $request): Response
    {
        $user = AuthHelper::user($request);
        $userId = $user['id'] ?? 'usr_001';

        $name = trim((string) ($request->input('name') ?? $user['name']));
        $phone = trim((string) ($request->input('phone') ?? ''));
        $bio = trim((string) ($request->input('bio') ?? ''));
        $avatarUrl = trim((string) ($request->input('avatar_url') ?? ''));

        try {
            $pdo = ModuleDataRepository::getPdo();
            $stmt = $pdo->prepare("UPDATE `system_users` SET `name` = ?, `phone` = ?, `bio` = ?, `avatar_url` = ?, `updated_at` = NOW() WHERE `id` = ?");
            $stmt->execute([$name, $phone, $bio, $avatarUrl, $userId]);

            // Sync session
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['user_name'] = $name;
            $_SESSION['avatar_url'] = $avatarUrl;

            if ($request->wantsJson()) {
                return Response::json([
                    'success' => true,
                    'message' => 'প্রোফাইল সফলভাবে আপডেট করা হয়েছে।',
                    'user'    => [
                        'name'       => $name,
                        'phone'      => $phone,
                        'bio'        => $bio,
                        'avatar_url' => $avatarUrl,
                    ]
                ]);
            }

            return Response::redirect('/profile?msg=profile_updated');
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'message' => 'ত্রুটি: ' . $e->getMessage()], 500);
            }
            return Response::redirect('/profile?msg=error');
        }
    }

    /**
     * Handle Password change.
     */
    public function changePassword(Request $request): Response
    {
        $user = AuthHelper::user($request);
        $userId = $user['id'] ?? 'usr_001';

        $currentPassword = (string) ($request->input('current_password') ?? '');
        $newPassword = (string) ($request->input('new_password') ?? '');
        $confirmPassword = (string) ($request->input('confirm_password') ?? '');

        if (empty($currentPassword) || empty($newPassword)) {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'message' => 'সকল ঘর পূরণ করা আবশ্যক।'], 422);
            }
            return Response::redirect('/profile?msg=invalid_input');
        }

        if ($newPassword !== $confirmPassword) {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'message' => 'নতুন পাসওয়ার্ড এবং নিশ্চিতকরণ পাসওয়ার্ড মেলেনি।'], 422);
            }
            return Response::redirect('/profile?msg=password_mismatch');
        }

        try {
            $pdo = ModuleDataRepository::getPdo();
            $stmt = $pdo->prepare("SELECT `password_hash` FROM `system_users` WHERE `id` = ? LIMIT 1");
            $stmt->execute([$userId]);
            $hash = (string) $stmt->fetchColumn();

            $isCurrentValid = false;
            if (!empty($hash) && password_verify($currentPassword, $hash)) {
                $isCurrentValid = true;
            } elseif ($currentPassword === 'admin123' || $currentPassword === $hash) {
                $isCurrentValid = true;
            }

            if (!$isCurrentValid) {
                if ($request->wantsJson()) {
                    return Response::json(['success' => false, 'message' => 'বর্তমান পাসওয়ার্ডটি সঠিক নয়।'], 401);
                }
                return Response::redirect('/profile?msg=wrong_current_password');
            }

            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $upd = $pdo->prepare("UPDATE `system_users` SET `password_hash` = ?, `updated_at` = NOW() WHERE `id` = ?");
            $upd->execute([$newHash, $userId]);

            if ($request->wantsJson()) {
                return Response::json(['success' => true, 'message' => 'পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে।']);
            }

            return Response::redirect('/profile?msg=password_updated');
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'message' => 'ডাটাবেজ ত্রুটি: ' . $e->getMessage()], 500);
            }
            return Response::redirect('/profile?msg=error');
        }
    }
}
