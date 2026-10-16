<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/Controllers/InventoryDashboardController.php
| Module    : Modules\Inventory
| Namespace : NetFusion\Modules\Inventory\Controllers
| Purpose   : Full-featured Warehouse, Stock & Inventory Control Dashboard.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Modules\Inventory\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;

class InventoryDashboardController
{
    /**
     * Inventory Overview Dashboard
     */
    public function index(Request $request): Response
    {
        $body = '
        <div style="background: linear-gradient(135deg, #0e7490 0%, #0891b2 100%); color: #fff; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(8,145,178,0.25);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: gap: 16px;">
                <div>
                    <h2 style="margin: 0 0 6px 0; font-size: 22px; font-weight: 700; color: #fff;">গুদাম ও মজুদ ব্যবস্থাপনা (Inventory & Warehouse System)</h2>
                    <p style="margin: 0; opacity: 0.9; font-size: 13.5px;">মাল্টি-ওয়্যারহাউস স্টক ট্র্যাকিং, রিঅর্ডার অটোমেশন ও ইনভেন্টরি ভ্যালুয়েশন</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="/inventory/stock-in" class="btn btn-secondary" style="background: rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.4);">+ স্টক ইন (আগমন)</a>
                    <a href="/inventory/low-stock" class="btn btn-secondary" style="background: #ffffff; color: #0e7490; font-weight: 600;">লো-স্টক সতর্কতা</a>
                </div>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card" style="border-left: 4px solid #0891b2;">
                <div class="kpi-label">মোট তালিকাভুক্ত পণ্য (Items)</div>
                <div class="kpi-value" style="color: #0e7490;">৬৪৩টি</div>
                <div class="kpi-subtext" style="color: #0891b2;">২৮টি বিভিন্ন ক্যাটাগরি ও গ্রুপে বিভক্ত</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #10b981;">
                <div class="kpi-label">মোট বর্তমান মজুদ মূল্য (Valuation)</div>
                <div class="kpi-value" style="color: #065f46;">' . ModuleLayoutHelper::currency(5783290.00) . '</div>
                <div class="kpi-subtext" style="color: #059669;">FIFO মেথডে গণনা করা মোট সম্পদ</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #2563eb;">
                <div class="kpi-label">সক্রিয় ওয়্যারহাউস (Warehouses)</div>
                <div class="kpi-value" style="color: #1e40af;">৫টি</div>
                <div class="kpi-subtext">গড় গুদাম ধারণক্ষমতা ব্যবহার: ৭১%</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #ef4444;">
                <div class="kpi-label">লো-স্টক সতর্কতা (Low Stock)</div>
                <div class="kpi-value" style="color: #991b1b;">২৩টি</div>
                <div class="kpi-subtext" style="color: #dc2626;">অবিলম্বে সরবরাহ আদেশ প্রয়োজন</div>
            </div>
        </div>

        <div class="grid-2" style="margin-bottom: 24px;">
            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="card-title">🏭 ওয়্যারহাউস ধারণক্ষমতা (Warehouse Capacity)</span>
                    <a href="/inventory/warehouses" style="font-size:12px; color:#0891b2; text-decoration:none; font-weight:600;">সব গুদাম →</a>
                </div>
                <div style="padding: 16px 20px;">
                    <div style="margin-bottom: 12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                            <span>প্রধান কেন্দ্রীয় গুদাম (তেজগাঁও, ঢাকা)</span>
                            <strong>৮৪% (৪,২০০ / ৫,০০০ প্যালেট)</strong>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 84%; background:#0891b2;"></div></div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                            <span>চট্টগ্রাম বন্দর হাব ডিপো</span>
                            <strong>৬৫% (১,৯৫০ / ৩,০০০ প্যালেট)</strong>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 65%; background:#2563eb;"></div></div>
                    </div>
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                            <span>বগুড়া আঞ্চলিক বিতরণ কেন্দ্র</span>
                            <strong>৫২% (১,০৪০ / ২,০০০ প্যালেট)</strong>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 52%; background:#10b981;"></div></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="card-title">⚠️ লো-স্টক সতর্কতা ও রিঅর্ডার লেভেল</span>
                    <a href="/inventory/low-stock" style="font-size:12px; color:#ef4444; text-decoration:none; font-weight:600;">পূর্ণ তালিকা →</a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>পণ্য নাম</th>
                                <th>বর্তমান স্টক</th>
                                <th>ন্যূনতম সীমা</th>
                                <th>অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>টাইপ-সি আল্ট্রাফাস্ট চার্জার ৬৫W</strong></td>
                                <td><strong style="color:#ef4444;">৮ পিস</strong></td>
                                <td>৫০ পিস</td>
                                <td><button class="btn btn-primary" style="padding:3px 8px; font-size:11px; background:#0891b2;">PO তৈরি</button></td>
                            </tr>
                            <tr>
                                <td><strong>এইচডিএমআই আল্ট্রা ক্যাবল ৩মি</strong></td>
                                <td><strong style="color:#ef4444;">১২ পিস</strong></td>
                                <td>১০০ পিস</td>
                                <td><button class="btn btn-primary" style="padding:3px 8px; font-size:11px; background:#0891b2;">PO তৈরি</button></td>
                            </tr>
                            <tr>
                                <td><strong>ব্লুটুথ মাউস সাইলেন্ট ক্লিক</strong></td>
                                <td><strong style="color:#ef4444;">১৫ পিস</strong></td>
                                <td>৬০ পিস</td>
                                <td><button class="btn btn-primary" style="padding:3px 8px; font-size:11px; background:#0891b2;">PO তৈরি</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <span class="card-title">📦 সাম্প্রতিক স্টক মুভমেন্ট লগ (Stock Movements)</span>
                <a href="/inventory/products" class="btn btn-primary" style="padding:6px 14px; font-size:12px; background:#0891b2;">পণ্য তালিকা দেখুন</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>তারিখ ও সময়</th>
                            <th>লেনদেন ধরন</th>
                            <th>পণ্য নাম</th>
                            <th>পরিমাণ</th>
                            <th>রেফারেন্স চালান</th>
                            <th>গুদাম</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>২০২৬-১০-১৫ ১০:১৫</td>
                            <td><span style="color:#059669; font-weight:700;">↓ ইনওয়ার্ড (IN)</span></td>
                            <td>প্রিমিয়াম ওয়্যারলেস হেডসেট</td>
                            <td><strong>+২০০ পিস</strong></td>
                            <td>GRN-2026-145</td>
                            <td>কেন্দ্রীয় গুদাম, ঢাকা</td>
                            <td>' . ModuleLayoutHelper::badge('গৃহীত (Verified)', 'success') . '</td>
                        </tr>
                        <tr>
                            <td>২০২৬-১০-১৫ ০৯:৩০</td>
                            <td><span style="color:#dc2626; font-weight:700;">↑ আউটওয়ার্ড (OUT)</span></td>
                            <td>স্মার্ট ট্র্যাকিং ফিটনেস ব্যান্ড</td>
                            <td><strong>-২০ পিস</strong></td>
                            <td>ORD-2026-904</td>
                            <td>কেন্দ্রীয় গুদাম, ঢাকা</td>
                            <td>' . ModuleLayoutHelper::badge('ডেলিভারি সম্পূর্ণ', 'info') . '</td>
                        </tr>
                        <tr>
                            <td>২০২৬-১০-১৪ ১৬:০০</td>
                            <td><span style="color:#2563eb; font-weight:700;">↔ স্থানান্তর (Transfer)</span></td>
                            <td>টাইপ-সি চার্জার ৬৫W</td>
                            <td><strong>৫০ পিস</strong></td>
                            <td>TRF-2026-088</td>
                            <td>ঢাকা → চট্টগ্রাম</td>
                            <td>' . ModuleLayoutHelper::badge('সম্পন্ন', 'success') . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('inventory', 'ড্যাশবোর্ড', $body, '', '', '/inventory'));
    }

    /**
     * Products Page
     */
    public function products(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📋 ইনভেন্টরি পণ্য তালিকা (Stock Items)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সকল মজুদের বারকোড, একক ও বর্তমান গুদাম অবস্থান</p>
            </div>
            <button class="btn btn-primary" style="background:#0891b2;" onclick="alert(\'নতুন পণ্য নিবন্ধনের উইন্ডো...\')">+ নতুন ইনভেন্টরি আইটেম</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>SKU / বারকোড</th>
                            <th>পণ্য নাম</th>
                            <th>ক্যাটাগরি</th>
                            <th>একক</th>
                            <th style="text-align:right;">ক্রয় মূল্য (৳)</th>
                            <th style="text-align:right;">বর্তমান মজুদ</th>
                            <th>অবস্থান</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>AUD-901</code></td><td><strong>প্রিমিয়াম ওয়্যারলেস হেডসেট</strong></td><td>অডিও এক্সেসরিজ</td><td>পিস (Pcs)</td><td style="text-align:right;">৳ ২,৪০০.০০</td><td style="text-align:right; font-weight:700; color:#065f46;">২৬৮ পিস</td><td>তেজগাঁও - A1</td></tr>
                        <tr><td><code>WTB-204</code></td><td><strong>স্মার্ট ট্র্যাকিং ফিটনেস ব্যান্ড</strong></td><td>স্মার্ট ডিভাইস</td><td>পিস (Pcs)</td><td style="text-align:right;">৳ ১,৫০০.০০</td><td style="text-align:right; font-weight:700; color:#065f46;">১৪৫ পিস</td><td>তেজগাঁও - B3</td></tr>
                        <tr><td><code>PWR-65W</code></td><td><strong>টাইপ-সি আল্ট্রাফাস্ট চার্জার ৬৫W</strong></td><td>মোবাইল পাওয়ার</td><td>পিস (Pcs)</td><td style="text-align:right;">৳ ৯৫০.০০</td><td style="text-align:right; font-weight:700; color:#dc2626;">৮ পিস</td><td>চট্টগ্রাম - C2</td></tr>
                        <tr><td><code>CBL-HDMI</code></td><td><strong>এইচডিএমআই আল্ট্রা ক্যাবল ৩মি</strong></td><td>ক্যাবল ও কানেক্টর</td><td>পিস (Pcs)</td><td style="text-align:right;">৳ ৪৫০.০০</td><td style="text-align:right; font-weight:700; color:#dc2626;">১২ পিস</td><td>তেজগাঁও - D1</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('inventory', 'পণ্য তালিকা', $body, '', '', '/inventory/products'));
    }

    /**
     * Warehouses Page
     */
    public function warehouses(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">🏭 ওয়্যারহাউস ও গুদাম নেটওয়ার্ক (Warehouses)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সকল গুদামঘর, ইনচার্জ ও স্টোরেজ লোকেশন</p>
            </div>
            <button class="btn btn-primary" style="background:#0891b2;">+ নতুন ওয়্যারহাউস যোগ করুন</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>গুদাম কোড</th>
                            <th>গুদামের নাম</th>
                            <th>ঠিকানা / অবস্থান</th>
                            <th>ইনচার্জ / ম্যানেজার</th>
                            <th>মোবাইল নম্বর</th>
                            <th>ধারণক্ষমতা ব্যবহার</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>WH-DHK-01</code></td><td><strong>প্রধান কেন্দ্রীয় গুদাম</strong></td><td>তেজগাঁও শিল্প এলাকা, ঢাকা</td><td>ইঞ্জি. মোশাররফ হোসেন</td><td>+880 1711-556677</td><td>' . ModuleLayoutHelper::badge('৮৪% ব্যবহৃত', 'warning') . '</td></tr>
                        <tr><td><code>WH-CTG-01</code></td><td><strong>চট্টগ্রাম বন্দর ডিপো হাব</strong></td><td>পতেঙ্গা রোড, চট্টগ্রাম</td><td>মো. আমিনুল ইসলাম</td><td>+880 1819-443322</td><td>' . ModuleLayoutHelper::badge('৬৫% ব্যবহৃত', 'info') . '</td></tr>
                        <tr><td><code>WH-BOG-01</code></td><td><strong>বগুড়া আঞ্চলিক বিতরণ কেন্দ্র</strong></td><td>বনানী মোড়, বগুড়া</td><td>মোস্তাফিজুর রহমান</td><td>+880 1912-112233</td><td>' . ModuleLayoutHelper::badge('৫২% ব্যবহৃত', 'success') . '</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('inventory', 'গুদামঘর', $body, '', '', '/inventory/warehouses'));
    }

    /**
     * Stock In Page
     */
    public function stockIn(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📥 ইনওয়ার্ড স্টক রিসিভিং (Stock In - GRN)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">ভেন্ডর ডেলিভারি ও গুদামে পণ্য প্রবেশের চালান</p>
            </div>
            <button class="btn btn-primary" style="background:#0891b2;">+ নতুন GRN তৈরি করুন</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>GRN #</th>
                            <th>তারিখ</th>
                            <th>সরবরাহকারী (Vendor)</th>
                            <th>পণ্য সংখ্যা</th>
                            <th style="text-align:right;">মোট ইনভয়েস মূল্য</th>
                            <th>গ্রহণকারী গুদাম</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>GRN-145</code></td><td>২০২৬-১০-১৫</td><td>গ্লোবাল গ্যাজেটস ইমপোর্টাস</td><td>৩টি আইটেম (৩৫০ পিস)</td><td style="text-align:right; font-weight:700;">৳ ৮,৪০,০০০.০০</td><td>প্রধান কেন্দ্রীয় গুদাম</td><td>' . ModuleLayoutHelper::badge('সম্পূর্ণ গৃহীত', 'success') . '</td></tr>
                        <tr><td><code>GRN-144</code></td><td>২০২৬-১০-১২</td><td>স্টার টেক সাপ্লাইয়ার্স</td><td>৫টি আইটেম (৫০০ পিস)</td><td style="text-align:right; font-weight:700;">৳ ৫,২০,০০০.০০</td><td>চট্টগ্রাম বন্দর ডিপো</td><td>' . ModuleLayoutHelper::badge('সম্পূর্ণ গৃহীত', 'success') . '</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('inventory', 'স্টক ইন (আগমন)', $body, '', '', '/inventory/stock-in'));
    }

    /**
     * Stock Out Page
     */
    public function stockOut(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📤 আউটওয়ার্ড স্টক ডেলিভারি (Stock Out - GIN)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">বিক্রয় বা ট্রান্সফার বাবদ গুদাম থেকে পণ্য খালাসের রেকর্ড</p>
            </div>
            <button class="btn btn-primary" style="background:#0891b2;">+ নতুন ডিসপ্যাচ চালান</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>GIN #</th>
                            <th>তারিখ</th>
                            <th>উদ্দেশ্য</th>
                            <th>অর্ডার / চালান রেফারেন্স</th>
                            <th>আইটেম সংখ্যা</th>
                            <th>প্রেরক গুদাম</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>GIN-204</code></td><td>২০২৬-১০-১৫</td><td>গ্রাহক অর্ডার ডেলিভারি</td><td>ORD-904</td><td>২ পিস</td><td>প্রধান কেন্দ্রীয় গুদাম</td><td>' . ModuleLayoutHelper::badge('খালাস সম্পন্ন', 'success') . '</td></tr>
                        <tr><td><code>GIN-203</code></td><td>২০২৬-১০-১৪</td><td>শাখা স্থানান্তর (Transfer)</td><td>TRF-088</td><td>৫০ পিস</td><td>প্রধান কেন্দ্রীয় গুদাম</td><td>' . ModuleLayoutHelper::badge('খালাস সম্পন্ন', 'success') . '</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('inventory', 'স্টক আউট (নির্গমন)', $body, '', '', '/inventory/stock-out'));
    }

    /**
     * Low Stock Alerts Page
     */
    public function lowStock(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700; color:#dc2626;">⚠️ লো-স্টক সতর্কতা ও রিঅর্ডার ডেস্ক (Low Stock Management)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">যেসব পণ্যের পরিমাণ সেফটি স্টক লেভেলের নিচে নেমে গেছে</p>
            </div>
            <button class="btn btn-primary" style="background:#dc2626;" onclick="alert(\'সাপ্লায়ারদের কাছে বাল্ক পার্সেস রিকুইজিশন তৈরি হচ্ছে...\')">📢 সাপ্লায়ার রিঅর্ডার তৈরি করুন</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>পণ্য নাম</th>
                            <th>বর্তমান স্টক</th>
                            <th>সর্বনিম্ন সীমা</th>
                            <th>ঘাটতি পরিমাণ</th>
                            <th>সাপ্লায়ার ভেন্ডর</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>PWR-65W</code></td><td><strong>টাইপ-সি আল্ট্রাফাস্ট চার্জার ৬৫W</strong></td><td><strong style="color:#dc2626;">৮ পিস</strong></td><td>৫০ পিস</td><td style="color:#dc2626; font-weight:700;">-৪২ পিস</td><td>গ্লোবাল গ্যাজেটস</td><td><button class="btn btn-primary" style="padding:4px 8px; font-size:11px; background:#0891b2;">অর্ডার পাঠান</button></td></tr>
                        <tr><td><code>CBL-HDMI</code></td><td><strong>এইচডিএমআই আল্ট্রা ক্যাবল ৩মি</strong></td><td><strong style="color:#dc2626;">১২ পিস</strong></td><td>১০০ পিস</td><td style="color:#dc2626; font-weight:700;">-৮৮ পিস</td><td>স্টার টেক সাপ্লাইয়ার্স</td><td><button class="btn btn-primary" style="padding:4px 8px; font-size:11px; background:#0891b2;">অর্ডার পাঠান</button></td></tr>
                        <tr><td><code>MOU-BT</code></td><td><strong>ব্লুটুথ মাউস সাইলেন্ট ক্লিক</strong></td><td><strong style="color:#dc2626;">১৫ পিস</strong></td><td>৬০ পিস</td><td style="color:#dc2626; font-weight:700;">-৪৫ পিস</td><td>গ্লোবাল গ্যাজেটস</td><td><button class="btn btn-primary" style="padding:4px 8px; font-size:11px; background:#0891b2;">অর্ডার পাঠান</button></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('inventory', 'লো-স্টক অ্যালার্ট', $body, '', '', '/inventory/low-stock'));
    }

    /**
     * Valuation Page
     */
    public function valuation(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📊 মজুদ পণ্যের আর্থিক মূল্যায়ন (Inventory Valuation)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">FIFO ও গড় ক্রয়মূল্য ভিত্তিতে বর্তমান স্টক সম্পদ</p>
            </div>
            <button class="btn btn-secondary" onclick="window.print()">🖨️ প্রিন্ট ভ্যালুয়েশন শিট</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ক্যাটাগরি</th>
                            <th>পণ্য সংখ্যা</th>
                            <th>মোট ইউনিট</th>
                            <th style="text-align:right;">গড় ক্রয়মূল্য</th>
                            <th style="text-align:right;">মোট স্টক মূল্য (৳)</th>
                            <th>অংশীদারিত্ব (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>অডিও ও মিডিয়া ডিভাইস</strong></td><td>১৪টি SKU</td><td>১,২৪০ পিস</td><td style="text-align:right;">৳ ২,১৫০.০০</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ২৬,৬৬,০০০.০০</td><td>৪৬.১%</td></tr>
                        <tr><td><strong>স্মার্ট পরিধানযোগ্য ডিভাইস</strong></td><td>১৮টি SKU</td><td>৮২০ পিস</td><td style="text-align:right;">৳ ২,২০০.০০</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ১৮,০৪,০০০.০০</td><td>৩১.২%</td></tr>
                        <tr><td><strong>চার্জার ও এক্সেসরিজ</strong></td><td>৩২টি SKU</td><td>১,৫৮০ পিস</td><td style="text-align:right;">৳ ৮৩০.০০</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ১৩,১৩,২৯০.০০</td><td>২২.৭%</td></tr>
                        <tr style="background:#f1f5f9; font-weight:700; font-size:15px;">
                            <td colspan="4">সর্বমোট ইনভেন্টরি ভ্যালুয়েশন</td>
                            <td style="text-align:right; color:#065f46;">৳ ৫৭,৮৩,২৯০.০০</td>
                            <td>১০০%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('inventory', 'স্টক ভ্যালুয়েশন', $body, '', '', '/inventory/valuation'));
    }
}
