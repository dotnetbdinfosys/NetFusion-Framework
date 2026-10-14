#!/usr/bin/env bash
# ==============================================================================
# NetFusion Framework — Universal Linux Production Deployment & Setup Script
# Supported OS : Ubuntu, Debian, CentOS, RHEL, AlmaLinux, Rocky Linux, Alpine
# Author       : Md. Atiquzzaman Ashish
# Company      : DotNetBD
# Website      : www.dotnetbd.com
# Copyright    : © DotNetBD. All rights reserved.
# License      : MIT
# Version      : 1.0.0
# ==============================================================================

set -e

COLOR_GREEN="\033[32m"
COLOR_CYAN="\033[36m"
COLOR_YELLOW="\033[33m"
COLOR_RED="\033[31m"
COLOR_RESET="\033[0m"

echo -e "${COLOR_CYAN}====================================================================${COLOR_RESET}"
echo -e "${COLOR_CYAN}   NetFusion Framework — Universal Linux Server Deployment Engine  ${COLOR_RESET}"
echo -e "${COLOR_CYAN}====================================================================${COLOR_RESET}"
echo ""

# 1. Detect OS and Package Manager
OS_TYPE="Unknown"
WEB_USER="www-data"

if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS_TYPE=$NAME
elif [ -f /etc/redhat-release ]; then
    OS_TYPE="RedHat/CentOS"
elif [ -f /etc/debian_version ]; then
    OS_TYPE="Debian"
fi

echo -e "${COLOR_GREEN}✔ Detected Operating System:${COLOR_RESET} $OS_TYPE"

# Detect Web Server User
if id "www-data" &>/dev/null; then
    WEB_USER="www-data"
elif id "nginx" &>/dev/null; then
    WEB_USER="nginx"
elif id "apache" &>/dev/null; then
    WEB_USER="apache"
fi
echo -e "${COLOR_GREEN}✔ Web Server User:${COLOR_RESET} $WEB_USER"

# 2. Check PHP 8.2+ Binary
if ! command -v php &>/dev/null; then
    echo -e "${COLOR_RED}✖ Error: PHP binary not found! Please install PHP 8.2 or higher.${COLOR_RESET}"
    exit 1
fi

PHP_VER=$(php -r "echo PHP_VERSION;")
echo -e "${COLOR_GREEN}✔ Detected PHP Version:${COLOR_RESET} $PHP_VER"

php -r "if (version_compare(PHP_VERSION, '8.2.0', '<')) { exit(1); }" || {
    echo -e "${COLOR_RED}✖ Error: NetFusion requires PHP 8.2.0 or higher. Found: $PHP_VER${COLOR_RESET}"
    exit 1
}

# 3. Create Required Directory Hierarchy
echo -e "${COLOR_CYAN}● Preparing application directories...${COLOR_RESET}"
mkdir -p storage/framework/cache
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p storage/app/public
mkdir -p bootstrap/cache
mkdir -p public

# 4. Environment Configuration
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        echo -e "${COLOR_YELLOW}⚠ .env not found. Copying from .env.example...${COLOR_RESET}"
        cp .env.example .env
    else
        echo -e "${COLOR_YELLOW}⚠ Creating standard production .env...${COLOR_RESET}"
        cat << 'EOF' > .env
APP_NAME=NetFusion
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=sqlite
DB_DATABASE=storage/database.sqlite

CACHE_STORE=file
QUEUE_DRIVER=database
STORAGE_DISK=public
EOF
    fi
fi

# 5. Install / Update Composer Dependencies if composer.phar or composer is present
if command -v composer &>/dev/null; then
    echo -e "${COLOR_CYAN}● Optimizing composer autoload classmap...${COLOR_RESET}"
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist || true
fi

# 6. Apply Linux Directory Permissions
echo -e "${COLOR_CYAN}● Setting secure ownership and write permissions...${COLOR_RESET}"
if [ "$(id -u)" -eq 0 ]; then
    chown -R $WEB_USER:$WEB_USER storage public/storage 2>/dev/null || true
    chmod -R 775 storage
else
    chmod -R 775 storage 2>/dev/null || true
fi

# SELinux support for CentOS / RHEL / AlmaLinux
if command -v getenforce &>/dev/null; then
    if [ "$(getenforce)" = "Enforcing" ]; then
        echo -e "${COLOR_CYAN}● Applying SELinux read-write context for storage...${COLOR_RESET}"
        chcon -R -t httpd_sys_rw_content_t storage 2>/dev/null || true
    fi
fi

# 7. Run Migrations & Storage Link
echo -e "${COLOR_CYAN}● Running database migrations...${COLOR_RESET}"
php netfusion migrate || true

echo -e "${COLOR_CYAN}● Creating public storage symbolic link...${COLOR_RESET}"
php netfusion storage:link || true

echo -e "${COLOR_CYAN}● Running framework optimization...${COLOR_RESET}"
php netfusion optimize || true

# 8. Run Diagnostics Doctor
echo ""
php netfusion server:check

# 9. DotNetBD License CMS Status
echo ""
echo -e "${COLOR_CYAN}● DotNetBD License Status:${COLOR_RESET}"
php netfusion license:check || {
    echo -e "${COLOR_YELLOW}ℹ Note: To activate your software license, execute:${COLOR_RESET}"
    echo -e "  ${COLOR_CYAN}php netfusion license:activate <LICENSE_KEY> <COMPANY_NAME>${COLOR_RESET}"
}

echo ""
echo -e "${COLOR_GREEN}====================================================================${COLOR_RESET}"
echo -e "${COLOR_GREEN}✔ NetFusion Framework deployment completed successfully on $OS_TYPE!${COLOR_RESET}"
echo -e "${COLOR_GREEN}====================================================================${COLOR_RESET}"
