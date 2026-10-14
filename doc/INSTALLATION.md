<!--
|--------------------------------------------------------------------------
| NetFusion Framework Installation Guide
|--------------------------------------------------------------------------
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
-->

# NetFusion Framework — সার্বজনীন ইনস্টলেশন গাইড (Universal Installation Guide)

এই গাইডে **NetFusion Framework**-কে বিভিন্ন অপারেটিং সিস্টেম (Ubuntu, Debian, CentOS, RedHat Enterprise Linux, AlmaLinux, Rocky Linux, Alpine, Windows) এবং ডকার কনটেইনারে সফলভাবে ইনস্টল ও সেটআপ করার সম্পূর্ণ ধাপসমূহ আলোচনা করা হয়েছে।

---

## ১. সিস্টেমের পূর্বশর্ত (System Requirements)

- **PHP**: `>= 8.2.0` (PHP 8.2 বা 8.3 প্রস্তাবিত)
- **ওয়েব সার্ভার**: Nginx (প্রস্তাবিত) অথবা Apache 2.4+
- **ডাটাবেস**: MySQL 8.0+ / MariaDB 10.6+ / PostgreSQL 14+ / SQLite 3
- **আবশ্যক পিএইচপি এক্সটেনশনসমূহ (Required Extensions)**:
  - `pdo` ও ডাটাবেস ড্রাইভার (`pdo_mysql`, `pdo_pgsql`, বা `pdo_sqlite`)
  - `openssl` (টোকেন ও এনক্রিপশনের জন্য)
  - `mbstring` (মাল্টি-বাইট স্ট্রিং হ্যান্ডলিং)
  - `curl` (ওয়েবহুক ও রিমোট রিকোয়েস্ট)
  - `json` (API সিরিয়ালাইজেশন)
  - `ctype` (টাইপ ভ্যালিডেশন)
  - `filter` (ডাটা স্যানিটাইজেশন)
  - `bcmath` (ডাবল-এন্ট্রি একাউন্টিং লেজার ক্যালকুলেশন)
  - `tokenizer` (অটোলোডিং ও রিফ্লেকশন)
- **প্রস্তাবিত এক্সটেনশনসমূহ (Recommended Extensions)**:
  - `opcache` (JIT পারফরম্যান্সের জন্য)
  - `gd` (ইমেজ ও ডকুমেন্ট হ্যান্ডলিং)
  - `zip` (প্যাকেজ এক্সট্রাকশন)
  - `redis` (হাই-স্পিড ক্যাশ ও কিউ ড্রাইভারে ব্যবহারের জন্য)
  - `pcntl` (ব্যাকগ্রাউন্ড কিউ য়ার্কার সিগন্যাল হ্যান্ডলিং - লিনাক্স/ইউনিক্স)

---

## ২. সার্বজনীন এক-ক্লিক অটো-ইনস্টলার (Universal One-Click Installer)

### ক. GitHub ওয়ান-লাইনার ইনস্টলেশন (Curl / Wget দিয়ে সরাসরি ইনস্টল):
সরাসরি যেকোনো লিনাক্স সার্ভারে টার্মিনালে নিচের এক লাইনের কমান্ড রান করে সম্পূর্ণ ফ্রেমওয়ার্ক ক্লোন ও ইনস্টল করা যাবে:

```bash
# curl দিয়ে এক ক্লিকে ইনস্টল করুন
curl -sSL https://raw.githubusercontent.com/dotnetbdinfosys/NetFusion-Framework/main/install.sh | bash

# অথবা wget ব্যবহার করতে চাইলে
wget -qO- https://raw.githubusercontent.com/dotnetbdinfosys/NetFusion-Framework/main/install.sh | bash
```

### খ. GitHub Clone করার পর ইনস্টলেশন:
যদি রিপোজিটরিটি ম্যানুয়ালি গিট ক্লোন করা হয়ে থাকে:

```bash
git clone https://github.com/dotnetbdinfosys/NetFusion-Framework.git /var/www/netfusion
cd /var/www/netfusion
chmod +x install.sh
./install.sh
```

**এই স্ক্রিপ্টটি স্বয়ংক্রিয়ভাবে যা করে:**
1. অপারেটিং সিস্টেম (Ubuntu, Debian, CentOS, RHEL, Alma, Rocky, Alpine) শনাক্ত করে।
2. সার্ভারের উপযুক্ত ওয়েব ইউজার (`www-data`, `nginx`, `apache`) নির্বাচন করে।
3. `.env.example` থেকে `.env` ফাইল তৈরি করে এবং সিক্রেট অ্যাপ কি (`APP_KEY`) জেনারেট করে।
4. `storage/` এবং `bootstrap/cache/` ডিরেক্টরি পারমিশন `775` ও সঠিক ওনারশিপ সেট করে।
5. RHEL / CentOS সিস্টেমে SELinux রুলস (`httpd_sys_rw_content_t`) কার্যকর করে।
6. পাবলিক স্টোরেজ সিমলিংক তৈরি করে (`php netfusion storage:link`)।
7. ডাটাবেস মাইগ্রেশন সম্পন্ন করে (`php netfusion migrate`)।
8. ফ্রেমওয়ার্ক ডায়াগনস্টিকস (`php netfusion server:check`) চালিয়ে ইনস্টলেশন যাচাই করে।

---

## ৩. ম্যানুয়াল ওএস ভিত্তিক ইনস্টলেশন গাইড (OS-Specific Installation)

### ক. Ubuntu / Debian

```bash
# ১. সিস্টেম আপডেট ও পিএইচপি ডিপেন্ডেন্সি ইনস্টল
sudo apt update && sudo apt install -y \
    php8.2-cli php8.2-fpm php8.2-mysql php8.2-sqlite3 php8.2-pgsql \
    php8.2-mbstring php8.2-openssl php8.2-curl php8.2-bcmath \
    php8.2-gd php8.2-zip php8.2-redis composer git

# ২. রিপোজিটরি ক্লোন করুন
cd /var/www
sudo git clone <repo_url> netfusion
cd netfusion

# ৩. Composer ডিপেন্ডেন্সি ও ক্লাস ম্যাপ অপ্টিমাইজেশন
composer install --no-dev --optimize-autoloader

# ৪. এনভায়রনমেন্ট কনফিগারেশন
cp .env.example .env
nano .env   # ডাটাবেস ক্রেডেনশিয়াল সেট করুন

# ৫. ডিরেক্টরি পারমিশন কনফিগারেশন
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# ৬. সিমলিংক ও মাইগ্রেশন চালান
php netfusion storage:link
php netfusion migrate
php netfusion server:check

# ৭. DotNetBD লাইসেন্স কী সক্রিয়করণ (License Activation)
php netfusion license:activate <LICENSE_KEY> "<COMPANY_NAME>"
```

---

### খ. CentOS / RHEL / AlmaLinux / Rocky Linux

```bash
# ১. EPEL এবং Remi রিপোজিটরি যুক্ত করুন
sudo dnf install -y epel-release
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf module reset php -y
sudo dnf module enable php:remi-8.2 -y

# ২. পিএইচপি ও মডিউলসমূহ ইনস্টল করুন
sudo dnf install -y \
    php-cli php-fpm php-mysqlnd php-pgsql php-sqlite3 \
    php-mbstring php-openssl php-curl php-bcmath \
    php-gd php-pecl-zip php-pecl-redis composer git

# ৩. প্রজেক্ট ফাইল স্থাপন
cd /var/www
sudo git clone <repo_url> netfusion
cd netfusion
composer install --no-dev --optimize-autoloader

# ৪. ফাইল ওনারশিপ (Nginx বা Apache ইউজারের জন্য)
sudo chown -R nginx:nginx storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# ৫. SELinux কনটেক্সট আপডেট (অত্যন্ত গুরুত্বপূর্ণ)
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/netfusion/storage(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/netfusion/bootstrap/cache(/.*)?"
sudo restorecon -Rv /var/www/netfusion/storage
sudo restorecon -Rv /var/www/netfusion/bootstrap/cache

# নেটওয়ার্ক কানেক্টিভিটি পারমিশন (ডাটাবেস ও আউটবাউন্ড ওয়েবহুকের জন্য)
sudo setsebool -P httpd_can_network_connect 1
sudo setsebool -P httpd_can_network_connect_db 1

# ৬. সেটআপ সম্পন্ন করুন
php netfusion storage:link
php netfusion migrate
php netfusion server:check
```

---

### গ. Alpine Linux

```bash
# ১. Alpine প্যাকেজ ম্যানেজার (apk) দিয়ে ডিপেন্ডেন্সি ইনস্টল
apk update && apk add --no-cache \
    php82 php82-fpm php82-pdo php82-pdo_mysql php82-pdo_pgsql php82-pdo_sqlite \
    php82-mbstring php82-openssl php82-curl php82-bcmath php82-gd \
    php82-zip php82-redis composer git bash

# ২. প্রজেক্ট সেটআপ
cd /var/www/netfusion
composer install --no-dev --optimize-autoloader
cp .env.example .env
chown -R nginx:nginx storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php netfusion storage:link
php netfusion migrate
php netfusion server:check
```

---

### ঘ. Windows Local Development (Laragon / XAMPP / Native PHP)

1. নিশ্চিত করুন আপনার সিস্টেমে PHP 8.2+ ইনস্টল করা আছে এবং সিস্টেম `PATH`-এ যুক্ত রয়েছে।
2. `php.ini` ফাইলে এক্সটেনশনগুলো আনকমেন্ট করুন:
   ```ini
   extension=pdo_mysql
   extension=pdo_sqlite
   extension=mbstring
   extension=openssl
   extension=curl
   extension=bcmath
   extension=gd
   extension=fileinfo
   ```
3. কমান্ড প্রম্পট বা পাওয়ারশেল ওপেন করে প্রজেক্ট ডিরেক্টরিতে যান:
   ```powershell
   composer install --optimize-autoloader
   copy .env.example .env
   php netfusion storage:link
   php netfusion migrate
   php netfusion server:check
   ```
4. লোকাল বিল্ট-ইন সার্ভার চালু করুন:
   ```powershell
   php -S 127.0.0.1:8000 -t public
   ```

---

## ৪. ডকার কনটেইনারের মাধ্যমে ডেপ্লয়মেন্ট (Docker & Docker Compose)

NetFusion-এ প্রোডাকশন-রেডি মাল্টি-সার্ভিস ডকার কনফিগারেশন প্রস্তুত রয়েছে:

```bash
# ১. কনটেইনার বিল্ড ও ব্যাকগ্রাউন্ডে রান করুন
docker-compose up -d --build

# ২. চালু থাকা সার্ভিসগুলো যাচাই করুন
docker-compose ps

# ৩. অ্যাপ কনটেইনারে মাইগ্রেশন ও হেলথ চেক রান করুন
docker-compose exec app php netfusion migrate
docker-compose exec app php netfusion server:check
docker-compose exec app php netfusion optimize
```

**ডকার সেবা ক্লাস্টার:**
- `app`: PHP 8.2 FPM Alpine (JIT OPcache সহ)
- `web`: High Performance Nginx Reverse Proxy (Port 80/443)
- `db`: MariaDB 11 Enterprise (Port 3306)
- `redis`: In-Memory Redis 7 Engine (Port 6379)
- `queue`: ব্যাকগ্রাউন্ড কিউ য়ার্কার ডেমন
- `scheduler`: ১ মিনিট পর্যায়ক্রমিক ক্রন ডেমন

---

## ৫. সার্ভার ভ্যালিডেশন ও প্রোডাকশন অপ্টিমাইজেশন

ইনস্টলেশন শেষে ফ্রেমওয়ার্কের দুটি মাস্টার কমান্ড রান করা বাঞ্ছনীয়:

```bash
# ক. সার্ভারের সকল প্যারামিটার ও ড্রাইভারের পূর্ণাঙ্গ ডায়াগনস্টিকস
php netfusion server:check

# খ. প্রোডাকশন পারফরম্যান্স ক্যাশিং সক্রিয়করণ
php netfusion optimize
```
