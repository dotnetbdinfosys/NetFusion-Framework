<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Controllers/BillingController.php
| Module    : Modules\Billing
| Namespace : NetFusion\Modules\Billing\Controllers
| Purpose   : Full-featured SaaS Subscription & Billing Management Dashboard.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Modules\Billing\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;
use NetFusion\App\Services\ModuleDataRepository;

class BillingController
{
    /**
     * Billing Overview Dashboard
     */
    public function index(Request $request): Response
    {
        $body = '
        <div style="background: linear-gradient(135deg, #5b21b6 0%, #7c3aed 100%); color: #fff; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(124,58,237,0.25);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h2 style="margin: 0 0 6px 0; font-size: 22px; font-weight: 700; color: #fff;">SaaS সাবস্ক্রিপশন ও অটোমেটেড বিলিং সিস্টেম</h2>
                    <p style="margin: 0; opacity: 0.9; font-size: 13.5px;">রিক্যুরিং বিলিং, ইনভয়েসিং, পেমেন্ট গেটওয়ে ও মাল্টি-টিয়ার প্ল্যান ম্যানেজমেন্ট</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="/billing/invoices" class="btn btn-secondary" style="background: rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.4);">+ নতুন ইনভয়েস</a>
                    <a href="/billing/plans" class="btn btn-secondary" style="background: #ffffff; color: #5b21b6; font-weight: 600;">প্ল্যান তালিকা</a>
                </div>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card" style="border-left: 4px solid #10b981;">
                <div class="kpi-label">মোট সংগৃহীত আয় (Revenue)</div>
                <div class="kpi-value" style="color: #065f46;">' . ModuleLayoutHelper::currency(845930.00) . '</div>
                <div class="kpi-subtext" style="color: #059669;">↑ ১৮.৩% বৃদ্ধি বিগত কোয়ার্টারের তুলনায়</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #7c3aed;">
                <div class="kpi-label">সক্রিয় সাবস্ক্রিপশন (Active Subs)</div>
                <div class="kpi-value" style="color: #6d28d9;">১২৩টি</div>
                <div class="kpi-subtext" style="color: #7c3aed;">৮৯টি এন্টারপ্রাইজ, ৩৪টি স্টার্টার</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #f59e0b;">
                <div class="kpi-label">মাসিক রিকারিং আয় (MRR)</div>
                <div class="kpi-value" style="color: #b45309;">' . ModuleLayoutHelper::currency(70494.17) . '</div>
                <div class="kpi-subtext">ARR পূর্বাভাস: ৳ ৮,৪৫,৯৩০.০০</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #ef4444;">
                <div class="kpi-label">বকেয়া ইনভয়েস (Overdue)</div>
                <div class="kpi-value" style="color: #991b1b;">১৭টি</div>
                <div class="kpi-subtext" style="color: #dc2626;">বকেয়া মোট ৳ ৫৮,৪৫০.০০</div>
            </div>
        </div>

        <div class="grid-2" style="margin-bottom: 24px;">
            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="card-title">📦 সাবস্ক্রিপশন প্ল্যান সমূহ ও ইউজার ডিস্ট্রিবিউশন</span>
                    <a href="/billing/plans" style="font-size:12px; color:#7c3aed; text-decoration:none; font-weight:600;">সব প্ল্যান →</a>
                </div>
                <div style="padding: 16px 20px;">
                    <div style="margin-bottom: 14px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:5px;">
                            <span><strong>Enterprise Suite</strong> (৳ ৯,৯৯৯/মাস)</span>
                            <span style="color:#7c3aed; font-weight:700;">৬৪ জন গ্রাহক (৫২%)</span>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 52%; background:#7c3aed;"></div></div>
                    </div>
                    <div style="margin-bottom: 14px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:5px;">
                            <span><strong>Professional Pro</strong> (৳ ৪,৯৯৯/মাস)</span>
                            <span style="color:#2563eb; font-weight:700;">৩৯ জন গ্রাহক (৩২%)</span>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 32%; background:#2563eb;"></div></div>
                    </div>
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:5px;">
                            <span><strong>Starter Tier</strong> (৳ ১,৯৯৯/মাস)</span>
                            <span style="color:#10b981; font-weight:700;">২০ জন গ্রাহক (১৬%)</span>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 16%; background:#10b981;"></div></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="card-title">⚠️ বকেয়া সতর্কবার্তা ও রিকভারি অ্যাকশন</span>
                    <a href="/billing/overdue" style="font-size:12px; color:#ef4444; text-decoration:none; font-weight:600;">বকেয়া তালিকা →</a>
                </div>
                <div style="padding: 16px 20px;">
                    <div style="padding: 12px; background: #fef2f2; border-left: 4px solid #ef4444; border-radius: 6px; margin-bottom: 12px;">
                        <div style="font-weight: 700; color: #991b1b; font-size: 13.5px;">১৭টি ইনভয়েসের নির্ধারিত মেয়াদ উত্তীর্ণ হয়েছে</div>
                        <div style="font-size: 12.5px; color: #b91c1c; margin-top: 3px;">অটোমেটেড এসএমএস ও ইমেইল রিমাইন্ডার শিডিউল সক্রিয় রয়েছে।</div>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px; padding:8px 0; border-bottom:1px solid #f1f5f9;">
                        <span>গড় পেমেন্ট রিকভারি সাইকেল</span>
                        <strong>৩.৪ দিন</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px; padding:8px 0;">
                        <span>গেটওয়ে ট্রানজেকশন সাফল্য হার</span>
                        <strong style="color:#059669;">৯৮.৭%</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <span class="card-title">📄 সাম্প্রতিক বিলিং ইনভয়েস সমূহ</span>
                <a href="/billing/invoices" class="btn btn-primary" style="padding:6px 14px; font-size:12px; background:#7c3aed;">সব ইনভয়েস দেখুন</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ইনভয়েস #</th>
                            <th>গ্রাহক / প্রতিষ্ঠান</th>
                            <th>সাবস্ক্রিপশন প্ল্যান</th>
                            <th>ইস্যু তারিখ</th>
                            <th>মেয়াদ শেষ</th>
                            <th style="text-align:right;">মোট পরিমাণ</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>INV-2026-781</strong></td>
                            <td>ঢাকা টেক সল্যুশনস লিঃ</td>
                            <td>Enterprise Suite (বার্ষিক)</td>
                            <td>২০২৬-১০-১৪</td>
                            <td>২০২৬-১০-২৪</td>
                            <td style="text-align:right; font-weight:700; color:#065f46;">৳ ১,১৯,৯৮৮.০০</td>
                            <td>' . ModuleLayoutHelper::badge('পরিশোধিত', 'success') . '</td>
                        </tr>
                        <tr>
                            <td><strong>INV-2026-780</strong></td>
                            <td>ব্লু-স্কাই সফটওয়্যার লিমিটেড</td>
                            <td>Professional Pro (মাসিক)</td>
                            <td>২০২৬-১০-১৪</td>
                            <td>২০২৬-১০-২১</td>
                            <td style="text-align:right; font-weight:700; color:#065f46;">৳ ৪,৯৯৯.০০</td>
                            <td>' . ModuleLayoutHelper::badge('পরিশোধিত', 'success') . '</td>
                        </tr>
                        <tr>
                            <td><strong>INV-2026-779</strong></td>
                            <td>প্রাইম লজিস্টিকস কর্পোরেশন</td>
                            <td>Enterprise Suite (মাসিক)</td>
                            <td>২০২৬-১০-১৩</td>
                            <td>২০২৬-১০-২০</td>
                            <td style="text-align:right; font-weight:700; color:#b45309;">৳ ৯,৯৯৯.০০</td>
                            <td>' . ModuleLayoutHelper::badge('পেন্ডিং', 'warning') . '</td>
                        </tr>
                        <tr>
                            <td><strong>INV-2026-778</strong></td>
                            <td>স্মার্ট ফ্যাশনস ডট কম</td>
                            <td>Professional Pro (মাসিক)</td>
                            <td>২০২৬-১০-০১</td>
                            <td>২০২৬-১০-০৮</td>
                            <td style="text-align:right; font-weight:700; color:#991b1b;">৳ ৪,৯৯৯.০০</td>
                            <td>' . ModuleLayoutHelper::badge('মেয়াদোত্তীর্ণ', 'danger') . '</td>
                        </tr>
                        <tr>
                            <td><strong>INV-2026-777</strong></td>
                            <td>মেঘনা গ্রুপ আইটি ডিপার্টমেন্ট</td>
                            <td>Enterprise Multi-Tenant</td>
                            <td>২০২৬-১০-১২</td>
                            <td>২০২৬-১০-১৯</td>
                            <td style="text-align:right; font-weight:700; color:#065f46;">৳ ২৪,৯৯৯.০০</td>
                            <td>' . ModuleLayoutHelper::badge('পরিশোধিত', 'success') . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('billing', 'ড্যাশবোর্ড', $body, '', '', '/billing'));
    }

    /**
     * Invoices List Page - Fully Functional & Editable
     */
    public function invoices(Request $request): Response
    {
        $invoices = ModuleDataRepository::all('billing_invoices');
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
        foreach ($invoices as $inv) {
            $invId = htmlspecialchars($inv['id'] ?? '');
            $customer = htmlspecialchars($inv['customer'] ?? '');
            $plan = htmlspecialchars($inv['plan'] ?? '');
            $cycle = htmlspecialchars($inv['cycle'] ?? 'মাসিক');
            $amount = (float) ($inv['amount'] ?? 0);
            $method = htmlspecialchars($inv['method'] ?? '');
            $status = $inv['status'] ?? 'pending';
            $dueDate = htmlspecialchars($inv['due_date'] ?? date('Y-m-d'));

            $badgeType = match ($status) {
                'paid' => 'success',
                'overdue' => 'danger',
                'cancelled' => 'secondary',
                default => 'warning',
            };

            $statusText = match ($status) {
                'paid' => 'পরিশোধিত (Paid)',
                'overdue' => 'মেয়াদোত্তীর্ণ (Overdue)',
                'cancelled' => 'বাতিল (Cancelled)',
                default => 'পেন্ডিং (Pending)',
            };

            $jsonAttr = htmlspecialchars(json_encode($inv, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

            $rowsHtml .= '
            <tr id="row-' . $invId . '">
                <td><strong>' . $invId . '</strong></td>
                <td class="col-customer">' . $customer . '</td>
                <td class="col-plan">' . $plan . '</td>
                <td class="col-cycle">' . $cycle . '</td>
                <td class="col-amount" style="text-align:right; font-weight:700;">' . ModuleLayoutHelper::currency($amount) . '</td>
                <td class="col-method">' . $method . '</td>
                <td class="col-status">' . ModuleLayoutHelper::badge($statusText, $badgeType) . '</td>
                <td>
                    <div style="display:flex; gap:6px; align-items:center;">
                        <button type="button" class="btn btn-secondary" style="padding:4px 9px; font-size:11.5px; color:#5b21b6; border-color:#ddd6fe;" onclick=\'openEditInvoiceModal(' . $jsonAttr . ')\'>✏️ এডিট</button>
                        <a href="/billing/invoices/edit?id=' . urlencode($invId) . '" class="btn btn-secondary" style="padding:4px 8px; font-size:11.5px;" title="ফুল পেজ এডিট">📄</a>
                        <button type="button" class="btn btn-secondary" style="padding:4px 8px; font-size:11.5px;" onclick="window.print()">রশিদ</button>
                    </div>
                </td>
            </tr>';
        }

        $body = '
        ' . $flashAlert . '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📄 ইনভয়েস তালিকা (All Invoices)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সকল সফল ও পেন্ডিং বিলিং চালান এবং রশিদ ব্যবস্থাপনা ও সম্পাদনা</p>
            </div>
            <button class="btn btn-primary" style="background:#7c3aed;" onclick="openNewInvoiceModal()">+ নতুন ইনভয়েস তৈরি</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ইনভয়েস #</th>
                            <th>গ্রাহক / প্রতিষ্ঠান</th>
                            <th>প্ল্যান</th>
                            <th>বিলিং সাইকেল</th>
                            <th style="text-align:right;">টাকা</th>
                            <th>পদ্ধতি</th>
                            <th>স্ট্যাটাস</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody id="invoicesTableBody">
                        ' . $rowsHtml . '
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ EDIT INVOICE MODAL ══ -->
        <div id="invoiceModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:16px;">
            <div style="background:#fff; border-radius:12px; width:100%; max-width:540px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.15); overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                    <h3 id="modalTitle" style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">✏️ ইনভয়েস এডিট করুন</h3>
                    <button type="button" onclick="closeInvoiceModal()" style="border:none; background:transparent; font-size:18px; cursor:pointer; color:#64748b;">✕</button>
                </div>
                <form id="invoiceForm" method="POST" action="/billing/invoices/update" style="padding:20px;" onsubmit="handleInvoiceSubmit(event)">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px; color:#475569;">ইনভয়েস নাম্বার *</label>
                            <input type="text" id="inv_id" name="id" readonly required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; background:#f1f5f9; font-weight:600;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px; color:#475569;">পরিশোধের মেয়াদ (Due Date)</label>
                            <input type="date" id="inv_due_date" name="due_date" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                    </div>

                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px; color:#475569;">গ্রাহক / প্রতিষ্ঠান নাম *</label>
                        <input type="text" id="inv_customer" name="customer" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px; color:#475569;">সাবস্ক্রিপশন প্ল্যান</label>
                            <input type="text" id="inv_plan" name="plan" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px; color:#475569;">বিলিং সাইকেল</label>
                            <select id="inv_cycle" name="cycle" style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                                <option value="মাসিক">মাসিক (Monthly)</option>
                                <option value="বার্ষিক">বার্ষিক (Yearly)</option>
                                <option value="ত্রৈমাসিক">ত্রৈমাসিক (Quarterly)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px; color:#475569;">মোট টাকার পরিমাণ (৳) *</label>
                            <input type="number" step="0.01" id="inv_amount" name="amount" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px; color:#475569;">পেমেন্ট পদ্ধতি</label>
                            <select id="inv_method" name="method" style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                                <option value="সিটি ব্যাংক কার্ড">সিটি ব্যাংক কার্ড</option>
                                <option value="বিকাশ গেটওয়ে">বিকাশ গেটওয়ে</option>
                                <option value="নগদ পেমেন্ট">নগদ পেমেন্ট</option>
                                <option value="ভিসা কার্ড">ভিসা কার্ড</option>
                                <option value="ব্যাংক ট্রান্সফার">ব্যাংক ট্রান্সফার</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px; color:#475569;">ইনভয়েস স্ট্যাটাস</label>
                        <select id="inv_status" name="status" style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600;">
                            <option value="paid" style="color:#065f46;">পরিশোধিত (Paid)</option>
                            <option value="pending" style="color:#b45309;">পেন্ডিং (Pending)</option>
                            <option value="overdue" style="color:#991b1b;">মেয়াদোত্তীর্ণ (Overdue)</option>
                            <option value="cancelled" style="color:#475569;">বাতিল (Cancelled)</option>
                        </select>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeInvoiceModal()">বাতিল</button>
                        <button type="submit" id="saveInvoiceBtn" class="btn btn-primary" style="background:#7c3aed;">💾 সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openEditInvoiceModal(item) {
            document.getElementById("modalTitle").innerText = "✏️ ইনভয়েস এডিট করুন: " + item.id;
            document.getElementById("inv_id").value = item.id || "";
            document.getElementById("inv_id").readOnly = true;
            document.getElementById("inv_customer").value = item.customer || "";
            document.getElementById("inv_plan").value = item.plan || "";
            document.getElementById("inv_cycle").value = item.cycle || "মাসিক";
            document.getElementById("inv_amount").value = item.amount || 0;
            document.getElementById("inv_method").value = item.method || "বিকাশ গেটওয়ে";
            document.getElementById("inv_due_date").value = item.due_date || new Date().toISOString().split("T")[0];
            document.getElementById("inv_status").value = item.status || "pending";
            document.getElementById("invoiceModal").style.display = "flex";
        }

        function openNewInvoiceModal() {
            const nextNum = "INV-" + Math.floor(100 + Math.random() * 900);
            document.getElementById("modalTitle").innerText = "➕ নতুন ইনভয়েস তৈরি করুন";
            document.getElementById("inv_id").value = nextNum;
            document.getElementById("inv_id").readOnly = false;
            document.getElementById("inv_customer").value = "";
            document.getElementById("inv_plan").value = "Enterprise Suite (মাসিক)";
            document.getElementById("inv_cycle").value = "মাসিক";
            document.getElementById("inv_amount").value = 9999.00;
            document.getElementById("inv_method").value = "সিটি ব্যাংক কার্ড";
            document.getElementById("inv_due_date").value = new Date().toISOString().split("T")[0];
            document.getElementById("inv_status").value = "pending";
            document.getElementById("invoiceModal").style.display = "flex";
        }

        function closeInvoiceModal() {
            document.getElementById("invoiceModal").style.display = "none";
        }

        async function handleInvoiceSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById("saveInvoiceBtn");
            btn.disabled = true;
            btn.innerText = "সংরক্ষণ হচ্ছে...";

            const form = document.getElementById("invoiceForm");
            const formData = new FormData(form);

            try {
                const response = await fetch("/billing/invoices/update", {
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
                // If fetch fails, submit traditionally
                form.submit();
            } finally {
                btn.disabled = false;
                btn.innerText = "💾 সংরক্ষণ করুন";
            }
        }
        </script>
        ';

        return Response::html(ModuleLayoutHelper::wrap('billing', 'ইনভয়েস তালিকা', $body, '', '', '/billing/invoices'));
    }

    /**
     * Standalone Edit Invoice Page (GET /billing/invoices/edit?id=...)
     */
    public function editInvoice(Request $request): Response
    {
        $id = (string) $request->query('id', '');
        $invoice = ModuleDataRepository::find('billing_invoices', 'id', $id);

        if (!$invoice) {
            return Response::redirect('/billing/invoices?msg=' . urlencode('ইনভয়েস পাওয়া যায়নি'));
        }

        $invId = htmlspecialchars($invoice['id']);
        $customer = htmlspecialchars($invoice['customer'] ?? '');
        $plan = htmlspecialchars($invoice['plan'] ?? '');
        $cycle = htmlspecialchars($invoice['cycle'] ?? 'মাসিক');
        $amount = (float) ($invoice['amount'] ?? 0);
        $method = htmlspecialchars($invoice['method'] ?? '');
        $dueDate = htmlspecialchars($invoice['due_date'] ?? date('Y-m-d'));
        $status = $invoice['status'] ?? 'pending';

        $body = '
        <div style="max-width:680px; margin:0 auto;">
            <div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">✏️ ইনভয়েস সম্পাদনা: ' . $invId . '</h2>
                    <p style="margin:0; font-size:13px; color:#64748b;">গ্রাহক তথ্য, বিলিং প্ল্যান, পরিমাণ ও স্ট্যাটাস পরিবর্তন করুন</p>
                </div>
                <a href="/billing/invoices" class="btn btn-secondary">← ফিরে যান</a>
            </div>

            <div class="card" style="padding:24px;">
                <form method="POST" action="/billing/invoices/update">
                    <input type="hidden" name="id" value="' . $invId . '">

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">ইনভয়েস নম্বর</label>
                            <input type="text" value="' . $invId . '" disabled style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; background:#f1f5f9; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">পরিশোধের শেষ তারিখ (Due Date)</label>
                            <input type="date" name="due_date" value="' . $dueDate . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">গ্রাহক / প্রতিষ্ঠানের নাম</label>
                        <input type="text" name="customer" value="' . $customer . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">সাবস্ক্রিপশন প্ল্যান</label>
                            <input type="text" name="plan" value="' . $plan . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">বিলিং সাইকেল</label>
                            <select name="cycle" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                                <option value="মাসিক" ' . ($cycle === 'মাসিক' ? 'selected' : '') . '>মাসিক (Monthly)</option>
                                <option value="বার্ষিক" ' . ($cycle === 'বার্ষিক' ? 'selected' : '') . '>বার্ষিক (Yearly)</option>
                                <option value="ত্রৈমাসিক" ' . ($cycle === 'ত্রৈমাসিক' ? 'selected' : '') . '>ত্রৈমাসিক (Quarterly)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">টাকার পরিমাণ (৳)</label>
                            <input type="number" step="0.01" name="amount" value="' . $amount . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">পেমেন্ট মেথড</label>
                            <input type="text" name="method" value="' . $method . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="margin-bottom:24px;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">স্ট্যাটাস</label>
                        <select name="status" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:600;">
                            <option value="paid" ' . ($status === 'paid' ? 'selected' : '') . '>পরিশোধিত (Paid)</option>
                            <option value="pending" ' . ($status === 'pending' ? 'selected' : '') . '>পেন্ডিং (Pending)</option>
                            <option value="overdue" ' . ($status === 'overdue' ? 'selected' : '') . '>মেয়াদোত্তীর্ণ (Overdue)</option>
                            <option value="cancelled" ' . ($status === 'cancelled' ? 'selected' : '') . '>বাতিল (Cancelled)</option>
                        </select>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <a href="/billing/invoices" class="btn btn-secondary">বাতিল</a>
                        <button type="submit" class="btn btn-primary" style="background:#7c3aed;">💾 পরিবর্তন সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('billing', 'ইনভয়েস এডিট', $body, '', '', '/billing/invoices'));
    }

    /**
     * Update / Create Invoice Handler (POST /billing/invoices/update)
     */
    public function updateInvoice(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            $id = 'INV-' . strtoupper(bin2hex(random_bytes(3)));
        }

        $customer = trim((string) $request->input('customer', 'গ্রাহক'));
        $plan = trim((string) $request->input('plan', 'Enterprise Suite'));
        $cycle = trim((string) $request->input('cycle', 'মাসিক'));
        $amount = (float) $request->input('amount', 0.0);
        $method = trim((string) $request->input('method', 'সিটি ব্যাংক কার্ড'));
        $dueDate = trim((string) $request->input('due_date', date('Y-m-d')));
        $status = trim((string) $request->input('status', 'pending'));

        $data = [
            'id' => $id,
            'customer' => $customer,
            'plan' => $plan,
            'cycle' => $cycle,
            'amount' => $amount,
            'method' => $method,
            'due_date' => $dueDate,
            'status' => $status,
        ];

        $existing = ModuleDataRepository::find('billing_invoices', 'id', $id);
        if ($existing) {
            ModuleDataRepository::update('billing_invoices', 'id', $id, $data);
            $msg = "ইনভয়েস {$id} সফলভাবে আপডেট করা হয়েছে।";
        } else {
            ModuleDataRepository::insert('billing_invoices', $data);
            $msg = "নতুন ইনভয়েস {$id} সফলভাবে তৈরি করা হয়েছে।";
        }

        if ($request->wantsJson() || $request->isAjax()) {
            return Response::json([
                'success' => true,
                'message' => $msg,
                'data' => $data,
            ]);
        }

        return Response::redirect('/billing/invoices?msg=' . urlencode($msg));
    }

    /**
     * Subscriptions Page - Fully Functional & Editable
     */
    public function subscriptions(Request $request): Response
    {
        $subscriptions = ModuleDataRepository::all('billing_subscriptions');
        $flashMsg = $request->query('msg');
        $flashAlert = '';
        if ($flashMsg) {
            $flashAlert = '
            <div style="padding: 12px 16px; background: #ecfdf5; border-left: 4px solid #10b981; border-radius: 6px; margin-bottom: 20px; color: #065f46; font-size: 13.5px;">
                ✅ ' . htmlspecialchars($flashMsg) . '
            </div>';
        }

        $rowsHtml = '';
        foreach ($subscriptions as $sub) {
            $subId = htmlspecialchars($sub['id'] ?? '');
            $customer = htmlspecialchars($sub['customer'] ?? '');
            $plan = htmlspecialchars($sub['plan'] ?? '');
            $startDate = htmlspecialchars($sub['start_date'] ?? '');
            $renewalDate = htmlspecialchars($sub['renewal_date'] ?? '');
            $autoRenew = htmlspecialchars($sub['auto_renew'] ?? 'চালু (Yes)');
            $status = $sub['status'] ?? 'active';

            $badgeType = match ($status) {
                'active' => 'success',
                'grace_period' => 'warning',
                default => 'danger',
            };

            $statusText = match ($status) {
                'active' => 'সক্রিয় (Active)',
                'grace_period' => 'গ্রেস পিরিয়ড (Grace)',
                default => 'নিষ্ক্রিয় (Inactive)',
            };

            $jsonAttr = htmlspecialchars(json_encode($sub, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

            $rowsHtml .= '
            <tr>
                <td><code>' . $subId . '</code></td>
                <td><strong>' . $customer . '</strong></td>
                <td>' . $plan . '</td>
                <td>' . $startDate . '</td>
                <td>' . $renewalDate . '</td>
                <td>' . $autoRenew . '</td>
                <td>' . ModuleLayoutHelper::badge($statusText, $badgeType) . '</td>
                <td>
                    <button type="button" class="btn btn-secondary" style="padding:4px 9px; font-size:11.5px; color:#5b21b6;" onclick=\'openEditSubModal(' . $jsonAttr . ')\'>✏️ এডিট</button>
                </td>
            </tr>';
        }

        $body = '
        ' . $flashAlert . '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">🔁 সাবস্ক্রিপশন তালিকা (Active Subscriptions)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">গ্রাহকভিত্তিক স্বয়ংক্রিয় রিনিউয়াল, প্যাকেজ স্ট্যাটাস ও সম্পাদন</p>
            </div>
            <button class="btn btn-primary" style="background:#7c3aed;" onclick="openNewSubModal()">+ নতুন সাবস্ক্রিপশন বরাদ্দ</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>সাবস্ক্রিপশন আইডি</th>
                            <th>গ্রাহক / কোম্পানি</th>
                            <th>প্যাকেজ</th>
                            <th>শুরুর তারিখ</th>
                            <th>পরবর্তী রিনিউ</th>
                            <th>অটো-রিনিউ</th>
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

        <!-- ══ EDIT SUB MODAL ══ -->
        <div id="subModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:16px;">
            <div style="background:#fff; border-radius:12px; width:100%; max-width:500px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.15); overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                    <h3 id="subModalTitle" style="margin:0; font-size:16px; font-weight:700;">✏️ সাবস্ক্রিপশন সম্পাদনা</h3>
                    <button type="button" onclick="closeSubModal()" style="border:none; background:transparent; font-size:18px; cursor:pointer;">✕</button>
                </div>
                <form method="POST" action="/billing/subscriptions/update" style="padding:20px;">
                    <input type="hidden" id="sub_id" name="id">

                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">গ্রাহক / কোম্পানি নাম</label>
                        <input type="text" id="sub_customer" name="customer" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">প্যাকেজ / প্ল্যান</label>
                            <input type="text" id="sub_plan" name="plan" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">অটো-রিনিউ</label>
                            <select id="sub_auto_renew" name="auto_renew" style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                                <option value="চালু (Yes)">চালু (Yes)</option>
                                <option value="বন্ধ (No)">বন্ধ (No)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">শুরুর তারিখ</label>
                            <input type="date" id="sub_start_date" name="start_date" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">পরবর্তী রিনিউ তারিখ</label>
                            <input type="date" id="sub_renewal_date" name="renewal_date" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">স্ট্যাটাস</label>
                        <select id="sub_status" name="status" style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600;">
                            <option value="active">সক্রিয় (Active)</option>
                            <option value="grace_period">গ্রেস পিরিয়ড (Grace)</option>
                            <option value="inactive">নিষ্ক্রিয় (Inactive)</option>
                        </select>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeSubModal()">বাতিল</button>
                        <button type="submit" class="btn btn-primary" style="background:#7c3aed;">💾 সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openEditSubModal(item) {
            document.getElementById("subModalTitle").innerText = "✏️ সাবস্ক্রিপশন এডিট: " + item.id;
            document.getElementById("sub_id").value = item.id;
            document.getElementById("sub_customer").value = item.customer || "";
            document.getElementById("sub_plan").value = item.plan || "";
            document.getElementById("sub_auto_renew").value = item.auto_renew || "চালু (Yes)";
            document.getElementById("sub_start_date").value = item.start_date || "";
            document.getElementById("sub_renewal_date").value = item.renewal_date || "";
            document.getElementById("sub_status").value = item.status || "active";
            document.getElementById("subModal").style.display = "flex";
        }

        function openNewSubModal() {
            const nextId = "SUB-0" + Math.floor(100 + Math.random() * 900);
            document.getElementById("subModalTitle").innerText = "➕ নতুন সাবস্ক্রিপশন বরাদ্দ";
            document.getElementById("sub_id").value = nextId;
            document.getElementById("sub_customer").value = "";
            document.getElementById("sub_plan").value = "Professional Pro";
            document.getElementById("sub_auto_renew").value = "চালু (Yes)";
            document.getElementById("sub_start_date").value = new Date().toISOString().split("T")[0];
            document.getElementById("sub_renewal_date").value = new Date(Date.now() + 30*86400000).toISOString().split("T")[0];
            document.getElementById("sub_status").value = "active";
            document.getElementById("subModal").style.display = "flex";
        }

        function closeSubModal() {
            document.getElementById("subModal").style.display = "none";
        }
        </script>
        ';

        return Response::html(ModuleLayoutHelper::wrap('billing', 'সাবস্ক্রিপশন', $body, '', '', '/billing/subscriptions'));
    }

    /**
     * Update Subscription Handler (POST /billing/subscriptions/update)
     */
    public function updateSubscription(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            $id = 'SUB-' . rand(1000, 9999);
        }

        $data = [
            'id' => $id,
            'customer' => trim((string) $request->input('customer', 'গ্রাহক')),
            'plan' => trim((string) $request->input('plan', 'Enterprise Suite')),
            'start_date' => trim((string) $request->input('start_date', date('Y-m-d'))),
            'renewal_date' => trim((string) $request->input('renewal_date', date('Y-m-d', strtotime('+30 days')))),
            'auto_renew' => trim((string) $request->input('auto_renew', 'চালু (Yes)')),
            'status' => trim((string) $request->input('status', 'active')),
        ];

        $existing = ModuleDataRepository::find('billing_subscriptions', 'id', $id);
        if ($existing) {
            ModuleDataRepository::update('billing_subscriptions', 'id', $id, $data);
            $msg = "সাবস্ক্রিপশন {$id} সফলভাবে আপডেট হয়েছে।";
        } else {
            ModuleDataRepository::insert('billing_subscriptions', $data);
            $msg = "নতুন সাবস্ক্রিপশন {$id} বরাদ্দ করা হয়েছে।";
        }

        if ($request->wantsJson() || $request->isAjax()) {
            return Response::json(['success' => true, 'message' => $msg, 'data' => $data]);
        }

        return Response::redirect('/billing/subscriptions?msg=' . urlencode($msg));
    }

    /**
     * Plans Page - Fully Functional & Editable
     */
    public function plans(Request $request): Response
    {
        $plans = ModuleDataRepository::all('billing_plans');
        $flashMsg = $request->query('msg');
        $flashAlert = '';
        if ($flashMsg) {
            $flashAlert = '
            <div style="padding: 12px 16px; background: #ecfdf5; border-left: 4px solid #10b981; border-radius: 6px; margin-bottom: 20px; color: #065f46; font-size: 13.5px;">
                ✅ ' . htmlspecialchars($flashMsg) . '
            </div>';
        }

        $cardsHtml = '';
        $colors = ['starter' => '#10b981', 'pro' => '#2563eb', 'enterprise' => '#7c3aed', 'custom' => '#64748b'];

        foreach ($plans as $plan) {
            $planId = htmlspecialchars($plan['id'] ?? '');
            $name = htmlspecialchars($plan['name'] ?? '');
            $price = (float) ($plan['price'] ?? 0);
            $interval = htmlspecialchars($plan['interval'] ?? 'মাসিক');
            $users = htmlspecialchars($plan['users'] ?? '');
            $features = htmlspecialchars($plan['features'] ?? '');
            $topColor = $colors[$planId] ?? '#7c3aed';

            $featuresList = '';
            foreach (explode(',', $features) as $feat) {
                $feat = trim($feat);
                if ($feat) {
                    $featuresList .= '<li>' . htmlspecialchars($feat) . '</li>';
                }
            }
            if ($users) {
                $featuresList = '<li><strong>' . $users . '</strong></li>' . $featuresList;
            }

            $jsonAttr = htmlspecialchars(json_encode($plan, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

            $cardsHtml .= '
            <div class="card" style="border-top:4px solid ' . $topColor . ';">
                <div class="card-header">
                    <span class="card-title">' . $name . '</span>
                    <span style="font-weight:700; color:' . $topColor . ';">' . ModuleLayoutHelper::currency($price) . ' / ' . $interval . '</span>
                </div>
                <div style="padding:16px 20px;">
                    <ul style="margin:0 0 16px 0; padding-left:20px; line-height:1.8; color:#475569; font-size:13.5px;">
                        ' . $featuresList . '
                    </ul>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="btn btn-secondary" style="flex:1;" onclick=\'openEditPlanModal(' . $jsonAttr . ')\'>✏️ এডিট প্ল্যান</button>
                        <a href="/billing/plans/edit?id=' . urlencode($planId) . '" class="btn btn-secondary" style="padding:6px 12px;" title="ফুল পেজ এডিট">📄</a>
                    </div>
                </div>
            </div>';
        }

        $body = '
        ' . $flashAlert . '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📦 সাবস্ক্রিপশন প্ল্যান সমূহ (Billing Plans)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সিস্টেমের মূল্য প্যাকেজ, ফিচার ও টিয়ার নির্ধারণ ও সম্পাদনা</p>
            </div>
            <button class="btn btn-primary" style="background:#7c3aed;" onclick="openNewPlanModal()">+ নতুন প্যাকেজ তৈরি</button>
        </div>

        <div class="grid-2">
            ' . $cardsHtml . '
        </div>

        <!-- ══ EDIT PLAN MODAL ══ -->
        <div id="planModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:16px;">
            <div style="background:#fff; border-radius:12px; width:100%; max-width:520px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.15); overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                    <h3 id="planModalTitle" style="margin:0; font-size:16px; font-weight:700;">✏️ প্ল্যান প্যাকেজ এডিট করুন</h3>
                    <button type="button" onclick="closePlanModal()" style="border:none; background:transparent; font-size:18px; cursor:pointer;">✕</button>
                </div>
                <form method="POST" action="/billing/plans/update" style="padding:20px;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">প্ল্যান কোড / আইডি</label>
                            <input type="text" id="plan_id" name="id" readonly required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; background:#f1f5f9; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">প্ল্যানের নাম *</label>
                            <input type="text" id="plan_name" name="name" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">মূল্য (৳) *</label>
                            <input type="number" step="0.01" id="plan_price" name="price" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">বিলিং ইন্টারভ্যাল</label>
                            <select id="plan_interval" name="interval" style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                                <option value="মাসিক">মাসিক (Monthly)</option>
                                <option value="বার্ষিক">বার্ষিক (Yearly)</option>
                                <option value="ত্রৈমাসিক">ত্রৈমাসিক (Quarterly)</option>
                                <option value="কাস্টম">কাস্টম কোটেশন</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">ইউজার / টেন্যান্ট ক্যাপাসিটি</label>
                        <input type="text" id="plan_users" name="users" placeholder="যেমন: ২৫ জন ইউজার অ্যাক্সেস" style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">ফিচার তালিকা (কমা দিয়ে লিখুন)</label>
                        <textarea id="plan_features" name="features" rows="3" style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; resize:vertical;"></textarea>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn btn-secondary" onclick="closePlanModal()">বাতিল</button>
                        <button type="submit" class="btn btn-primary" style="background:#7c3aed;">💾 সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openEditPlanModal(item) {
            document.getElementById("planModalTitle").innerText = "✏️ প্ল্যান এডিট: " + item.name;
            document.getElementById("plan_id").value = item.id || "";
            document.getElementById("plan_id").readOnly = true;
            document.getElementById("plan_name").value = item.name || "";
            document.getElementById("plan_price").value = item.price || 0;
            document.getElementById("plan_interval").value = item.interval || "মাসিক";
            document.getElementById("plan_users").value = item.users || "";
            document.getElementById("plan_features").value = item.features || "";
            document.getElementById("planModal").style.display = "flex";
        }

        function openNewPlanModal() {
            const nextKey = "plan_" + Math.floor(100 + Math.random() * 900);
            document.getElementById("planModalTitle").innerText = "➕ নতুন প্যাকেজ প্ল্যান যোগ করুন";
            document.getElementById("plan_id").value = nextKey;
            document.getElementById("plan_id").readOnly = false;
            document.getElementById("plan_name").value = "";
            document.getElementById("plan_price").value = 2999.00;
            document.getElementById("plan_interval").value = "মাসিক";
            document.getElementById("plan_users").value = "১০ জন ইউজার";
            document.getElementById("plan_features").value = "অ্যাকাউন্টিং মডিউল, ইনভয়েস জেনারেশন, ইমেইল রিপোর্ট";
            document.getElementById("planModal").style.display = "flex";
        }

        function closePlanModal() {
            document.getElementById("planModal").style.display = "none";
        }
        </script>
        ';

        return Response::html(ModuleLayoutHelper::wrap('billing', 'প্ল্যান সমূহ', $body, '', '', '/billing/plans'));
    }

    /**
     * Standalone Edit Plan Page (GET /billing/plans/edit?id=...)
     */
    public function editPlan(Request $request): Response
    {
        $id = (string) $request->query('id', '');
        $plan = ModuleDataRepository::find('billing_plans', 'id', $id);

        if (!$plan) {
            return Response::redirect('/billing/plans?msg=' . urlencode('প্ল্যান প্যাকেজ পাওয়া যায়নি'));
        }

        $planId = htmlspecialchars($plan['id']);
        $name = htmlspecialchars($plan['name'] ?? '');
        $price = (float) ($plan['price'] ?? 0);
        $interval = htmlspecialchars($plan['interval'] ?? 'মাসিক');
        $users = htmlspecialchars($plan['users'] ?? '');
        $features = htmlspecialchars($plan['features'] ?? '');

        $body = '
        <div style="max-width:680px; margin:0 auto;">
            <div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">✏️ প্ল্যান প্যাকেজ সম্পাদনা: ' . $name . '</h2>
                    <p style="margin:0; font-size:13px; color:#64748b;">প্যাকেজের মূল্য, মেয়াদ ও ফিচার সমূহ পরিবর্তন করুন</p>
                </div>
                <a href="/billing/plans" class="btn btn-secondary">← ফিরে যান</a>
            </div>

            <div class="card" style="padding:24px;">
                <form method="POST" action="/billing/plans/update">
                    <input type="hidden" name="id" value="' . $planId . '">

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">প্ল্যান কোড</label>
                            <input type="text" value="' . $planId . '" disabled style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; background:#f1f5f9; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">প্ল্যান নাম</label>
                            <input type="text" name="name" value="' . $name . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">মূল্য (৳)</label>
                            <input type="number" step="0.01" name="price" value="' . $price . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">বিলিং ইন্টারভ্যাল</label>
                            <input type="text" name="interval" value="' . $interval . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">ইউজার / টেন্যান্ট ক্যাপাসিটি</label>
                        <input type="text" name="users" value="' . $users . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                    </div>

                    <div style="margin-bottom:24px;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">ফিচার তালিকা</label>
                        <textarea name="features" rows="4" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; line-height:1.6;">' . $features . '</textarea>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <a href="/billing/plans" class="btn btn-secondary">বাতিল</a>
                        <button type="submit" class="btn btn-primary" style="background:#7c3aed;">💾 সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('billing', 'প্ল্যান এডিট', $body, '', '', '/billing/plans'));
    }

    /**
     * Update Plan Handler (POST /billing/plans/update)
     */
    public function updatePlan(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            $id = 'plan_' . strtolower(bin2hex(random_bytes(3)));
        }

        $data = [
            'id' => $id,
            'name' => trim((string) $request->input('name', 'New Plan')),
            'price' => (float) $request->input('price', 0.0),
            'interval' => trim((string) $request->input('interval', 'মাসিক')),
            'users' => trim((string) $request->input('users', '')),
            'features' => trim((string) $request->input('features', '')),
            'status' => 'active',
        ];

        $existing = ModuleDataRepository::find('billing_plans', 'id', $id);
        if ($existing) {
            ModuleDataRepository::update('billing_plans', 'id', $id, $data);
            $msg = "প্যাকেজ প্ল্যান {$data['name']} সফলভাবে আপডেট করা হয়েছে।";
        } else {
            ModuleDataRepository::insert('billing_plans', $data);
            $msg = "নতুন প্যাকেজ প্ল্যান {$data['name']} তৈরি করা হয়েছে।";
        }

        if ($request->wantsJson() || $request->isAjax()) {
            return Response::json(['success' => true, 'message' => $msg, 'data' => $data]);
        }

        return Response::redirect('/billing/plans?msg=' . urlencode($msg));
    }

    /**
     * Payments History Page
     */
    public function payments(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">💵 পেমেন্ট ট্রানজেকশন হিস্টোরি (Payment Logs)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">গেটওয়ে ট্রানজেকশন ও ডিপোজিট হিস্টোরি</p>
            </div>
            <button class="btn btn-secondary" onclick="window.print()">🖨️ এক্সপোর্ট ট্রানজেকশন</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ট্রানজেকশন ID</th>
                            <th>গেটওয়ে</th>
                            <th>ইনভয়েস #</th>
                            <th>গ্রাহক</th>
                            <th style="text-align:right;">পরিমাণ</th>
                            <th>তারিখ ও সময়</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>TXN-BK-9812</code></td><td>bKash Payment Gateway</td><td>INV-780</td><td>ব্লু-স্কাই সফটওয়্যার</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৪,৯৯৯.০০</td><td>২০২৬-১০-১৪ ১৫:২৩</td><td>' . ModuleLayoutHelper::badge('সফল', 'success') . '</td></tr>
                        <tr><td><code>TXN-ST-4410</code></td><td>Stripe Visa/Mastercard</td><td>INV-781</td><td>ঢাকা টেক সল্যুশনস</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ১,১৯,৯৮৮.০০</td><td>২০২৬-১০-১৪ ১১:০২</td><td>' . ModuleLayoutHelper::badge('সফল', 'success') . '</td></tr>
                        <tr><td><code>TXN-NG-2104</code></td><td>Nagad Direct Pay</td><td>INV-777</td><td>মেঘনা গ্রুপ আইটি</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ২৪,৯৯৯.০০</td><td>২০২৬-১০-১২ ১৬:৪৫</td><td>' . ModuleLayoutHelper::badge('সফল', 'success') . '</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('billing', 'পেমেন্ট হিস্টোরি', $body, '', '', '/billing/payments'));
    }

    /**
     * Overdue Invoices Page
     */
    public function overdue(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700; color:#dc2626;">⚠️ বকেয়া ও ড্যু ইনভয়েস ম্যানেজমেন্ট (Overdue Collection)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">মেয়াদোত্তীর্ণ ইনভয়েসের ফলো-আপ ও কালেকশন শিডিউল</p>
            </div>
            <button class="btn btn-primary" style="background:#dc2626;" onclick="alert(\'সকল বকেয়া ক্লায়েন্টকে অটোমেটিক নোটিফিকেশন পাঠানো হয়েছে।\')">📢 একসাথে সবাইকে রিমাইন্ডার পাঠান</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ইনভয়েস #</th>
                            <th>গ্রাহক প্রতিষ্ঠান</th>
                            <th>মেয়াদোত্তীর্ণের দিন</th>
                            <th style="text-align:right;">বকেয়া টাকা</th>
                            <th>যোগাযোগ ফোন</th>
                            <th>সর্বশেষ নোটিশ</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>INV-778</td><td>স্মার্ট ফ্যাশনস ডট কম</td><td>৭ দিন অতিবাহিত</td><td style="text-align:right; font-weight:700; color:#dc2626;">৳ ৪,৯৯৯.০০</td><td>+880 1711-223344</td><td>ইমেইল পাঠানো হয়েছে</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">কল লগ</button></td></tr>
                        <tr><td>INV-765</td><td>রেনেসাঁ অটোমোবাইলস</td><td>১৪ দিন অতিবাহিত</td><td style="text-align:right; font-weight:700; color:#dc2626;">৳ ৯,৯৯৯.০০</td><td>+880 1819-556677</td><td>এসএমএস সতর্কবার্তা</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">কল লগ</button></td></tr>
                        <tr><td>INV-752</td><td>ইস্টার্ন ট্রেডার্স কর্প</td><td>২১ দিন অতিবাহিত</td><td style="text-align:right; font-weight:700; color:#dc2626;">৳ ১৪,৯৯৯.০০</td><td>+880 1912-334455</td><td>সার্ভিস সাসপেনশন নোটিশ</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">কল লগ</button></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('billing', 'বকেয়া ও ড্যু', $body, '', '', '/billing/overdue'));
    }
}