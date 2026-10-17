<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/Controllers/CrmDashboardController.php
| Module    : Modules\CRM
| Namespace : NetFusion\Modules\CRM\Controllers
| Purpose   : Full-featured CRM, Sales Pipeline & Helpdesk Dashboard.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Modules\CRM\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;
use NetFusion\App\Services\ModuleDataRepository;

class CrmDashboardController
{
    /**
     * CRM Overview Dashboard
     */
    public function index(Request $request): Response
    {
        $body = '
        <div style="background: linear-gradient(135deg, #065f46 0%, #059669 100%); color: #fff; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(5,150,105,0.25);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h2 style="margin: 0 0 6px 0; font-size: 22px; font-weight: 700; color: #fff;">গ্রাহক সম্পর্ক ব্যবস্থাপনা (CRM) ও সেলস পাইপলাইন</h2>
                    <p style="margin: 0; opacity: 0.9; font-size: 13.5px;">লিড ট্র্যাকিং, কাস্টমার এনগেজমেন্ট, ডিল পাইপলাইন ও সাপোর্ট টিকিট হেল্পডেস্ক</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="/crm/leads" class="btn btn-secondary" style="background: rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.4);">+ নতুন লিড যোগ করুন</a>
                    <a href="/crm/deals" class="btn btn-secondary" style="background: #ffffff; color: #065f46; font-weight: 600;">ডিল পাইপলাইন</a>
                </div>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card" style="border-left: 4px solid #059669;">
                <div class="kpi-label">মোট সক্রিয় লিডস (Total Leads)</div>
                <div class="kpi-value" style="color: #065f46;">৩৪৭টি</div>
                <div class="kpi-subtext" style="color: #059669;">↑ ২৪টি নতুন লিড এই সপ্তাহে যোগ হয়েছে</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #2563eb;">
                <div class="kpi-label">চলমান ডিল (Open Deals)</div>
                <div class="kpi-value" style="color: #1e40af;">৮৯টি</div>
                <div class="kpi-subtext">পাইপলাইন মোট মূল্য: ৳ ১৮,৫০,০০০.০০</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #10b981;">
                <div class="kpi-label">জয়ী ডিল (Deals Won)</div>
                <div class="kpi-value" style="color: #047857;">৬২টি</div>
                <div class="kpi-subtext" style="color: #10b981;">কনভার্শন রেট: ৩৯.৫%</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #f59e0b;">
                <div class="kpi-label">ওপেন সাপোর্ট টিকিট (Helpdesk)</div>
                <div class="kpi-value" style="color: #b45309;">১৫টি</div>
                <div class="kpi-subtext">গড় সমাধান সময়: ২.৪ ঘণ্টা</div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <span class="card-title">💼 সেলস পাইপলাইন ফানেল ও স্টেজ মনিটরিং</span>
                <a href="/crm/deals" style="font-size:12px; color:#059669; text-decoration:none; font-weight:600;">ফুল পাইপলাইন দেখুন →</a>
            </div>
            <div style="padding: 20px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; text-align: center;">
                        <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">১. যোগ্য লিড (Qualified)</div>
                        <div style="font-size: 22px; font-weight: 700; color: #2563eb; margin: 6px 0;">৮৭টি</div>
                        <div style="font-size: 12px; color: #64748b;">সম্ভাব্য মান: ৳ ৫,২০,০০০</div>
                    </div>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; text-align: center;">
                        <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">২. প্রপোজাল প্রেরিত</div>
                        <div style="font-size: 22px; font-weight: 700; color: #7c3aed; margin: 6px 0;">৫৪টি</div>
                        <div style="font-size: 12px; color: #64748b;">সম্ভাব্য মান: ৳ ৮,১০,০০০</div>
                    </div>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; text-align: center;">
                        <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">৩. দরকষাকষি (Negotiation)</div>
                        <div style="font-size: 22px; font-weight: 700; color: #f59e0b; margin: 6px 0;">৩৮টি</div>
                        <div style="font-size: 12px; color: #64748b;">সম্ভাব্য মান: ৳ ৫,২০,০০০</div>
                    </div>
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px; text-align: center;">
                        <div style="font-size: 12px; color: #065f46; font-weight: 600; text-transform: uppercase;">৪. সফল চুক্তি (Closed Won)</div>
                        <div style="font-size: 22px; font-weight: 700; color: #059669; margin: 6px 0;">৬২টি</div>
                        <div style="font-size: 12px; color: #047857; font-weight: 600;">অর্জিত মান: ৳ ১৮,৫০,০০০</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid-2" style="margin-bottom: 24px;">
            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="card-title">🎯 সাম্প্রতিক লিডস (Recent Leads)</span>
                    <a href="/crm/leads" style="font-size:12px; color:#059669; text-decoration:none; font-weight:600;">সকল লিড →</a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>নাম ও প্রতিষ্ঠান</th>
                                <th>সোর্স</th>
                                <th style="text-align:right;">সম্ভাব্য মান</th>
                                <th>স্ট্যাটাস</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>তানভীর আহমেদ</strong><br><small style="color:#64748b;">প্রাইম টেক লিমিটেড</small></td>
                                <td>ওয়েবসাইট ফর্ম</td>
                                <td style="text-align:right; font-weight:600;">৳ ৭৫,০০০</td>
                                <td>' . ModuleLayoutHelper::badge('নতুন', 'info') . '</td>
                            </tr>
                            <tr>
                                <td><strong>নুসরাত জাহান</strong><br><small style="color:#64748b;">এলিট ফ্যাশনস</small></td>
                                <td>লিংকডইন</td>
                                <td style="text-align:right; font-weight:600;">৳ ৪৫,০০০</td>
                                <td>' . ModuleLayoutHelper::badge('যোগাযোগকৃত', 'warning') . '</td>
                            </tr>
                            <tr>
                                <td><strong>মাহমুদুল হাসান</strong><br><small style="color:#64748b;">স্কাইলাইন লজিস্টিকস</small></td>
                                <td>রেফারেল</td>
                                <td style="text-align:right; font-weight:600;">৳ ১,২০,০০০</td>
                                <td>' . ModuleLayoutHelper::badge('যোগ্য লিড', 'success') . '</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="card-title">🎫 ওপেন হেল্পডেস্ক টিকিট (Support Tickets)</span>
                    <a href="/crm/tickets" style="font-size:12px; color:#059669; text-decoration:none; font-weight:600;">সকল টিকিট →</a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>টিকিট #</th>
                                <th>বিষয়</th>
                                <th>অগ্রাধিকার</th>
                                <th>অ্যাসাইনি</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>TCK-109</strong></td>
                                <td>পেমেন্ট গেটওয়ে ইন্টিগ্রেশন এপিআই ইস্যু</td>
                                <td>' . ModuleLayoutHelper::badge('জরুরি (High)', 'danger') . '</td>
                                <td>সাকিব রহমান</td>
                            </tr>
                            <tr>
                                <td><strong>TCK-108</strong></td>
                                <td>ইনভয়েস পিডিএফ বাংলা ফন্ট ডিসপ্লে সমস্যা</td>
                                <td>' . ModuleLayoutHelper::badge('মাঝারি (Med)', 'warning') . '</td>
                                <td>আসিফ ইকবাল</td>
                            </tr>
                            <tr>
                                <td><strong>TCK-107</strong></td>
                                <td>টেন্যান্ট রোল পারমিশন এসাইন করার প্রশ্ন</td>
                                <td>' . ModuleLayoutHelper::badge('সাধারণ (Low)', 'info') . '</td>
                                <td>রিয়াদ মাহমুদ</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('crm', 'ড্যাশবোর্ড', $body, '', '', '/crm'));
    }

    /**
     * Leads Page - Fully Functional & Editable
     */
    public function leads(Request $request): Response
    {
        $leads = ModuleDataRepository::all('crm_leads');
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
        foreach ($leads as $lead) {
            $id = htmlspecialchars($lead['id'] ?? '');
            $name = htmlspecialchars($lead['name'] ?? '');
            $company = htmlspecialchars($lead['company'] ?? '');
            $phone = htmlspecialchars($lead['phone'] ?? '');
            $val = (float) ($lead['value'] ?? 0);
            $stage = htmlspecialchars($lead['stage'] ?? 'নতুন লিড');

            $jsonAttr = htmlspecialchars(json_encode($lead, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

            $rowsHtml .= '
            <tr id="row-' . $id . '">
                <td><code>' . $id . '</code></td>
                <td><strong>' . $name . '</strong></td>
                <td>' . $company . '</td>
                <td>' . $phone . '</td>
                <td>' . ModuleLayoutHelper::badge($stage, 'info') . '</td>
                <td style="text-align:right; font-weight:700; color:#065f46;">' . ModuleLayoutHelper::currency($val) . '</td>
                <td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td>
                <td>
                    <div style="display:flex; gap:6px; align-items:center;">
                        <button type="button" class="btn btn-secondary" style="padding:4px 9px; font-size:11.5px; color:#059669; border-color:#a7f3d0;" onclick=\'openEditLeadModal(' . $jsonAttr . ')\'>✏️ এডিট</button>
                        <a href="/crm/leads/edit?id=' . urlencode($id) . '" class="btn btn-secondary" style="padding:4px 8px; font-size:11.5px;" title="ফুল পেজ এডিট">📄</a>
                        <button type="button" class="btn btn-secondary" style="padding:4px 8px; font-size:11.5px; color:#dc2626; border-color:#fecaca;" onclick="confirmDeleteRecord(\'crm_leads\', \'' . $id . '\', \'/crm/leads/delete\', \'' . htmlspecialchars($name, ENT_QUOTES) . ' (' . $id . ')\')">🗑️ মুছুন</button>
                    </div>
                </td>
            </tr>';
        }

        $body = '
        ' . $flashAlert . '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">🎯 লিড ম্যানেজমেন্ট (Lead Registry)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সকল সম্ভাব্য ক্লায়েন্ট, যোগাযোগ, অপরচুনিটি ভ্যালু ও স্ট্যাটাস সম্পাদন</p>
            </div>
            <button class="btn btn-primary" style="background:#059669;" onclick="openNewLeadModal()">+ নতুন লিড অ্যাড করুন</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>লিড আইডি</th>
                            <th>নাম</th>
                            <th>প্রতিষ্ঠান</th>
                            <th>ইমেইল / ফোন</th>
                            <th>পর্যায় (Stage)</th>
                            <th style="text-align:right;">সম্ভাব্য ভ্যালু</th>
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

        <!-- ══ EDIT LEAD MODAL ══ -->
        <div id="leadModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:16px;">
            <div style="background:#fff; border-radius:12px; width:100%; max-width:520px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.15); overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                    <h3 id="leadModalTitle" style="margin:0; font-size:16px; font-weight:700;">✏️ লিড তথ্য এডিট করুন</h3>
                    <button type="button" onclick="closeLeadModal()" style="border:none; background:transparent; font-size:18px; cursor:pointer;">✕</button>
                </div>
                <form id="leadForm" method="POST" action="/crm/leads/update" style="padding:20px;" onsubmit="handleLeadSubmit(event)">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">লিড আইডি *</label>
                            <input type="text" id="ld_id" name="id" readonly required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; background:#f1f5f9; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">ক্লায়েন্ট নাম *</label>
                            <input type="text" id="ld_name" name="name" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">প্রতিষ্ঠান / কোম্পানি</label>
                            <input type="text" id="ld_company" name="company" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">ফোন / মোবাইল *</label>
                            <input type="text" id="ld_phone" name="phone" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">সম্ভাব্য ভ্যালু (৳) *</label>
                            <input type="number" step="0.01" id="ld_value" name="value" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">পাইপলাইন পর্যায়</label>
                            <input type="text" id="ld_stage" name="stage" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeLeadModal()">বাতিল</button>
                        <button type="submit" id="saveLeadBtn" class="btn btn-primary" style="background:#059669;">💾 সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openEditLeadModal(item) {
            document.getElementById("leadModalTitle").innerText = "✏️ লিড এডিট: " + item.name;
            document.getElementById("ld_id").value = item.id || "";
            document.getElementById("ld_id").readOnly = true;
            document.getElementById("ld_name").value = item.name || "";
            document.getElementById("ld_company").value = item.company || "";
            document.getElementById("ld_phone").value = item.phone || "";
            document.getElementById("ld_value").value = item.value || 0;
            document.getElementById("ld_stage").value = item.stage || "Proposal";
            document.getElementById("leadModal").style.display = "flex";
        }

        function openNewLeadModal() {
            const nextId = "LD-" + Math.floor(100 + Math.random() * 900);
            document.getElementById("leadModalTitle").innerText = "➕ নতুন লিড যোগ করুন";
            document.getElementById("ld_id").value = nextId;
            document.getElementById("ld_id").readOnly = false;
            document.getElementById("ld_name").value = "";
            document.getElementById("ld_company").value = "";
            document.getElementById("ld_phone").value = "+880 1";
            document.getElementById("ld_value").value = 100000.00;
            document.getElementById("ld_stage").value = "New Lead";
            document.getElementById("leadModal").style.display = "flex";
        }

        function closeLeadModal() {
            document.getElementById("leadModal").style.display = "none";
        }

        async function handleLeadSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById("saveLeadBtn");
            btn.disabled = true;
            btn.innerText = "সংরক্ষণ হচ্ছে...";

            const form = document.getElementById("leadForm");
            const formData = new FormData(form);

            try {
                const response = await fetch("/crm/leads/update", {
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

        return Response::html(ModuleLayoutHelper::wrap('crm', 'লিড ম্যানেজমেন্ট', $body, '', '', '/crm/leads'));
    }

    /**
     * Standalone Edit Lead Page (GET /crm/leads/edit?id=...)
     */
    public function editLead(Request $request): Response
    {
        $id = (string) $request->query('id', '');
        $lead = ModuleDataRepository::find('crm_leads', 'id', $id);

        if (!$lead) {
            return Response::redirect('/crm/leads?msg=' . urlencode('লিড পাওয়া যায়নি'));
        }

        $ldId = htmlspecialchars($lead['id']);
        $name = htmlspecialchars($lead['name'] ?? '');
        $company = htmlspecialchars($lead['company'] ?? '');
        $phone = htmlspecialchars($lead['phone'] ?? '');
        $val = (float) ($lead['value'] ?? 0);
        $stage = htmlspecialchars($lead['stage'] ?? '');

        $body = '
        <div style="max-width:680px; margin:0 auto;">
            <div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">✏️ লিড সম্পাদনা: ' . $ldId . '</h2>
                    <p style="margin:0; font-size:13px; color:#64748b;">ক্লায়েন্ট নাম, প্রতিষ্ঠান, যোগাযোগ ও অপরচুনিটি ভ্যালু পরিবর্তন করুন</p>
                </div>
                <a href="/crm/leads" class="btn btn-secondary">← ফিরে যান</a>
            </div>

            <div class="card" style="padding:24px;">
                <form method="POST" action="/crm/leads/update">
                    <input type="hidden" name="id" value="' . $ldId . '">

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">লিড আইডি</label>
                            <input type="text" value="' . $ldId . '" disabled style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; background:#f1f5f9; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">ক্লায়েন্টের নাম</label>
                            <input type="text" name="name" value="' . $name . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">প্রতিষ্ঠান</label>
                            <input type="text" name="company" value="' . $company . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">মোবাইল নম্বর</label>
                            <input type="text" name="phone" value="' . $phone . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">সম্ভাব্য ভ্যালু (৳)</label>
                            <input type="number" step="0.01" name="value" value="' . $val . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">পাইপলাইন পর্যায়</label>
                            <input type="text" name="stage" value="' . $stage . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <a href="/crm/leads" class="btn btn-secondary">বাতিল</a>
                        <button type="submit" class="btn btn-primary" style="background:#059669;">💾 পরিবর্তন সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('crm', 'লিড এডিট', $body, '', '', '/crm/leads'));
    }

    /**
     * Update / Create Lead Handler (POST /crm/leads/update)
     */
    public function updateLead(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            $id = 'LD-' . rand(100, 999);
        }

        $data = [
            'id' => $id,
            'name' => trim((string) $request->input('name', 'Lead Name')),
            'company' => trim((string) $request->input('company', '')),
            'phone' => trim((string) $request->input('phone', '')),
            'value' => (float) $request->input('value', 0.0),
            'stage' => trim((string) $request->input('stage', 'New Lead')),
            'status' => 'active',
        ];

        $existing = ModuleDataRepository::find('crm_leads', 'id', $id);
        if ($existing) {
            ModuleDataRepository::update('crm_leads', 'id', $id, $data);
            $msg = "লিড {$data['name']} সফলভাবে আপডেট করা হয়েছে।";
        } else {
            ModuleDataRepository::insert('crm_leads', $data);
            $msg = "নতুন লিড {$data['name']} সফলভাবে যোগ করা হয়েছে।";
        }

        if ($request->wantsJson() || $request->isAjax()) {
            return Response::json(['success' => true, 'message' => $msg, 'data' => $data]);
        }

        return Response::redirect('/crm/leads?msg=' . urlencode($msg));
    }

    /**
     * Deals Page
     */
    public function deals(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">💼 ডিল পাইপলাইন (Deal Pipeline)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সেলস কানবান ও ক্লোজিং প্রোবাবিলিটি ট্র্যাকিং</p>
            </div>
            <button class="btn btn-primary" style="background:#059669;">+ নতুন ডিল খুলুন</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ডিল নাম</th>
                            <th>গ্রাহক প্রতিষ্ঠান</th>
                            <th>পাইপলাইন স্টেজ</th>
                            <th>সম্ভাবনা (%)</th>
                            <th style="text-align:right;">চুক্তি মূল্য (৳)</th>
                            <th>সম্ভাব্য ক্লোজিং</th>
                            <th>ম্যানেজার</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>ইন্টারনেট বিলিং অটোমেশন সিস্টেম</strong></td><td>প্রাইম টেক লিমিটেড</td><td><span style="color:#7c3aed; font-weight:600;">প্রস্তাবনা পর্যায়</span></td><td>৭৫%</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ১,৫০,০০০.০০</td><td>২০২৬-১০-২৮</td><td>সাকিব রহমান</td></tr>
                        <tr><td><strong>কর্পোরেট ইআরপি অ্যান্ড একাউন্টিং</strong></td><td>স্কাইলাইন লজিস্টিকস</td><td><span style="color:#f59e0b; font-weight:600;">দরকষাকষি</span></td><td>৯০%</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ২,৫০,০০০.০০</td><td>২০২৬-১০-২০</td><td>আসিফ ইকবাল</td></tr>
                        <tr><td><strong>মাল্টি-টেন্যান্ট পিওএস সলিউশন</strong></td><td>এলিট ফ্যাশনস চেইন</td><td><span style="color:#2563eb; font-weight:600;">যোগ্য লিড</span></td><td>৫০%</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৮৫,০০০.০০</td><td>২০২৬-১১-০৫</td><td>রিয়াদ মাহমুদ</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('crm', 'ডিল পাইপলাইন', $body, '', '', '/crm/deals'));
    }

    /**
     * Companies Page
     */
    public function companies(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">🏢 কোম্পানি ডিরেক্টরি (Accounts & Companies)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">বি২বি ক্লায়েন্ট ও অংশীদার প্রতিষ্ঠানের পূর্ণাঙ্গ তালিকা</p>
            </div>
            <button class="btn btn-primary" style="background:#059669;">+ নতুন কোম্পানি যুক্ত করুন</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>কোম্পানির নাম</th>
                            <th>ইন্ডাস্ট্রি</th>
                            <th>শহর / ঠিকানা</th>
                            <th>কর্মচারী সাইজ</th>
                            <th>ওয়েবসাইট</th>
                            <th>লাইফসাইকেল স্টেজ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>প্রাইম টেক লিমিটেড</strong></td><td>সফটওয়্যার ও আইটি</td><td>গুলশান, ঢাকা</td><td>৫০-১০০ জন</td><td>primetech.bd</td><td>' . ModuleLayoutHelper::badge('কাস্টমার', 'success') . '</td></tr>
                        <tr><td><strong>এলিট ফ্যাশনস পিএলসি</strong></td><td>গার্মেন্টস ও রিটেইল</td><td>উত্তরা, ঢাকা</td><td>২০০+ জন</td><td>elitefashion.com</td><td>' . ModuleLayoutHelper::badge('সুযোগ (Opportunity)', 'warning') . '</td></tr>
                        <tr><td><strong>স্কাইলাইন লজিস্টিকস</strong></td><td>সাপ্লাই চেইন ও শিপিং</td><td>চট্টগ্রাম বন্দর এলাকা</td><td>১০০-১৫০ জন</td><td>skyline.bd</td><td>' . ModuleLayoutHelper::badge('কাস্টমার', 'success') . '</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('crm', 'কোম্পানি ডিরেক্টরি', $body, '', '', '/crm/companies'));
    }

    /**
     * Contacts Page
     */
    public function contacts(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📞 কনটাক্ট ম্যানেজার (Customer Contacts)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সকল ক্লায়েন্ট প্রতিনিধির সরাসরি ফোন, ইমেইল ও সোশ্যাল প্রোফাইল</p>
            </div>
            <button class="btn btn-primary" style="background:#059669;">+ নতুন কনটাক্ট তৈরি</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ব্যক্তির নাম</th>
                            <th>পদবি ও কোম্পানি</th>
                            <th>ইমেইল ঠিকানা</th>
                            <th>মোবাইল নম্বর</th>
                            <th>হোয়াটসঅ্যাপ</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>ইঞ্জি. তানভীর আহমেদ</strong></td><td>সিটিও, প্রাইম টেক লিঃ</td><td>tanvir@primetech.bd</td><td>+880 1711-223344</td><td><span style="color:#059669;">সক্রিয়</span></td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">ইমেইল পাঠান</button></td></tr>
                        <tr><td><strong>নুসরাত জাহান</strong></td><td>এইচআর হেড, এলিট ফ্যাশনস</td><td>nusrat@elitefashion.com</td><td>+880 1819-334455</td><td><span style="color:#059669;">সক্রিয়</span></td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">ইমেইল পাঠান</button></td></tr>
                        <tr><td><strong>মাহমুদুল হাসান</strong></td><td>অপারেশনস ডিরেক্টর, স্কাইলাইন</td><td>hasan@skyline.bd</td><td>+880 1912-778899</td><td><span style="color:#059669;">সক্রিয়</span></td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">ইমেইল পাঠান</button></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('crm', 'কনটাক্ট ম্যানেজার', $body, '', '', '/crm/contacts'));
    }

    /**
     * Tickets Page
     */
    public function tickets(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">🎫 কাস্টমার সাপোর্ট হেল্পডেস্ক (Tickets)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">গ্রাহকদের প্রশ্ন ও টেকনিক্যাল সমস্যা সমাধান ডেস্ক</p>
            </div>
            <button class="btn btn-primary" style="background:#059669;">+ সাপোর্ট টিকিট ওপেন করুন</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>টিকিট ID</th>
                            <th>বিষয় ও বিবরণ</th>
                            <th>ক্লায়েন্ট</th>
                            <th>প্রায়োরিটি</th>
                            <th>দায়িত্বপ্রাপ্ত</th>
                            <th>স্ট্যাটাস</th>
                            <th>আপডেট</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>TCK-109</code></td><td><strong>পেমেন্ট গেটওয়ে এপিআই কলব্যাক টাইমআউট</strong></td><td>প্রাইম টেক লিঃ</td><td>' . ModuleLayoutHelper::badge('উচ্চ (High)', 'danger') . '</td><td>সাকিব রহমান</td><td>' . ModuleLayoutHelper::badge('চলমান (In Progress)', 'warning') . '</td><td>১৫ মিনিট আগে</td></tr>
                        <tr><td><code>TCK-108</code></td><td><strong>ইনভয়েস বাংলা পিডিএফ ফন্ট সাইজ অ্যাডজাস্ট</strong></td><td>এলিট ফ্যাশনস</td><td>' . ModuleLayoutHelper::badge('মাঝারি', 'warning') . '</td><td>আসিফ ইকবাল</td><td>' . ModuleLayoutHelper::badge('খোলা (Open)', 'info') . '</td><td>২ ঘণ্টা আগে</td></tr>
                        <tr><td><code>TCK-107</code></td><td><strong>মাল্টি-টেন্যান্ট রোল ও পারমিশন কনফিগারেশন</strong></td><td>স্কাইলাইন লিঃ</td><td>' . ModuleLayoutHelper::badge('স্বাভাবিক', 'info') . '</td><td>রিয়াদ মাহমুদ</td><td>' . ModuleLayoutHelper::badge('সমাধানকৃত', 'success') . '</td><td>গতকাল</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('crm', 'সাপোর্ট টিকিট', $body, '', '', '/crm/tickets'));
    }

    /**
     * Analytics Page
     */
    public function analytics(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📊 সেলস অ্যানালিটিকস ও পারফরম্যান্স (Sales Metrics)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">কনভার্শন রেট, সেলস সাইকেল ও চ্যানেল এফিসিয়েন্সি রিপোর্ট</p>
            </div>
            <button class="btn btn-secondary" onclick="window.print()">🖨️ প্রিন্ট অ্যানালিটিকস</button>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-header"><span class="card-title">📈 লিড সোর্স পারফরম্যান্স</span></div>
                <div style="padding:16px 20px;">
                    <div style="margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;"><span>ওয়েবসাইট ও অর্গানিক সার্চ</span><strong>৪৫% (১৫৬টি লিড)</strong></div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:45%; background:#059669;"></div></div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;"><span>রেফারেল ও সরাসরি নেটওয়ার্ক</span><strong>৩০% (১০৪টি লিড)</strong></div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:30%; background:#2563eb;"></div></div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;"><span>সোশ্যাল মিডিয়া ক্যাম্পেইন</span><strong>২৫% (৮৭টি লিড)</strong></div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:25%; background:#f59e0b;"></div></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><span class="card-title">⏱️ সেলস সাইকেল মেট্রিক্স</span></div>
                <div style="padding:16px 20px;">
                    <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f1f5f9;">
                        <span>গড় লিড-টু-ডিল কনভার্শন সময়</span><strong>৭.২ দিন</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f1f5f9;">
                        <span>গড় ডিল সাইজ (Average Deal Size)</span><strong style="color:#065f46;">৳ ২৯,৮৪০.০০</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:8px 0;">
                        <span>টিম লিড রেসপন্স টাইম</span><strong style="color:#059669;">১৮ মিনিট</strong>
                    </div>
                </div>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('crm', 'সেলস অ্যানালিটিকস', $body, '', '', '/crm/analytics'));
    }

    /**
     * Delete a lead and log audit footprint to MySQL
     */
    public function deleteLead(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            return Response::json(['success' => false, 'message' => 'লিড আইডি প্রদান করা আবশ্যক।'], 400);
        }

        $lead = ModuleDataRepository::find('crm_leads', 'id', $id);
        if (!$lead) {
            return Response::json(['success' => false, 'message' => 'লিড পাওয়া যায়নি।'], 404);
        }

        $deleted = ModuleDataRepository::delete('crm_leads', 'id', $id);
        if ($deleted) {
            return Response::json(['success' => true, 'message' => "লিড {$lead['name']} ({$id}) সফলভাবে মুছে ফেলা হয়েছে এবং অডিট টেবিলে ফুটপ্রিন্ট সংরক্ষিত হয়েছে।"]);
        }
        return Response::json(['success' => false, 'message' => 'লিড মুছতে ব্যর্থ হয়েছে।'], 500);
    }
}
