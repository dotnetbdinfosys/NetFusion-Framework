<?php
/**
 * NetFusion Framework - Enterprise License Fabric
 *
 * Graphical Web Controller for DotNetBD License CMS (API Engine v5.1.8) Activation and Management.
 *
 * @package    NetFusion\App\Http\Controllers
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\App\Http\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\Foundation\License\LicenseManager;
use Throwable;

class LicenseController
{
    protected LicenseManager $license;

    public function __construct(?LicenseManager $license = null)
    {
        $this->license = $license ?? new LicenseManager();
    }

    /**
     * Display the License Management & Activation Graphical User Interface.
     */
    public function index(Request $request): Response
    {
        $status = $this->license->status();
        $domain = $this->license->detectDomain();

        return Response::html($this->renderHtml($status, $domain));
    }

    /**
     * Handle AJAX / Form submission for License Activation.
     */
    public function activate(Request $request): Response
    {
        $licenseKey  = (string) ($request->input('license_key') ?? '');
        $companyName = (string) ($request->input('company_name') ?? '');
        $domain      = $request->input('domain') ? (string) $request->input('domain') : null;

        if (empty($licenseKey) || empty($companyName)) {
            return Response::json([
                'success' => false,
                'message' => 'Both License Key and Company Name are required fields.',
            ], 422);
        }

        try {
            $result = $this->license->activate($licenseKey, $companyName, $domain);

            return Response::json([
                'success' => true,
                'message' => $result['message'] ?? 'License activated successfully!',
                'data'    => $result,
            ]);
        } catch (Throwable $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Check/Refresh remote heartbeat validity.
     */
    public function check(Request $request): Response
    {
        try {
            $validation = $this->license->validate(true);

            return Response::json([
                'success' => $validation['valid'] ?? false,
                'message' => $validation['message'] ?? '',
                'data'    => $this->license->status(),
            ]);
        } catch (Throwable $e) {
            return Response::json([
                'success' => false,
                'message' => 'Heartbeat check failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deactivate local license.
     */
    public function deactivate(Request $request): Response
    {
        $this->license->deactivate();

        return Response::json([
            'success' => true,
            'message' => 'License has been deactivated from this installation.',
        ]);
    }

    /**
     * Render the standalone modern responsive HTML GUI for License Management.
     *
     * @param array<string, mixed> $status
     * @param string               $domain
     */
    protected function renderHtml(array $status, string $domain): string
    {
        $isActive = !empty($status['is_active']);
        $statusBadge = $isActive
            ? '<span class="badge badge-success">● ACTIVE & VERIFIED</span>'
            : '<span class="badge badge-warning">● UNLICENSED / INACTIVE</span>';

        $keyMasked = $isActive ? htmlspecialchars($status['license_key'] ?? '') : '';
        $company = $isActive ? htmlspecialchars($status['company_name'] ?? '') : '';
        $expires = $isActive ? htmlspecialchars($status['expires_at'] ?? 'Lifetime') : '';
        $daysRemaining = $status['days_remaining'] ?? null;
        $boundDomain = $isActive ? htmlspecialchars($status['domain'] ?? $domain) : htmlspecialchars($domain);
        $maxServers = $isActive ? htmlspecialchars((string) ($status['max_servers'] ?? '1')) : '1';

        $daysBadge = ($daysRemaining !== null)
            ? "<span class='tag'>" . intval($daysRemaining) . " days remaining</span>"
            : ($isActive ? "<span class='tag'>Lifetime License</span>" : "");

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetFusion Framework — License Management Portal</title>
    <style>
        :root {
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #0b0f19;
            --card-bg: #111827;
            --card-border: #1f2937;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --input-bg: #1f2937;
            --input-border: #374151;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        body { background-color: var(--bg); color: var(--text-main); min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px 16px; }
        .container { width: 100%; max-width: 680px; }
        .brand { text-align: center; margin-bottom: 28px; }
        .brand h1 { font-size: 26px; font-weight: 700; color: #fff; letter-spacing: -0.5px; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .brand p { color: var(--text-muted); font-size: 14px; margin-top: 6px; }
        .card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 32px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 10px 10px -5px rgba(0, 0, 0, 0.3); }
        .header-bar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--card-border); padding-bottom: 20px; margin-bottom: 24px; }
        .badge { display: inline-flex; align-items: center; padding: 5px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .tag { background: rgba(59, 130, 246, 0.2); color: #60a5fa; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; margin-left: 6px; }
        .meta-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 24px; background: rgba(15, 23, 42, 0.6); padding: 18px; border-radius: 10px; border: 1px solid var(--card-border); }
        .meta-item label { display: block; font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; margin-bottom: 4px; }
        .meta-item value { display: block; font-size: 14px; font-weight: 600; color: #fff; word-break: break-all; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text-main); margin-bottom: 8px; }
        .form-control { width: 100%; background: var(--input-bg); border: 1px solid var(--input-border); border-radius: 8px; padding: 12px 14px; color: #fff; font-size: 14px; transition: border-color 0.2s, box-shadow 0.2s; outline: none; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25); }
        .btn { display: inline-flex; align-items: center; justify-content: center; width: 100%; padding: 13px 20px; border-radius: 8px; font-size: 15px; font-weight: 600; border: none; cursor: pointer; transition: background-color 0.2s, transform 0.1s; text-decoration: none; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: #374151; color: #fff; margin-top: 10px; }
        .btn-secondary:hover { background: #4b5563; }
        .btn-danger { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); margin-top: 10px; }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.3); }
        .btn:active { transform: scale(0.99); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .alert { padding: 14px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; display: none; line-height: 1.5; }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        .footer { text-align: center; margin-top: 24px; font-size: 13px; color: #64748b; }
        .footer a { color: var(--primary); text-decoration: none; }
        .actions-group { display: flex; gap: 10px; margin-top: 16px; }
        .actions-group .btn { width: 50%; }
        @media (max-width: 580px) {
            .meta-grid { grid-template-columns: 1fr; }
            .actions-group { flex-direction: column; }
            .actions-group .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="brand">
            <h1>🛡️ NetFusion Framework</h1>
            <p>DotNetBD License CMS (API Engine v5.1.8) Management Portal</p>
        </div>

        <div class="card">
            <div class="header-bar">
                <div>
                    <h2 style="font-size: 18px; color: #fff;">License Status</h2>
                    <span style="font-size: 12px; color: var(--text-muted);">Cryptographic Server Binding</span>
                </div>
                <div>{$statusBadge}</div>
            </div>

            <div id="alertBox" class="alert"></div>

            <div id="activeDetails" style="display: " . ($isActive ? 'block' : 'none') . ";">
                <div class="meta-grid">
                    <div class="meta-item">
                        <label>Licensed Company</label>
                        <value id="valCompany">{$company}</value>
                    </div>
                    <div class="meta-item">
                        <label>License Key</label>
                        <value id="valKey">{$keyMasked}</value>
                    </div>
                    <div class="meta-item">
                        <label>Bound Domain</label>
                        <value id="valDomain">{$boundDomain}</value>
                    </div>
                    <div class="meta-item">
                        <label>Server Capacity</label>
                        <value id="valServerLimit">{$maxServers} Server(s)</value>
                    </div>
                    <div class="meta-item" style="grid-column: span 2;">
                        <label>Expiration Date</label>
                        <value><span id="valExpires">{$expires}</span> {$daysBadge}</value>
                    </div>
                </div>

                <div class="actions-group">
                    <button type="button" id="btnRefresh" class="btn btn-secondary">🔄 Refresh Heartbeat</button>
                    <button type="button" id="btnShowForm" class="btn btn-secondary">✏️ Change Key</button>
                </div>
                <button type="button" id="btnDeactivate" class="btn btn-danger">⚠️ Deactivate License</button>
            </div>

            <form id="licenseForm" style="display: " . ($isActive ? 'none' : 'block') . ";">
                <div class="form-group">
                    <label for="license_key">DotNetBD License Key <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="license_key" name="license_key" class="form-control" placeholder="NF-XXXX-XXXX-XXXX-XXXX" required autofocus value="{$keyMasked}">
                </div>

                <div class="form-group">
                    <label for="company_name">Company Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="company_name" name="company_name" class="form-control" placeholder="Your Registered Company Name" required value="{$company}">
                </div>

                <div class="form-group">
                    <label for="domain">Client Domain / Hostname</label>
                    <input type="text" id="domain" name="domain" class="form-control" value="{$boundDomain}" placeholder="Detected domain name">
                    <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Auto-detected from server environment. Leave as-is unless binding to custom alias.</small>
                </div>

                <button type="submit" id="btnSubmit" class="btn btn-primary">⚡ Activate License Now</button>
                <div id="cancelChangeGroup" style="display: none; margin-top: 10px;">
                    <button type="button" id="btnCancelChange" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>

        <div class="footer">
            Powered by <strong>DotNetBD License CMS</strong> &bull; Developed by <a href="https://www.dotnetbd.com" target="_blank">Md. Atiquzzaman Ashish</a>
        </div>
    </div>

    <script>
        const form = document.getElementById('licenseForm');
        const alertBox = document.getElementById('alertBox');
        const btnSubmit = document.getElementById('btnSubmit');
        const activeDetails = document.getElementById('activeDetails');
        const btnRefresh = document.getElementById('btnRefresh');
        const btnShowForm = document.getElementById('btnShowForm');
        const btnCancelChange = document.getElementById('btnCancelChange');
        const cancelChangeGroup = document.getElementById('cancelChangeGroup');
        const btnDeactivate = document.getElementById('btnDeactivate');

        function showAlert(msg, isSuccess = true) {
            alertBox.style.display = 'block';
            alertBox.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-error');
            alertBox.innerHTML = (isSuccess ? '✅ ' : '❌ ') + msg;
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            btnSubmit.disabled = true;
            btnSubmit.innerText = 'Connecting to https://lic.dotnetbd.com...';
            alertBox.style.display = 'none';

            const payload = {
                license_key: document.getElementById('license_key').value.trim(),
                company_name: document.getElementById('company_name').value.trim(),
                domain: document.getElementById('domain').value.trim()
            };

            try {
                const res = await fetch('/license/activate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();

                if (json.success) {
                    showAlert(json.message || 'License activated successfully!', true);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert(json.message || 'License activation failed.', false);
                    btnSubmit.disabled = false;
                    btnSubmit.innerText = '⚡ Activate License Now';
                }
            } catch (err) {
                showAlert('Network request failed: ' + err.message, false);
                btnSubmit.disabled = false;
                btnSubmit.innerText = '⚡ Activate License Now';
            }
        });

        if (btnRefresh) {
            btnRefresh.addEventListener('click', async function() {
                btnRefresh.disabled = true;
                btnRefresh.innerText = 'Refreshing...';
                try {
                    const res = await fetch('/license/check', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' }
                    });
                    const json = await res.json();
                    showAlert(json.message, json.success);
                    setTimeout(() => window.location.reload(), 1200);
                } catch (err) {
                    showAlert('Heartbeat verification failed: ' + err.message, false);
                    btnRefresh.disabled = false;
                    btnRefresh.innerText = '🔄 Refresh Heartbeat';
                }
            });
        }

        if (btnShowForm) {
            btnShowForm.addEventListener('click', function() {
                activeDetails.style.display = 'none';
                form.style.display = 'block';
                cancelChangeGroup.style.display = 'block';
            });
        }

        if (btnCancelChange) {
            btnCancelChange.addEventListener('click', function() {
                form.style.display = 'none';
                activeDetails.style.display = 'block';
                cancelChangeGroup.style.display = 'none';
            });
        }

        if (btnDeactivate) {
            btnDeactivate.addEventListener('click', async function() {
                if (!confirm('Are you sure you want to deactivate the license on this server?')) return;
                btnDeactivate.disabled = true;
                try {
                    const res = await fetch('/license/deactivate', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' }
                    });
                    const json = await res.json();
                    showAlert(json.message, true);
                    setTimeout(() => window.location.reload(), 1000);
                } catch (err) {
                    showAlert('Deactivation failed: ' + err.message, false);
                    btnDeactivate.disabled = false;
                }
            });
        }
    </script>
</body>
</html>
HTML;
    }
}
