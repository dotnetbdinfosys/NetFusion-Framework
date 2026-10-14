# NetFusion Framework

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://php.net)
[![DotNetBD Engine](https://img.shields.io/badge/DotNetBD-v5.1.8-success.svg)](https://www.dotnetbd.com)

**NetFusion Framework** is a pure PHP 8.2+ Enterprise Full-Stack & HMVC Framework engineered with zero third-party runtime Composer dependencies, built-in distributed architecture, enterprise-grade DotNetBD License CMS integration, and resilient microservice engines.

---

## ⚡ Quick Start / One-Click Installation

You can install and bootstrap the complete NetFusion Framework directly on any Linux server (Ubuntu, Debian, CentOS, RHEL, AlmaLinux, Rocky, Alpine) using a single command:

```bash
# Install via cURL
curl -sSL https://raw.githubusercontent.com/dotnetbdinfosys/NetFusion-Framework/main/install.sh | bash

# Or install via wget
wget -qO- https://raw.githubusercontent.com/dotnetbdinfosys/NetFusion-Framework/main/install.sh | bash
```

---

## 🚀 Manual Installation from GitHub

If you prefer to clone the repository manually:

```bash
# 1. Clone the repository
git clone https://github.com/dotnetbdinfosys/NetFusion-Framework.git /var/www/netfusion

# 2. Enter directory and run the installer
cd /var/www/netfusion
chmod +x install.sh
./install.sh
```

---

## 🔑 DotNetBD License Activation

NetFusion Framework includes native DotNetBD Enterprise License CMS (v5.1.8) validation. You can activate your license via CLI or Web GUI:

### 1. CLI Activation:
```bash
php netfusion license:activate <LICENSE_KEY> "<COMPANY_NAME>"
```

### 2. Web GUI Portal:
Visit `http://your-domain.com/license` in your web browser, enter your **License Key** and **Company Name**, and click **Activate License Now**.

---

## 📋 System Requirements

- **PHP**: `>= 8.2.0` (CLI & FPM)
- **Web Server**: Nginx (Recommended) or Apache 2.4+
- **Extensions**: `pdo`, `mbstring`, `openssl`, `curl`, `json`, `filter`, `ctype`, `bcmath`
- **Database**: SQLite 3, MySQL 8.0+, MariaDB 10.6+, or PostgreSQL 14+

---

## 📖 Documentation & Guides

- [Full Installation Guide (বাংলা)](doc/INSTALLATION.md)
- [Security Policy](SECURITY.md)
- [Contributing Guidelines](CONTRIBUTING.md)

---

## 🏢 Author & Enterprise Support

- **Author**: Md. Atiquzzaman Ashish
- **Company**: [DotNetBD](https://www.dotnetbd.com)
- **License**: [MIT License](LICENSE)
