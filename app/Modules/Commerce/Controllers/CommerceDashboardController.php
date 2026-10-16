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
| Version   : 1.3.5
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Modules\Commerce\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;

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
     * Orders Page
     */
    public function orders(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📋 অর্ডার ম্যানেজমেন্ট (All Orders)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">কাস্টমার পারচেজ অর্ডার, ফুলফিলমেন্ট ও শিপিং ট্র্যাকিং</p>
            </div>
            <button class="btn btn-primary" style="background:#d97706;" onclick="alert(\'ম্যানুয়াল অর্ডার তৈরি উইন্ডো...\')">+ নতুন ম্যানুয়াল অর্ডার</button>
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
                        <tr><td><code>ORD-904</code></td><td><strong>সাদিয়া আফরিন</strong><br><small>+880 1711-234567</small></td><td>২ আইটেম</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৫,৭০০.০০</td><td>বিকাশ অনলাইন</td><td>' . ModuleLayoutHelper::badge('ডেলিভার্ড', 'success') . '</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;" onclick="window.print()">চালান</button></td></tr>
                        <tr><td><code>ORD-903</code></td><td><strong>মেহরাব হোসেন</strong><br><small>+880 1819-876543</small></td><td>১ আইটেম</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ২,২০০.০০</td><td>ভিসা কার্ড</td><td>' . ModuleLayoutHelper::badge('শিপড', 'info') . '</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;" onclick="window.print()">চালান</button></td></tr>
                        <tr><td><code>ORD-902</code></td><td><strong>রাশেদুল করিম</strong><br><small>+880 1912-345678</small></td><td>৩ আইটেম</td><td style="text-align:right; font-weight:700; color:#b45309;">৳ ৪,৩৫০.০০</td><td>ক্যাশ অন ডেলিভারি</td><td>' . ModuleLayoutHelper::badge('প্রসেসিং', 'warning') . '</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;" onclick="window.print()">চালান</button></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('commerce', 'অর্ডার ম্যানেজমেন্ট', $body, '', '', '/commerce/orders'));
    }

    /**
     * Products Page
     */
    public function products(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📦 প্রোডাক্ট ক্যাটালগ (Product Catalog)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">ই-কমার্স পণ্যের দাম, ভ্যারিয়েন্ট ও ইনভেন্টরি স্টক নিয়ন্ত্রণ</p>
            </div>
            <button class="btn btn-primary" style="background:#d97706;">+ নতুন পণ্য যোগ করুন</button>
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
                        <tr><td><code>AUD-901</code></td><td><strong>প্রিমিয়াম ওয়্যারলেস হেডসেট</strong></td><td>অডিও এক্সেসরিজ</td><td style="text-align:right; font-weight:700;">৳ ৩,৫০০.০০</td><td style="text-align:right; font-weight:600; color:#065f46;">৬৮ পিস</td><td>' . ModuleLayoutHelper::badge('লাইভ', 'success') . '</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">এডিট</button></td></tr>
                        <tr><td><code>WTB-204</code></td><td><strong>স্মার্ট ট্র্যাকিং ফিটনেস ব্যান্ড</strong></td><td>স্মার্ট গ্যাজেট</td><td style="text-align:right; font-weight:700;">৳ ২,২০০.০০</td><td style="text-align:right; font-weight:600; color:#065f46;">৪৫ পিস</td><td>' . ModuleLayoutHelper::badge('লাইভ', 'success') . '</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">এডিট</button></td></tr>
                        <tr><td><code>PWR-65W</code></td><td><strong>টাইপ-সি আল্ট্রাফাস্ট চার্জার ৬৫W</strong></td><td>মোবাইল এক্সেসরিজ</td><td style="text-align:right; font-weight:700;">৳ ১,৪৫০.০০</td><td style="text-align:right; font-weight:600; color:#dc2626;">৮ পিস</td><td>' . ModuleLayoutHelper::badge('কম স্টক', 'warning') . '</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">এডিট</button></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('commerce', 'প্রোডাক্ট ক্যাটালগ', $body, '', '', '/commerce/products'));
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
