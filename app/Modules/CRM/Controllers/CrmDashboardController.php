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
| Version   : 1.3.1
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Modules\CRM\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\App\Http\Helpers\ModuleLayoutHelper;

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
     * Leads Page
     */
    public function leads(Request $request): Response
    {
        $body = '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">🎯 লিড ম্যানেজমেন্ট (Lead Registry)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সকল সম্ভাব্য ক্লায়েন্ট ও সেলস অপরচুনিটি ট্র্যাকিং</p>
            </div>
            <button class="btn btn-primary" style="background:#059669;" onclick="alert(\'নতুন লিড যুক্ত করার উইন্ডো...\')">+ নতুন লিড অ্যাড করুন</button>
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
                            <th>উৎস (Source)</th>
                            <th style="text-align:right;">সম্ভাব্য ভ্যালু</th>
                            <th>স্ট্যাটাস</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>LD-01</code></td><td><strong>তানভীর আহমেদ</strong></td><td>প্রাইম টেক লিমিটেড</td><td>tanvir@primetech.bd<br>+880 1711-223344</td><td>ওয়েবসাইট ফর্ম</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৭৫,০০০.০০</td><td>' . ModuleLayoutHelper::badge('নতুন', 'info') . '</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">প্রোফাইল</button></td></tr>
                        <tr><td><code>LD-02</code></td><td><strong>নুসরাত জাহান</strong></td><td>এলিট ফ্যাশনস</td><td>nusrat@elitefashion.com<br>+880 1819-334455</td><td>লিংকডইন ইনবক্স</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৪৫,০০০.০০</td><td>' . ModuleLayoutHelper::badge('কন্টাক্টেড', 'warning') . '</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">প্রোফাইল</button></td></tr>
                        <tr><td><code>LD-03</code></td><td><strong>মাহমুদুল হাসান</strong></td><td>স্কাইলাইন লজিস্টিকস</td><td>hasan@skyline.bd<br>+880 1912-778899</td><td>রেফারেল পার্টনার</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ১,২০,০০০.০০</td><td>' . ModuleLayoutHelper::badge('কোয়ালিফাইড', 'success') . '</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">প্রোফাইল</button></td></tr>
                        <tr><td><code>LD-04</code></td><td><strong>ফারহানা ইসলাম</strong></td><td>গ্রীন ভ্যালি এগ্রো</td><td>farhana@greenvalley.bd<br>+880 1610-998877</td><td>ফেসবুক অ্যাডস</td><td style="text-align:right; font-weight:700; color:#065f46;">৳ ৩৫,০০০.০০</td><td>' . ModuleLayoutHelper::badge('প্রস্তাবনা পাঠানো', 'warning') . '</td><td><button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">প্রোফাইল</button></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('crm', 'লিড ম্যানেজমেন্ট', $body, '', '', '/crm/leads'));
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
}
