# NetFusion Framework — Changelog

All notable changes to the NetFusion Framework are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.3.5] - 2026-10-16

### Added
- **Dedicated HMVC Module Dashboards & Sub-Page Routing (46 total routes):**
  - **Accounting (`/accounting`):** Double-entry ledger metrics, interactive Chart of Accounts (`/accounting/chart-of-accounts`), general journal posting (`/accounting/journal`), ledger book (`/accounting/ledger`), trial balance report (`/accounting/trial-balance`), income statement P&L (`/accounting/income-statement`), balance sheet (`/accounting/balance-sheet`), and VAT/NBR tax compliance report (`/accounting/tax-report`).
  - **Billing (`/billing`):** SaaS subscription overview, MRR and ARR telemetry, full invoices registry (`/billing/invoices`), recurring subscriptions (`/billing/subscriptions`), tier plans management (`/billing/plans`), payment transactions log (`/billing/payments`), and overdue debt collection desk (`/billing/overdue`).
  - **CRM (`/crm`):** 4-stage visual sales pipeline funnel (Qualified, Proposal, Negotiation, Won), lead management registry (`/crm/leads`), deal pipeline Kanban (`/crm/deals`), B2B corporate directory (`/crm/companies`), customer contact directory (`/crm/contacts`), helpdesk support ticket queue (`/crm/tickets`), and sales velocity analytics (`/crm/analytics`).
  - **Commerce (`/commerce`):** E-Commerce and POS storefront engine, completed order fulfillment register (`/commerce/orders`), product SKU catalog with stock indicators (`/commerce/products`), customer lifetime value directory (`/commerce/customers`), payment gateway breakdown (`/commerce/payments`), discount coupons management (`/commerce/coupons`), and sales category performance reports (`/commerce/reports`).
  - **HRM (`/hrm`):** Employee roster with role badges (`/hrm/employees`), organizational department structures (`/hrm/departments`), daily biometric attendance log (`/hrm/attendance`), leave request approvals and balances (`/hrm/leave`), monthly salary disbursement sheet (`/hrm/payroll`), and quarterly KPI appraisal reviews (`/hrm/performance`).
  - **Inventory (`/inventory`):** Warehouse stock valuation (FIFO method), SKU inventory catalog (`/inventory/products`), multi-warehouse occupancy tracking (`/inventory/warehouses`), Goods Receipt Note register (`/inventory/stock-in`), Goods Issue Note dispatch register (`/inventory/stock-out`), low-stock reorder triggers (`/inventory/low-stock`), and category asset valuation reports (`/inventory/valuation`).
  - **System (`/system`):** Live PHP 8.2+ runtime inspection, Zend Engine introspection, real-time memory usage and peak monitors (`/system/status`), and dynamic `module.json` manifest inspector (`/system/modules`).
- **Unified Light UI Layout Helper (`ModuleLayoutHelper`):**
  - Created [`app/Http/Helpers/ModuleLayoutHelper.php`](file:///D:/SERVER/www/NetFusion-Framework/app/Http/Helpers/ModuleLayoutHelper.php) implementing unified header, brand accent color injection, active route highlighter, cross-module jumping links, and responsive grid helpers (`kpiCard()`, `currency()`, `badge()`).
- **Main Admin Dashboard HMVC Integration:**
  - Added "বিজনেস মডিউলস (HMVC)" navigation group inside the core executive sidebar (`app/Http/Controllers/DashboardController.php`) providing one-click access to all 8 business modules.

### Changed
- Promoted framework release version from `1.3.1` to `1.3.5` across all core containers, release manifests, configuration files, and installer templates.
- Cleaned up repetitive comment blocks in System module controllers and route registries.

### Verified
- Automated full HTTP dispatch test suite passed 100% across all 46 dashboard and sub-page routes without external database requirements.

---

## [1.3.1] - 2026-10-15

### Added
- **Multi-Tenancy Engine:**
  - Tenant context resolver via query parameter (`tenant_id`), HTTP header (`X-Tenant-ID`), or subdomain.
  - Interactive multi-tenant switching dropdown with live metrics isolation.
  - Tenant creation modal with database driver configuration (MySQL, PostgreSQL, SQLite).
- **Dynamic Module Toggle (On/Off) Switch System:**
  - Runtime module enabler/disabler persisted in `storage/modules_state.json`.
  - Visual toggle switch interface with instant reload and fail-safe dependency checks.
- **Enterprise RBAC User Variants & Permissions Fabric:**
  - Granular role management (Super Admin, Admin, Manager, Operator, Viewer).
  - Permission matrix covering 7 core modules with read/write/delete/export authorizations.
  - User creation and role assignment dialog.

---

## [1.3.0] - 2026-10-14

### Added
- **Unified Executive Management Dashboard (`/admin/dashboard`):**
  - Modern light UI theme with responsive sidebar navigation, executive KPI cards, live system diagnostics telemetry, and module status grid.
  - Core navigation linking to Tenancy, Module Management, RBAC Matrix, License Portal, and System Control.

---

## [1.2.0] - 2026-10-13

### Added
- **Enterprise License CMS & Validation Fabric:**
  - Cryptographic RSA-SHA256 signature verification for DotNetBD license keys.
  - 4-Tier licensing categories (Community, Professional, Enterprise, Sovereign).
  - Hardware machine fingerprinting and offline license verification.

---

## [1.1.0] - 2026-10-12

### Added
- **Web-Based Graphical Installer Wizard (`/install` & `public/install.php`):**
  - Step-by-step browser wizard for zero-CLI deployment on shared hosting and CloudPanel.
  - System requirement checks (PHP 8.2+, PDO, OpenSSL, Mbstring, cURL, BCMath, Fileinfo).
  - Automated `.env` generation and database migration runner.
  - One-click Linux installer script (`install.sh`) via `curl` and `wget`.

---

## [1.0.0] - 2026-10-10

### Initial Release
- **Sovereign Quantum & Physical Intelligence Fabric Golden Master Release:**
  - 120-Phase full-stack architecture with pure PHP 8.2+ design and zero runtime Composer dependencies.
  - Built-in PSR-11 DI Container, Event-Driven Architecture, High-Performance Router, and HMVC Modular Engine.
  - Complete 1,135 regression tests with 100% pass rate.
