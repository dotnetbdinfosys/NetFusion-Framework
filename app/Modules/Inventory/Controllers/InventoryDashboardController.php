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
use NetFusion\App\Services\ModuleDataRepository;

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
     * Products Page - Fully Functional & Editable
     */
    public function products(Request $request): Response
    {
        $products = ModuleDataRepository::all('inventory_products');
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
        foreach ($products as $prod) {
            $sku = htmlspecialchars($prod['id'] ?? $prod['sku'] ?? '');
            $name = htmlspecialchars($prod['name'] ?? '');
            $category = htmlspecialchars($prod['category'] ?? '');
            $unit = htmlspecialchars($prod['unit'] ?? 'পিস (Pcs)');
            $costPrice = (float) ($prod['cost_price'] ?? 0);
            $stock = (int) ($prod['stock'] ?? 0);
            $warehouse = htmlspecialchars($prod['warehouse'] ?? '');
            $status = $prod['status'] ?? ($stock <= 10 ? 'low_stock' : 'in_stock');

            $stockStyle = $stock <= 10 ? 'color:#dc2626; font-weight:700;' : 'color:#065f46; font-weight:700;';
            $jsonAttr = htmlspecialchars(json_encode($prod, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

            $rowsHtml .= '
            <tr id="row-' . $sku . '">
                <td><code>' . $sku . '</code></td>
                <td><strong>' . $name . '</strong></td>
                <td>' . $category . '</td>
                <td>' . $unit . '</td>
                <td style="text-align:right;">' . ModuleLayoutHelper::currency($costPrice) . '</td>
                <td style="text-align:right; ' . $stockStyle . '">' . $stock . ' ' . $unit . '</td>
                <td>' . $warehouse . '</td>
                <td>
                    <div style="display:flex; gap:6px; align-items:center;">
                        <button type="button" class="btn btn-secondary" style="padding:4px 9px; font-size:11.5px; color:#0891b2; border-color:#cffafe;" onclick=\'openEditProductModal(' . $jsonAttr . ')\'>✏️ এডিট আইটেম</button>
                        <a href="/inventory/products/edit?id=' . urlencode($sku) . '" class="btn btn-secondary" style="padding:4px 8px; font-size:11.5px;" title="ফুল পেজ এডিট">📄</a>
                        <button type="button" class="btn btn-secondary" style="padding:4px 8px; font-size:11.5px; color:#dc2626; border-color:#fecaca;" onclick="confirmDeleteRecord(\'inventory_products\', \'' . $sku . '\', \'/inventory/products/delete\', \'' . htmlspecialchars($name, ENT_QUOTES) . ' (' . $sku . ')\')">🗑️ মুছুন</button>
                    </div>
                </td>
            </tr>';
        }

        $body = '
        ' . $flashAlert . '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">📋 ইনভেন্টরি পণ্য তালিকা (Stock Items)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সকল মজুদের বারকোড, মূল্য, গুদাম অবস্থান ও রিয়েলটাইম তথ্য সম্পাদন</p>
            </div>
            <button class="btn btn-primary" style="background:#0891b2;" onclick="openNewProductModal()">+ নতুন ইনভেন্টরি আইটেম</button>
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
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $rowsHtml . '
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ EDIT INVENTORY ITEM MODAL ══ -->
        <div id="productModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:16px;">
            <div style="background:#fff; border-radius:12px; width:100%; max-width:540px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.15); overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                    <h3 id="productModalTitle" style="margin:0; font-size:16px; font-weight:700;">✏️ ইনভেন্টরি আইটেম এডিট করুন</h3>
                    <button type="button" onclick="closeProductModal()" style="border:none; background:transparent; font-size:18px; cursor:pointer;">✕</button>
                </div>
                <form id="productForm" method="POST" action="/inventory/products/update" style="padding:20px;" onsubmit="handleProductSubmit(event)">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">SKU / কোড *</label>
                            <input type="text" id="prod_sku" name="sku" readonly required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; background:#f1f5f9; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">ক্যাটাগরি</label>
                            <input type="text" id="prod_category" name="category" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                    </div>

                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">পণ্যের নাম *</label>
                        <input type="text" id="prod_name" name="name" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600;">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">ক্রয় মূল্য (৳) *</label>
                            <input type="number" step="0.01" id="prod_cost_price" name="cost_price" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">বিক্রয় মূল্য (৳)</label>
                            <input type="number" step="0.01" id="prod_selling_price" name="selling_price" style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:700;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">মজুদ পরিমাণ *</label>
                            <input type="number" id="prod_stock" name="stock" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">একক (Unit)</label>
                            <input type="text" id="prod_unit" name="unit" placeholder="পিস (Pcs), বক্স ইত্যাদি" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">গুদাম ঘর / অবস্থান</label>
                        <input type="text" id="prod_warehouse" name="warehouse" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeProductModal()">বাতিল</button>
                        <button type="submit" id="saveProductBtn" class="btn btn-primary" style="background:#0891b2;">💾 সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openEditProductModal(item) {
            document.getElementById("productModalTitle").innerText = "✏️ আইটেম এডিট: " + item.sku;
            document.getElementById("prod_sku").value = item.sku || item.id || "";
            document.getElementById("prod_sku").readOnly = true;
            document.getElementById("prod_name").value = item.name || "";
            document.getElementById("prod_category").value = item.category || "";
            document.getElementById("prod_unit").value = item.unit || "পিস (Pcs)";
            document.getElementById("prod_cost_price").value = item.cost_price || 0;
            document.getElementById("prod_selling_price").value = item.selling_price || 0;
            document.getElementById("prod_stock").value = item.stock || 0;
            document.getElementById("prod_warehouse").value = item.warehouse || "";
            document.getElementById("productModal").style.display = "flex";
        }

        function openNewProductModal() {
            const nextSku = "SKU-" + Math.floor(100 + Math.random() * 900);
            document.getElementById("productModalTitle").innerText = "➕ নতুন ইনভেন্টরি আইটেম যোগ করুন";
            document.getElementById("prod_sku").value = nextSku;
            document.getElementById("prod_sku").readOnly = false;
            document.getElementById("prod_name").value = "";
            document.getElementById("prod_category").value = "সাধারণ সামগ্রী";
            document.getElementById("prod_unit").value = "পিস (Pcs)";
            document.getElementById("prod_cost_price").value = 1000.00;
            document.getElementById("prod_selling_price").value = 1400.00;
            document.getElementById("prod_stock").value = 50;
            document.getElementById("prod_warehouse").value = "তেজগাঁও - A1";
            document.getElementById("productModal").style.display = "flex";
        }

        function closeProductModal() {
            document.getElementById("productModal").style.display = "none";
        }

        async function handleProductSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById("saveProductBtn");
            btn.disabled = true;
            btn.innerText = "সংরক্ষণ হচ্ছে...";

            const form = document.getElementById("productForm");
            const formData = new FormData(form);

            try {
                const response = await fetch("/inventory/products/update", {
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

        return Response::html(ModuleLayoutHelper::wrap('inventory', 'পণ্য তালিকা', $body, '', '', '/inventory/products'));
    }

    /**
     * Standalone Edit Product Page (GET /inventory/products/edit?id=...)
     */
    public function editProduct(Request $request): Response
    {
        $id = (string) $request->query('id', '');
        $product = ModuleDataRepository::find('inventory_products', 'id', $id);

        if (!$product) {
            return Response::redirect('/inventory/products?msg=' . urlencode('পণ্য আইটেম পাওয়া যায়নি'));
        }

        $sku = htmlspecialchars($product['id'] ?? $product['sku']);
        $name = htmlspecialchars($product['name'] ?? '');
        $category = htmlspecialchars($product['category'] ?? '');
        $unit = htmlspecialchars($product['unit'] ?? 'পিস (Pcs)');
        $costPrice = (float) ($product['cost_price'] ?? 0);
        $sellingPrice = (float) ($product['selling_price'] ?? 0);
        $stock = (int) ($product['stock'] ?? 0);
        $warehouse = htmlspecialchars($product['warehouse'] ?? '');

        $body = '
        <div style="max-width:680px; margin:0 auto;">
            <div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">✏️ আইটেম সম্পাদনা: ' . $sku . '</h2>
                    <p style="margin:0; font-size:13px; color:#64748b;">পণ্যের নাম, মূল্য, মজুদ সংখ্যা ও গুদাম পরিবর্তন করুন</p>
                </div>
                <a href="/inventory/products" class="btn btn-secondary">← ফিরে যান</a>
            </div>

            <div class="card" style="padding:24px;">
                <form method="POST" action="/inventory/products/update">
                    <input type="hidden" name="sku" value="' . $sku . '">

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">SKU / কোড</label>
                            <input type="text" value="' . $sku . '" disabled style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; background:#f1f5f9; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">ক্যাটাগরি</label>
                            <input type="text" name="category" value="' . $category . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">পণ্যের পুরো নাম</label>
                        <input type="text" name="name" value="' . $name . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">ক্রয় মূল্য (৳)</label>
                            <input type="number" step="0.01" name="cost_price" value="' . $costPrice . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">বিক্রয় মূল্য (৳)</label>
                            <input type="number" step="0.01" name="selling_price" value="' . $sellingPrice . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">বর্তমান মজুদ</label>
                            <input type="number" name="stock" value="' . $stock . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">একক</label>
                            <input type="text" name="unit" value="' . $unit . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="margin-bottom:24px;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">ওয়্যারহাউস অবস্থান</label>
                        <input type="text" name="warehouse" value="' . $warehouse . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <a href="/inventory/products" class="btn btn-secondary">বাতিল</a>
                        <button type="submit" class="btn btn-primary" style="background:#0891b2;">💾 পরিবর্তন সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('inventory', 'পণ্য এডিট', $body, '', '', '/inventory/products'));
    }

    /**
     * Update / Create Product Handler (POST /inventory/products/update)
     */
    public function updateProduct(Request $request): Response
    {
        $sku = trim((string) $request->input('sku', ''));
        if ($sku === '') {
            $sku = 'SKU-' . strtoupper(bin2hex(random_bytes(3)));
        }

        $costPrice = (float) $request->input('cost_price', 0.0);
        $sellingPrice = (float) $request->input('selling_price', $costPrice * 1.3);
        $stock = (int) $request->input('stock', 0);

        $data = [
            'id' => $sku,
            'sku' => $sku,
            'name' => trim((string) $request->input('name', 'Product Name')),
            'category' => trim((string) $request->input('category', 'General')),
            'unit' => trim((string) $request->input('unit', 'পিস (Pcs)')),
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'stock' => $stock,
            'warehouse' => trim((string) $request->input('warehouse', 'তেজগাঁও - A1')),
            'status' => $stock <= 10 ? 'low_stock' : 'in_stock',
        ];

        $existing = ModuleDataRepository::find('inventory_products', 'id', $sku);
        if ($existing) {
            ModuleDataRepository::update('inventory_products', 'id', $sku, $data);
            $msg = "পণ্য {$sku} সফলভাবে আপডেট করা হয়েছে।";
        } else {
            ModuleDataRepository::insert('inventory_products', $data);
            $msg = "নতুন পণ্য {$sku} সফলভাবে যোগ করা হয়েছে।";
        }

        if ($request->wantsJson() || $request->isAjax()) {
            return Response::json(['success' => true, 'message' => $msg, 'data' => $data]);
        }

        return Response::redirect('/inventory/products?msg=' . urlencode($msg));
    }

    /**
     * Warehouses Page - Fully Functional & Editable
     */
    public function warehouses(Request $request): Response
    {
        $warehouses = ModuleDataRepository::all('inventory_warehouses');
        $flashMsg = $request->query('msg');
        $flashAlert = '';
        if ($flashMsg) {
            $flashAlert = '
            <div style="padding: 12px 16px; background: #ecfdf5; border-left: 4px solid #10b981; border-radius: 6px; margin-bottom: 20px; color: #065f46; font-size: 13.5px;">
                ✅ ' . htmlspecialchars($flashMsg) . '
            </div>';
        }

        $rowsHtml = '';
        foreach ($warehouses as $wh) {
            $code = htmlspecialchars($wh['code'] ?? $wh['id'] ?? '');
            $name = htmlspecialchars($wh['name'] ?? '');
            $location = htmlspecialchars($wh['location'] ?? '');
            $incharge = htmlspecialchars($wh['incharge'] ?? '');
            $capacity = htmlspecialchars($wh['capacity'] ?? '৫০% ব্যবহৃত');

            $jsonAttr = htmlspecialchars(json_encode($wh, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

            $rowsHtml .= '
            <tr id="row-' . $code . '">
                <td><code>' . $code . '</code></td>
                <td><strong>' . $name . '</strong></td>
                <td>' . $location . '</td>
                <td>' . $incharge . '</td>
                <td>' . ModuleLayoutHelper::badge($capacity, 'info') . '</td>
                <td>
                    <div style="display:flex; gap:6px; align-items:center;">
                        <button type="button" class="btn btn-secondary" style="padding:4px 9px; font-size:11.5px; color:#0891b2;" onclick=\'openEditWhModal(' . $jsonAttr . ')\'>✏️ এডিট</button>
                        <button type="button" class="btn btn-secondary" style="padding:4px 8px; font-size:11.5px; color:#dc2626; border-color:#fecaca;" onclick="confirmDeleteRecord(\'inventory_warehouses\', \'' . $code . '\', \'/inventory/warehouses/delete\', \'' . htmlspecialchars($name, ENT_QUOTES) . ' (' . $code . ')\')">🗑️ মুছুন</button>
                    </div>
                </td>
            </tr>';
        }

        $body = '
        ' . $flashAlert . '
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">🏭 ওয়্যারহাউস ও গুদাম নেটওয়ার্ক (Warehouses)</h2>
                <p style="margin:0; font-size:13px; color:#64748b;">সকল গুদামঘর, ইনচার্জ, ধারণক্ষমতা ও স্টোরেজ লোকেশন ব্যবস্থাপনা</p>
            </div>
            <button class="btn btn-primary" style="background:#0891b2;" onclick="openNewWhModal()">+ নতুন ওয়্যারহাউস যোগ করুন</button>
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
                            <th>ধারণক্ষমতা ব্যবহার</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $rowsHtml . '
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ EDIT WH MODAL ══ -->
        <div id="whModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:16px;">
            <div style="background:#fff; border-radius:12px; width:100%; max-width:500px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.15); overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                    <h3 id="whModalTitle" style="margin:0; font-size:16px; font-weight:700;">✏️ গুদাম তথ্য এডিট</h3>
                    <button type="button" onclick="closeWhModal()" style="border:none; background:transparent; font-size:18px; cursor:pointer;">✕</button>
                </div>
                <form method="POST" action="/inventory/warehouses/update" style="padding:20px;">
                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">গুদাম কোড *</label>
                        <input type="text" id="wh_code" name="code" readonly required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; background:#f1f5f9; font-weight:700;">
                    </div>
                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">গুদামের পুরো নাম *</label>
                        <input type="text" id="wh_name" name="name" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600;">
                    </div>
                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">ঠিকানা / অবস্থান</label>
                        <input type="text" id="wh_location" name="location" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px;">
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">ইনচার্জ / ম্যানেজার</label>
                            <input type="text" id="wh_incharge" name="incharge" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:5px;">ধারণক্ষমতা বিবরণ</label>
                            <input type="text" id="wh_capacity" name="capacity" required style="width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeWhModal()">বাতিল</button>
                        <button type="submit" class="btn btn-primary" style="background:#0891b2;">💾 সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openEditWhModal(item) {
            document.getElementById("whModalTitle").innerText = "✏️ গুদাম এডিট: " + item.name;
            document.getElementById("wh_code").value = item.code || item.id || "";
            document.getElementById("wh_code").readOnly = true;
            document.getElementById("wh_name").value = item.name || "";
            document.getElementById("wh_location").value = item.location || "";
            document.getElementById("wh_incharge").value = item.incharge || "";
            document.getElementById("wh_capacity").value = item.capacity || "";
            document.getElementById("whModal").style.display = "flex";
        }

        function openNewWhModal() {
            const nextCode = "WH-0" + Math.floor(10 + Math.random() * 90);
            document.getElementById("whModalTitle").innerText = "➕ নতুন ওয়্যারহাউস যোগ করুন";
            document.getElementById("wh_code").value = nextCode;
            document.getElementById("wh_code").readOnly = false;
            document.getElementById("wh_name").value = "";
            document.getElementById("wh_location").value = "ঢাকা";
            document.getElementById("wh_incharge").value = "";
            document.getElementById("wh_capacity").value = "৫০% পূর্ণ (৩,০০০ স্কয়ারফুট)";
            document.getElementById("whModal").style.display = "flex";
        }

        function closeWhModal() {
            document.getElementById("whModal").style.display = "none";
        }
        </script>
        ';

        return Response::html(ModuleLayoutHelper::wrap('inventory', 'গুদামঘর', $body, '', '', '/inventory/warehouses'));
    }

    /**
     * Standalone Edit Warehouse Page (GET /inventory/warehouses/edit?id=...)
     */
    public function editWarehouse(Request $request): Response
    {
        $id = (string) $request->query('id', '');
        $wh = ModuleDataRepository::find('inventory_warehouses', 'id', $id);

        if (!$wh) {
            return Response::redirect('/inventory/warehouses?msg=' . urlencode('গুদাম পাওয়া যায়নি'));
        }

        $code = htmlspecialchars($wh['code'] ?? $wh['id']);
        $name = htmlspecialchars($wh['name'] ?? '');
        $location = htmlspecialchars($wh['location'] ?? '');
        $incharge = htmlspecialchars($wh['incharge'] ?? '');
        $capacity = htmlspecialchars($wh['capacity'] ?? '');

        $body = '
        <div style="max-width:680px; margin:0 auto;">
            <div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">✏️ গুদাম সম্পাদনা: ' . $name . '</h2>
                    <p style="margin:0; font-size:13px; color:#64748b;">গুদামের ঠিকানা, ইনচার্জ ও ধারণক্ষমতা আপডেট করুন</p>
                </div>
                <a href="/inventory/warehouses" class="btn btn-secondary">← ফিরে যান</a>
            </div>

            <div class="card" style="padding:24px;">
                <form method="POST" action="/inventory/warehouses/update">
                    <input type="hidden" name="code" value="' . $code . '">

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">গুদাম কোড</label>
                        <input type="text" value="' . $code . '" disabled style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; background:#f1f5f9; font-weight:700;">
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">গুদামের নাম</label>
                        <input type="text" name="name" value="' . $name . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;">
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">ঠিকানা ও লোকেশন</label>
                        <input type="text" name="location" value="' . $location . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">ইনচার্জ / ম্যানেজার</label>
                            <input type="text" name="incharge" value="' . $incharge . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">ধারণক্ষমতা বিবরণ</label>
                            <input type="text" name="capacity" value="' . $capacity . '" required style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <a href="/inventory/warehouses" class="btn btn-secondary">বাতিল</a>
                        <button type="submit" class="btn btn-primary" style="background:#0891b2;">💾 পরিবর্তন সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>
        ';

        return Response::html(ModuleLayoutHelper::wrap('inventory', 'গুদাম এডিট', $body, '', '', '/inventory/warehouses'));
    }

    /**
     * Update / Create Warehouse Handler (POST /inventory/warehouses/update)
     */
    public function updateWarehouse(Request $request): Response
    {
        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            $code = 'WH-' . rand(10, 99);
        }

        $data = [
            'id' => $code,
            'code' => $code,
            'name' => trim((string) $request->input('name', 'Central Warehouse')),
            'location' => trim((string) $request->input('location', 'ঢাকা')),
            'incharge' => trim((string) $request->input('incharge', 'ম্যানেজার')),
            'capacity' => trim((string) $request->input('capacity', '৫০% ব্যবহৃত')),
        ];

        $existing = ModuleDataRepository::find('inventory_warehouses', 'id', $code);
        if ($existing) {
            ModuleDataRepository::update('inventory_warehouses', 'id', $code, $data);
            $msg = "গুদাম {$code} সফলভাবে আপডেট করা হয়েছে।";
        } else {
            ModuleDataRepository::insert('inventory_warehouses', $data);
            $msg = "নতুন গুদাম {$code} সফলভাবে যোগ করা হয়েছে।";
        }

        if ($request->wantsJson() || $request->isAjax()) {
            return Response::json(['success' => true, 'message' => $msg, 'data' => $data]);
        }

        return Response::redirect('/inventory/warehouses?msg=' . urlencode($msg));
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

    /**
     * Delete an inventory product and log audit footprint to MySQL
     */
    public function deleteProduct(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            return Response::json(['success' => false, 'message' => 'পণ্য SKU বা আইডি প্রদান করা আবশ্যক।'], 400);
        }

        $prod = ModuleDataRepository::find('inventory_products', 'id', $id);
        if (!$prod) {
            return Response::json(['success' => false, 'message' => 'পণ্যটি পাওয়া যায়নি।'], 404);
        }

        $deleted = ModuleDataRepository::delete('inventory_products', 'id', $id);
        if ($deleted) {
            return Response::json(['success' => true, 'message' => "পণ্য {$prod['name']} ({$id}) সফলভাবে মুছে ফেলা হয়েছে এবং অডিট টেবিলে ফুটপ্রিন্ট সংরক্ষিত হয়েছে।"]);
        }
        return Response::json(['success' => false, 'message' => 'পণ্য মুছতে ব্যর্থ হয়েছে।'], 500);
    }

    /**
     * Delete a warehouse and log audit footprint to MySQL
     */
    public function deleteWarehouse(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            return Response::json(['success' => false, 'message' => 'ওয়্যারহাউস কোড প্রদান করা আবশ্যক।'], 400);
        }

        $wh = ModuleDataRepository::find('inventory_warehouses', 'id', $id);
        if (!$wh) {
            return Response::json(['success' => false, 'message' => 'ওয়্যারহাউসটি পাওয়া যায়নি।'], 404);
        }

        $deleted = ModuleDataRepository::delete('inventory_warehouses', 'id', $id);
        if ($deleted) {
            return Response::json(['success' => true, 'message' => "ওয়্যারহাউস {$wh['name']} ({$id}) সফলভাবে মুছে ফেলা হয়েছে এবং অডিট টেবিলে ফুটপ্রিন্ট সংরক্ষিত হয়েছে।"]);
        }
        return Response::json(['success' => false, 'message' => 'ওয়্যারহাউস মুছতে ব্যর্থ হয়েছে।'], 500);
    }
}
