<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Accounting/Controllers/AccountingDashboardController.php
| Module    : Modules\Accounting
| Namespace : NetFusion\Modules\Accounting\Controllers
| Purpose   : Full-featured Enterprise Accounting & Double-Entry Ledger Dashboard.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Modules\Accounting\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;

class AccountingDashboardController
{
    /**
     * Accounting Overview Dashboard
     */
    public function index(Request $request): Response
    {
        $body = '
        <div style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); color: #fff; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(37,99,235,0.25);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h2 style="margin: 0 0 6px 0; font-size: 22px; font-weight: 700; color: #fff;">এন্টারপ্রাইজ ডাবল-এন্ট্রি অ্যাকাউন্টিং ও ফিন্যান্সিয়াল সিস্টেম</h2>
                    <p style="margin: 0; opacity: 0.9; font-size: 13.5px;">লেজার, চার্ট অব অ্যাকাউন্টস, জার্নাল এন্ট্রি এবং ফিন্যান্সিয়াল রিপোর্টিং ইঞ্জিন</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="/accounting/journal" class="btn btn-secondary" style="background: rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.4);">+ নতুন জার্নাল এন্ট্রি</a>
                    <a href="/accounting/trial-balance" class="btn btn-secondary" style="background: #ffffff; color: #1e3a8a; font-weight: 600;">ট্রায়াল ব্যালেন্স</a>
                </div>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card" style="border-left: 4px solid #10b981;">
                <div class="kpi-label">মোট রাজস্ব / আয় (FY 2026)</div>
                <div class="kpi-value" style="color: #065f46;">' . ModuleLayoutHelper::currency(487350.00) . '</div>
                <div class="kpi-subtext" style="color: #059669;">↑ ১২.৪% গত মাসের তুলনায় বৃদ্ধি</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #ef4444;">
                <div class="kpi-label">মোট পরিচালন ব্যয় (Expenses)</div>
                <div class="kpi-value" style="color: #991b1b;">' . ModuleLayoutHelper::currency(213840.00) . '</div>
                <div class="kpi-subtext" style="color: #dc2626;">↓ ৪.১% বাজেট খরচের নিচে</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #2563eb;">
                <div class="kpi-label">নিট মুনাফা (Net Profit)</div>
                <div class="kpi-value" style="color: #1e40af;">' . ModuleLayoutHelper::currency(273510.00) . '</div>
                <div class="kpi-subtext" style="color: #2563eb;">মুনাফার মার্জিন ৫৬.১২%</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #8b5cf6;">
                <div class="kpi-label">সক্রিয় অ্যাকাউন্টস (COA)</div>
                <div class="kpi-value">১৪৭টি</div>
                <div class="kpi-subtext">৫টি প্রধান হেড ও ৩২টি সাব-লেজার</div>
            </div>
        </div>

        <div class="grid-2" style="margin-bottom: 24px;">
            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="card-title">📈 আয় বিবরণী সারসংক্ষেপ (P&L Summary)</span>
                    <a href="/accounting/income-statement" style="font-size:12px; color:#2563eb; text-decoration:none; font-weight:600;">পূর্ণ বিবরণী →</a>
                </div>
                <div style="padding: 16px 20px;">
                    <div style="display:flex; justify-content:space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9;">
                        <span style="color:#475569;">গ্রস বিক্রয় রাজস্ব</span>
                        <strong style="color:#0f172a;">৳ ৫,২০,০০০.০০</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9;">
                        <span style="color:#475569;">পণ্য বিক্রয়ের খরচ (COGS)</span>
                        <strong style="color:#ef4444;">- ৳ ৩২,৬৫০.০০</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9;">
                        <span style="color:#475569;">মোট মুনাফা (Gross Profit)</span>
                        <strong style="color:#059669;">৳ ৪,৮৭,৩৫০.০০</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9;">
                        <span style="color:#475569;">অফিস ও পরিচালন ব্যয়</span>
                        <strong style="color:#ef4444;">- ৳ ২,১৩,৮৪০.০০</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding: 12px 0 4px 0; font-size:15px;">
                        <span style="font-weight:700; color:#0f172a;">নিট অপারেটিং ইনকাম</span>
                        <strong style="color:#2563eb; font-size:16px;">৳ ২,৭৩,৫১০.০০</strong>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="card-title">🏦 ব্যালেন্স শিট রেশিও ও সম্পদ</span>
                    <a href="/accounting/balance-sheet" style="font-size:12px; color:#2563eb; text-decoration:none; font-weight:600;">ব্যালেন্স শিট →</a>
                </div>
                <div style="padding: 16px 20px;">
                    <div style="margin-bottom: 14px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:5px;">
                            <span style="color:#475569;">মোট সম্পদ (Current + Fixed Assets)</span>
                            <strong>৳ ১২,৪৫,০০০.০০</strong>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 78%; background:#2563eb;"></div></div>
                    </div>
                    <div style="margin-bottom: 14px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:5px;">
                            <span style="color:#475569;">দায়বদ্ধতা (Liabilities)</span>
                            <strong>৳ ৩,২০,০০০.০০</strong>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 25%; background:#ef4444;"></div></div>
                    </div>
                    <div style="margin-bottom: 14px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:5px;">
                            <span style="color:#475569;">শেয়ারহোল্ডার ইকুইটি (Equity)</span>
                            <strong>৳ ৯,২৫,০০০.০০</strong>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 75%; background:#10b981;"></div></div>
                    </div>
                    <div style="background:#f8fafc; border-radius:8px; padding:10px 14px; font-size:12.5px; color:#64748b; margin-top:8px;">
                        ⚖️ লেজার সমীকরণ: সম্পদ (১২,৪৫,০০০) = দায় (৩,২০,০০০) + ইকুইটি (৯,২৫,০০০) — ব্যালেন্সড।
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <span class="card-title">📝 সাম্প্রতিক জার্নাল এন্ট্রি (Double-Entry Ledger)</span>
                <a href="/accounting/journal" class="btn btn-primary" style="padding:6px 14px; font-size:12px;">সব জার্নাল দেখুন</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ভাউচার #</th>
                            <th>তারিখ</th>
                            <th>বিবরণ ও লেজার হেড</th>
                            <th>রেফারেন্স</th>
                            <th style="text-align:right;">ডেবিট (৳)</th>
                            <th style="text-align:right;">ক্রেডিট (৳)</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong style="color:#2563eb;">JV-2026-089</strong></td>
                            <td>২০২৬-১০-১৫</td>
                            <td>ক্লায়েন্ট ইনভয়েস পেমেন্ট গ্রহণ - সিটি ব্যাংক এ/সি</td>
                            <td>INV-2026-104</td>
                            <td style="text-align:right; font-weight:600; color:#065f46;">৳ ৪৫,০০০.০০</td>
                            <td style="text-align:right; color:#94a3b8;">-</td>
                            <td>' . ModuleLayoutHelper::badge('পোস্টেড', 'success') . '</td>
                        </tr>
                        <tr>
                            <td><strong style="color:#2563eb;">JV-2026-088</strong></td>
                            <td>২০২৬-১০-১৫</td>
                            <td>অফিস স্পেস ভাড়া পরিশোধ (অক্টোবর ২০২৬)</td>
                            <td>RENT-OCT-26</td>
                            <td style="text-align:right; color:#94a3b8;">-</td>
                            <td style="text-align:right; font-weight:600; color:#991b1b;">৳ ৩৫,০০০.০০</td>
                            <td>' . ModuleLayoutHelper::badge('পোস্টেড', 'success') . '</td>
                        </tr>
                        <tr>
                            <td><strong style="color:#2563eb;">JV-2026-087</strong></td>
                            <td>২০২৬-১০-১৪</td>
                            <td>ক্লাউড সার্ভার এডব্লিউএস হোস্ট ফি ডেবিট</td>
                            <td>AWS-INV-773</td>
                            <td style="text-align:right; font-weight:600; color:#991b1b;">৳ ১২,৪০০.০০</td>
                            <td style="text-align:right; color:#94a3b8;">-</td>
                            <td>' . ModuleLayoutHelper::badge('পোস্টেড', 'success') . '</td>
                        </tr>
                        <tr>
                            <td><strong style="color:#2563eb;">JV-2026-086</strong></td>
                            <td>২০২৬-১০-১৪</td>
                            <td>আইটি সরঞ্জাম অবচয় চার্জ (Depreciation Expense)</td>
                            <td>DEP-2026-Q3</td>
                            <td style="text-align:right; font-weight:600; color:#475569;">৳ ৮,৭৫০.০০</td>
                            <td style="text-align:right; font-weight:600; color:#475569;">৳ ৮,৭৫০.০০</td>
                            <td>' . ModuleLayoutHelper::badge('অডিটেড', 'info') . '</td>
                        </tr>
                        <tr>
                            <td><strong style="color:#2563eb;">JV-2026-085</strong></td>
                            <td>২০২৬-১০-১৩</td>
                            <td>সফটওয়্যার লাইসেন্স সেলস রেভিনিউ ক্রেডিট</td>
                            <td>LIC-ENTERPRISE-01</td>
                            <td style="text-align:right; color:#94a3b8;">-</td>
                            <td style="text-align:right; font-weight:600; color:#065f46;">৳ ১,২০,০০০.০০</td>
                            <td>' . ModuleLayoutHelper::badge('পোস্টেড', 'success') . '</td>
                        </tr>
                        <tr>
                            <td><strong style="color:#2563eb;">JV-2026-084</strong></td>
                            <td>২০২৬-১০-১২</td>
                            <td>স্টাফ চা-নাস্তা ও ক্যাজুয়াল খরচ (Petty Cash)</td>
                            <td>PETTY-1012</td>
                            <td style="text-align:right; font-weight:600; color:#991b1b;">৳ ৩,২০০.০০</td>
                            <td style="text-align:right; color:#94a3b8;">-</td>
                            <td>' . ModuleLayoutHelper::badge('ড্রাফট', 'warning') . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <span class="card-title">📋 চার্ট অব অ্যাকাউন্টস (Top Active Accounts)</span>
                <a href="/accounting/chart-of-accounts" style="font-size:12px; color:#2563eb; text-decoration:none; font-weight:600;">সকল অ্যাকাউন্টস →</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>অ্যাকাউন্ট কোড</th>
                            <th>অ্যাকাউন্ট নাম</th>
                            <th>শ্রেণী / টাইপ</th>
                            <th>মুদ্রা</th>
                            <th style="text-align:right;">চলতি ব্যালেন্স</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>1010-01</code></td>
                            <td><strong>সিটি ব্যাংক পিএলসি - প্রধান চলতি হিসাব</strong></td>
                            <td><span style="color:#2563eb; font-weight:500;">Asset (Current)</span></td>
                            <td>BDT</td>
                            <td style="text-align:right; font-weight:700; color:#065f46;">৳ ৭,৮৪,৫০০.০০</td>
                            <td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td>
                        </tr>
                        <tr>
                            <td><code>1020-01</code></td>
                            <td><strong>ব্র্যাক ব্যাংক - অপারেশনাল ডিপোজিট</strong></td>
                            <td><span style="color:#2563eb; font-weight:500;">Asset (Bank)</span></td>
                            <td>BDT</td>
                            <td style="text-align:right; font-weight:700; color:#065f46;">৳ ৩,৪৫,০০০.০০</td>
                            <td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td>
                        </tr>
                        <tr>
                            <td><code>2010-01</code></td>
                            <td><strong>অ্যাকাউন্টস পেয়েবল (সরবরাহকারী দেনা)</strong></td>
                            <td><span style="color:#ef4444; font-weight:500;">Liability</span></td>
                            <td>BDT</td>
                            <td style="text-align:right; font-weight:700; color:#991b1b;">৳ ১,৮৫,২০০.০০</td>
                            <td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td>
                        </tr>
                        <tr>
                            <td><code>4010-01</code></td>
                            <td><strong>সফটওয়্যার সাবস্ক্রিপশন ও লাইসেন্স সেলস</strong></td>
                            <td><span style="color:#10b981; font-weight:500;">Revenue</span></td>
                            <td>BDT</td>
                            <td style="text-align:right; font-weight:700; color:#065f46;">৳ ৪,৮৭,৩৫০.০০</td>
                            <td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td>
                        </tr>
                        <tr>
                            <td><code>5010-01</code></td>
                            <td><strong>ক্লাউড ইনফ্রাস্ট্রাকচার ও সার্ভার খরচ</strong></td>
                            <td><span style="color:#f59e0b; font-weight:500;">Expense</span></td>
                            <td>BDT</td>
                            <td style="text-align:right; font-weight:700; color:#475569;">৳ ৫৮,৪০০.০০</td>
                            <td>' . ModuleLayoutHelper::badge('সক্রিয়', 'success') . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('accounting', 'ড্যাশবোর্ড', $body, '', '', '/accounting'));
    }

    /**
     * Chart of Accounts Page
     */
    public function chartOfAccounts(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📋 চার্ট অব অ্যাকাউন্টস (Chart of Accounts)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">কোম্পানির সকল আর্থিক খাতের হায়ারারকিক্যাল লেজার তালিকা</p>
            </div>
            <button class="btn btn-primary" onclick="alert(\'নতুন লেজার হেড তৈরি ফর্ম সক্রিয় হচ্ছে...\')">+ নতুন অ্যাকাউন্ট হেড</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>কোড</th>
                            <th>অ্যাকাউন্ট নাম</th>
                            <th>ধরণ (Category)</th>
                            <th>প্যারেন্ট হেড</th>
                            <th>নোট / বিবরণ</th>
                            <th style="text-align:right;">ব্যালেন্স (৳)</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>1000</code></td><td><strong>সম্পদ (Assets)</strong></td><td>Asset</td><td>-</td><td>সকল চলতি ও স্থায়ী সম্পদ</td><td style="text-align:right; font-weight:700;">৳ ১২,৪৫,০০০.০০</td><td>-</td></tr>
                        <tr><td><code>1010</code></td><td>&nbsp; └ চলতি সম্পদ (Current Assets)</td><td>Asset</td><td>1000</td><td>ক্যাশ ও ব্যাংক ব্যালেন্স</td><td style="text-align:right; font-weight:600;">৳ ১১,২৯,৫০০.০০</td><td><a href="/accounting/ledger" style="color:#2563eb; text-decoration:none;">লেজার</a></td></tr>
                        <tr><td><code>1010-01</code></td><td>&nbsp; &nbsp; └ সিটি ব্যাংক পিএলসি</td><td>Asset (Bank)</td><td>1010</td><td>কর্পোরেট একাউন্ট</td><td style="text-align:right; font-weight:600; color:#065f46;">৳ ৭,৮৪,৫০০.০০</td><td><a href="/accounting/ledger" style="color:#2563eb; text-decoration:none;">লেজার</a></td></tr>
                        <tr><td><code>1010-02</code></td><td>&nbsp; &nbsp; └ ব্র্যাক ব্যাংক পিএলসি</td><td>Asset (Bank)</td><td>1010</td><td>অপারেশনাল একাউন্ট</td><td style="text-align:right; font-weight:600; color:#065f46;">৳ ৩,৪৫,০০০.০০</td><td><a href="/accounting/ledger" style="color:#2563eb; text-decoration:none;">লেজার</a></td></tr>
                        <tr><td><code>2000</code></td><td><strong>দায় সমূহ (Liabilities)</strong></td><td>Liability</td><td>-</td><td>স্বল্প ও দীর্ঘমেয়াদী দেনা</td><td style="text-align:right; font-weight:700;">৳ ৩,২০,০০০.০০</td><td>-</td></tr>
                        <tr><td><code>2010</code></td><td>&nbsp; └ চলতি দেনা (Accounts Payable)</td><td>Liability</td><td>2000</td><td>ভেন্ডর ও সরবরাহকারী পাওনা</td><td style="text-align:right; font-weight:600; color:#991b1b;">৳ ১,৮৫,২০০.০০</td><td><a href="/accounting/ledger" style="color:#2563eb; text-decoration:none;">লেজার</a></td></tr>
                        <tr><td><code>3000</code></td><td><strong>ইকুইটি (Equity)</strong></td><td>Equity</td><td>-</td><td>শেয়ার মূলধন ও রিটেইনড আর্নিংস</td><td style="text-align:right; font-weight:700;">৳ ৯,২৫,০০০.০০</td><td>-</td></tr>
                        <tr><td><code>4000</code></td><td><strong>রাজস্ব (Revenue)</strong></td><td>Revenue</td><td>-</td><td>পণ্য ও সেবা বিক্রয় থেকে আয়</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৪,৮৭,৩৫০.০০</td><td><a href="/accounting/income-statement" style="color:#2563eb; text-decoration:none;">রিপোর্ট</a></td></tr>
                        <tr><td><code>5000</code></td><td><strong>ব্যয় সমূহ (Expenses)</strong></td><td>Expense</td><td>-</td><td>সকল অপারেশনাল খরচ</td><td style="text-align:right; font-weight:700; color:#991b1b;">৳ ২,১৩,৮৪০.০০</td><td><a href="/accounting/ledger" style="color:#2563eb; text-decoration:none;">লেজার</a></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('accounting', 'চার্ট অব অ্যাকাউন্টস', $body, '', '', '/accounting/chart-of-accounts'));
    }

    /**
     * Journal Entries Page
     */
    public function journal(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📝 জার্নাল ভাউচার এন্ট্রি (General Journal)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সকল ডাবল-এন্ট্রি লেনদেনের সময়ানুক্রমিক রেকর্ড</p>
            </div>
            <button class="btn btn-primary" onclick="alert(\'নতুন ভাউচার ক্রিয়েট ডায়ালগ...\')">+ নতুন জার্নাল ভাউচার</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ভাউচার #</th>
                            <th>তারিখ</th>
                            <th>হিসাব খাত (Account Head)</th>
                            <th>বিবরণ ও বিবরণী</th>
                            <th style="text-align:right;">ডেবিট (৳)</th>
                            <th style="text-align:right;">ক্রেডিট (৳)</th>
                            <th>পোস্টিং</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="background:#fafafa;">
                            <td><strong>JV-2026-092</strong></td>
                            <td>২০২৬-১০-১৫</td>
                            <td>1010-01 সিটি ব্যাংক<br><span style="color:#64748b;">4010-01 সফটওয়্যার সেলস</span></td>
                            <td>ইনভয়েস #104 এর বিপরীতে সফটওয়্যার ডেলিভারি বাবদ পূর্ণ অর্থ প্রাপ্তি।</td>
                            <td style="text-align:right; font-weight:600; color:#065f46;">৳ ৫০,০০০.০০<br>-</td>
                            <td style="text-align:right; font-weight:600; color:#065f46;">-<br>৳ ৫০,০০০.০০</td>
                            <td>' . ModuleLayoutHelper::badge('পোস্টেড', 'success') . '</td>
                        </tr>
                        <tr>
                            <td><strong>JV-2026-091</strong></td>
                            <td>২০২৬-১০-১৪</td>
                            <td>5010-01 সার্ভার খরচ<br><span style="color:#64748b;">1010-01 সিটি ব্যাংক</span></td>
                            <td>AWS ক্লাউড ডেডিকেটেড সার্ভার মাসিক ইনফ্রাস্ট্রাকচার চার্জ পরিশোধ।</td>
                            <td style="text-align:right; font-weight:600; color:#991b1b;">৳ ১২,৪০০.০০<br>-</td>
                            <td style="text-align:right; font-weight:600; color:#991b1b;">-<br>৳ ১২,৪০০.০০</td>
                            <td>' . ModuleLayoutHelper::badge('পোস্টেড', 'success') . '</td>
                        </tr>
                        <tr style="background:#fafafa;">
                            <td><strong>JV-2026-090</strong></td>
                            <td>২০২৬-১০-১৩</td>
                            <td>5020-01 অফিস স্টেশনারি<br><span style="color:#64748b;">1010-03 পেটি ক্যাশ</span></td>
                            <td>প্রিন্টার টোনার ও অফিস পেপার ক্রয় বাবদ ক্যাশ ভাউচার অনুমোদন।</td>
                            <td style="text-align:right; font-weight:600; color:#991b1b;">৳ ৪,২০০.০০<br>-</td>
                            <td style="text-align:right; font-weight:600; color:#991b1b;">-<br>৳ ৪,২০০.০০</td>
                            <td>' . ModuleLayoutHelper::badge('পোস্টেড', 'success') . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('accounting', 'জার্নাল এন্ট্রি', $body, '', '', '/accounting/journal'));
    }

    /**
     * Ledger Book Page
     */
    public function ledger(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📈 খতিয়ান বহি (General Ledger)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">অ্যাকাউন্টভিত্তিক বিস্তারিত লেনদেন ও চলমান জের (Running Balance)</p>
            </div>
            <select style="padding:7px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:13px;">
                <option>1010-01 সিটি ব্যাংক পিএলসি</option>
                <option>1010-02 ব্র্যাক ব্যাংক পিএলসি</option>
                <option>4010-01 সফটওয়্যার সেলস</option>
            </select>
        </div>

        <div class="card">
            <div class="card-header" style="background:#f8fafc;">
                <strong>অ্যাকাউন্ট: 1010-01 সিটি ব্যাংক পিএলসি (চলতি হিসাব) | প্রারম্ভিক ব্যালেন্স: ৳ ৬,৫১,৯০০.০০</strong>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>তারিখ</th>
                            <th>ভাউচার #</th>
                            <th>বিবরণ</th>
                            <th style="text-align:right;">ডেবিট (৳)</th>
                            <th style="text-align:right;">ক্রেডিট (৳)</th>
                            <th style="text-align:right;">চলমান জের (৳)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>২০২৬-১০-০১</td><td>OP-001</td><td>প্রারম্ভিক জের (Opening Balance)</td><td style="text-align:right;">-</td><td style="text-align:right;">-</td><td style="text-align:right; font-weight:700;">৳ ৬,৫১,৯০০.০০</td></tr>
                        <tr><td>২০২৬-১০-০৫</td><td>JV-081</td><td>ক্লায়েন্ট পেমেন্ট গ্রহণ</td><td style="text-align:right; color:#065f46;">৳ ৭৫,০০০.০০</td><td style="text-align:right;">-</td><td style="text-align:right; font-weight:700;">৳ ৭,২৬,৯০০.০০</td></tr>
                        <tr><td>২০২৬-১০-০৯</td><td>JV-083</td><td>অফিস ইউটিলিটি বিল পরিশোধ</td><td style="text-align:right;">-</td><td style="text-align:right; color:#991b1b;">৳ ১৪,৮০০.০০</td><td style="text-align:right; font-weight:700;">৳ ৭,১২,১০০.০০</td></tr>
                        <tr><td>২০২৬-১০-১৫</td><td>JV-089</td><td>সফটওয়্যার লাইসেন্স ফি গ্রহণ</td><td style="text-align:right; color:#065f46;">৳ ৭২,৪০০.০০</td><td style="text-align:right;">-</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৭,৮৪,৫০০.০০</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('accounting', 'লেজার বুক', $body, '', '', '/accounting/ledger'));
    }

    /**
     * Trial Balance Page
     */
    public function trialBalance(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">⚖️ ট্রায়াল ব্যালেন্স (Trial Balance Report)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সকল লেজার অ্যাকাউন্টের ডেবিট ও ক্রেডিট ভারসাম্য যাচাই</p>
            </div>
            <button class="btn btn-secondary" onclick="window.print()">🖨️ প্রিন্ট / এক্সপোর্ট</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>কোড</th>
                            <th>অ্যাকাউন্ট বিবরণ</th>
                            <th style="text-align:right;">ডেবিট ব্যালেন্স (৳)</th>
                            <th style="text-align:right;">ক্রেডিট ব্যালেন্স (৳)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>1010-01</td><td>সিটি ব্যাংক পিএলসি - প্রধান হিসাব</td><td style="text-align:right; font-weight:600;">৳ ৭,৮৪,৫০০.০০</td><td style="text-align:right;">-</td></tr>
                        <tr><td>1010-02</td><td>ব্র্যাক ব্যাংক পিএলসি - অপারেশনাল</td><td style="text-align:right; font-weight:600;">৳ ৩,৪৫,০০০.০০</td><td style="text-align:right;">-</td></tr>
                        <tr><td>1020-01</td><td>প্রাপ্য হিসাব (Accounts Receivable)</td><td style="text-align:right; font-weight:600;">৳ ১,১৫,৫০০.০০</td><td style="text-align:right;">-</td></tr>
                        <tr><td>2010-01</td><td>প্রদেয় হিসাব (Accounts Payable)</td><td style="text-align:right;">-</td><td style="text-align:right; font-weight:600;">৳ ১,৮৫,২০০.০০</td></tr>
                        <tr><td>3010-01</td><td>শেয়ার মূলধন (Capital Fund)</td><td style="text-align:right;">-</td><td style="text-align:right; font-weight:600;">৳ ৯,২৫,০০০.০০</td></tr>
                        <tr><td>4010-01</td><td>সফটওয়্যার বিক্রয় আয়</td><td style="text-align:right;">-</td><td style="text-align:right; font-weight:600;">৳ ৪,৮৭,৩৫০.০০</td></tr>
                        <tr><td>5010-01</td><td>অফিস ভাড়া ও প্রশাসনিক ব্যয়</td><td style="text-align:right; font-weight:600;">৳ ১,৫৫,৪৪০.০০</td><td style="text-align:right;">-</td></tr>
                        <tr><td>5020-01</td><td>ক্লাউড সার্ভার ও আইটি অবকাঠামো ব্যয়</td><td style="text-align:right; font-weight:600;">৳ ৫৮,৪০০.০০</td><td style="text-align:right;">-</td></tr>
                        <tr style="background:#f1f5f9; font-size:15px; font-weight:700;">
                            <td colspan="2">সর্বমোট (Total Trial Balance)</td>
                            <td style="text-align:right; color:#065f46;">৳ ১৪,৫৮,৮৪০.০০</td>
                            <td style="text-align:right; color:#065f46;">৳ ১৪,৫৮,৮৪০.০০</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="padding:14px 20px; background:#ecfdf5; color:#065f46; font-size:13px; font-weight:600; border-top:1px solid #a7f3d0;">
                ✓ ট্রায়াল ব্যালেন্স সম্পূর্ণ মিল রয়েছে: ডেবিট (৳ ১৪,৫৮,৮৪০.০০) = ক্রেডিট (৳ ১৪,৫৮,৮৪০.০০)।
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('accounting', 'ট্রায়াল ব্যালেন্স', $body, '', '', '/accounting/trial-balance'));
    }

    /**
     * Income Statement Page
     */
    public function incomeStatement(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">💰 লাভ-ক্ষতি বিবরণী (Income Statement / P&L)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">অর্থবছর ২০২৫-২০২৬ (জুলাই - অক্টোবর)</p>
            </div>
            <button class="btn btn-secondary" onclick="window.print()">🖨️ প্রিন্ট রিপোর্ট</button>
        </div>

        <div class="card" style="max-width:900px; margin:0 auto;">
            <div style="padding:24px;">
                <h3 style="margin:0 0 16px 0; font-size:16px; border-bottom:2px solid #2563eb; padding-bottom:8px; color:#2563eb;">১. পরিচালন রাজস্ব (Operating Revenue)</h3>
                <div style="display:flex; justify-content:space-between; padding:8px 0;"><span>সফটওয়্যার সেলস ও সাবস্ক্রিপশন ফি</span><strong>৳ ৪,৫০,০০০.০০</strong></div>
                <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #e2e8f0;"><span>কাস্টমাইজেশন ও সাপোর্ট সার্ভিস ফি</span><strong>৳ ৩৭,৩৫০.০০</strong></div>
                <div style="display:flex; justify-content:space-between; padding:10px 0; font-weight:700; color:#065f46;"><span>মোট রাজস্ব (Total Revenue)</span><span>৳ ৪,৮৭,৩৫০.০০</span></div>

                <h3 style="margin:24px 0 16px 0; font-size:16px; border-bottom:2px solid #ef4444; padding-bottom:8px; color:#ef4444;">২. পরিচালন ব্যয় (Operating Expenses)</h3>
                <div style="display:flex; justify-content:space-between; padding:8px 0;"><span>কর্মকর্তা-কর্মচারী বেতন ও ভাতা</span><strong>৳ ১,২০,০০০.০০</strong></div>
                <div style="display:flex; justify-content:space-between; padding:8px 0;"><span>অফিস স্পেস ভাড়া ও বিদ্যুৎ বিল</span><strong>৳ ৩৫,৪৪০.০০</strong></div>
                <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #e2e8f0;"><span>ক্লাউড সার্ভার, ব্যান্ডউইডথ ও সিকিউরিটি</span><strong>৳ ৫৮,৪০০.০০</strong></div>
                <div style="display:flex; justify-content:space-between; padding:10px 0; font-weight:700; color:#991b1b;"><span>মোট ব্যয় (Total Expenses)</span><span>- ৳ ২,১৩,৮৪০.০০</span></div>

                <div style="margin-top:24px; padding:16px; background:#eff6ff; border-radius:8px; display:flex; justify-content:space-between; font-size:17px; font-weight:700;">
                    <span style="color:#1e3a8a;">নিট মুনাফা (Net Profit Before Tax)</span>
                    <span style="color:#2563eb;">৳ ২,৭৩,৫১০.০০</span>
                </div>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('accounting', 'আয় বিবরণী', $body, '', '', '/accounting/income-statement'));
    }

    /**
     * Balance Sheet Page
     */
    public function balanceSheet(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">🏦 ব্যালেন্স শিট (Balance Sheet)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">আর্থিক অবস্থার বিবরণী — ১৫ অক্টোবর ২০২৬ অনুযায়ী</p>
            </div>
            <button class="btn btn-secondary" onclick="window.print()">🖨️ প্রিন্ট ব্যালেন্স শিট</button>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-header" style="background:#f0fdf4; color:#065f46; font-weight:700;">
                    সম্পদ সমূহ (Assets)
                </div>
                <div style="padding:16px 20px;">
                    <div style="font-weight:600; color:#475569; margin-bottom:8px;">চলতি সম্পদ (Current Assets)</div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0;"><span>ব্যাংক হিসাব সমুহ</span><strong>৳ ১১,২৯,৫০০.০০</strong></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0;"><span>প্রাপ্য হিসাব (Receivables)</span><strong>৳ ১,১৫,৫০০.০০</strong></div>
                    <div style="font-weight:600; color:#475569; margin:14px 0 8px 0;">স্থায়ী সম্পদ (Fixed Assets)</div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;"><span>আইটি সরঞ্জাম ও ফার্নিচার</span><strong>৳ ১,৮৭,৫০০.০০</strong></div>
                    <div style="display:flex; justify-content:space-between; padding:12px 0 4px 0; font-size:16px; font-weight:700; color:#065f46;">
                        <span>সর্বমোট সম্পদ</span>
                        <span>৳ ১৪,৩২,৫০০.০০</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background:#fef2f2; color:#991b1b; font-weight:700;">
                    দায় ও মালিকানা স্বত্ব (Liabilities & Equity)
                </div>
                <div style="padding:16px 20px;">
                    <div style="font-weight:600; color:#475569; margin-bottom:8px;">দায় সমূহ (Liabilities)</div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0;"><span>অ্যাকাউন্টস পেয়েবল (Payables)</span><strong>৳ ১,৮৫,২০০.০০</strong></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0;"><span>অন্যান্য চলতি দায়</span><strong>৳ ৪৯,৫০০.০০</strong></div>
                    <div style="font-weight:600; color:#475569; margin:14px 0 8px 0;">মালিকানা স্বত্ব (Equity)</div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0;"><span>শেয়ার মূলধন (Capital)</span><strong>৳ ৯,২৫,০০০.০০</strong></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;"><span>বর্তমান মেয়াদের রিটেইনড আর্নিংস</span><strong>৳ ২,৭২,৮০০.০০</strong></div>
                    <div style="display:flex; justify-content:space-between; padding:12px 0 4px 0; font-size:16px; font-weight:700; color:#991b1b;">
                        <span>সর্বমোট দায় ও স্বত্ব</span>
                        <span>৳ ১৪,৩২,৫০০.০০</span>
                    </div>
                </div>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('accounting', 'ব্যালেন্স শিট', $body, '', '', '/accounting/balance-sheet'));
    }

    /**
     * VAT & Tax Report Page
     */
    public function taxReport(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📦 ভ্যাট ও কর রিপোর্ট (VAT & Tax Compliance)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">এনবিআর চালান ও উৎসে কর কর্তন (VDS / TDS) বিবরণী</p>
            </div>
            <button class="btn btn-secondary" onclick="window.print()">🖨️ এক্সপোর্ট এনবিআর ফর্ম্যাট</button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ইনভয়েস #</th>
                            <th>গ্রাহক / ভেন্ডর</th>
                            <th>লেনদেন মূল্য (৳)</th>
                            <th>ভ্যাট রেট</th>
                            <th>প্রদেয় ভ্যাট (৳)</th>
                            <th>TDS কর্তন (৳)</th>
                            <th>চালান স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>INV-2026-101</td><td>এপেক্স টেকনোলজিস লিঃ</td><td>৳ ১,৫০,০০০.০০</td><td>৫.০%</td><td>৳ ৭,৫০০.০০</td><td>৳ ৫,০০০.০০</td><td>' . ModuleLayoutHelper::badge('পরিশোধিত', 'success') . '</td></tr>
                        <tr><td>INV-2026-102</td><td>বাংলা নেটওয়ার্ক সিস্টেমস</td><td>৳ ৮০,০০০.০০</td><td>৫.০%</td><td>৳ ৪,০০০.০০</td><td>৳ ২,৪০০.০০</td><td>' . ModuleLayoutHelper::badge('পরিশোধিত', 'success') . '</td></tr>
                        <tr><td>INV-2026-103</td><td>গ্রীন ডেল্টা করপোরেট</td><td>৳ ২,২০,০০০.০০</td><td>৫.০%</td><td>৳ ১১,০০০.০০</td><td>৳ ৭,০০০.০০</td><td>' . ModuleLayoutHelper::badge('পেন্ডিং', 'warning') . '</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('accounting', 'ভ্যাট / ট্যাক্স রিপোর্ট', $body, '', '', '/accounting/tax-report'));
    }
}
