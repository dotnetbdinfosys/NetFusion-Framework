<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/System/Controllers/SystemController.php
| Module    : Modules\System
| Namespace : NetFusion\Modules\System\Controllers
| Purpose   : System Introspection, Health Monitor & HMVC Module Inspector.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Modules\System\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;

class SystemController
{
    /**
     * System Status & Environment Introspection
     */
    public function status(Request $request): Response
    {
        $phpVersion   = PHP_VERSION;
        $phpOs        = PHP_OS . ' (' . php_uname('m') . ')';
        $frameworkVer = app()->version();
        $memUsage     = round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';
        $memPeak      = round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';
        $memLimit     = ini_get('memory_limit');
        $maxExecution = ini_get('max_execution_time') . 's';
        $uploadMax    = ini_get('upload_max_filesize');
        $postMax      = ini_get('post_max_size');
        $opcacheOn    = function_exists('opcache_get_status') && (bool) ini_get('opcache.enable');

        // Check key extensions
        $extensions = ['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'curl', 'bcmath', 'json', 'fileinfo', 'gd', 'tokenizer'];
        $extRows = '';
        foreach ($extensions as $ext) {
            $loaded = extension_loaded($ext);
            $extRows .= sprintf(
                '<tr><td><code>%s</code></td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars($ext),
                $loaded ? 'সক্রিয় (Installed)' : 'অনুপস্থিত (Missing)',
                $loaded ? ModuleLayoutHelper::badge('সক্রিয়', 'success') : ModuleLayoutHelper::badge('নিষ্ক্রিয়', 'danger')
            );
        }

        $body = '
        <div style="background: linear-gradient(135deg, #334155 0%, #475569 100%); color: #fff; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(71,85,105,0.25);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h2 style="margin: 0 0 6px 0; font-size: 22px; font-weight: 700; color: #fff;">সিস্টেম কনফিগারেশন ও ফ্রেমওয়ার্ক হেলথ মনিটর</h2>
                    <p style="margin: 0; opacity: 0.9; font-size: 13.5px;">PHP ৮.২+ রানটাইম পরিবেশ, এক্সটেনশন অডিট ও কোর ইঞ্জিন মেট্রিক্স</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="/system/modules" class="btn btn-secondary" style="background: rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.4);">মডিউল ইনস্পেক্টর</a>
                    <a href="/health" class="btn btn-secondary" style="background: #ffffff; color: #334155; font-weight: 600;">সিস্টেম হেলথ API</a>
                </div>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card" style="border-left: 4px solid #64748b;">
                <div class="kpi-label">PHP ইঞ্জিন সংস্করণ</div>
                <div class="kpi-value">' . $phpVersion . '</div>
                <div class="kpi-subtext">Zend Engine ' . zend_version() . '</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #2563eb;">
                <div class="kpi-label">NetFusion সংস্করণ</div>
                <div class="kpi-value" style="color: #1e40af;">v' . $frameworkVer . '</div>
                <div class="kpi-subtext" style="color: #2563eb;">Enterprise Dynamic HMVC</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #10b981;">
                <div class="kpi-label">বর্তমান মেমোরি ব্যবহার</div>
                <div class="kpi-value" style="color: #065f46;">' . $memUsage . '</div>
                <div class="kpi-subtext">সর্বোচ্চ পিক: ' . $memPeak . ' (সীমা: ' . $memLimit . ')</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #f59e0b;">
                <div class="kpi-label">অপারেটিং সিস্টেম (OS)</div>
                <div class="kpi-value" style="font-size:16px; margin: 8px 0;">' . $phpOs . '</div>
                <div class="kpi-subtext">SAPI: ' . php_sapi_name() . '</div>
            </div>
        </div>

        <div class="grid-2" style="margin-bottom: 24px;">
            <div class="card">
                <div class="card-header"><span class="card-title">⚙️ সার্ভার এনভায়রনমেন্ট কনফিগারেশন</span></div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ডিরেক্টিভ</th>
                                <th>মান (Value)</th>
                                <th>স্ট্যাটাস</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><code>memory_limit</code></td><td>' . $memLimit . '</td><td>' . ModuleLayoutHelper::badge('অনুকূল', 'success') . '</td></tr>
                            <tr><td><code>max_execution_time</code></td><td>' . $maxExecution . '</td><td>' . ModuleLayoutHelper::badge('অনুকূল', 'success') . '</td></tr>
                            <tr><td><code>upload_max_filesize</code></td><td>' . $uploadMax . '</td><td>' . ModuleLayoutHelper::badge('সক্রিয়', 'info') . '</td></tr>
                            <tr><td><code>post_max_size</code></td><td>' . $postMax . '</td><td>' . ModuleLayoutHelper::badge('সক্রিয়', 'info') . '</td></tr>
                            <tr><td><code>opcache.enable</code></td><td>' . ($opcacheOn ? 'Enabled' : 'Disabled') . '</td><td>' . ($opcacheOn ? ModuleLayoutHelper::badge('সক্রিয়', 'success') : ModuleLayoutHelper::badge('ঐচ্ছিক', 'warning')) . '</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><span class="card-title">🔌 প্রয়োজনীয় PHP এক্সটেনশন অডিট</span></div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>এক্সটেনশন নাম</th>
                                <th>বিবরণ</th>
                                <th>স্ট্যাটাস</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $extRows . '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('system', 'ড্যাশবোর্ড', $body, '', '', '/system/status'));
    }

    /**
     * HMVC Modules Inspector
     */
    public function modules(Request $request): Response
    {
        $baseDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Modules';
        $moduleDirs = glob($baseDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];

        $rows = '';
        $totalModules = 0;
        $enabledCount = 0;

        foreach ($moduleDirs as $dir) {
            $manifestPath = $dir . DIRECTORY_SEPARATOR . 'module.json';
            $modName = basename($dir);
            $totalModules++;

            if (file_exists($manifestPath)) {
                $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
                $name     = $manifest['name'] ?? $modName;
                $version  = $manifest['version'] ?? '1.0.0';
                $desc     = $manifest['description'] ?? 'No description provided';
                $enabled  = $manifest['enabled'] ?? true;
                $priority = $manifest['priority'] ?? 10;
            } else {
                $name     = $modName;
                $version  = '1.0.0';
                $desc     = 'Legacy module without manifest';
                $enabled  = true;
                $priority = 10;
            }

            if ($enabled) {
                $enabledCount++;
            }

            $badge = $enabled 
                ? ModuleLayoutHelper::badge('সক্রিয় (Enabled)', 'success')
                : ModuleLayoutHelper::badge('নিষ্ক্রিয় (Disabled)', 'danger');

            $rows .= sprintf(
                '<tr>
                    <td><strong>%s</strong><br><small style="color:#64748b;">%s</small></td>
                    <td><code>v%s</code></td>
                    <td>%s</td>
                    <td style="text-align:center;"><code>%d</code></td>
                    <td>%s</td>
                    <td><a href="/admin/modules" class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">টগল কনফিগ</a></td>
                </tr>',
                htmlspecialchars($name),
                htmlspecialchars($dir),
                htmlspecialchars($version),
                htmlspecialchars($desc),
                $priority,
                $badge
            );
        }

        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📦 HMVC মডিউল ইনস্পেক্টর (Module Registry)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সিস্টেমে স্ক্যানকৃত সকল ডাইনামিক প্লাগইন ও মডিউল তালিকা</p>
            </div>
            <div style="font-size:13px; background:#f8fafc; border:1px solid #e2e8f0; padding:6px 14px; border-radius:6px;">
                মোট মডিউল: <strong>' . $totalModules . 'টি</strong> | সক্রিয়: <strong style="color:#059669;">' . $enabledCount . 'টি</strong>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>মডিউল নাম ও পাথ</th>
                            <th>ভার্সন</th>
                            <th>বিবরণ ও ভূমিকা</th>
                            <th style="text-align:center;">অগ্রাধিকার</th>
                            <th>স্ট্যাটাস</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $rows . '
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('system', 'মডিউল ইনস্পেক্টর', $body, '', '', '/system/modules'));
    }
}
