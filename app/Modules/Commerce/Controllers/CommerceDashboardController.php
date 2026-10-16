<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Commerce/Controllers/CommerceDashboardController.php
| Module    : Modules\Commerce
| Namespace : NetFusion\Modules\Commerce\Controllers
| Purpose   : Full-featured E-Commerce & Point-of-Sale (POS) Management Dashboard.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Modules\Commerce\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;
use NetFusion\App\Services\ModuleDataRepository;

class CommerceDashboardController
{
    /**
     * Commerce Overview Dashboard
     */
    public function index(Request $request): Response
    {
        $body = '
        <div style="background: linear-gradient(135deg, #b45309 0%, #d97706 100%); color: #fff; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(217,119,6,0.25);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h2 style="margin: 0 0 6px 0; font-size: 22px; font-weight: 700; color: #fff;">ই-কমার্স ও পয়েন্ট অব সেলস (POS) ম্যানেজমেন্ট সিস্টেম</h2>
                    <p style="margin: 0; opacity: 0.9; font-size: 13.5px;">ডিজিটাল স্টোরফ্রন্ট, অর্ডার ফুলফিলমেন্ট, ইনভেন্টরি সিঙ্ক ও পেমেন্ট গেটওয়ে ইন্টিগ্রেশন</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="/commerce/orders" class="btn btn-secondary" style="background: rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.4);">+ নতুন অর্ডার</a>
                    <a href="/commerce/products" class="btn btn-secondary" style="background: #ffffff; color: #b45309; font-weight: 600;">পণ্য ক্যাটালগ</a>
                </div>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card" style="border-left: 4px solid #10b981;">
                <div class="kpi-label">মোট বিক্রয় রাজস্ব (Total Sales)</div>
                <div class="kpi-value" style="color: #065f46;">' . ModuleLayoutHelper::currency(1234560.00) . '</div>
                <div class="kpi-subtext" style="color: #059669;">↑ ১৪.৮% বিক্রয় বৃদ্ধি চলতি মাসে</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #d97706;">
                <div class="kpi-label">মোট সম্পন্ন অর্ডার (Orders)</div>
                <div class="kpi-value" style="color: #b45309;">৫৮৭টি</div>
                <div class="kpi-subtext">৯৮.২% সফল ডেলিভারি রেট</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #2563eb;">
                <div class="kpi-label">নিবন্ধিত ক্রেতা (Customers)</div>
                <div class="kpi-value" style="color: #1e40af;">১,২৩৪ জন</div>
                <div class="kpi-subtext" style="color: #2563eb;">↑ ৪২ জন নতুন ক্রেতা যোগ হয়েছে</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #8b5cf6;">
                <div class="kpi-label">গড় অর্ডার ভ্যালু (AOV)</div>
                <div class="kpi-value" style="color: #6d28d9;">' . ModuleLayoutHelper::currency(2102.32) . '</div>
                <div class="kpi-subtext">রিপিট পারচেজ রেট: ৩১.৫%</div>
            </div>
        </div>

        <div class="grid-2" style="margin-bottom: 24px;">
            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="card-title">🛍️ সর্বাধিক বিক্রিত পণ্য (Top Selling Products)</span>
                    <a href="/commerce/products" style="font-size:12px; color:#d97706; text-decoration:none; font-weight:600;">সকল পণ্য →</a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>পণ্য নাম</th>
                                <th>মূল্য (৳)</th>
                                <th>বিক্রি</th>
                                <th>স্টক অবস্থা</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>প্রিমিয়াম ওয়্যারলেস হেডসেট</strong><br><small style="color:#64748b;">SKU: AUD-901</small></td>
                                <td>৳ ৩,৫০০</td>
                                <td><strong>১৪২ পিস</strong></td>
                                <td>' . ModuleLayoutHelper::badge('পর্যাপ্ত (৬৮)', 'success') . '</td>
                            </tr>
                            <tr>
                                <td><strong>স্মার্ট ট্র্যাকিং ফিটনেস ব্যান্ড</strong><br><small style="color:#64748b;">SKU: WTB-204</small></td>
                                <td>৳ ২,২০০</td>
                                <td><strong>৯৮ পিস</strong></td>
                                <td>' . ModuleLayoutHelper::badge('পর্যাপ্ত (৪৫)', 'success') . '</td>
                            </tr>
                            <tr>
                                <td><strong>টাইপ-সি আল্ট্রাফাস্ট চার্জার ৬৫W</strong><br><small style="color:#64748b;">SKU: PWR-65W</small></td>
                                <td>৳ ১,৪৫০</td>
                                <td><strong>৮৬ পিস</strong></td>
                                <td>' . ModuleLayoutHelper::badge('কম স্টক (৮)', 'warning') . '</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="card-title">💳 পেমেন্ট মাধ্যম অনুপাত (Payment Methods)</span>
                    <a href="/commerce/payments" style="font-size:12px; color:#d97706; text-decoration:none; font-weight:600;">পেমেন্ট রিপোর্ট →</a>
                </div>
                <div style="padding: 16px 20px;">
                    <div style="margin-bottom: 12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                            <span>বিকাশ (bKash Payment Gateway)</span>
                            <strong>৫২% (৳ ৬,৪১,৯৭০.০০)</strong>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 52%; background:#e2136e;"></div></div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                            <span>ক্যাশ অন ডেলিভারি (COD)</span>
                            <strong>২৮% (৳ ৩,৪৫,৬৭৫.০০)</strong>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 28%; background:#d97706;"></div></div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                            <span>ভিসা ও মাস্টারকার্ড</span>
                            <strong>২০% (৳ ২,৪৬,৯১৫.০০)</strong>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 20%; background:#2563eb;"></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <span class="card-title">📋 সাম্প্রতিক ইকমার্স অর্ডার সমূহ</span>
                <a href="/commerce/orders" class="btn btn-primary" style="padding:6px 14px; font-size:12px; background:#d97706;">সব অর্ডার দেখুন</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>অর্ডার #</th>
                            <th>গ্রাহক</th>
                            <th>আইটেম</th>
                            <th>পেমেন্ট</th>
                            <th>ডেলিভারি স্ট্যাটাস</th>
                            <th style="text-align:right;">মোট টাকা</th>
                            <th>তারিখ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>ORD-2026-904</strong></td>
                            <td>সাদিয়া আফরিন</td>
                            <td>২টি পণ্য</td>
                            <td>' . ModuleLayoutHelper::badge('পরিশোধিত', 'success') . '</td>
                            <td><span style="color:#059669; font-weight:600;">ডেলিভার্ড</span></td>
                            <td style="text-align:right; font-weight:700; color:#065f46;">৳ ৫,৭০০.০০</td>
                            <td>আজ, ০৯:৪০</td>
                        </tr>
                        <tr>
                            <td><strong>ORD-2026-903</strong></td>
                            <td>মেহরাব হোসেন</td>
                            <td>১টি পণ্য</td>
                            <td>' . ModuleLayoutHelper::badge('পরিশোধিত', 'success') . '</td>
                            <td><span style="color:#2563eb; font-weight:600;">পথে আছে (In Transit)</span></td>
                            <td style="text-align:right; font-weight:700; color:#065f46;">৳ ২,২০০.০০</td>
                            <td>আজ, ০৮:১৫</td>
                        </tr>
                        <tr>
                            <td><strong>ORD-2026-902</strong></td>
                            <td>রাশেদুল করিম</td>
                            <td>৩টি পণ্য</td>
                            <td>' . ModuleLayoutHelper::badge('ক্যাশ অন ডেলিভারি', 'warning') . '</td>
                            <td><span style="color:#f59e0b; font-weight:600;">প্যাকিং চলছে</span></td>
                            <td style="text-align:right; font-weight:700; color:#b45309;">৳ ৪,৩৫০.০০</td>
                            <td>গতকাল</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('commerce', 'ড্যাশবোর্ড', $body, '', '', '/commerce'));
    }

    /**
     * Orders Page - Fully Functional & Editable
     */
    public function orders(Request $request): Response
    {
        $orders = ModuleDataRepository::all('commerce_orders');
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
        foreach ($orders as $ord) {
            $code = htmlspecialchars($ord['order_code'] ?? $ord['id'] ?? '');
            $customer = htmlspecialchars($ord['customer'] ?? '');
            $phone = htmlspecialchars($ord['phone'] ?? '');
            $items = (int) ($ord['items_count'] ?? 1);
            $total = (float) ($ord['total_amount'] ?? 0);
            $gateway = htmlspecialchars($ord['gateway'] ?? 'অনলাইন');
            $status = $ord['shipping_status'] ?? 'processing';

            $badgeType = match ($status) {
                'delivered' => 'success',
                'shipped' => 'info',
                'cancelled' => 'danger',
                default => 'warning',
            };

            $statusText = match ($status) {
                'delivered' => 'ডেলিভার্ড (Delivered)',
                'shipped' => 'শিপড (Shipped)',
                'cancelled' => 'বাতিল (Cancelled)',
                default => 'প্রসেসিং (Processing)',
            };

            $jsonAttr = htmlspecialchars(json_encode($ord, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

            $rowsHtml .= '
            <tr id="row-' . $code . '">
                <td><code>' . $code . '</code></td>
                <td><strong>' . $customer . '</strong><br><small style="color:#64748b;">' . $phone . '</small></td>
                <td>' . $items . ' আইটেম</td>
                <td style="text-align:right; font-weight:700; color:#065f46;">' . ModuleLayoutHelper::currency($total) . '</td>
                <td>' . $gateway . '</td>
                <td>' . ModuleLayoutHelper::badge($statusText, $badgeType) . '</td>
                <td>
                    <div style="display:flex; gap:6px; align-items:center;">
                        <button type="button" class="btn btn-secondary" style="padding:4px 9px; font-size:11.5px; color:#d97706; border-color:#fde68a;" onclick=\'openEditOrderModal(' . $jsonAttr . ')\'>✏️ এডিট</button>
                        <a href="/commerce/orders/edit?id=' . urlencode($code) . '" class="btn btn-secondary" style="padding:4px 8px; font-size:11.5px;" title="ফুল পেজ এডিট">📄</a>
                        <button type="button" class="btn btn-secondary" style="padding:4px 8px; font-size:11.5px;" onclick="window.print()">চালান</button>
                    </div>
                </td>
            </tr>';
        }

        $body = '
        ' . $flashAlert . '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📋 অর্ডার ম্যানেজমেন্ট (All Orders)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">কাস্টমার পারচেজ অর্ডার, ফুলফিলমেন্ট, গেটওয়ে ও শিপিং ট্র্যাকিং সম্পাদন</p>
            </div>
            <button class="btn btn-primary" style="background:#d97706;" onclick="openNewOrderModal()">+ নতুন ম্যানুয়াল অর্ডার</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>অর্ডার কোড</th>
                            <th>গ্রাহক নাম ও ফোন</th>
                            <th>পণ্য সংখ্যা</th>
                            <th style="text-align:right;">মোট মূল্য</th>
                            <th>পেমেন্ট গেটওয়ে</th>
                            <th>শিপিং স্ট্যাটাস</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $rowsHtml . '
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ EDIT ORDER MODAL ══ -->
        <div id="orderModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:16px;">
            <div style="background:#fff; border-radius:12px; width:100%; max-width:540px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.15); overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                    <h3 id="orderModalTitle" style="margin:0; font-size:16px; font-weight:700;">✏️ অর্ডার তথ্য এডিট করুন</h3>
                    <button type="button" onclick="closeOrderModal()" style="border:none; background:transparent; font-size:18px; cursor:pointer;">✕</button>
                </div>
                <form id="orderForm" method="POST" action="/commerce/orders/update" style="padding:20px;" onsubmit="handleOrderSubmit(event)">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">অর্ডার কোড *</label>
                            <input type="text" id="ord_code" name="order_code" readonly required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; background:#f1f5f9; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">পণ্য সংখ্যা (আইটেম)</label>
                            <input type="number" id="ord_items" name="items_count" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">গ্রাহকের নাম *</label>
                            <input type="text" id="ord_customer" name="customer" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">মোবাইল নম্বর *</label>
                            <input type="text" id="ord_phone" name="phone" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">মোট মূল্য (৳) *</label>
                            <input type="number" step="0.01" id="ord_total" name="total_amount" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">পেমেন্ট গেটওয়ে</label>
                            <select id="ord_gateway" name="gateway" style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                                <option value="বিকাশ অনলাইন">বিকাশ অনলাইন</option>
                                <option value="ভিসা কার্ড">ভিসা কার্ড</option>
                                <option value="ক্যাশ অন ডেলিভারি">ক্যাশ অন ডেলিভারি</option>
                                <option value="ব্যাংক ট্রান্সফার">ব্যাংক ট্রান্সফার</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">শিপিং স্ট্যাটাস</label>
                        <select id="ord_status" name="shipping_status" style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600;">
                            <option value="processing">প্রসেসিং (Processing)</option>
                            <option value="shipped">শিপড (Shipped)</option>
                            <option value="delivered">ডেলিভার্ড (Delivered)</option>
                            <option value="cancelled">বাতিল (Cancelled)</option>
                        </select>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeOrderModal()">বাতিল</button>
                        <button type="submit" id="saveOrderBtn" class="btn btn-primary" style="background:#d97706;">💾 সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openEditOrderModal(item) {
            document.getElementById("orderModalTitle").innerText = "✏️ অর্ডার এডিট: " + (item.order_code || item.id);
            document.getElementById("ord_code").value = item.order_code || item.id || "";
            document.getElementById("ord_code").readOnly = true;
            document.getElementById("ord_customer").value = item.customer || "";
            document.getElementById("ord_phone").value = item.phone || "";
            document.getElementById("ord_items").value = item.items_count || 1;
            document.getElementById("ord_total").value = item.total_amount || 0;
            document.getElementById("ord_gateway").value = item.gateway || "বিকাশ অনলাইন";
            document.getElementById("ord_status").value = item.shipping_status || "processing";
            document.getElementById("orderModal").style.display = "flex";
        }

        function openNewOrderModal() {
            const nextCode = "ORD-" + Math.floor(100 + Math.random() * 900);
            document.getElementById("orderModalTitle").innerText = "➕ নতুন ম্যানুয়াল অর্ডার তৈরি";
            document.getElementById("ord_code").value = nextCode;
            document.getElementById("ord_code").readOnly = false;
            document.getElementById("ord_customer").value = "";
            document.getElementById("ord_phone").value = "+880 1";
            document.getElementById("ord_items").value = 1;
            document.getElementById("ord_total").value = 2500.00;
            document.getElementById("ord_gateway").value = "ক্যাশ অন ডেলিভারি";
            document.getElementById("ord_status").value = "processing";
            document.getElementById("orderModal").style.display = "flex";
        }

        function closeOrderModal() {
            document.getElementById("orderModal").style.display = "none";
        }

        async function handleOrderSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById("saveOrderBtn");
            btn.disabled = true;
            btn.innerText = "সংরক্ষণ হচ্ছে...";

            const form = document.getElementById("orderForm");
            const formData = new FormData(form);

            try {
                const response = await fetch("/commerce/orders/update", {
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

        return Response::html(ModuleLayoutHelper::wrap('commerce', 'অর্ডার ম্যানেজমেন্ট', $body, '', '', '/commerce/orders'));
    }

    /**
     * Standalone Edit Order Page (GET /commerce/orders/edit?id=...)
     */
    public function editOrder(Request $request): Response
    {
        $id = (string) $request->query('id', '');
        $order = ModuleDataRepository::find('commerce_orders', 'id', $id);

        if (!$order) {
            return Response::redirect('/commerce/orders?msg=' . urlencode('অর্ডার পাওয়া যায়নি'));
        }

        $code = htmlspecialchars($order['order_code'] ?? $order['id']);
        $customer = htmlspecialchars($order['customer'] ?? '');
        $phone = htmlspecialchars($order['phone'] ?? '');
        $items = (int) ($order['items_count'] ?? 1);
        $total = (float) ($order['total_amount'] ?? 0);
        $gateway = htmlspecialchars($order['gateway'] ?? '');
        $status = $order['shipping_status'] ?? 'processing';

        $body = '
        <div style="max-width:680px; margin:0 auto;">
            <div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">✏️ অর্ডার সম্পাদনা: ' . $code . '</h2>
                    <p style="margin:0; font-size:13px; color:#64748b;">গ্রাহক, মোবাইল, মোট মূল্য ও শিপিং স্ট্যাটাস পরিবর্তন করুন</p>
                </div>
                <a href="/commerce/orders" class="btn btn-secondary">← ফিরে যান</a>
            </div>

            <div class="card" style="padding:24px;">
                <form method="POST" action="/commerce/orders/update">
                    <input type="hidden" name="order_code" value="' . $code . '">

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">অর্ডার কোড</label>
                            <input type="text" value="' . $code . '" disabled style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; background:#f1f5f9; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">পণ্য সংখ্যা</label>
                            <input type="number" name="items_count" value="' . $items . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">গ্রাহকের নাম</label>
                            <input type="text" name="customer" value="' . $customer . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">মোবাইল নম্বর</label>
                            <input type="text" name="phone" value="' . $phone . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">মোট মূল্য (৳)</label>
                            <input type="number" step="0.01" name="total_amount" value="' . $total . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">পেমেন্ট গেটওয়ে</label>
                            <input type="text" name="gateway" value="' . $gateway . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="margin-bottom:24px;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">শিপিং স্ট্যাটাস</label>
                        <select name="shipping_status" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:600;">
                            <option value="processing" ' . ($status === 'processing' ? 'selected' : '') . '>প্রসেসিং (Processing)</option>
                            <option value="shipped" ' . ($status === 'shipped' ? 'selected' : '') . '>শিপড (Shipped)</option>
                            <option value="delivered" ' . ($status === 'delivered' ? 'selected' : '') . '>ডেলিভার্ড (Delivered)</option>
                            <option value="cancelled" ' . ($status === 'cancelled' ? 'selected' : '') . '>বাতিল (Cancelled)</option>
                        </select>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <a href="/commerce/orders" class="btn btn-secondary">বাতিল</a>
                        <button type="submit" class="btn btn-primary" style="background:#d97706;">💾 পরিবর্তন সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('commerce', 'অর্ডার এডিট', $body, '', '', '/commerce/orders'));
    }

    /**
     * Update / Create Order Handler (POST /commerce/orders/update)
     */
    public function updateOrder(Request $request): Response
    {
        $code = trim((string) $request->input('order_code', ''));
        if ($code === '') {
            $code = 'ORD-' . rand(100, 999);
        }

        $data = [
            'id' => $code,
            'order_code' => $code,
            'customer' => trim((string) $request->input('customer', 'Customer')),
            'phone' => trim((string) $request->input('phone', '')),
            'items_count' => (int) $request->input('items_count', 1),
            'total_amount' => (float) $request->input('total_amount', 0.0),
            'gateway' => trim((string) $request->input('gateway', 'বিকাশ অনলাইন')),
            'shipping_status' => trim((string) $request->input('shipping_status', 'processing')),
        ];

        $existing = ModuleDataRepository::find('commerce_orders', 'id', $code);
        if ($existing) {
            ModuleDataRepository::update('commerce_orders', 'id', $code, $data);
            $msg = "অর্ডার {$code} সফলভাবে আপডেট করা হয়েছে।";
        } else {
            ModuleDataRepository::insert('commerce_orders', $data);
            $msg = "নতুন অর্ডার {$code} সফলভাবে তৈরি করা হয়েছে।";
        }

        if ($request->wantsJson() || $request->isAjax()) {
            return Response::json(['success' => true, 'message' => $msg, 'data' => $data]);
        }

        return Response::redirect('/commerce/orders?msg=' . urlencode($msg));
    }

    /**
     * Products Page - Fully Functional & Editable
     */
    public function products(Request $request): Response
    {
        $products = ModuleDataRepository::all('inventory_products');
        $flashMsg = $request->query('msg');
        $flashAlert = '';
        if ($flashMsg) {
            $flashAlert = '
            <div style="padding: 12px 16px; background: #ecfdf5; border-left: 4px solid #10b981; border-radius: 6px; margin-bottom: 20px; color: #065f46; font-size: 13.5px;">
                ✅ ' . htmlspecialchars($flashMsg) . '
            </div>';
        }

        $rowsHtml = '';
        foreach ($products as $prod) {
            $sku = htmlspecialchars($prod['id'] ?? $prod['sku'] ?? '');
            $name = htmlspecialchars($prod['name'] ?? '');
            $category = htmlspecialchars($prod['category'] ?? '');
            $price = (float) ($prod['selling_price'] ?? $prod['cost_price'] ?? 0);
            $stock = (int) ($prod['stock'] ?? 0);
            $stockStyle = $stock <= 10 ? 'color:#dc2626; font-weight:700;' : 'color:#065f46; font-weight:700;';

            $jsonAttr = htmlspecialchars(json_encode($prod, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

            $rowsHtml .= '
            <tr>
                <td><code>' . $sku . '</code></td>
                <td><strong>' . $name . '</strong></td>
                <td>' . $category . '</td>
                <td style="text-align:right; font-weight:700;">' . ModuleLayoutHelper::currency($price) . '</td>
                <td style="text-align:right; ' . $stockStyle . '">' . $stock . ' পিস</td>
                <td>' . ModuleLayoutHelper::badge('লাইভ (Active)', 'success') . '</td>
                <td>
                    <button type="button" class="btn btn-secondary" style="padding:4px 9px; font-size:11.5px; color:#d97706;" onclick=\'openEditCommerceProdModal(' . $jsonAttr . ')\'>✏️ এডিট</button>
                </td>
            </tr>';
        }

        $body = '
        ' . $flashAlert . '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📦 প্রোডাক্ট ক্যাটালগ (Product Catalog)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">ই-কমার্স পণ্যের দাম, ভ্যারিয়েন্ট ও ইনভেন্টরি স্টক নিয়ন্ত্রণ ও সম্পাদন</p>
            </div>
            <button class="btn btn-primary" style="background:#d97706;" onclick="window.location.href=\'/inventory/products\'">+ ইনভেন্টরি থেকে যুক্ত করুন</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>পণ্য নাম</th>
                            <th>ক্যাটাগরি</th>
                            <th style="text-align:right;">মূল্য (৳)</th>
                            <th style="text-align:right;">স্টক পরিমাণ</th>
                            <th>দৃশ্যমানতা</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $rowsHtml . '
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ EDIT COMMERCE PROD MODAL ══ -->
        <div id="commProdModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:16px;">
            <div style="background:#fff; border-radius:12px; width:100%; max-width:500px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.15); overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                    <h3 id="commProdModalTitle" style="margin:0; font-size:16px; font-weight:700;">✏️ পণ্য মূল্য ও স্টক এডিট</h3>
                    <button type="button" onclick="closeCommProdModal()" style="border:none; background:transparent; font-size:18px; cursor:pointer;">✕</button>
                </div>
                <form method="POST" action="/commerce/products/update" style="padding:20px;">
                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">SKU কোড</label>
                        <input type="text" id="cp_sku" name="sku" readonly required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; background:#f1f5f9; font-weight:700;">
                    </div>
                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">পণ্যের নাম</label>
                        <input type="text" id="cp_name" name="name" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600;">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">বিক্রয় মূল্য (৳)</label>
                            <input type="number" step="0.01" id="cp_price" name="selling_price" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">স্টক পরিমাণ</label>
                            <input type="number" id="cp_stock" name="stock" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:700;">
                        </div>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeCommProdModal()">বাতিল</button>
                        <button type="submit" class="btn btn-primary" style="background:#d97706;">💾 সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openEditCommerceProdModal(item) {
            document.getElementById("commProdModalTitle").innerText = "✏️ পণ্য এডিট: " + item.name;
            document.getElementById("cp_sku").value = item.sku || item.id || "";
            document.getElementById("cp_name").value = item.name || "";
            document.getElementById("cp_price").value = item.selling_price || item.cost_price || 0;
            document.getElementById("cp_stock").value = item.stock || 0;
            document.getElementById("commProdModal").style.display = "flex";
        }

        function closeCommProdModal() {
            document.getElementById("commProdModal").style.display = "none";
        }
        </script>
        ';

        return Response::html(ModuleLayoutHelper::wrap('commerce', 'প্রোডাক্ট ক্যাটালগ', $body, '', '', '/commerce/products'));
    }

    /**
     * Standalone Edit Commerce Product Page (GET /commerce/products/edit?id=...)
     */
    public function editProduct(Request $request): Response
    {
        return Response::redirect('/inventory/products/edit?id=' . urlencode((string) $request->query('id', '')));
    }

    /**
     * Update Commerce Product Handler (POST /commerce/products/update)
     */
    public function updateProduct(Request $request): Response
    {
        $sku = trim((string) $request->input('sku', ''));
        $data = [
            'name' => trim((string) $request->input('name', '')),
            'selling_price' => (float) $request->input('selling_price', 0.0),
            'stock' => (int) $request->input('stock', 0),
        ];

        ModuleDataRepository::update('inventory_products', 'id', $sku, $data);
        $msg = "কমার্স পণ্য {$sku} সফলভাবে আপডেট হয়েছে।";

        if ($request->wantsJson() || $request->isAjax()) {
            return Response::json(['success' => true, 'message' => $msg, 'data' => $data]);
        }

        return Response::redirect('/commerce/products?msg=' . urlencode($msg));
    }

    /**
     * Customers Page
     */
    public function customers(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">👤 গ্রাহক তালিকা (Store Customers)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সকল ক্রেতার প্রোফাইল, ক্রয় ইতিহাস ও অর্ডারের পরিমাণ</p>
            </div>
            <button class="btn btn-secondary" onclick="window.print()">🖨️ এক্সপোর্ট গ্রাহক তালিকা</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ক্রেতার নাম</th>
                            <th>মোবাইল নম্বর</th>
                            <th>শহর / ঠিকানা</th>
                            <th>মোট অর্ডার</th>
                            <th style="text-align:right;">মোট ব্যয়কৃত টাকা</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>সাদিয়া আফরিন</strong></td><td>+880 1711-234567</td><td>ধানমন্ডি, ঢাকা</td><td>১২টি অর্ডার</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৩৪,৫০০.০০</td><td>' . ModuleLayoutHelper::badge('ভিআইপি ক্রেতা', 'success') . '</td></tr>
                        <tr><td><strong>মেহরাব হোসেন</strong></td><td>+880 1819-876543</td><td>পাঁচলাইশ, চট্টগ্রাম</td><td>৬টি অর্ডার</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ১৫,৪০০.০০</td><td>' . ModuleLayoutHelper::badge('নিয়মিত', 'info') . '</td></tr>
                        <tr><td><strong>রাশেদুল করিম</strong></td><td>+880 1912-345678</td><td>উপশহর, সিলেট</td><td>৩টি অর্ডার</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৮,৭০০.০০</td><td>' . ModuleLayoutHelper::badge('নতুন', 'warning') . '</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('commerce', 'গ্রাহক তালিকা', $body, '', '', '/commerce/customers'));
    }

    /**
     * Payments Page
     */
    public function payments(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">💳 পেমেন্ট গেটওয়ে ও ট্রানজেকশন (Payment Logs)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">অনলাইন পেমেন্ট গেটওয়ে ইন্টিগ্রেশন ও সেটেলমেন্ট রিপোর্ট</p>
            </div>
            <button class="btn btn-secondary">পেমেন্ট গেটওয়ে সেটিংস</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>গেটওয়ে নাম</th>
                            <th>ট্রানজেকশন রেফারেন্স</th>
                            <th>অর্ডার কোড</th>
                            <th style="text-align:right;">পরিমাণ</th>
                            <th>গেটওয়ে ফি</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>bKash Merchant Direct</strong></td><td>TRX-BK-87123</td><td>ORD-904</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৫,৭০০.০০</td><td>১.২%</td><td>' . ModuleLayoutHelper::badge('নিষ্পত্তিকৃত (Settled)', 'success') . '</td></tr>
                        <tr><td><strong>SSLCOMMERZ Visa</strong></td><td>TRX-SSL-44391</td><td>ORD-903</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ২,২০০.০০</td><td>২.০%</td><td>' . ModuleLayoutHelper::badge('নিষ্পত্তিকৃত (Settled)', 'success') . '</td></tr>
                        <tr><td><strong>Cash on Delivery (RedX)</strong></td><td>COD-RDX-1092</td><td>ORD-902</td><td style="text-align:right; font-weight:700; color:#b45309;">৳ ৪,৩৫০.০০</td><td>৳ ৬০.০০</td><td>' . ModuleLayoutHelper::badge('কালেকশন পেন্ডিং', 'warning') . '</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('commerce', 'পেমেন্ট গেটওয়ে', $body, '', '', '/commerce/payments'));
    }

    /**
     * Coupons Page
     */
    public function coupons(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">🏷️ কুপন ও ডিসকাউন্ট ভাউচার (Discounts)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">প্রমোশনাল কোড ও ডিসকাউন্ট অফার ব্যবস্থাপনা</p>
            </div>
            <button class="btn btn-primary" style="background:#d97706;">+ নতুন কুপন যোগ করুন</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>কুপন কোড</th>
                            <th>ছাড়ের ধরণ</th>
                            <th>ছাড়ের পরিমাণ</th>
                            <th>সর্বনিম্ন ক্রয়সীমা</th>
                            <th>ব্যবহারের সংখ্যা</th>
                            <th>মেয়াদ শেষ</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>FUSION10</code></td><td>শতাংশ (%)</td><td>১০% ছাড়</td><td>৳ ১,৫০০.০০</td><td>১৪৫ বার ব্যবহৃত</td><td>২০২৬-১২-৩১</td><td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td></tr>
                        <tr><td><code>WELCOME500</code></td><td>ফিক্সড টাকা</td><td>৳ ৫০০.০০ ফ্ল্যাট</td><td>৳ ৩,০০০.০০</td><td>৮২ বার ব্যবহৃত</td><td>২০২৬-১১-৩০</td><td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td></tr>
                        <tr><td><code>FREESHIP</code></td><td>ফ্রি ডেলিভারি</td><td>১০০% শিপিং ছাড়</td><td>৳ ১,০০০.০০</td><td>২১৪ বার ব্যবহৃত</td><td>২০২৬-১০-৩১</td><td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('commerce', 'কুপন ও ছাড়', $body, '', '', '/commerce/coupons'));
    }

    /**
     * Reports Page
     */
    public function reports(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📊 কমার্স সেলস রিপোর্ট (Commerce Analytics)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">মাসিক ও ক্যাটাগরিভিত্তিক বিক্রয় পারফরম্যান্স বিশ্লেষণ</p>
            </div>
            <button class="btn btn-secondary" onclick="window.print()">🖨️ প্রিন্ট সেলস রিপোর্ট</button>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-header"><span class="card-title">📈 ক্যাটাগরি অনুসারে মোট রাজস্ব</span></div>
                <div style="padding:16px 20px;">
                    <div style="margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;"><span>অডিও ও সাউন্ড গ্যাজেট</span><strong style="color:#065f46;">৳ ৫,৪০,০০০.০০ (৪৪%)</strong></div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:44%; background:#d97706;"></div></div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;"><span>স্মার্ট পরিধানযোগ্য ডিভাইস</span><strong style="color:#065f46;">৳ ৩,৮৫,০০০.০০ (৩১%)</strong></div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:31%; background:#2563eb;"></div></div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;"><span>চার্জার ও পাওয়ার এক্সেসরিজ</span><strong style="color:#065f46;">৳ ৩,০৯,৫৬০.০০ (২৫%)</strong></div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:25%; background:#10b981;"></div></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><span class="card-title">📦 রিটার্ন ও রিফান্ড রেট মেট্রিক্স</span></div>
                <div style="padding:16px 20px;">
                    <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f1f5f9;">
                        <span>মোট সম্পন্ন ডেলিভারি</span><strong>৫৮৭টি</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f1f5f9;">
                        <span>রিটার্নকৃত আইটেম সংখ্যা</span><strong style="color:#991b1b;">৮টি (১.৩৬%)</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:8px 0;">
                        <span>কাস্টমার সন্তুষ্টি স্কোর (CSAT)</span><strong style="color:#065f46;">৪.৮৫ / ৫.০০ (৯৭%)</strong>
                    </div>
                </div>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('commerce', 'সেলস রিপোর্ট', $body, '', '', '/commerce/reports'));
    }
}
