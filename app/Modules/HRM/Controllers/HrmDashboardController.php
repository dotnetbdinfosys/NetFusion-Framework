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
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Modules\HRM\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;
use NetFusion\App\Services\ModuleDataRepository;

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
     * Employees Page - Fully Functional & Editable
     */
    public function employees(Request $request): Response
    {
        $employees = ModuleDataRepository::all('hrm_employees');
        $flashMsg = $request->query('msg');
        $flashAlert = '';
        if ($flashMsg) {
            $flashAlert = '
            <div style="padding: 12px 16px; background: #ecfdf5; border-left: 4px solid #10b981; border-radius: 6px; margin-bottom: 20px; color: #065f46; font-size: 13.5px; display: flex; justify-content: space-between; align-items: center;">
                <span>✅ ' . htmlspecialchars($flashMsg) . '</span>
                <span style="cursor: pointer;" onclick="this.parentElement.style.display=\'none\'">✕</span>
            </div>';
        }

        $rowsHtml = '';
        foreach ($employees as $emp) {
            $code = htmlspecialchars($emp['id'] ?? '');
            $name = htmlspecialchars($emp['name'] ?? '');
            $dept = htmlspecialchars($emp['department'] ?? '');
            $desig = htmlspecialchars($emp['designation'] ?? '');
            $salary = (float) ($emp['salary'] ?? 0);
            $status = $emp['status'] ?? 'active';

            $badgeType = match ($status) {
                'active' => 'success',
                'probation' => 'warning',
                default => 'secondary',
            };

            $statusText = match ($status) {
                'active' => 'স্থায়ী (Permanent)',
                'probation' => 'প্রবেশন (Probation)',
                default => 'অব্যাহতি (Resigned)',
            };

            $jsonAttr = htmlspecialchars(json_encode($emp, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

            $rowsHtml .= '
            <tr id="row-' . $code . '">
                <td><code>' . $code . '</code></td>
                <td><strong>' . $name . '</strong></td>
                <td>' . $dept . '</td>
                <td>' . $desig . '</td>
                <td style="text-align:right; font-weight:700; color:#065f46;">' . ModuleLayoutHelper::currency($salary) . '</td>
                <td>' . ModuleLayoutHelper::badge($statusText, $badgeType) . '</td>
                <td>
                    <div style="display:flex; gap:6px; align-items:center;">
                        <button type="button" class="btn btn-secondary" style="padding:4px 9px; font-size:11.5px; color:#dc2626; border-color:#fecaca;" onclick=\'openEditEmpModal(' . $jsonAttr . ')\'>✏️ এডিট</button>
                        <a href="/hrm/employees/edit?id=' . urlencode($code) . '" class="btn btn-secondary" style="padding:4px 8px; font-size:11.5px;" title="ফুল পেজ এডিট">📄</a>
                        <button type="button" class="btn btn-secondary" style="padding:4px 8px; font-size:11.5px; color:#dc2626; border-color:#fecaca;" onclick="confirmDeleteRecord(\'hrm_employees\', \'' . $code . '\', \'/hrm/employees/delete\', \'' . htmlspecialchars($name, ENT_QUOTES) . ' (' . $code . ')\')">🗑️ মুছুন</button>
                    </div>
                </td>
            </tr>';
        }

        $body = '
        ' . $flashAlert . '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">👤 কর্মচারী তালিকা (Employee Roster)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সকল পূর্ণকালীন, পার্ট-টাইম কর্মীর পদবি, বেতন ও স্ট্যাটাস সম্পাদন</p>
            </div>
            <button class="btn btn-primary" style="background:#dc2626;" onclick="openNewEmpModal()">+ নতুন কর্মী নিবন্ধন</button>
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
                            <th style="text-align:right;">বেতন (৳)</th>
                            <th>স্ট্যাটাস</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $rowsHtml . '
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ EDIT EMPLOYEE MODAL ══ -->
        <div id="empModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:16px;">
            <div style="background:#fff; border-radius:12px; width:100%; max-width:520px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.15); overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                    <h3 id="empModalTitle" style="margin:0; font-size:16px; font-weight:700;">✏️ কর্মী তথ্য এডিট করুন</h3>
                    <button type="button" onclick="closeEmpModal()" style="border:none; background:transparent; font-size:18px; cursor:pointer;">✕</button>
                </div>
                <form id="empForm" method="POST" action="/hrm/employees/update" style="padding:20px;" onsubmit="handleEmpSubmit(event)">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">কর্মী কোড *</label>
                            <input type="text" id="emp_id" name="id" readonly required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; background:#f1f5f9; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">কর্মীর পুরো নাম *</label>
                            <input type="text" id="emp_name" name="name" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">বিভাগ (Department) *</label>
                            <input type="text" id="emp_department" name="department" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">পদবি (Designation) *</label>
                            <input type="text" id="emp_designation" name="designation" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">মাসিক বেতন (৳) *</label>
                            <input type="number" step="0.01" id="emp_salary" name="salary" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">চাকরির স্ট্যাটাস</label>
                            <select id="emp_status" name="status" style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600;">
                                <option value="active">স্থায়ী (Active)</option>
                                <option value="probation">প্রবেশন (Probation)</option>
                                <option value="resigned">অব্যাহতি (Resigned)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeEmpModal()">বাতিল</button>
                        <button type="submit" id="saveEmpBtn" class="btn btn-primary" style="background:#dc2626;">💾 সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openEditEmpModal(item) {
            document.getElementById("empModalTitle").innerText = "✏️ কর্মী তথ্য এডিট: " + item.name;
            document.getElementById("emp_id").value = item.id || "";
            document.getElementById("emp_id").readOnly = true;
            document.getElementById("emp_name").value = item.name || "";
            document.getElementById("emp_department").value = item.department || "";
            document.getElementById("emp_designation").value = item.designation || "";
            document.getElementById("emp_salary").value = item.salary || 0;
            document.getElementById("emp_status").value = item.status || "active";
            document.getElementById("empModal").style.display = "flex";
        }

        function openNewEmpModal() {
            const nextId = "EMP-0" + Math.floor(10 + Math.random() * 90);
            document.getElementById("empModalTitle").innerText = "➕ নতুন কর্মী নিবন্ধন করুন";
            document.getElementById("emp_id").value = nextId;
            document.getElementById("emp_id").readOnly = false;
            document.getElementById("emp_name").value = "";
            document.getElementById("emp_department").value = "সফটওয়্যার ইঞ্জিনিয়ারিং";
            document.getElementById("emp_designation").value = "সফটওয়্যার ডেভেলপার";
            document.getElementById("emp_salary").value = 60000.00;
            document.getElementById("emp_status").value = "active";
            document.getElementById("empModal").style.display = "flex";
        }

        function closeEmpModal() {
            document.getElementById("empModal").style.display = "none";
        }

        async function handleEmpSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById("saveEmpBtn");
            btn.disabled = true;
            btn.innerText = "সংরক্ষণ হচ্ছে...";

            const form = document.getElementById("empForm");
            const formData = new FormData(form);

            try {
                const response = await fetch("/hrm/employees/update", {
                    method: "POST",
                    headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" },
                    body: formData
                });
                const res = await response.json();
                if (res.success) {
                    alert("✅ " + res.message);
                    window.location.reload();
                } else {
                    alert("❌ ত্রুটি: " + (res.message || "সংরক্ষণ করা যায়নি"));
                }
            } catch (err) {
                form.submit();
            } finally {
                btn.disabled = false;
                btn.innerText = "💾 সংরক্ষণ করুন";
            }
        }
        </script>
        ';

        return Response::html(ModuleLayoutHelper::wrap('hrm', 'কর্মচারী তালিকা', $body, '', '', '/hrm/employees'));
    }

    /**
     * Standalone Edit Employee Page (GET /hrm/employees/edit?id=...)
     */
    public function editEmployee(Request $request): Response
    {
        $id = (string) $request->query('id', '');
        $emp = ModuleDataRepository::find('hrm_employees', 'id', $id);

        if (!$emp) {
            return Response::redirect('/hrm/employees?msg=' . urlencode('কর্মচারী তথ্য পাওয়া যায়নি'));
        }

        $empId = htmlspecialchars($emp['id']);
        $name = htmlspecialchars($emp['name'] ?? '');
        $dept = htmlspecialchars($emp['department'] ?? '');
        $desig = htmlspecialchars($emp['designation'] ?? '');
        $salary = (float) ($emp['salary'] ?? 0);
        $status = $emp['status'] ?? 'active';

        $body = '
        <div style="max-width:680px; margin:0 auto;">
            <div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">✏️ কর্মী তথ্য সম্পাদনা: ' . $empId . '</h2>
                    <p style="margin:0; font-size:13px; color:#64748b;">নাম, বিভাগ, পদবি ও বেতন স্কেল পরিবর্তন করুন</p>
                </div>
                <a href="/hrm/employees" class="btn btn-secondary">← ফিরে যান</a>
            </div>

            <div class="card" style="padding:24px;">
                <form method="POST" action="/hrm/employees/update">
                    <input type="hidden" name="id" value="' . $empId . '">

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">কর্মী কোড</label>
                            <input type="text" value="' . $empId . '" disabled style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; background:#f1f5f9; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">পুরো নাম</label>
                            <input type="text" name="name" value="' . $name . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">বিভাগ</label>
                            <input type="text" name="department" value="' . $dept . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">পদবি</label>
                            <input type="text" name="designation" value="' . $desig . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">মাসিক বেতন (৳)</label>
                            <input type="number" step="0.01" name="salary" value="' . $salary . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">স্ট্যাটাস</label>
                            <select name="status" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:600;">
                                <option value="active" ' . ($status === 'active' ? 'selected' : '') . '>স্থায়ী (Active)</option>
                                <option value="probation" ' . ($status === 'probation' ? 'selected' : '') . '>প্রবেশন (Probation)</option>
                                <option value="resigned" ' . ($status === 'resigned' ? 'selected' : '') . '>অব্যাহতি (Resigned)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <a href="/hrm/employees" class="btn btn-secondary">বাতিল</a>
                        <button type="submit" class="btn btn-primary" style="background:#dc2626;">💾 পরিবর্তন সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('hrm', 'কর্মী এডিট', $body, '', '', '/hrm/employees'));
    }

    /**
     * Update / Create Employee Handler (POST /hrm/employees/update)
     */
    public function updateEmployee(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            $id = 'EMP-0' . rand(10, 99);
        }

        $data = [
            'id' => $id,
            'name' => trim((string) $request->input('name', 'Employee')),
            'department' => trim((string) $request->input('department', 'General')),
            'designation' => trim((string) $request->input('designation', 'Staff')),
            'salary' => (float) $request->input('salary', 0.0),
            'status' => trim((string) $request->input('status', 'active')),
        ];

        $existing = ModuleDataRepository::find('hrm_employees', 'id', $id);
        if ($existing) {
            ModuleDataRepository::update('hrm_employees', 'id', $id, $data);
            $msg = "কর্মী {$data['name']} এর তথ্য সফলভাবে আপডেট করা হয়েছে।";
        } else {
            ModuleDataRepository::insert('hrm_employees', $data);
            $msg = "নতুন কর্মী {$data['name']} সফলভাবে নিবন্ধিত করা হয়েছে।";
        }

        if ($request->wantsJson() || $request->isAjax()) {
            return Response::json(['success' => true, 'message' => $msg, 'data' => $data]);
        }

        return Response::redirect('/hrm/employees?msg=' . urlencode($msg));
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

    /**
     * Delete an employee and log audit footprint to MySQL
     */
    public function deleteEmployee(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            return Response::json(['success' => false, 'message' => 'কর্মকর্তা আইডি প্রদান করা আবশ্যক।'], 400);
        }

        $emp = ModuleDataRepository::find('hrm_employees', 'id', $id);
        if (!$emp) {
            return Response::json(['success' => false, 'message' => 'কর্মকর্তা পাওয়া যায়নি।'], 404);
        }

        $deleted = ModuleDataRepository::delete('hrm_employees', 'id', $id);
        if ($deleted) {
            return Response::json(['success' => true, 'message' => "কর্মকর্তা {$emp['name']} ({$id}) সফলভাবে মুছে ফেলা হয়েছে এবং অডিট টেবিলে ফুটপ্রিন্ট সংরক্ষিত হয়েছে।"]);
        }
        return Response::json(['success' => false, 'message' => 'কর্মকর্তা মুছতে ব্যর্থ হয়েছে।'], 500);
    }
}
