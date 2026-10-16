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
| Version   : 1.3.1
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Modules\Billing\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;

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
     * Invoices List Page
     */
    public function invoices(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📄 ইনভয়েস তালিকা (All Invoices)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সকল সফল ও পেন্ডিং বিলিং চালান এবং রশিদ</p>
            </div>
            <button class="btn btn-primary" style="background:#7c3aed;" onclick="alert(\'নতুন ইনভয়েস জেনারেট হচ্ছে...\')">+ নতুন ইনভয়েস তৈরি</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ইনভয়েস #</th>
                            <th>গ্রাহক</th>
                            <th>প্ল্যান</th>
                            <th>বিলিং সাইকেল</th>
                            <th style="text-align:right;">টাকা</th>
                            <th>পদ্ধতি</th>
                            <th>স্ট্যাটাস</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>INV-781</strong></td>
                            <td>ঢাকা টেক সল্যুশনস</td>
                            <td>Enterprise</td>
                            <td>বার্ষিক</td>
                            <td style="text-align:right; font-weight:700;">৳ ১,১৯,৯৮৮.০০</td>
                            <td>সিটি ব্যাংক কার্ড</td>
                            <td>' . ModuleLayoutHelper::badge('পেইড', 'success') . '</td>
                            <td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;" onclick="window.print()">রশিদ</button></td>
                        </tr>
                        <tr>
                            <td><strong>INV-780</strong></td>
                            <td>ব্লু-স্কাই সফটওয়্যার</td>
                            <td>Professional</td>
                            <td>মাসিক</td>
                            <td style="text-align:right; font-weight:700;">৳ ৪,৯৯৯.০০</td>
                            <td>বিকাশ গেটওয়ে</td>
                            <td>' . ModuleLayoutHelper::badge('পেইড', 'success') . '</td>
                            <td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;" onclick="window.print()">রশিদ</button></td>
                        </tr>
                        <tr>
                            <td><strong>INV-779</strong></td>
                            <td>প্রাইম লজিস্টিকস</td>
                            <td>Enterprise</td>
                            <td>মাসিক</td>
                            <td style="text-align:right; font-weight:700;">৳ ৯,৯৯৯.০০</td>
                            <td>নগদ পেমেন্ট</td>
                            <td>' . ModuleLayoutHelper::badge('পেন্ডিং', 'warning') . '</td>
                            <td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;" onclick="alert(\'রিমাইন্ডার পাঠানো হয়েছে\')">রিমাইন্ডার</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('billing', 'ইনভয়েস তালিকা', $body, '', '', '/billing/invoices'));
    }

    /**
     * Subscriptions Page
     */
    public function subscriptions(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">🔁 সাবস্ক্রিপশন তালিকা (Active Subscriptions)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">গ্রাহকভিত্তিক স্বয়ংক্রিয় রিনিউয়াল ও প্যাকেজ স্ট্যাটাস</p>
            </div>
            <button class="btn btn-primary" style="background:#7c3aed;">+ নতুন সাবস্ক্রিপশন বরাদ্দ</button>
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
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>SUB-0101</code></td><td><strong>ঢাকা টেক সল্যুশনস লিঃ</strong></td><td>Enterprise</td><td>২০২৬-০১-০১</td><td>২০২৭-০১-০১</td><td>চালু (Yes)</td><td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td></tr>
                        <tr><td><code>SUB-0102</code></td><td><strong>ব্লু-স্কাই সফটওয়্যার লিমিটেড</strong></td><td>Professional</td><td>২০২৬-১০-১৪</td><td>২০২৬-১১-১৪</td><td>চালু (Yes)</td><td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td></tr>
                        <tr><td><code>SUB-0103</code></td><td><strong>প্রাইম লজিস্টিকস কর্পোরেশন</strong></td><td>Enterprise</td><td>২০২৬-১০-১৩</td><td>২০২৬-১১-১৩</td><td>চালু (Yes)</td><td>' . ModuleLayoutHelper::badge('গ্রেস পিরিয়ড', 'warning') . '</td></tr>
                        <tr><td><code>SUB-0104</code></td><td><strong>গ্রীন ডেল্টা করপোরেট</strong></td><td>Starter</td><td>২০২৬-০৮-০১</td><td>২০২৬-১১-০১</td><td>বন্ধ (No)</td><td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('billing', 'সাবস্ক্রিপশন', $body, '', '', '/billing/subscriptions'));
    }

    /**
     * Plans Page
     */
    public function plans(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📦 সাবস্ক্রিপশন প্ল্যান সমূহ (Billing Plans)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সিস্টেমের মূল্য প্যাকেজ ও ফিচার নির্ধারণ</p>
            </div>
            <button class="btn btn-primary" style="background:#7c3aed;">+ নতুন প্যাকেজ তৈরি</button>
        </div>

        <div class="grid-2">
            <div class="card" style="border-top:4px solid #10b981;">
                <div class="card-header"><span class="card-title">Starter Tier</span><span style="font-weight:700; color:#10b981;">৳ ১,৯৯৯ / মাস</span></div>
                <div style="padding:16px 20px;">
                    <ul style="margin:0 0 16px 0; padding-left:20px; line-height:1.8; color:#475569; font-size:13.5px;">
                        <li>৫ জন ইউজার অ্যাক্সেস</li>
                        <li>১টি টেন্যান্ট ডেটাবেস</li>
                        <li>বেসিক অ্যাকাউন্টিং ও ইনভয়েস</li>
                        <li>ইমেইল সাপোর্ট</li>
                    </ul>
                    <button class="btn btn-secondary" style="width:100%;">এডিট প্ল্যান</button>
                </div>
            </div>

            <div class="card" style="border-top:4px solid #2563eb;">
                <div class="card-header"><span class="card-title">Professional Pro</span><span style="font-weight:700; color:#2563eb;">৳ ৪,৯৯৯ / মাস</span></div>
                <div style="padding:16px 20px;">
                    <ul style="margin:0 0 16px 0; padding-left:20px; line-height:1.8; color:#475569; font-size:13.5px;">
                        <li>২৫ জন ইউজার অ্যাক্সেস</li>
                        <li>৫টি ব্রাঞ্চ / টেন্যান্ট সাপোর্ট</li>
                        <li>অ্যাকাউন্টিং, বিলিং, সিআরএম মডিউল</li>
                        <li>প্রায়োরিটি সাপোর্ট ও এসএমএস নোটিফিকেশন</li>
                    </ul>
                    <button class="btn btn-secondary" style="width:100%;">এডিট প্ল্যান</button>
                </div>
            </div>

            <div class="card" style="border-top:4px solid #7c3aed;">
                <div class="card-header"><span class="card-title">Enterprise Suite</span><span style="font-weight:700; color:#7c3aed;">৳ ৯,৯৯৯ / মাস</span></div>
                <div style="padding:16px 20px;">
                    <ul style="margin:0 0 16px 0; padding-left:20px; line-height:1.8; color:#475569; font-size:13.5px;">
                        <li>আনলিমিটেড ইউজার ও রোলস</li>
                        <li>মাল্টি-টেন্যান্ট আইসোলেশন ইঞ্জিন</li>
                        <li>সকল HMVC মডিউল সক্রিয়</li>
                        <li>২৪/৭ ডেডিকেটেড সাপোর্ট ও এসএলএ</li>
                    </ul>
                    <button class="btn btn-secondary" style="width:100%;">এডিট প্ল্যান</button>
                </div>
            </div>

            <div class="card" style="border-top:4px solid #64748b;">
                <div class="card-header"><span class="card-title">Custom Enterprise</span><span style="font-weight:700; color:#64748b;">কাস্টম কোটেশন</span></div>
                <div style="padding:16px 20px;">
                    <ul style="margin:0 0 16px 0; padding-left:20px; line-height:1.8; color:#475569; font-size:13.5px;">
                        <li>অন-প্রিমিসেস ডেডিকেটেড ক্লাস্টার</li>
                        <li>কাস্টম মডিউল ডেভেলপমেন্ট</li>
                        <li>হোয়াইট-লেবেল ব্রান্ডিং অপশন</li>
                    </ul>
                    <button class="btn btn-secondary" style="width:100%;">এডিট প্ল্যান</button>
                </div>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('billing', 'প্ল্যান সমূহ', $body, '', '', '/billing/plans'));
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