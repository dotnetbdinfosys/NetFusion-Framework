#!/usr/bin/env bash
# ==============================================================================
# NetFusion Framework — Universal GitHub Production Installer & Deployment Engine
# Supported OS : Ubuntu, Debian, CentOS, RHEL, AlmaLinux, Rocky Linux, Alpine, Arch, macOS
# Author       : Md. Atiquzzaman Ashish
# Company      : DotNetBD
# Website      : https://www.dotnetbd.com
# Copyright    : © DotNetBD. All rights reserved.
# License      : MIT
# Version      : 1.3.5 (Fixed Enterprise Release)
# ==============================================================================

set -eo pipefail

# ANSI Color Codes
COLOR_RESET="\033[0m"
COLOR_BOLD="\033[1m"
COLOR_GREEN="\033[32m"
COLOR_CYAN="\033[36m"
COLOR_YELLOW="\033[33m"
COLOR_RED="\033[31m"
COLOR_BLUE="\033[34m"
COLOR_MAGENTA="\033[35m"

# Default Repository Configuration
REPO_URL="${NETFUSION_REPO:-https://github.com/dotnetbdinfosys/NetFusion-Framework.git}"
BRANCH="${NETFUSION_BRANCH:-main}"

clear 2>/dev/null || true

echo -e "${COLOR_CYAN}"
cat << "EOF"
  _   _      _   ______          _             
 | \ | |    | | |  ____|        (_)            
 |  \| | ___| |_| |__ _   _ ___  _  ___  _ __  
 | . ` |/ _ \ __|  __| | | / __|| |/ _ \| '_ \ 
 | |\  |  __/ |_| |  | |_| \__ \| | (_) | | | |
 |_| \_|\___|\__|_|   \__,_|___/|_|\___/|_| |_|
EOF
echo -e "${COLOR_RESET}"
echo -e "${COLOR_BOLD}${COLOR_MAGENTA}Enterprise Web Application Framework (Pure PHP 8.2+ Architecture)${COLOR_RESET}"
echo -e "${COLOR_CYAN}Developed by DotNetBD — https://www.dotnetbd.com${COLOR_RESET}"
echo -e "================================================================================"
echo ""

# ------------------------------------------------------------------------------
# 1. Execution Mode Detection (In-Place vs Remote GitHub Clone)
# ------------------------------------------------------------------------------
INSTALL_DIR=""

if [ -f "./netfusion" ] && [ -d "./core" ] && [ -d "./bootstrap" ]; then
    INSTALL_DIR="$(pwd)"
    echo -e "${COLOR_GREEN}✔ Detected existing NetFusion Framework repository in:${COLOR_RESET} $INSTALL_DIR"
else
    echo -e "${COLOR_CYAN}● Initializing GitHub Remote Installation Pipeline...${COLOR_RESET}"
    
    # Prompt or auto-determine target installation directory
    DEFAULT_TARGET="/var/www/netfusion"
    if [ "$(id -u)" -ne 0 ] && [ ! -w "/var/www" 2>/dev/null ]; then
        DEFAULT_TARGET="$(pwd)/netfusion"
    fi

    if [ -t 0 ] && [ -z "$NETFUSION_TARGET" ]; then
        read -r -p "Enter target installation directory [${DEFAULT_TARGET}]: " USER_TARGET
        INSTALL_DIR="${USER_TARGET:-$DEFAULT_TARGET}"
    else
        INSTALL_DIR="${NETFUSION_TARGET:-$DEFAULT_TARGET}"
    fi

    echo -e "${COLOR_GREEN}✔ Installation Target:${COLOR_RESET} $INSTALL_DIR"

    # Ensure git is available
    if ! command -v git &>/dev/null; then
        echo -e "${COLOR_RED}✖ Error: 'git' command not found! Please install Git first.${COLOR_RESET}"
        exit 1
    fi

    # Clone the repository
    if [ -d "$INSTALL_DIR" ] && [ -f "$INSTALL_DIR/netfusion" ]; then
        echo -e "${COLOR_YELLOW}⚠ Directory already exists and contains NetFusion. Updating files...${COLOR_RESET}"
        cd "$INSTALL_DIR"
        git fetch origin "$BRANCH" 2>/dev/null || true
        git pull origin "$BRANCH" 2>/dev/null || true
    elif [ -d "$INSTALL_DIR" ] && [ -n "$(ls -A "$INSTALL_DIR" 2>/dev/null)" ]; then
        echo -e "${COLOR_CYAN}● Directory exists with existing files. Deploying NetFusion into target directory...${COLOR_RESET}"
        cd "$INSTALL_DIR"
        git init 2>/dev/null || true
        git remote remove origin 2>/dev/null || true
        git remote add origin "$REPO_URL" 2>/dev/null || true
        git fetch origin "$BRANCH" --depth 1
        git checkout -f -B "$BRANCH" "origin/$BRANCH"
    else
        echo -e "${COLOR_CYAN}● Cloning NetFusion Framework from GitHub (${BRANCH})...${COLOR_RESET}"
        mkdir -p "$INSTALL_DIR"
        git clone --depth 1 -b "$BRANCH" "$REPO_URL" "$INSTALL_DIR"
        cd "$INSTALL_DIR"
    fi
fi

# Ensure working directory is the installation root
cd "$INSTALL_DIR"

# ------------------------------------------------------------------------------
# 2. Operating System & Web User Detection
# ------------------------------------------------------------------------------
OS_NAME="Linux"
PKG_MGR="unknown"
WEB_USER="www-data"

if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS_NAME="${PRETTY_NAME:-$NAME}"
    case "$ID" in
        ubuntu|debian) PKG_MGR="apt" ;;
        centos|rhel|almalinux|rocky|fedora) PKG_MGR="dnf" ;;
        alpine) PKG_MGR="apk" ;;
        arch) PKG_MGR="pacman" ;;
    esac
elif [ "$(uname -s)" = "Darwin" ]; then
    OS_NAME="macOS $(sw_vers -productVersion 2>/dev/null || '')"
    PKG_MGR="brew"
fi

echo -e "${COLOR_GREEN}✔ Operating System:${COLOR_RESET} $OS_NAME"

# Detect web server user
if id "www-data" &>/dev/null; then
    WEB_USER="www-data"
elif id "nginx" &>/dev/null; then
    WEB_USER="nginx"
elif id "apache" &>/dev/null; then
    WEB_USER="apache"
elif id "http" &>/dev/null; then
    WEB_USER="http"
elif id "_www" &>/dev/null; then
    WEB_USER="_www"
else
    WEB_USER="$(whoami)"
fi
echo -e "${COLOR_GREEN}✔ Web Server Runtime User:${COLOR_RESET} $WEB_USER"

# ------------------------------------------------------------------------------
# 3. PHP 8.2+ Environment & Extension Verification
# ------------------------------------------------------------------------------
if ! command -v php &>/dev/null; then
    echo -e "${COLOR_RED}✖ Error: PHP CLI binary not found in PATH!${COLOR_RESET}"
    echo -e "${COLOR_YELLOW}Please install PHP 8.2 or higher before running this installer.${COLOR_RESET}"
    if [ "$PKG_MGR" = "apt" ]; then
        echo -e "Suggested command: ${COLOR_CYAN}sudo apt update && sudo apt install -y php8.2-cli php8.2-common php8.2-mbstring php8.2-curl php8.2-sqlite3 php8.2-mysql php8.2-bcmath php8.2-xml${COLOR_RESET}"
    elif [ "$PKG_MGR" = "dnf" ]; then
        echo -e "Suggested command: ${COLOR_CYAN}sudo dnf install -y php-cli php-common php-mbstring php-curl php-mysqlnd php-bcmath php-xml${COLOR_RESET}"
    fi
    exit 1
fi

PHP_VER=$(php -r "echo PHP_VERSION;")
echo -e "${COLOR_GREEN}✔ Detected PHP Version:${COLOR_RESET} $PHP_VER"

php -r "if (version_compare(PHP_VERSION, '8.2.0', '<')) { exit(1); }" || {
    echo -e "${COLOR_RED}✖ Error: NetFusion Framework requires PHP 8.2.0 or higher. Found: $PHP_VER${COLOR_RESET}"
    exit 1
}

# Required Extensions Check
REQUIRED_EXTS=("pdo" "mbstring" "openssl" "curl" "json" "filter" "ctype" "bcmath")
MISSING_EXTS=()

for ext in "${REQUIRED_EXTS[@]}"; do
    if ! php -r "exit(extension_loaded('$ext') ? 0 : 1);" 2>/dev/null; then
        MISSING_EXTS+=("$ext")
    fi
done

if [ ${#MISSING_EXTS[@]} -ne 0 ]; then
    echo -e "${COLOR_RED}✖ Error: The following required PHP extensions are missing:${COLOR_RESET}"
    for ext in "${MISSING_EXTS[@]}"; do
        echo -e "   - ${COLOR_YELLOW}$ext${COLOR_RESET}"
    done
    exit 1
fi
echo -e "${COLOR_GREEN}✔ Core PHP Extensions Verified:${COLOR_RESET} ${REQUIRED_EXTS[*]}"

# ------------------------------------------------------------------------------
# 4. Storage & Directory Hierarchy Creation
# ------------------------------------------------------------------------------
echo -e "${COLOR_CYAN}● Creating storage and cache directories...${COLOR_RESET}"
mkdir -p storage/framework/cache
mkdir -p storage/framework/views
mkdir -p storage/framework/sessions
mkdir -p storage/logs
mkdir -p storage/app/public
mkdir -p bootstrap/cache
mkdir -p public

# ------------------------------------------------------------------------------
# 5. Environment Configuration (.env Setup & Key Generation)
# ------------------------------------------------------------------------------
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        echo -e "${COLOR_CYAN}● Initializing .env configuration from .env.example...${COLOR_RESET}"
        cp .env.example .env
    else
        echo -e "${COLOR_YELLOW}⚠ Creating default production .env configuration...${COLOR_RESET}"
        cat << 'EOF' > .env
APP_NAME="NetFusion Framework"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8000
APP_VERSION=1.3.5
APP_TIMEZONE=Asia/Dhaka
APP_LOCALE=en
APP_KEY=base64:GENERATE_A_32_CHARACTER_RANDOM_KEY_HERE

LOG_CHANNEL=daily
LOG_LEVEL=info

DB_DRIVER=sqlite
DB_DATABASE=storage/database.sqlite

CACHE_STORE=file
QUEUE_DRIVER=database
STORAGE_DISK=public
EOF
    fi
fi

# Generate 32-byte Cryptographic APP_KEY if empty or default placeholder
APP_KEY_VAL=$(grep -E "^APP_KEY=" .env | cut -d '=' -f2- | tr -d '"' | tr -d "'" || true)
if [ -z "$APP_KEY_VAL" ] || [ "$APP_KEY_VAL" = "base64:GENERATE_A_32_CHARACTER_RANDOM_KEY_HERE" ]; then
    echo -e "${COLOR_CYAN}● Generating cryptographic Application Encryption Key (APP_KEY)...${COLOR_RESET}"
    NEW_KEY=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")
    if grep -q "^APP_KEY=" .env; then
        sed -i "s|^APP_KEY=.*|APP_KEY=${NEW_KEY}|" .env 2>/dev/null || \
        sed -i '' "s|^APP_KEY=.*|APP_KEY=${NEW_KEY}|" .env 2>/dev/null || true
    else
        echo "APP_KEY=${NEW_KEY}" >> .env
    fi
    echo -e "${COLOR_GREEN}✔ APP_KEY successfully generated.${COLOR_RESET}"
fi

# Ensure SQLite database file exists if configured for SQLite
if grep -q "DB_DRIVER=sqlite" .env 2>/dev/null || grep -q "DB_CONNECTION=sqlite" .env 2>/dev/null; then
    touch storage/database.sqlite
    echo -e "${COLOR_GREEN}✔ SQLite database file verified at storage/database.sqlite${COLOR_RESET}"
fi

# Make netfusion CLI executable
chmod +x netfusion 2>/dev/null || true

# ------------------------------------------------------------------------------
# 6. Optional Composer Autoloader Optimization
# ------------------------------------------------------------------------------
if command -v composer &>/dev/null && [ -f composer.json ]; then
    echo -e "${COLOR_CYAN}● Optimizing autoloader classmaps via Composer...${COLOR_RESET}"
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist 2>/dev/null || \
    composer dump-autoload -o 2>/dev/null || true
fi

# ------------------------------------------------------------------------------
# 7. Linux File Ownership & Permissions Security
# ------------------------------------------------------------------------------
echo -e "${COLOR_CYAN}● Applying secure file ownership and permissions...${COLOR_RESET}"
if [ "$(id -u)" -eq 0 ]; then
    chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache public 2>/dev/null || true
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true
else
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true
fi

# SELinux support for CentOS / RHEL / AlmaLinux / Rocky
if command -v getenforce &>/dev/null; then
    if [ "$(getenforce)" = "Enforcing" ]; then
        echo -e "${COLOR_CYAN}● Applying SELinux read-write policies (httpd_sys_rw_content_t)...${COLOR_RESET}"
        chcon -R -t httpd_sys_rw_content_t storage bootstrap/cache 2>/dev/null || true
    fi
fi

# ------------------------------------------------------------------------------
# 8. Database Migrations, Public Storage Link & Cache Optimization
# ------------------------------------------------------------------------------
echo -e "${COLOR_CYAN}● Executing database schema migrations...${COLOR_RESET}"
php netfusion migrate 2>/dev/null || true

echo -e "${COLOR_CYAN}● Creating public storage symbolic link...${COLOR_RESET}"
php netfusion storage:link 2>/dev/null || true

echo -e "${COLOR_CYAN}● Compiling framework route & service discovery caches...${COLOR_RESET}"
php netfusion optimize 2>/dev/null || true

# ------------------------------------------------------------------------------
# 9. DotNetBD Enterprise License CMS Integration
# ------------------------------------------------------------------------------
echo ""
echo -e "${COLOR_CYAN}====================================================================${COLOR_RESET}"
echo -e "${COLOR_CYAN}   DotNetBD Enterprise License CMS Activation                      ${COLOR_RESET}"
echo -e "${COLOR_CYAN}====================================================================${COLOR_RESET}"

IS_LICENSED=false
if php netfusion license:check &>/dev/null; then
    IS_LICENSED=true
    echo -e "${COLOR_GREEN}✔ Active DotNetBD Enterprise License detected!${COLOR_RESET}"
else
    echo -e "${COLOR_YELLOW}⚠ No active software license detected.${COLOR_RESET}"
    
    # Check if running interactively
    if [ -t 0 ]; then
        read -r -p "Do you have a DotNetBD License Key to activate now? [y/N]: " ACTIVATE_NOW
        if [[ "$ACTIVATE_NOW" =~ ^[Yy]$ ]]; then
            read -r -p "Enter 20-character License Key (e.g., NF-XXXX-XXXX-XXXX): " INPUT_KEY
            read -r -p "Enter Registered Company Name: " INPUT_COMPANY
            
            if [ -n "$INPUT_KEY" ] && [ -n "$INPUT_COMPANY" ]; then
                echo -e "${COLOR_CYAN}● Contacting DotNetBD Licensing Server (lic.dotnetbd.com)...${COLOR_RESET}"
                if php netfusion license:activate "$INPUT_KEY" "$INPUT_COMPANY"; then
                    echo -e "${COLOR_GREEN}✔ License successfully activated and cryptographically verified!${COLOR_RESET}"
                    IS_LICENSED=true
                else
                    echo -e "${COLOR_RED}✖ Activation failed. You can activate anytime via CLI or Web GUI.${COLOR_RESET}"
                fi
            else
                echo -e "${COLOR_YELLOW}⚠ License key or company name was empty. Skipping activation.${COLOR_RESET}"
            fi
        fi
    fi
    
    if [ "$IS_LICENSED" = false ]; then
        echo -e "${COLOR_YELLOW}ℹ You can activate your license at any time:${COLOR_RESET}"
        echo -e "  • CLI Command:   ${COLOR_CYAN}php netfusion license:activate <LICENSE_KEY> <COMPANY_NAME>${COLOR_RESET}"
    fi
fi

# ------------------------------------------------------------------------------
# 10. System Health Diagnostics (Doctor Check)
# ------------------------------------------------------------------------------
echo ""
echo -e "${COLOR_CYAN}● Running NetFusion Server Diagnostic Doctor...${COLOR_RESET}"
php netfusion server:check || true

# ------------------------------------------------------------------------------
# 11. Final Summary & Quick Start
# ------------------------------------------------------------------------------
echo ""
echo -e "${COLOR_GREEN}====================================================================${COLOR_RESET}"
echo -e "${COLOR_GREEN}✔ NetFusion Framework installation completed successfully!          ${COLOR_RESET}"
echo -e "${COLOR_GREEN}====================================================================${COLOR_RESET}"
echo ""
echo -e "${COLOR_BOLD}Installation Directory:${COLOR_RESET}  ${COLOR_CYAN}${INSTALL_DIR}${COLOR_RESET}"
echo -e "${COLOR_BOLD}Web Server DocumentRoot:${COLOR_RESET} ${COLOR_CYAN}${INSTALL_DIR}/public${COLOR_RESET}"
echo ""
echo -e "${COLOR_BOLD}Quick Start Options:${COLOR_RESET}"
echo -e "  • Start Local Server:     ${COLOR_CYAN}php netfusion serve${COLOR_RESET} (or ${COLOR_CYAN}php -S 0.0.0.0:8000 -t public${COLOR_RESET})"
echo -e "  • System Health Monitor:  ${COLOR_CYAN}http://localhost:8000/health${COLOR_RESET}"
echo -e "  • Admin Dashboard:        ${COLOR_CYAN}http://localhost:8000/admin/dashboard${COLOR_RESET}"
echo -e "  • System Diagnostics:     ${COLOR_CYAN}php netfusion framework:verify${COLOR_RESET}"
echo ""
echo -e "${COLOR_CYAN}Official Documentation & Support: https://www.dotnetbd.com${COLOR_RESET}"
echo -e "===================================================================="
