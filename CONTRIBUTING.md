# Contributing to NetFusion Framework

Thank you for your interest in contributing to **NetFusion Framework**! As an enterprise-grade sovereign computing fabric, we maintain high standards for architectural integrity, performance, and documentation.

---

## 🏛️ Code Standards & Rules

1. **Pure PHP 8.2+ Architecture**:
   - Strictly **zero runtime 3rd-party dependencies**. Core framework features must not depend on external Composer packages.
   - Enforce strict typing in all PHP files: `declare(strict_types=1);`.
   - Adhere to PSR-12 coding style and PSR-4 namespacing standards.

2. **Standard File Header Comments**:
   Every PHP source file must include the official DotNetBD enterprise header:
   ```php
   /*
    |--------------------------------------------------------------------------
    | NetFusion Framework
    |--------------------------------------------------------------------------
    |
    | File      : <file_path>
    | Author    : Md. Atiquzzaman Ashish
    | Company   : DotNetBD
    | Website   : www.dotnetbd.com
    | Copyright : © DotNetBD. All rights reserved.
    | License   : MIT
    | Version   : 1.0.0
    |
    |--------------------------------------------------------------------------
    */
   ```

3. **Testing Requirements**:
   - Any new feature or bugfix must be accompanied by comprehensive unit tests.
   - All tests must pass cleanly: `php scratch/run_all_tests.php` with 100% pass rate.
   - Golden Master verification must succeed: `php netfusion framework:verify` (100/100 score).

---

## 🌿 Git Workflow & Branches

1. **Fork the repository** on GitHub.
2. **Clone your fork**:
   ```bash
   git clone https://github.com/<your-username>/NetFusion-Framework.git
   cd NetFusion-Framework
   ```
3. **Create a topic branch**:
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/issue-description
   ```
4. **Commit messages**:
   Follow Conventional Commits format:
   - `feat: add XYZ subsystem`
   - `fix: resolve timing leak in HMAC verification`
   - `docs: update CLI reference for new command`
   - `perf: optimize matrix transposition in LBM grid`
5. **Push and create a Pull Request**:
   Push to your fork and submit a PR to the `main` branch.

---

## 💬 Community & Questions

- Website: [www.dotnetbd.com](https://www.dotnetbd.com)
- Questions: Open a GitHub Discussion or contact `developer@dotnetbd.com`.
