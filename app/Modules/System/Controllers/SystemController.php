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
use NetFusion\App\Http\Helpers\AuthHelper;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;
use NetFusion\App\Http\Helpers\SiteSettingsHelper;
use NetFusion\App\Services\ModuleDataRepository;
use DateTimeImmutable;
use ZipArchive;

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
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📦 HMVC মডিউল ইনস্পেক্টর (Module Registry)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সিস্টেমে স্ক্যানকৃত সকল ডাইনামিক প্লাগইন ও মডিউল তালিকা</p>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="font-size:13px; background:#f8fafc; border:1px solid #e2e8f0; padding:6px 14px; border-radius:6px;">
                    মোট মডিউল: <strong>' . $totalModules . 'টি</strong> | সক্রিয়: <strong style="color:#059669;">' . $enabledCount . 'টি</strong>
                </div>
                <a href="/system/modules/upload" class="btn btn-primary" style="background:#2563eb; color:#ffffff; padding:7px 14px; border-radius:6px; text-decoration:none; font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                    📤 নতুন মডিউল আপলোড
                </a>
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

    /**
     * Module & Extension Upload Page (UI)
     */
    public function uploadForm(Request $request): Response
    {
        $locale   = SiteSettingsHelper::resolveLocale();
        $isBn     = ($locale === 'bn');

        $statusMsg = '';
        $statusType = (string) $request->query('status', '');
        $msgText    = (string) $request->query('msg', '');

        if ($statusType === 'success') {
            $statusMsg = '<div style="background:#ecfdf5; border:1px solid #10b981; color:#065f46; padding:14px 18px; border-radius:8px; margin-bottom:20px; font-weight:500;">
                ✔ ' . htmlspecialchars($msgText ?: 'অপারেশন সফল হয়েছে।') . '
            </div>';
        } elseif ($statusType === 'error') {
            $statusMsg = '<div style="background:#fef2f2; border:1px solid #ef4444; color:#991b1b; padding:14px 18px; border-radius:8px; margin-bottom:20px; font-weight:500;">
                ⚠ ' . htmlspecialchars($msgText ?: 'একটি ত্রুটি ঘটেছে। অনুগ্রহ করে আবার চেষ্টা করুন।') . '
            </div>';
        }

        // Fetch installed modules list for quick summary
        $baseDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Modules';
        $moduleDirs = glob($baseDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        $installedRows = '';
        foreach ($moduleDirs as $dir) {
            $manifestPath = $dir . DIRECTORY_SEPARATOR . 'module.json';
            $dirName = basename($dir);
            $modName = $dirName;
            $modVer  = '1.0.0';
            $modDesc = 'Module';
            $enabled = true;

            if (file_exists($manifestPath)) {
                $m = json_decode((string) file_get_contents($manifestPath), true) ?: [];
                $modName = $m['name'] ?? $dirName;
                $modVer  = $m['version'] ?? '1.0.0';
                $modDesc = $m['description'] ?? $dirName;
                $enabled = $m['enabled'] ?? true;
            }

            $badge = $enabled 
                ? ModuleLayoutHelper::badge($isBn ? 'সক্রিয়' : 'Active', 'success')
                : ModuleLayoutHelper::badge($isBn ? 'নিষ্ক্রিয়' : 'Disabled', 'danger');

            $installedRows .= sprintf(
                '<tr>
                    <td><strong>%s</strong></td>
                    <td><code>app/Modules/%s</code></td>
                    <td><code>v%s</code></td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
                htmlspecialchars($modName),
                htmlspecialchars($dirName),
                htmlspecialchars($modVer),
                htmlspecialchars($modDesc),
                $badge
            );
        }

        $headingText = $isBn ? '📤 মডিউল ও এক্সটেনশন আপলোড হাব' : '📤 Module & Extension Upload Hub';
        $subHeading  = $isBn ? 'নতুন মডিউল প্যাকেজ (.ZIP) আপলোড করুন অথবা ব্রাউজার থেকেই ইনস্ট্যান্ট মডিউল স্কাফোল্ড তৈরি করুন' : 'Upload custom module packages (.ZIP) or scaffold new HMVC extensions directly from the browser.';

        $body = '
        ' . $statusMsg . '

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:22px; font-weight:700; color:#0f172a;">' . $headingText . '</h2>
                <p style="margin:0; font-size:13.5px; color:#64748b;">' . $subHeading . '</p>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="/system/modules/download-sample" class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:6px; background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; padding:8px 14px; border-radius:6px; text-decoration:none; font-size:13px; font-weight:600;">
                    📥 ' . ($isBn ? 'স্যাম্পল মডিউল জিপ ডাউনলোড' : 'Download Sample ZIP') . '
                </a>
                <a href="/system/modules" class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:6px; background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; padding:8px 14px; border-radius:6px; text-decoration:none; font-size:13px; font-weight:600;">
                    📦 ' . ($isBn ? 'মডিউল তালিকা' : 'Modules Registry') . '
                </a>
            </div>
        </div>

        <div class="grid-2" style="margin-bottom:24px;">
            <!-- CARD 1: ZIP Upload -->
            <div class="card">
                <div class="card-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:14px 18px;">
                    <span class="card-title" style="font-size:15px; font-weight:700; color:#1e293b;">
                        📦 ' . ($isBn ? 'মডিউল / এক্সটেনশন প্যাকেজ আপলোড (.ZIP)' : 'Upload Module Package (.ZIP)') . '
                    </span>
                </div>
                <div style="padding: 22px;">
                    <form id="moduleUploadForm" action="/system/modules/upload" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload">

                        <div id="dropZone" style="border: 2px dashed #94a3b8; border-radius: 10px; padding: 32px 20px; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.2s ease; margin-bottom: 18px;">
                            <div style="font-size: 38px; margin-bottom: 8px;">📁</div>
                            <div style="font-weight: 600; font-size: 14.5px; color: #1e293b; margin-bottom: 4px;">
                                ' . ($isBn ? 'এখানে .ZIP ফাইল টেনে আনুন অথবা ক্লিক করে নির্বাচন করুন' : 'Drag & drop .ZIP file here, or click to browse') . '
                            </div>
                            <div style="font-size: 12.5px; color: #64748b;">
                                ' . ($isBn ? 'শুধুমাত্র অনুমোদিত .ZIP ফরম্যাট (সর্বোচ্চ ৫০ মেগাবাইট)' : 'Accepted formats: .ZIP archive (Max 50MB)') . '
                            </div>
                            <input type="file" id="module_zip" name="module_zip" accept=".zip,application/zip,application/x-zip-compressed" style="display:none;" required>
                            <div id="fileSelectedName" style="display:none; margin-top:12px; font-weight:600; color:#2563eb; background:#eff6ff; padding:6px 12px; border-radius:6px; font-size:13px;"></div>
                        </div>

                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; margin-bottom:18px;">
                            <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:#334155; margin-bottom:8px; cursor:pointer;">
                                <input type="checkbox" name="overwrite" value="1" style="width:16px; height:16px; accent-color:#2563eb;">
                                ' . ($isBn ? 'বিদ্যমান মডিউল থাকলে ওভাররাইট/আপগ্রেড অনুমোদন করুন' : 'Allow overwrite/upgrade if module already exists') . '
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:#334155; cursor:pointer;">
                                <input type="checkbox" name="auto_enable" value="1" checked style="width:16px; height:16px; accent-color:#2563eb;">
                                ' . ($isBn ? 'ইনস্টলের পর স্বয়ংক্রিয়ভাবে সক্রিয় করুন (Auto-enable on install)' : 'Auto-enable module immediately upon installation') . '
                            </label>
                        </div>

                        <button type="submit" id="uploadSubmitBtn" style="width:100%; background:#2563eb; color:#ffffff; font-weight:700; font-size:14px; padding:12px; border:none; border-radius:8px; cursor:pointer; display:flex; justify-content:center; align-items:center; gap:8px; transition:background 0.15s ease;">
                            🚀 ' . ($isBn ? 'মডিউল আপলোড ও ইনস্টল করুন' : 'Upload & Install Module') . '
                        </button>
                    </form>
                </div>
            </div>

            <!-- CARD 2: Instant Scaffold Generator -->
            <div class="card">
                <div class="card-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:14px 18px;">
                    <span class="card-title" style="font-size:15px; font-weight:700; color:#1e293b;">
                        ⚡ ' . ($isBn ? 'ইনস্ট্যান্ট মডিউল স্কাফোল্ডার (Web Generator)' : 'Instant Module Scaffold Generator') . '
                    </span>
                </div>
                <div style="padding: 22px;">
                    <form id="scaffoldForm" action="/system/modules/upload" method="POST">
                        <input type="hidden" name="action" value="scaffold">

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                            <div>
                                <label style="display:block; font-size:12.5px; font-weight:600; color:#475569; margin-bottom:4px;">
                                    ' . ($isBn ? 'মডিউল নাম (PascalCase)' : 'Module Name (PascalCase)') . ' *
                                </label>
                                <input type="text" name="scaffold_name" id="scaffold_name" placeholder="e.g. CourierLogistics" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13.5px;" oninput="updateSlug(this.value)">
                            </div>
                            <div>
                                <label style="display:block; font-size:12.5px; font-weight:600; color:#475569; margin-bottom:4px;">
                                    ' . ($isBn ? 'ইউআরএল স্লাগ (Slug)' : 'URL Slug') . ' *
                                </label>
                                <input type="text" name="scaffold_slug" id="scaffold_slug" placeholder="e.g. courier-logistics" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13.5px;">
                            </div>
                        </div>

                        <div style="margin-bottom:12px;">
                            <label style="display:block; font-size:12.5px; font-weight:600; color:#475569; margin-bottom:4px;">
                                ' . ($isBn ? 'মডিউল বিবরণ (Description)' : 'Description') . '
                            </label>
                            <input type="text" name="scaffold_description" placeholder="e.g. Enterprise Courier and Tracking Hub" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13.5px;">
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:18px;">
                            <div>
                                <label style="display:block; font-size:12.5px; font-weight:600; color:#475569; margin-bottom:4px;">
                                    ' . ($isBn ? 'লেখক / টিম (Author)' : 'Author') . '
                                </label>
                                <input type="text" name="scaffold_author" value="DotNetBD Engineering" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13.5px;">
                            </div>
                            <div>
                                <label style="display:block; font-size:12.5px; font-weight:600; color:#475569; margin-bottom:4px;">
                                    ' . ($isBn ? 'আইকন ইমোজি (Icon)' : 'Icon Emoji') . '
                                </label>
                                <input type="text" name="scaffold_icon" value="🚀" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13.5px;">
                            </div>
                        </div>

                        <button type="submit" style="width:100%; background:#059669; color:#ffffff; font-weight:700; font-size:14px; padding:12px; border:none; border-radius:8px; cursor:pointer; display:flex; justify-content:center; align-items:center; gap:8px;">
                            ⚡ ' . ($isBn ? 'ইনস্ট্যান্ট মডিউল কাঠামো তৈরি করুন' : 'Generate Module Scaffold') . '
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- CARD 3: Specifications & Guidelines -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:14px 18px;">
                <span class="card-title" style="font-size:15px; font-weight:700; color:#1e293b;">
                    📋 ' . ($isBn ? 'NetFusion মডিউল ও এক্সটেনশন প্যাকেজ স্পেসিফিকেশন' : 'NetFusion Module Package Specifications') . '
                </span>
            </div>
            <div style="padding: 22px;">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; font-size:13px;">
                    <div>
                        <h4 style="margin:0 0 8px 0; color:#0f172a; font-size:14px;">📁 ' . ($isBn ? 'প্যাকেজের কাঠামোগত নিয়মাবলী' : 'Archive Package Structure') . '</h4>
                        <p style="color:#64748b; margin-bottom:10px;">
                            ' . ($isBn ? 'একটি মডিউল জিপে মূল ফোল্ডার হিসেবে <code>module.json</code> এবং সংশ্লিষ্ট কন্ট্রোলার ও রাউট থাকতে হবে:' : 'A valid module ZIP archive must contain <code>module.json</code> and standard HMVC directories:') . '
                        </p>
                        <pre style="background:#0f172a; color:#f8fafc; padding:12px 14px; border-radius:8px; font-size:12px; overflow-x:auto;">
MyModule.zip
├── module.json
├── config.php
├── Controllers/
│   └── MyModuleDashboardController.php
├── Providers/
│   └── MyModuleServiceProvider.php
├── Routes/
│   └── web.php
└── Views/
    └── dashboard.php</pre>
                    </div>

                    <div>
                        <h4 style="margin:0 0 8px 0; color:#0f172a; font-size:14px;">📄 ' . ($isBn ? 'module.json ম্যানিফেস্ট ফাইল ফরম্যাট' : 'module.json Manifest Schema') . '</h4>
                        <pre style="background:#0f172a; color:#f8fafc; padding:12px 14px; border-radius:8px; font-size:12px; overflow-x:auto;">
{
    "name": "MyModule",
    "slug": "my-module",
    "version": "1.0.0",
    "description": "Custom enterprise extension",
    "author": "Your Company / Developer",
    "enabled": true,
    "priority": 15,
    "providers": [
        "NetFusion\\Modules\\MyModule\\Providers\\MyModuleServiceProvider"
    ]
}</pre>
                        <div style="margin-top:12px;">
                            <a href="/system/modules/download-sample" style="color:#2563eb; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                                📥 ' . ($isBn ? 'রেডিমেড স্যাম্পল মডিউল ডাউনলোড করুন (.ZIP)' : 'Download ready-made Sample Module (.ZIP)') . ' &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 4: Current Modules Overview -->
        <div class="card">
            <div class="card-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:14px 18px; display:flex; justify-content:space-between; align-items:center;">
                <span class="card-title" style="font-size:15px; font-weight:700; color:#1e293b;">
                    🏛️ ' . ($isBn ? 'বর্তমানে ইনস্টলকৃত মডিউলসমূহ' : 'Currently Installed Modules') . '
                </span>
                <span style="font-size:12.5px; color:#64748b;">
                    ' . count($moduleDirs) . ' ' . ($isBn ? 'টি মডিউল লোড করা হয়েছে' : 'modules loaded') . '
                </span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>' . ($isBn ? 'মডিউল নাম' : 'Module Name') . '</th>
                            <th>' . ($isBn ? 'ইনস্টল লোকেশন' : 'Location') . '</th>
                            <th>' . ($isBn ? 'ভার্সন' : 'Version') . '</th>
                            <th>' . ($isBn ? 'বিবরণ' : 'Description') . '</th>
                            <th>' . ($isBn ? 'স্ট্যাটাস' : 'Status') . '</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $installedRows . '
                    </tbody>
                </table>
            </div>
        </div>
        ';

        $extraJs = '
        <script>
        const dropZone = document.getElementById("dropZone");
        const fileInput = document.getElementById("module_zip");
        const fileSelectedName = document.getElementById("fileSelectedName");

        if (dropZone && fileInput) {
            dropZone.addEventListener("click", () => fileInput.click());

            dropZone.addEventListener("dragover", (e) => {
                e.preventDefault();
                dropZone.style.borderColor = "#2563eb";
                dropZone.style.background = "#eff6ff";
            });

            dropZone.addEventListener("dragleave", () => {
                dropZone.style.borderColor = "#94a3b8";
                dropZone.style.background = "#f8fafc";
            });

            dropZone.addEventListener("drop", (e) => {
                e.preventDefault();
                dropZone.style.borderColor = "#94a3b8";
                dropZone.style.background = "#f8fafc";
                if (e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    updateFileName(e.dataTransfer.files[0]);
                }
            });

            fileInput.addEventListener("change", () => {
                if (fileInput.files.length) {
                    updateFileName(fileInput.files[0]);
                }
            });
        }

        function updateFileName(file) {
            if (file && fileSelectedName) {
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                fileSelectedName.textContent = "✔ নির্বাচিত: " + file.name + " (" + sizeMb + " MB)";
                fileSelectedName.style.display = "inline-block";
            }
        }

        function updateSlug(name) {
            const slugInput = document.getElementById("scaffold_slug");
            if (slugInput) {
                slugInput.value = name.replace(/([a-z])([A-Z])/g, "$1-$2")
                                      .replace(/[\s_]+/g, "-")
                                      .toLowerCase();
            }
        }
        </script>
        ';

        return Response::html(ModuleLayoutHelper::wrap('system', ($isBn ? 'মডিউল আপলোড হাব' : 'Module Upload Hub'), $body, '', $extraJs, '/system/modules/upload'));
    }

    /**
     * Handle Module Package (.ZIP) Upload or Instant Scaffold Creation
     */
    public function handleUpload(Request $request): Response
    {
        $action = (string) $request->input('action', 'upload');

        if ($action === 'scaffold') {
            return $this->createScaffold($request);
        }

        // Process ZIP File Upload
        $file = null;
        if (isset($_FILES['module_zip']) && $_FILES['module_zip']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['module_zip'];
        }

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $errCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            $msg = match ($errCode) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ফাইলের সাইজ পিএইচপির অনুমোদিত সাইজ (upload_max_filesize) অতিক্রম করেছে।',
                UPLOAD_ERR_PARTIAL => 'ফাইলটি আংশিক আপলোড হয়েছে, সম্পূর্ণ আপলোড করা যায়নি।',
                UPLOAD_ERR_NO_FILE => 'কোনো ফাইল নির্বাচন করা হয়নি। অনুগ্রহ করে একটি .ZIP ফাইল সিলেক্ট করুন।',
                default => 'ফাইল আপলোডে ত্রুটি ঘটেছে (Error Code: ' . $errCode . ')।',
            };
            return $this->respondUploadResult($request, false, $msg);
        }

        $origName = $file['name'];
        $tmpPath  = $file['tmp_name'];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if ($ext !== 'zip') {
            return $this->respondUploadResult($request, false, 'অবৈধ ফাইল ফরম্যাট! শুধুমাত্র .ZIP আর্কাইভ আপলোড করা অনুমোদিত।');
        }

        if (!class_exists('ZipArchive')) {
            return $this->respondUploadResult($request, false, 'সার্ভারে PHP ZipArchive এক্সটেনশন সক্রিয় নেই।');
        }

        $overwrite  = (bool) $request->input('overwrite', false);
        $autoEnable = (bool) $request->input('auto_enable', true);

        // Create temporary extraction directory
        $tmpExtractDir = storage_path('framework/tmp_uploads/' . uniqid('mod_extract_', true));
        if (!is_dir($tmpExtractDir)) {
            @mkdir($tmpExtractDir, 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            $this->recursiveDelete($tmpExtractDir);
            return $this->respondUploadResult($request, false, 'ZIP ফাইলটি খোলা যায়নি বা ফাইলটি ত্রুটিপূর্ণ।');
        }

        // Security Check: Path traversal inspection
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) continue;
            $entryName = $stat['name'];
            if (str_contains($entryName, '..') || str_starts_with($entryName, '/') || str_starts_with($entryName, '\\')) {
                $zip->close();
                $this->recursiveDelete($tmpExtractDir);
                return $this->respondUploadResult($request, false, 'নিরাপত্তা সতর্কতা: জিপ ফাইলের মধ্যে বিপজ্জনক পাথ ট্রাভার্সাল (..) শনাক্ত হয়েছে।');
            }
        }

        $zip->extractTo($tmpExtractDir);
        $zip->close();

        // Locate module.json and source directory
        $sourceDir = null;
        if (file_exists($tmpExtractDir . DIRECTORY_SEPARATOR . 'module.json')) {
            $sourceDir = $tmpExtractDir;
        } else {
            $subdirs = glob($tmpExtractDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
            foreach ($subdirs as $sdir) {
                if (file_exists($sdir . DIRECTORY_SEPARATOR . 'module.json')) {
                    $sourceDir = $sdir;
                    break;
                }
            }
            if (!$sourceDir && count($subdirs) === 1) {
                $sourceDir = $subdirs[0];
            } elseif (!$sourceDir) {
                $sourceDir = $tmpExtractDir;
            }
        }

        // Read or generate module.json
        $manifestPath = $sourceDir . DIRECTORY_SEPARATOR . 'module.json';
        $manifestData = [];

        if (file_exists($manifestPath)) {
            $manifestData = json_decode((string) file_get_contents($manifestPath), true) ?: [];
        }

        $rawName = $manifestData['name'] ?? pathinfo($origName, PATHINFO_FILENAME);
        $moduleName = preg_replace('/[^a-zA-Z0-9_]/', '', $rawName);
        if (empty($moduleName)) {
            $moduleName = 'Module' . date('YmdHis');
        }

        $slug = $manifestData['slug'] ?? strtolower(preg_replace('/[^a-z0-9_-]/', '', $moduleName));
        $version = $manifestData['version'] ?? '1.0.0';
        $description = $manifestData['description'] ?? 'Custom uploaded NetFusion extension.';

        if (!file_exists($manifestPath)) {
            $manifestData = [
                'name'        => $moduleName,
                'slug'        => $slug,
                'version'     => $version,
                'description' => $description,
                'author'      => $manifestData['author'] ?? 'Uploaded Extension',
                'enabled'     => $autoEnable,
                'priority'    => 15,
                'providers'   => [],
                'updated_at'  => (new DateTimeImmutable())->format('c'),
            ];
            file_put_contents($manifestPath, json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // Target Module Directory
        $destDir = base_path('app/Modules/' . $moduleName);

        if (is_dir($destDir)) {
            if (!$overwrite) {
                $this->recursiveDelete($tmpExtractDir);
                return $this->respondUploadResult($request, false, "মডিউল '{$moduleName}' ইতিমধ্যে সিস্টেমে ইনস্টল করা আছে। আপনি যদি এটিকে আপডেট করতে চান তবে 'বিদ্যমান মডিউল ওভাররাইট করুন' চেকবক্সে টিক দিন।");
            }
        } else {
            @mkdir($destDir, 0755, true);
        }

        // Copy files
        $this->recursiveCopy($sourceDir, $destDir);
        $this->recursiveDelete($tmpExtractDir);

        // Update module state in MySQL
        try {
            $pdo = ModuleDataRepository::getPdo();
            ModuleDataRepository::ensureTable('modules_state');
            $stmt = $pdo->prepare("INSERT INTO `modules_state` (`module_name`, `enabled`, `state_payload`, `updated_at`) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE `enabled` = VALUES(`enabled`), `state_payload` = VALUES(`state_payload`), `updated_at` = NOW()");
            $stmt->execute([
                $moduleName,
                $autoEnable ? 1 : 0,
                json_encode([
                    'enabled'    => $autoEnable,
                    'name'       => $moduleName,
                    'version'    => $version,
                    'updated_at' => (new DateTimeImmutable())->format('c'),
                ], JSON_UNESCAPED_SLASHES)
            ]);
        } catch (\Throwable) {}

        try {
            app(\NetFusion\Core\Modules\ModuleLoader::class)->reset();
        } catch (\Throwable) {}

        $successMsg = "মডিউল '{$moduleName}' (v{$version}) সফলভাবে আপলোড, আনপ্যাক এবং " . ($autoEnable ? 'সক্রিয়' : 'ইনস্টল') . " করা হয়েছে!";
        return $this->respondUploadResult($request, true, $successMsg, [
            'module'  => $moduleName,
            'version' => $version,
            'path'    => 'app/Modules/' . $moduleName,
        ]);
    }

    /**
     * Create a new module scaffold directly via web generator
     */
    protected function createScaffold(Request $request): Response
    {
        $rawName = trim((string) $request->input('scaffold_name', ''));
        $name    = preg_replace('/[^a-zA-Z0-9_]/', '', $rawName);
        $slug    = strtolower(trim((string) $request->input('scaffold_slug', '')));
        $slug    = preg_replace('/[^a-z0-9_-]/', '', $slug) ?: strtolower($name);
        $desc    = trim((string) $request->input('scaffold_description', '')) ?: "Enterprise module for {$name}";
        $author  = trim((string) $request->input('scaffold_author', '')) ?: 'DotNetBD Engineering';
        $icon    = trim((string) $request->input('scaffold_icon', '')) ?: '📦';

        if (empty($name)) {
            return $this->respondUploadResult($request, false, 'মডিউলের নাম প্রদান করা আবশ্যক (PascalCase ফরম্যাটে)।');
        }

        $moduleDir = base_path('app/Modules/' . $name);
        if (is_dir($moduleDir)) {
            return $this->respondUploadResult($request, false, "মডিউল '{$name}' ইতিমধ্যে সিস্টেমে বিদ্যমান!");
        }

        // Create directories
        $dirs = [
            $moduleDir,
            $moduleDir . '/Controllers',
            $moduleDir . '/Models',
            $moduleDir . '/Providers',
            $moduleDir . '/Routes',
            $moduleDir . '/Services',
            $moduleDir . '/Views',
        ];
        foreach ($dirs as $d) {
            if (!is_dir($d)) {
                @mkdir($d, 0755, true);
            }
        }

        $now = (new DateTimeImmutable())->format('c');

        // 1. module.json
        $manifest = [
            'name'        => $name,
            'slug'        => $slug,
            'version'     => '1.0.0',
            'description' => $desc,
            'author'      => $author,
            'enabled'     => true,
            'priority'    => 15,
            'icon'        => $icon,
            'providers'   => [
                "NetFusion\\Modules\\{$name}\\Providers\\{$name}ServiceProvider",
            ],
            'updated_at'  => $now,
        ];
        file_put_contents($moduleDir . '/module.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 2. config.php
        $configContent = <<<PHP
<?php

declare(strict_types=1);

return [
    'name'        => '{$name}',
    'slug'        => '{$slug}',
    'enabled'     => true,
    'version'     => '1.0.0',
    'description' => '{$desc}',
];
PHP;
        file_put_contents($moduleDir . '/config.php', $configContent);

        // 3. Provider
        $providerContent = <<<PHP
<?php

declare(strict_types=1);

namespace NetFusion\Modules\\{$name}\Providers;

use NetFusion\Core\Application;
use NetFusion\Core\Modules\ModuleServiceProvider;

class {$name}ServiceProvider extends ModuleServiceProvider
{
    public function register(Application \$app): void
    {
        // Bind module services into DI container
    }

    public function boot(Application \$app): void
    {
        \$this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
    }
}
PHP;
        file_put_contents($moduleDir . "/Providers/{$name}ServiceProvider.php", $providerContent);

        // 4. Controller
        $controllerContent = <<<PHP
<?php

declare(strict_types=1);

namespace NetFusion\Modules\\{$name}\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;

class {$name}DashboardController
{
    public function index(Request \$request): Response
    {
        \$kpi1 = ModuleLayoutHelper::kpiCard('Module Status', 'Active', 'Operational and Loaded');
        \$kpi2 = ModuleLayoutHelper::kpiCard('Module Version', 'v1.0.0', 'Initial Scaffold Release');
        \$kpi3 = ModuleLayoutHelper::kpiCard('Architecture', 'HMVC Isolated', 'Pure PHP 8.2+ Architecture');

        \$body = <<<HTML
        <div class="kpi-grid">
            {\$kpi1}
            {\$kpi2}
            {\$kpi3}
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">{$icon} {$name} Module Dashboard</span>
            </div>
            <div style="padding: 24px;">
                <h3 style="font-size:16px; margin-bottom:8px; color:#1e293b;">{$name} মডিউল সফলভাবে সক্রিয় হয়েছে</h3>
                <p style="color:#64748b; font-size:13.5px; margin-bottom:16px;">{$desc}</p>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; font-family:monospace; font-size:12.5px; color:#334155;">
                    <strong>Installed Directory:</strong> app/Modules/{$name}/<br>
                    <strong>Controller:</strong> app/Modules/{$name}/Controllers/{$name}DashboardController.php<br>
                    <strong>Route:</strong> /{$slug} (defined in app/Modules/{$name}/Routes/web.php)
                </div>
            </div>
        </div>
HTML;

        return Response::html(ModuleLayoutHelper::wrap('system', '{$name} Module', \$body, '', '', '/{$slug}'));
    }
}
PHP;
        file_put_contents($moduleDir . "/Controllers/{$name}DashboardController.php", $controllerContent);

        // 5. Routes
        $routesContent = <<<PHP
<?php

declare(strict_types=1);

use NetFusion\Core\Routing\Router;
use NetFusion\Modules\\{$name}\Controllers\\{$name}DashboardController;

/** @var Router \$router */
\$router->get('/{$slug}', [{$name}DashboardController::class, 'index'])->name('{$slug}.index');
PHP;
        file_put_contents($moduleDir . '/Routes/web.php', $routesContent);

        // 6. View
        $viewContent = <<<HTML
<div class="module-view">
    <h2><?= htmlspecialchars(\$name) ?></h2>
    <p><?= htmlspecialchars(\$description) ?></p>
</div>
HTML;
        file_put_contents($moduleDir . '/Views/dashboard.php', $viewContent);

        // 7. Update module states in MySQL
        try {
            $pdo = ModuleDataRepository::getPdo();
            ModuleDataRepository::ensureTable('modules_state');
            $stmt = $pdo->prepare("INSERT INTO `modules_state` (`module_name`, `enabled`, `state_payload`, `updated_at`) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE `enabled` = VALUES(`enabled`), `state_payload` = VALUES(`state_payload`), `updated_at` = NOW()");
            $stmt->execute([
                $name,
                1,
                json_encode([
                    'enabled'    => true,
                    'name'       => $name,
                    'version'    => '1.0.0',
                    'updated_at' => $now,
                ], JSON_UNESCAPED_SLASHES)
            ]);
        } catch (\Throwable) {}

        try {
            app(\NetFusion\Core\Modules\ModuleLoader::class)->reset();
        } catch (\Throwable) {}

        $successMsg = "নতুন মডিউল '{$name}' (/{$slug}) সফলভাবে জেনারেট ও সক্রিয় করা হয়েছে!";
        return $this->respondUploadResult($request, true, $successMsg, [
            'module'  => $name,
            'slug'    => $slug,
            'path'    => 'app/Modules/' . $name,
        ]);
    }

    /**
     * Generate and download a runnable Sample Module Boilerplate (.ZIP)
     */
    public function downloadSample(Request $request): Response
    {
        if (!class_exists('ZipArchive')) {
            return Response::html('<h1>ZipArchive extension not available</h1>', 500);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'nff_sample_') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return Response::html('<h1>Unable to create ZIP archive</h1>', 500);
        }

        // Add boilerplate files to ZIP
        $manifest = [
            'name'        => 'SampleExtension',
            'slug'        => 'sample-extension',
            'version'     => '1.0.0',
            'description' => 'Official Sample Starter Extension for NetFusion Framework',
            'author'      => 'DotNetBD Engineering',
            'enabled'     => true,
            'priority'    => 15,
            'icon'        => '🚀',
            'providers'   => [
                'NetFusion\\Modules\\SampleExtension\\Providers\\SampleExtensionServiceProvider',
            ],
            'updated_at'  => (new DateTimeImmutable())->format('c'),
        ];
        $zip->addFromString('SampleExtension/module.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $config = "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'name' => 'SampleExtension',\n    'slug' => 'sample-extension',\n    'enabled' => true,\n    'version' => '1.0.0',\n];\n";
        $zip->addFromString('SampleExtension/config.php', $config);

        $provider = "<?php\ndeclare(strict_types=1);\n\nnamespace NetFusion\\Modules\\SampleExtension\\Providers;\n\nuse NetFusion\\Core\\Application;\nuse NetFusion\\Core\\Modules\\ModuleServiceProvider;\n\nclass SampleExtensionServiceProvider extends ModuleServiceProvider\n{\n    public function register(Application \$app): void {}\n    public function boot(Application \$app): void\n    {\n        \$this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');\n    }\n}\n";
        $zip->addFromString('SampleExtension/Providers/SampleExtensionServiceProvider.php', $provider);

        $controller = <<<'CONTROLLER'
<?php

declare(strict_types=1);

namespace NetFusion\Modules\SampleExtension\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;

class SampleExtensionDashboardController
{
    public function index(Request $request): Response
    {
        $kpi1 = ModuleLayoutHelper::kpiCard('Extension Status', 'Operational', 'Sample Boilerplate Active');
        $kpi2 = ModuleLayoutHelper::kpiCard('Installed Version', 'v1.0.0', 'Certified NetFusion Extension');
        $kpi3 = ModuleLayoutHelper::kpiCard('HMVC Isolation', 'Active', 'Modular Architecture');

        $body = <<<HTML
        <div class="kpi-grid">
            {$kpi1}
            {$kpi2}
            {$kpi3}
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">🚀 SampleExtension Dashboard</span></div>
            <div style="padding:22px;">
                <p style="color:#475569; margin-bottom:12px;">This is a demonstration module uploaded and running inside NetFusion Framework.</p>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:6px; font-family:monospace; font-size:13px;">
                    Location: app/Modules/SampleExtension/<br>
                    Routes: app/Modules/SampleExtension/Routes/web.php (/sample-extension)<br>
                    Controller: app/Modules/SampleExtension/Controllers/SampleExtensionDashboardController.php
                </div>
            </div>
        </div>
HTML;

        return Response::html(ModuleLayoutHelper::wrap('system', 'SampleExtension', $body, '', '', '/sample-extension'));
    }
}
CONTROLLER;
        $zip->addFromString('SampleExtension/Controllers/SampleExtensionDashboardController.php', $controller);

        $routes = "<?php\ndeclare(strict_types=1);\n\nuse NetFusion\\Core\\Routing\\Router;\nuse NetFusion\\Modules\\SampleExtension\\Controllers\\SampleExtensionDashboardController;\n\n/** @var Router \$router */\n\$router->get('/sample-extension', [SampleExtensionDashboardController::class, 'index'])->name('sample-extension.index');\n";
        $zip->addFromString('SampleExtension/Routes/web.php', $routes);

        $views = "<div><h2>Sample Extension View</h2></div>\n";
        $zip->addFromString('SampleExtension/Views/dashboard.php', $views);

        $zip->close();

        $binary = (string) file_get_contents($tempFile);
        @unlink($tempFile);

        return new Response($binary, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="NetFusion-SampleExtension.zip"',
            'Content-Length'      => (string) strlen($binary),
            'Cache-Control'       => 'no-cache, must-revalidate',
        ]);
    }

    /**
     * Respond with either JSON (for AJAX) or HTTP redirect with query parameters
     */
    protected function respondUploadResult(Request $request, bool $success, string $message, array $extra = []): Response
    {
        if ($request->wantsJson() || ($request->header('X-Requested-With') === 'XMLHttpRequest')) {
            return Response::json(array_merge([
                'success' => $success,
                'message' => $message,
            ], $extra), $success ? 200 : 400);
        }

        $status = $success ? 'success' : 'error';
        $redirectUrl = '/system/modules/upload?status=' . $status . '&msg=' . urlencode($message);
        return Response::redirect($redirectUrl);
    }

    /**
     * Recursively copy a directory to destination
     */
    protected function recursiveCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        if ($dir === false) return;
        @mkdir($dst, 0755, true);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $srcPath = $src . DIRECTORY_SEPARATOR . $file;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $file;
            if (is_dir($srcPath)) {
                $this->recursiveCopy($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    /**
     * Recursively remove a directory and its contents
     */
    protected function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Deleted Records Footprint Audit Interface with Granular Time & RBAC Protection.
     */
    public function auditDeleted(Request $request): Response
    {
        $currentRole = AuthHelper::getCurrentRole($request);
        $isSuperAdmin = AuthHelper::isSuperAdmin($request);
        $roleLabelBn = AuthHelper::getRoleLabelBn($currentRole);

        $entityFilter = trim((string) $request->input('entity', ''));
        $logs = ModuleDataRepository::getAuditDeletedRecords($entityFilter ?: null, 100);

        $totalCount = count($logs);
        $lastDeletedTime = !empty($logs) ? ($logs[0]['deleted_at_readable'] ?? $logs[0]['created_at']) : 'কোনো ডাটা মোছা হয়নি';

        $kpis = '
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:20px;">
            ' . ModuleLayoutHelper::kpiCard('মোট মোছা রেকর্ড (Deleted Records)', (string)$totalCount, 'অডিট ফুটপ্রিন্ট লগ সংগৃহীত', '#dc2626') . '
            ' . ModuleLayoutHelper::kpiCard('ডাটাবেজ ইঞ্জিন', 'MySQL 8.4 (InnoDB)', 'JSON-Free Direct MySQL', '#2563eb') . '
            ' . ModuleLayoutHelper::kpiCard('অডিট নিরাপত্তা পলিসি', $isSuperAdmin ? 'সুপার অ্যাডমিন মোড (Full)' : 'ভিউ অনলি মোড (View Only)', 'audit_deleted_records', $isSuperAdmin ? '#059669' : '#d97706') . '
            ' . ModuleLayoutHelper::kpiCard('কোল্ড আর্কাইভ পলিসি', '১৮০ দিন (Cold Storage)', 'audit_cold_archive টেবিল', '#7c3aed') . '
        </div>';

        // Role indicator banner
        $roleBanner = $isSuperAdmin
            ? '<span style="display:inline-flex; align-items:center; gap:6px; background:#ecfdf5; border:1px solid #6ee7b7; color:#065f46; padding:5px 12px; border-radius:6px; font-size:12px; font-weight:600;">👑 বর্তমান ইউজার: ' . $roleLabelBn . ' &bull; <span style="color:#047857;">[ডাটা পুনরুদ্ধার ও কোল্ড আর্কাইভের সম্পূর্ণ পারমিশন সক্রিয়]</span></span>'
            : '<span style="display:inline-flex; align-items:center; gap:6px; background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:5px 12px; border-radius:6px; font-size:12px; font-weight:600;">👁️ বর্তমান ইউজার: ' . $roleLabelBn . ' &bull; <span style="color:#b45309;">[শুধুমাত্র দেখার অনুমতি &bull; ডাটা পুনরুদ্ধার ও মোছা সুরক্ষিতভাবে ব্লকড]</span></span>';

        $superActiveStyle = ($currentRole === 'super_admin') ? 'background:#2563eb; color:#ffffff; font-weight:600;' : 'background:#e2e8f0; color:#475569;';
        $managerActiveStyle = ($currentRole === 'manager') ? 'background:#d97706; color:#ffffff; font-weight:600;' : 'background:#e2e8f0; color:#475569;';
        $staffActiveStyle = ($currentRole === 'staff') ? 'background:#475569; color:#ffffff; font-weight:600;' : 'background:#e2e8f0; color:#475569;';

        $entityOptions = [
            '' => 'সকল মডিউল ও টেবিল (All Entities)',
            'billing_invoices' => 'বিলিং ইনভয়েস (billing_invoices)',
            'billing_plans' => 'বিলিং প্ল্যান (billing_plans)',
            'billing_subscriptions' => 'বিলিং সাবস্ক্রিপশন (billing_subscriptions)',
            'inventory_products' => 'ইনভেন্টরি পণ্য (inventory_products)',
            'inventory_warehouses' => 'গুদামঘর (inventory_warehouses)',
            'accounting_journal' => 'অ্যাকাউন্টিং জার্নাল (accounting_journal)',
            'commerce_orders' => 'কমার্স অর্ডার (commerce_orders)',
            'crm_leads' => 'সিআরএম লিড (crm_leads)',
            'hrm_employees' => 'এইচআরএম কর্মকর্তা (hrm_employees)',
            'tenants' => 'টেন্যান্ট (tenants)',
            'users' => 'ব্যবহারকারী (system_users)',
        ];

        $filterSelect = '<select class="form-control" style="width:auto; min-width:240px; display:inline-block;" onchange="location.href=\'/system/audit/deleted?entity=\' + encodeURIComponent(this.value) + \'&as_role=' . urlencode($currentRole) . '\'">';
        foreach ($entityOptions as $key => $lbl) {
            $sel = ($entityFilter === $key) ? 'selected' : '';
            $filterSelect .= "<option value=\"{$key}\" {$sel}>{$lbl}</option>";
        }
        $filterSelect .= '</select>';

        $rowsHtml = '';
        if (empty($logs)) {
            $rowsHtml = '<tr><td colspan="8" style="text-align:center; padding:35px; color:#64748b; font-size:14px;">✅ অডিট টেবিলে কোনো মোছা রেকর্ডের হিস্টোরি নেই। ফ্রেমওয়ার্কের সমস্ত ডাটা সুরক্ষিত রয়েছে।</td></tr>';
        } else {
            foreach ($logs as $row) {
                $auditId = (int) $row['id'];
                $entity = htmlspecialchars($row['entity'] ?? '');
                $recordId = htmlspecialchars($row['record_id'] ?? '');
                $deletedBy = htmlspecialchars($row['deleted_by'] ?? 'N/A');
                $ipAddress = htmlspecialchars($row['ip_address'] ?? '127.0.0.1');
                $userAgent = htmlspecialchars($row['user_agent'] ?? 'Unknown');
                $createdAt = htmlspecialchars($row['created_at'] ?? '');
                $reqUri = htmlspecialchars($row['request_uri'] ?? '');
                $rawJson = htmlspecialchars($row['record_data'] ?? '{}', ENT_QUOTES, 'UTF-8');

                // Precise Day & Time resolution
                $deletedDay = htmlspecialchars($row['deleted_day'] ?? '');
                $deletedTime = htmlspecialchars($row['deleted_time'] ?? '');
                $readableTime = htmlspecialchars($row['deleted_at_readable'] ?? '');

                if (empty($deletedDay) && !empty($createdAt)) {
                    $ts = strtotime($createdAt);
                    $dayEn = date('l', $ts);
                    $daysMap = ['Sunday' => 'রবিবার', 'Monday' => 'সোমবার', 'Tuesday' => 'মঙ্গলবার', 'Wednesday' => 'বুধবার', 'Thursday' => 'বৃহস্পতিবার', 'Friday' => 'শুক্রবার', 'Saturday' => 'শনিবার'];
                    $deletedDay = ($daysMap[$dayEn] ?? $dayEn) . " ({$dayEn})";
                    $deletedTime = date('h:i:s A', $ts) . ' [BST / ' . date('H:i:s', $ts) . ']';
                }

                $entityBadge = match ($entity) {
                    'billing_invoices', 'billing_plans', 'billing_subscriptions' => ModuleLayoutHelper::badge($entity, 'info'),
                    'inventory_products', 'inventory_warehouses' => ModuleLayoutHelper::badge($entity, 'warning'),
                    'accounting_journal' => ModuleLayoutHelper::badge($entity, 'primary'),
                    'commerce_orders' => ModuleLayoutHelper::badge($entity, 'warning'),
                    'crm_leads' => ModuleLayoutHelper::badge($entity, 'success'),
                    'hrm_employees' => ModuleLayoutHelper::badge($entity, 'danger'),
                    default => ModuleLayoutHelper::badge($entity, 'secondary'),
                };

                // Action Column: Super Admin gets instant restore, others get read-only lock badge
                if ($isSuperAdmin) {
                    $actionHtml = '<button type="button" class="btn btn-secondary" style="padding:4px 10px; font-size:11.5px; color:#065f46; border-color:#a7f3d0; background:#ecfdf5; font-weight:600;" onclick="restoreDeletedRecord(' . $auditId . ', \'' . $recordId . '\')">🔄 পুনরুদ্ধার</button>';
                } else {
                    $actionHtml = '<span class="badge" style="background:#f1f5f9; color:#64748b; font-size:11px; padding:4px 8px; border:1px solid #cbd5e1;" title="শুধুমাত্র Super Admin মোছা ডাটা পুনরুদ্ধার করতে পারেন">🔒 ভিউ অনলি</span>';
                }

                $rowsHtml .= '
                <tr id="audit-row-' . $auditId . '">
                    <td><code>#' . $auditId . '</code></td>
                    <td>' . $entityBadge . '</td>
                    <td><strong>' . $recordId . '</strong></td>
                    <td><span style="font-weight:600; color:#1e293b;">👤 ' . $deletedBy . '</span></td>
                    <td>
                        <span style="font-family:monospace; background:#f1f5f9; padding:2px 6px; border-radius:4px; font-size:11.5px;">🌐 ' . $ipAddress . '</span>
                        <div style="font-size:11px; color:#64748b; max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="' . $userAgent . '">' . $userAgent . '</div>
                    </td>
                    <td>
                        <div style="font-weight:600; color:#0f172a; font-size:12px;">📅 ' . $deletedDay . '</div>
                        <div style="color:#2563eb; font-weight:600; font-size:11.5px; margin-top:2px;">🕒 ' . $deletedTime . '</div>
                        <div style="color:#64748b; font-size:11px; margin-top:1px;">' . $createdAt . '</div>
                        <div style="color:#94a3b8; font-size:10px; max-width:160px; overflow:hidden; text-overflow:ellipsis;" title="' . $reqUri . '">URI: ' . $reqUri . '</div>
                    </td>
                    <td>
                        <button type="button" class="btn btn-secondary" style="padding:4px 8px; font-size:11.5px;" onclick=\'viewSnapshot(' . $rawJson . ', "' . $recordId . '", "' . $entity . '", "' . addslashes($readableTime ?: $createdAt) . '")\'>👁️ স্ন্যাপশট</button>
                    </td>
                    <td>
                        ' . $actionHtml . '
                    </td>
                </tr>';
            }
        }

        // Action Toolbar
        $adminTools = '';
        if ($isSuperAdmin) {
            $adminTools = '
            <button type="button" class="btn btn-secondary" style="padding:6px 12px; font-size:12px; background:#eff6ff; border-color:#93c5fd; color:#1d4ed8; font-weight:600;" onclick="triggerColdArchive(180)">
                ❄️ ১৮০+ দিনের পুরনো লগ কোল্ড আর্কাইভে পাঠান
            </button>
            <a href="/system/audit/cold-archive?as_role=' . urlencode($currentRole) . '" class="btn btn-secondary" style="padding:6px 12px; font-size:12px; background:#f8fafc; border-color:#cbd5e1; color:#334155;">
                📂 কোল্ড আর্কাইভ ভিউয়ার
            </a>';
        } else {
            $adminTools = '
            <a href="/system/audit/cold-archive?as_role=' . urlencode($currentRole) . '" class="btn btn-secondary" style="padding:6px 12px; font-size:12px; background:#f8fafc; border-color:#cbd5e1; color:#334155;">
                📂 কোল্ড আর্কাইভ ভিউয়ার
            </a>';
        }

        $body = '
        ' . $kpis . '

        <!-- Role & Permission Control Bar -->
        <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; margin-bottom:20px; flex-wrap:wrap; gap:12px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div>' . $roleBanner . '</div>
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:12px; font-weight:600; color:#64748b;">পারমিশন টেস্ট Persona:</span>
                <a href="?as_role=super_admin" class="btn btn-sm" style="padding:4px 9px; font-size:11px; ' . $superActiveStyle . '">👑 Super Admin</a>
                <a href="?as_role=manager" class="btn btn-sm" style="padding:4px 9px; font-size:11px; ' . $managerActiveStyle . '">👨‍💼 Manager (View Only)</a>
                <a href="?as_role=staff" class="btn btn-sm" style="padding:4px 9px; font-size:11px; ' . $staffActiveStyle . '">👔 Staff (View Only)</a>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700; color:#0f172a;">🐾 মোছা ডাটা ফুটপ্রিন্ট ও অডিট ট্র্যাকিং (Deleted Data Footprints)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">ইউজারের পরিচয়, আইপি অ্যাড্রেস, দিন-তারিখ-সময় (সেকেন্ডসহ) ও ডাটার পূর্ণাঙ্গ স্ন্যাপশট</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                ' . $adminTools . '
                ' . $filterSelect . '
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>অডিট ID</th>
                            <th>মডিউল / টেবিল</th>
                            <th>রেকর্ড ID</th>
                            <th>কে মুছেছে (Deleted By)</th>
                            <th>আইপি ও ব্রাউজার</th>
                            <th>দিন, তারিখ ও সময় (ঘণ্টা:মিনিট:সেকেন্ড)</th>
                            <th>ডাটা স্ন্যাপশট</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $rowsHtml . '
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Snapshot Viewer Modal -->
        <div id="snapshotModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(3px);">
            <div style="background:#ffffff; border-radius:12px; width:100%; max-width:680px; max-height:85vh; overflow-y:auto; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); padding:24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
                    <div>
                        <h3 id="snapshotModalTitle" style="margin:0 0 2px 0; font-size:16px; font-weight:700; color:#1e293b;">📋 ডাটা ফুটপ্রিন্ট স্ন্যাপশট</h3>
                        <div id="snapshotModalSubtitle" style="font-size:11.5px; color:#64748b;"></div>
                    </div>
                    <button type="button" onclick="closeSnapshotModal()" style="border:none; background:none; font-size:22px; cursor:pointer; color:#94a3b8;">&times;</button>
                </div>
                <div id="snapshotContent" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; font-family:monospace; font-size:12.5px; white-space:pre-wrap; max-height:400px; overflow-y:auto; color:#0f172a;"></div>
                <div style="display:flex; justify-content:flex-end; margin-top:20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeSnapshotModal()">বন্ধ করুন</button>
                </div>
            </div>
        </div>

        <script>
        const CURRENT_USER_ROLE = "' . $currentRole . '";

        function viewSnapshot(data, recordId, entity, timeText) {
            document.getElementById("snapshotModalTitle").innerText = "📋 " + entity + " (#" + recordId + ") — মোছার পূর্ববর্তী সম্পূর্ণ ডাটা";
            document.getElementById("snapshotModalSubtitle").innerText = "🕒 মোছার সময়: " + (timeText || "N/A");
            document.getElementById("snapshotContent").innerText = JSON.stringify(data, null, 4);
            document.getElementById("snapshotModal").style.display = "flex";
        }
        function closeSnapshotModal() {
            document.getElementById("snapshotModal").style.display = "none";
        }
        function restoreDeletedRecord(auditId, recordId) {
            if (CURRENT_USER_ROLE !== "super_admin") {
                alert("❌ অনুমতি অস্বীকৃত: শুধুমাত্র Super Admin মোছা ডাটা পুনরুদ্ধার করতে পারেন।");
                return;
            }

            if (!confirm("⚠️ আপনি কি নিশ্চিতভাবে এই রেকর্ডটি (#" + recordId + ") পুনরায় মূল MySQL টেবিলে ফিরিয়ে আনতে চান?")) {
                return;
            }
            const formData = new FormData();
            formData.append("audit_id", auditId);
            formData.append("user_role", CURRENT_USER_ROLE);

            fetch("/system/audit/restore", {
                method: "POST",
                headers: { 
                    "X-Requested-With": "XMLHttpRequest", 
                    "Accept": "application/json",
                    "X-User-Role": CURRENT_USER_ROLE
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("✅ " + data.message);
                    location.reload();
                } else {
                    alert("❌ পুনরুদ্ধার ব্যর্থ হয়েছে (403/500): " + (data.message || "ত্রুটি"));
                }
            })
            .catch(err => alert("ত্রুটি: " + err));
        }

        function triggerColdArchive(days) {
            if (CURRENT_USER_ROLE !== "super_admin") {
                alert("❌ অনুমতি অস্বীকৃত: শুধুমাত্র Super Admin কোল্ড আর্কাইভ প্রক্রিয়া চালাতে পারেন।");
                return;
            }
            if (!confirm("❄️ আপনি কি নিশ্চিতভাবে " + days + " দিনের বেশি পুরনো সমস্ত অডিট লগ মূল টেবিল থেকে সরিয়ে কোল্ড আর্কাইভে স্থানান্তর করতে চান?")) {
                return;
            }

            const formData = new FormData();
            formData.append("days", days);
            formData.append("user_role", CURRENT_USER_ROLE);

            fetch("/system/audit/archive-cold", {
                method: "POST",
                headers: { 
                    "X-Requested-With": "XMLHttpRequest", 
                    "Accept": "application/json",
                    "X-User-Role": CURRENT_USER_ROLE
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("✅ " + data.message);
                    location.reload();
                } else {
                    alert("❌ কোল্ড আর্কাইভ ব্যর্থ হয়েছে: " + (data.message || "ত্রুটি"));
                }
            })
            .catch(err => alert("ত্রুটি: " + err));
        }
        </script>
        ';

        return Response::html(ModuleLayoutHelper::wrap('system', 'অডিট ও ডিলিট ফুটপ্রিন্ট', $body, '', '', '/system/audit/deleted'));
    }

    /**
     * Restore a deleted record from audit footprint back into MySQL.
     * RESTRICTED: Strictly Super Admin only.
     */
    public function restoreDeleted(Request $request): Response
    {
        $operatorRole = AuthHelper::getCurrentRole($request);

        if (!AuthHelper::isSuperAdmin($request)) {
            return Response::json([
                'success' => false,
                'message' => 'অননুমোদিত অ্যাক্সেস (403 Forbidden): শুধুমাত্র Super Admin মোছা ডাটা পুনরুদ্ধার করতে পারবেন। অন্যরা কেবল দেখার অনুমতি পাবে।'
            ], 403);
        }

        $auditId = (int) $request->input('audit_id', 0);
        if ($auditId <= 0) {
            return Response::json(['success' => false, 'message' => 'সঠিক অডিট আইডি প্রদান করা আবশ্যক।'], 400);
        }

        $restored = ModuleDataRepository::restoreDeletedRecord($auditId, $operatorRole);
        if ($restored) {
            return Response::json([
                'success' => true,
                'message' => 'রেকর্ডটি সফলভাবে মূল MySQL টেবিলে পুনরুদ্ধার করা হয়েছে।'
            ]);
        }

        return Response::json([
            'success' => false,
            'message' => 'রেকর্ডটি পুনরুদ্ধার করা সম্ভব হয়নি বা অডিট রেকর্ড পাওয়া যায়নি।'
        ], 500);
    }

    /**
     * Archive old audit records to Cold Storage (e.g. older than 180 days).
     * RESTRICTED: Strictly Super Admin only.
     */
    public function archiveColdAudit(Request $request): Response
    {
        if (!AuthHelper::isSuperAdmin($request)) {
            return Response::json([
                'success' => false,
                'message' => 'অননুমোদিত অ্যাক্সেস (403 Forbidden): শুধুমাত্র Super Admin কোল্ড আর্কাইভ প্রক্রিয়া চালাতে পারেন।'
            ], 403);
        }

        $days = (int) $request->input('days', 180);
        if ($days <= 0) {
            $days = 180;
        }

        $user = AuthHelper::getCurrentUser($request)['email'] ?? 'super_admin';
        $result = ModuleDataRepository::archiveColdAuditRecords($days, "manual_admin_{$user}");

        return Response::json($result, $result['success'] ? 200 : 500);
    }

    /**
     * View Cold Archived Audit Records.
     */
    public function coldArchiveView(Request $request): Response
    {
        $currentRole = AuthHelper::getCurrentRole($request);
        $isSuperAdmin = AuthHelper::isSuperAdmin($request);

        $entityFilter = trim((string) $request->input('entity', ''));
        $coldLogs = ModuleDataRepository::getColdArchivedRecords($entityFilter ?: null, 100);

        $totalCount = count($coldLogs);

        $rowsHtml = '';
        if (empty($coldLogs)) {
            $rowsHtml = '<tr><td colspan="7" style="text-align:center; padding:35px; color:#64748b;">❄️ কোল্ড আর্কাইভে বর্তমানে কোনো সংরক্ষিত পুরনো লগ নেই। (১৮০ দিনের বেশি পুরনো লগ কোল্ড আর্কাইভে সরানো হয়)</td></tr>';
        } else {
            foreach ($coldLogs as $row) {
                $rawJson = htmlspecialchars($row['record_data'] ?? '{}', ENT_QUOTES, 'UTF-8');
                $rowsHtml .= '
                <tr>
                    <td><code>#' . (int)$row['id'] . '</code> (অরিজিনাল #' . (int)$row['original_audit_id'] . ')</td>
                    <td>' . ModuleLayoutHelper::badge((string)$row['entity'], 'secondary') . '</td>
                    <td><strong>' . htmlspecialchars((string)$row['record_id']) . '</strong></td>
                    <td>👤 ' . htmlspecialchars((string)($row['deleted_by'] ?? 'N/A')) . '</td>
                    <td>📅 ' . htmlspecialchars((string)($row['deleted_day'] ?? '')) . '<br><small>' . htmlspecialchars((string)$row['created_at']) . '</small></td>
                    <td>❄️ ' . htmlspecialchars((string)$row['archived_at']) . '<br><small style="color:#64748b;">' . htmlspecialchars((string)$row['archived_by']) . '</small></td>
                    <td>
                        <button type="button" class="btn btn-secondary" style="padding:3px 8px; font-size:11px;" onclick=\'alert("স্ন্যাপশট ডাটা:\\n" + JSON.stringify(' . $rawJson . ', null, 2))\'>👁️ ভিউ</button>
                    </td>
                </tr>';
            }
        }

        $body = '
        <div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">❄️ কোল্ড অডিট স্টোরেজ আর্কাইভ (Cold Audit Archive)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">১৮০ দিনের পুরনো ডাটা লং-টার্ম কমপ্লায়েন্স ও কোল্ড স্টোরেজ সুরক্ষার জন্য পৃথক টেবিলে সংরক্ষিত</p>
            </div>
            <div>
                <a href="/system/audit/deleted?as_role=' . urlencode($currentRole) . '" class="btn btn-secondary">← অ্যাক্টিভ অডিট লগে ফিরে যান</a>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>আর্কাইভ ID</th>
                            <th>মডিউল</th>
                            <th>রেকর্ড ID</th>
                            <th>কে মুছেছিল</th>
                            <th>মোছার তারিখ ও সময়</th>
                            <th>আর্কাইভ তারিখ ও উৎস</th>
                            <th>ডাটা</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $rowsHtml . '
                    </tbody>
                </table>
            </div>
        </div>';

        return Response::html(ModuleLayoutHelper::wrap('system', 'কোল্ড অডিট আর্কাইভ', $body, '', '', '/system/audit/cold-archive'));
    }

    /**
     * Purge active audit records.
     * RESTRICTED: Strictly Super Admin only.
     */
    public function purgeAudit(Request $request): Response
    {
        if (!AuthHelper::isSuperAdmin($request)) {
            return Response::json([
                'success' => false,
                'message' => 'অননুমোদিত অ্যাক্সেস (403 Forbidden): শুধুমাত্র Super Admin অডিট লগ মুছতে পারেন।'
            ], 403);
        }

        $auditId = $request->input('audit_id');
        $id = is_numeric($auditId) ? (int)$auditId : null;
        $success = ModuleDataRepository::purgeAuditRecords($id, 'super_admin');

        return Response::json([
            'success' => $success,
            'message' => $success ? 'অডিট রেকর্ড সফলভাবে পরিষ্কার করা হয়েছে।' : 'অডিট রেকর্ড মোছা সম্ভব হয়নি।'
        ]);
    }
}


