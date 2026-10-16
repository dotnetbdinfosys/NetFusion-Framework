<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/Controllers/HrmDashboardController.php
| Module    : Modules\HRM
| Namespace : NetFusion\Modules\HRM\Controllers
| Purpose   : Full-featured Human Resource Management (HRM) & Payroll Dashboard.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.3.1
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Modules\HRM\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;

class HrmDashboardController
{
    /**
     * HRM Overview Dashboard
     */
    public function index(Request $request): Response
    {
        $body = '
        <div style="background: linear-gradient(135deg, #991b1b 0%, #dc2626 100%); color: #fff; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(220,38,38,0.25);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h2 style="margin: 0 0 6px 0; font-size: 22px; font-weight: 700; color: #fff;">মানব সম্পদ ব্যবস্থাপনা (HRM) ও পেরোল অটোমেশন</h2>
                    <p style="margin: 0; opacity: 0.9; font-size: 13.5px;">কর্মচারী ডেটাবেস, ডিজিটাল উপস্থিতি, ছুটির আবেদন ও স্বয়ংক্রিয় বেতন বিতরণ ব্যবস্থা</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="/hrm/employees" class="btn btn-secondary" style="background: rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.4);">+ নতুন কর্মচারী নিয়োগ</a>
                    <a href="/hrm/payroll" class="btn btn-secondary" style="background: #ffffff; color: #991b1b; font-weight: 600;">চলতি মাসের পেরোল</a>
                </div>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card" style="border-left: 4px solid #dc2626;">
                <div class="kpi-label">মোট সক্রিয় কর্মচারী (Employees)</div>
                <div class="kpi-value" style="color: #991b1b;">১৮৪ জন</div>
                <div class="kpi-subtext" style="color: #dc2626;">১২টি কার্যকরী বিভাগ ও টিম</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #10b981;">
                <div class="kpi-label">আজকের উপস্থিতি (Attendance)</div>
                <div class="kpi-value" style="color: #065f46;">১৭৬ জন</div>
                <div class="kpi-subtext" style="color: #059669;">৯৫.৭% উপস্থিতি রেট</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #2563eb;">
                <div class="kpi-label">চলতি মাসের পেরোল বাজেট</div>
                <div class="kpi-value" style="color: #1e40af;">' . ModuleLayoutHelper::currency(3486400.00) . '</div>
                <div class="kpi-subtext">অক্টোবর ২০২৬ বেসিক ও অ্যালাউন্স</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #f59e0b;">
                <div class="kpi-label">আজকের ছুটির আবেদন (Leave)</div>
                <div class="kpi-value" style="color: #b45309;">৮ জন</div>
                <div class="kpi-subtext">৫টি অনুমোদিত, ৩টি পেন্ডিং</div>
            </div>
        </div>

        <div class="grid-2" style="margin-bottom: 24px;">
            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="card-title">🏢 বিভাগভিত্তিক কর্মী বণ্টন (Department Distribution)</span>
                    <a href="/hrm/departments" style="font-size:12px; color:#dc2626; text-decoration:none; font-weight:600;">সব বিভাগ →</a>
                </div>
                <div style="padding: 16px 20px;">
                    <div style="margin-bottom: 12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                            <span>সফটওয়্যার ও আইটি ইঞ্জিনিয়ারিং</span>
                            <strong>৫৮ জন (৩১.৫%)</strong>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 31.5%; background:#2563eb;"></div></div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                            <span>সেলস ও মার্কেটিং</span>
                            <strong>৪৬ জন (২৫.০%)</strong>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 25%; background:#059669;"></div></div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                            <span>কাস্টমার সাপোর্ট ও অপারেশনস</span>
                            <strong>৩৮ জন (২০.৬%)</strong>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 20.6%; background:#f59e0b;"></div></div>
                    </div>
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                            <span>ফিন্যান্স, এইচআর ও অ্যাডমিন</span>
                            <strong>৪২ জন (২২.৯%)</strong>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 22.9%; background:#7c3aed;"></div></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="card-title">📅 ছুটির অনুমোদন রিকোয়েস্ট (Pending Leaves)</span>
                    <a href="/hrm/leave" style="font-size:12px; color:#dc2626; text-decoration:none; font-weight:600;">ছুটির ড্যাশবোর্ড →</a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>কর্মচারী</th>
                                <th>ছুটির ধরণ</th>
                                <th>সময়কাল</th>
                                <th>অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>আরিফুল ইসলাম</strong><br><small style="color:#64748b;">সিনিয়র ডেভেলপার</small></td>
                                <td>অসুস্থতাজনিত (Sick)</td>
                                <td>৩ দিন</td>
                                <td><button class="btn btn-primary" style="padding:3px 8px; font-size:11px; background:#059669;">অনুমোদন</button></td>
                            </tr>
                            <tr>
                                <td><strong>নাজমুন নাহার</strong><br><small style="color:#64748b;">অ্যাকাউন্টস এক্সিকিউটিভ</small></td>
                                <td>নৈমিত্তিক (Casual)</td>
                                <td>১ দিন</td>
                                <td><button class="btn btn-primary" style="padding:3px 8px; font-size:11px; background:#059669;">অনুমোদন</button></td>
                            </tr>
                            <tr>
                                <td><strong>কায়সার হামিদ</strong><br><small style="color:#64748b;">সেলস ম্যানেজার</small></td>
                                <td>বার্ষিক ছুটি (Annual)</td>
                                <td>৫ দিন</td>
                                <td><button class="btn btn-primary" style="padding:3px 8px; font-size:11px; background:#059669;">অনুমোদন</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <span class="card-title">👤 সক্রিয় কর্মচারী ও পদবি তালিকা</span>
                <a href="/hrm/employees" class="btn btn-primary" style="padding:6px 14px; font-size:12px; background:#dc2626;">সব কর্মী দেখুন</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>কর্মী আইডি</th>
                            <th>নাম</th>
                            <th>বিভাগ</th>
                            <th>পদবি</th>
                            <th>মোবাইল নম্বর</th>
                            <th>যোগদানের তারিখ</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>EMP-0101</code></td>
                            <td><strong>আরিফুল ইসলাম</strong></td>
                            <td>সফটওয়্যার ইঞ্জিনিয়ারিং</td>
                            <td>Lead Backend Engineer</td>
                            <td>+880 1711-001122</td>
                            <td>২০২৩-০১-১৫</td>
                            <td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td>
                        </tr>
                        <tr>
                            <td><code>EMP-0102</code></td>
                            <td><strong>নাজমুন নাহার</strong></td>
                            <td>ফিন্যান্স ও একাউন্টিং</td>
                            <td>Senior Accountant</td>
                            <td>+880 1819-332211</td>
                            <td>২০২৩-০৬-০১</td>
                            <td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td>
                        </tr>
                        <tr>
                            <td><code>EMP-0103</code></td>
                            <td><strong>কায়সার হামিদ</strong></td>
                            <td>বিজনেস ডেভেলপমেন্ট</td>
                            <td>Regional Sales Head</td>
                            <td>+880 1912-887766</td>
                            <td>২০২৪-০৩-১০</td>
                            <td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td>
                        </tr>
                        <tr>
                            <td><code>EMP-0104</code></td>
                            <td><strong>ফাতিমা তুজ জোহরা</strong></td>
                            <td>মানব সম্পদ (HR)</td>
                            <td>HR Operations Manager</td>
                            <td>+880 1610-445566</td>
                            <td>২০২২-০৮-০১</td>
                            <td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('hrm', 'ড্যাশবোর্ড', $body, '', '', '/hrm'));
    }

    /**
     * Employees Page
     */
    public function employees(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">👤 কর্মচারী তালিকা (Employee Roster)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সকল পূর্ণকালীন, পার্ট-টাইম ও চুক্তিভিত্তিক কর্মীর তথ্যাবলি</p>
            </div>
            <button class="btn btn-primary" style="background:#dc2626;" onclick="alert(\'নতুন কর্মচারী রেজিস্ট্রেশন ফর্ম...\')">+ নতুন কর্মী নিবন্ধন</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>কর্মী কোড</th>
                            <th>নাম</th>
                            <th>বিভাগ</th>
                            <th>পদবি</th>
                            <th>ইমেইল ঠিকানা</th>
                            <th>মোবাইল নম্বর</th>
                            <th>স্ট্যাটাস</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>EMP-0101</code></td><td><strong>আরিফুল ইসলাম</strong></td><td>ইঞ্জিনিয়ারিং</td><td>Lead Backend Engineer</td><td>ariful@company.bd</td><td>+880 1711-001122</td><td>' . ModuleLayoutHelper::badge('স্থায়ী', 'success') . '</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">প্রোফাইল</button></td></tr>
                        <tr><td><code>EMP-0102</code></td><td><strong>নাজমুন নাহার</strong></td><td>ফিন্যান্স</td><td>Senior Accountant</td><td>nazmun@company.bd</td><td>+880 1819-332211</td><td>' . ModuleLayoutHelper::badge('স্থায়ী', 'success') . '</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">প্রোফাইল</button></td></tr>
                        <tr><td><code>EMP-0103</code></td><td><strong>কায়সার হামিদ</strong></td><td>মার্কেটিং</td><td>Regional Sales Head</td><td>kaisar@company.bd</td><td>+880 1912-887766</td><td>' . ModuleLayoutHelper::badge('স্থায়ী', 'success') . '</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">প্রোফাইল</button></td></tr>
                        <tr><td><code>EMP-0104</code></td><td><strong>ফাতিমা তুজ জোহরা</strong></td><td>এইচআর</td><td>HR Operations Manager</td><td>fatima@company.bd</td><td>+880 1610-445566</td><td>' . ModuleLayoutHelper::badge('স্থায়ী', 'success') . '</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">প্রোফাইল</button></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('hrm', 'কর্মচারী তালিকা', $body, '', '', '/hrm/employees'));
    }

    /**
     * Departments Page
     */
    public function departments(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">🏢 বিভাগ ব্যবস্থাপনা (Departments)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সাংগঠনিক কাঠামো ও বিভাগীয় প্রধানদের তালিকা</p>
            </div>
            <button class="btn btn-primary" style="background:#dc2626;">+ নতুন বিভাগ যোগ করুন</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>বিভাগ কোড</th>
                            <th>বিভাগের নাম</th>
                            <th>বিভাগীয় প্রধান (HOD)</th>
                            <th>মোট কর্মী</th>
                            <th style="text-align:right;">মাসিক বাজেট (৳)</th>
                            <th>লোকেশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>DEPT-IT</code></td><td><strong>সফটওয়্যার ইঞ্জিনিয়ারিং ও আইটি</strong></td><td>মাহমুদুল হাসান</td><td>৫৮ জন</td><td style="text-align:right; font-weight:700;">৳ ১২,৫০,০০০.০০</td><td>প্রধান কার্যালয়, ঢাকা</td></tr>
                        <tr><td><code>DEPT-FIN</code></td><td><strong>ফিন্যান্স ও একাউন্টিং</strong></td><td>নাজমুন নাহার</td><td>১৮ জন</td><td style="text-align:right; font-weight:700;">৳ ৪,২০,০০০.০০</td><td>প্রধান কার্যালয়, ঢাকা</td></tr>
                        <tr><td><code>DEPT-SALES</code></td><td><strong>সেলস ও বিজনেস ডেভেলপমেন্ট</strong></td><td>কায়সার হামিদ</td><td>৪৬ জন</td><td style="text-align:right; font-weight:700;">৳ ৯,৮০,০০০.০০</td><td>আঞ্চলিক অফিস, চট্টগ্রাম</td></tr>
                        <tr><td><code>DEPT-HR</code></td><td><strong>মানব সম্পদ ও প্রশাসন</strong></td><td>ফাতিমা তুজ জোহরা</td><td>১২ জন</td><td style="text-align:right; font-weight:700;">৳ ২,৮০,০০০.০০</td><td>প্রধান কার্যালয়, ঢাকা</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('hrm', 'বিভাগ ব্যবস্থাপনা', $body, '', '', '/hrm/departments'));
    }

    /**
     * Attendance Page
     */
    public function attendance(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">⏰ উপস্থিতি ট্র্যাকিং (Attendance Log)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">দৈনিক বায়োমেট্রিক ও ডিজিটাল উপস্থিতি রেজিস্টার (আজ: ' . date('Y-m-d') . ')</p>
            </div>
            <button class="btn btn-secondary" onclick="window.print()">🖨️ এক্সপোর্ট উপস্থিতি শিট</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>কর্মী আইডি</th>
                            <th>নাম</th>
                            <th>ইন টাইম</th>
                            <th>আউট টাইম</th>
                            <th>মোট ঘণ্টা</th>
                            <th>উপস্থিতি স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>EMP-0101</td><td><strong>আরিফুল ইসলাম</strong></td><td>০৮:৫৮ AM</td><td>-</td><td>চলমান</td><td>' . ModuleLayoutHelper::badge('সময়মতো উপস্থিত', 'success') . '</td></tr>
                        <tr><td>EMP-0102</td><td><strong>নাজমুন নাহার</strong></td><td>০৯:০২ AM</td><td>-</td><td>চলমান</td><td>' . ModuleLayoutHelper::badge('সময়মতো উপস্থিত', 'success') . '</td></tr>
                        <tr><td>EMP-0103</td><td><strong>কায়সার হামিদ</strong></td><td>০৯:৩৫ AM</td><td>-</td><td>চলমান</td><td>' . ModuleLayoutHelper::badge('বিলম্বিত (Late)', 'warning') . '</td></tr>
                        <tr><td>EMP-0104</td><td><strong>ফাতিমা তুজ জোহরা</strong></td><td>০৮:৫২ AM</td><td>-</td><td>চলমান</td><td>' . ModuleLayoutHelper::badge('সময়মতো উপস্থিত', 'success') . '</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('hrm', 'উপস্থিতি ট্র্যাকিং', $body, '', '', '/hrm/attendance'));
    }

    /**
     * Leave Page
     */
    public function leave(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📅 ছুটির ব্যবস্থাপনা (Leave Management)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">ছুটির আবেদন, ব্যালেন্স ট্র্যাকিং ও বিভাগীয় অনুমোদন</p>
            </div>
            <button class="btn btn-primary" style="background:#dc2626;">+ ছুটির আবেদন জমা দিন</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>আবেদন #</th>
                            <th>কর্মচারী</th>
                            <th>বিভাগ</th>
                            <th>ছুটির ধরণ</th>
                            <th>শুরুর তারিখ</th>
                            <th>শেষের তারিখ</th>
                            <th>মোট দিন</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>LV-2026-44</td><td>আরিফুল ইসলাম</td><td>ইঞ্জিনিয়ারিং</td><td>মেডিকেল লিভ</td><td>২০২৬-১০-১৮</td><td>২০২৬-১০-২০</td><td>৩ দিন</td><td>' . ModuleLayoutHelper::badge('অনুমোদিত', 'success') . '</td></tr>
                        <tr><td>LV-2026-43</td><td>নাজমুন নাহার</td><td>ফিন্যান্স</td><td>ক্যাজুয়াল লিভ</td><td>২০২৬-১০-১৬</td><td>২০২৬-১০-১৬</td><td>১ দিন</td><td>' . ModuleLayoutHelper::badge('অনুমোদিত', 'success') . '</td></tr>
                        <tr><td>LV-2026-42</td><td>কায়সার হামিদ</td><td>মার্কেটিং</td><td>অ্যানুয়াল লিভ</td><td>২০২৬-১০-২২</td><td>২০২৬-১০-২৬</td><td>৫ দিন</td><td>' . ModuleLayoutHelper::badge('পেন্ডিং', 'warning') . '</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('hrm', 'ছুটির ব্যবস্থাপনা', $body, '', '', '/hrm/leave'));
    }

    /**
     * Payroll Page
     */
    public function payroll(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">💰 বেতন ও পেরোল ডিসবার্সমেন্ট (Payroll Processing)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">মাসিক বেতন শিট, প্রভিডেন্ট ফান্ড ও ট্যাক্স কর্তন বিবরণী</p>
            </div>
            <button class="btn btn-primary" style="background:#dc2626;" onclick="alert(\'পেরোল প্রসেসিং শুরু হচ্ছে...\')">💸 চলতি মাসের পেরোল প্রসেস করুন</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>কর্মী আইডি</th>
                            <th>নাম</th>
                            <th style="text-align:right;">মূল বেতন (Basic)</th>
                            <th style="text-align:right;">ভাতা (Allowance)</th>
                            <th style="text-align:right;">কর্তন (Deduction)</th>
                            <th style="text-align:right;">প্রদেয় নিট বেতন</th>
                            <th>পেমেন্ট স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>EMP-0101</td><td><strong>আরিফুল ইসলাম</strong></td><td style="text-align:right;">৳ ৭০,০০০.০০</td><td style="text-align:right;">৳ ২০,০০০.০০</td><td style="text-align:right; color:#dc2626;">-৳ ৫,০০০.০০</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৮৫,০০০.০০</td><td>' . ModuleLayoutHelper::badge('পরিশোধিত', 'success') . '</td></tr>
                        <tr><td>EMP-0102</td><td><strong>নাজমুন নাহার</strong></td><td style="text-align:right;">৳ ৪৫,০০০.০০</td><td style="text-align:right;">৳ ১৫,০০০.০০</td><td style="text-align:right; color:#dc2626;">-৳ ৩,০০০.০০</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৫৭,০০০.০০</td><td>' . ModuleLayoutHelper::badge('পরিশোধিত', 'success') . '</td></tr>
                        <tr><td>EMP-0103</td><td><strong>কায়সার হামিদ</strong></td><td style="text-align:right;">৳ ৬০,০০০.০০</td><td style="text-align:right;">৳ ২৫,০০০.০০</td><td style="text-align:right; color:#dc2626;">-৳ ৪,৫০০.০০</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৮০,৫০০.০০</td><td>' . ModuleLayoutHelper::badge('পরিশোধিত', 'success') . '</td></tr>
                        <tr><td>EMP-0104</td><td><strong>ফাতিমা তুজ জোহরা</strong></td><td style="text-align:right;">৳ ৫০,০০০.০০</td><td style="text-align:right;">৳ ১৮,০০০.০০</td><td style="text-align:right; color:#dc2626;">-৳ ৩,৮০০.০০</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৬৪,২০০.০০</td><td>' . ModuleLayoutHelper::badge('পরিশোধিত', 'success') . '</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('hrm', 'বেতন ব্যবস্থাপনা', $body, '', '', '/hrm/payroll'));
    }

    /**
     * Performance Page
     */
    public function performance(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📊 পারফরম্যান্স ও অ্যাপ্রাইজাল রিভিউ (Performance KPI)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">ত্রৈমাসিক কর্মী মূল্যায়ন ও অগ্রগতি রিপোর্ট</p>
            </div>
            <button class="btn btn-secondary">নতুন রিভিউ ফর্ম</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>কর্মচারী</th>
                            <th>বিভাগ</th>
                            <th>কেপিআই স্কোর</th>
                            <th>অগ্রগতি রেটিং</th>
                            <th>রিভিউয়ার</th>
                            <th>পরবর্তী রিভিউ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>আরিফুল ইসলাম</strong></td><td>ইঞ্জিনিয়ারিং</td><td><strong style="color:#065f46;">৯৪ / ১০০</strong></td><td>' . ModuleLayoutHelper::badge('অসাধারণ (Outstanding)', 'success') . '</td><td>মাহমুদুল হাসান</td><td>২০২৭-০১-১৫</td></tr>
                        <tr><td><strong>নাজমুন নাহার</strong></td><td>ফিন্যান্স</td><td><strong style="color:#065f46;">৮৮ / ১০০</strong></td><td>' . ModuleLayoutHelper::badge('খুব ভালো (Very Good)', 'success') . '</td><td>নাজমুন নাহার</td><td>২০২৭-০১-১৫</td></tr>
                        <tr><td><strong>কায়সার হামিদ</strong></td><td>মার্কেটিং</td><td><strong style="color:#2563eb;">৮৫ / ১০০</strong></td><td>' . ModuleLayoutHelper::badge('ভালো (Good)', 'info') . '</td><td>কায়সার হামিদ</td><td>২০২৭-০১-১৫</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('hrm', 'পারফরম্যান্স রিভিউ', $body, '', '', '/hrm/performance'));
    }
}
