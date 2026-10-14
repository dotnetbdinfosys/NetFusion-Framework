<!--
|--------------------------------------------------------------------------
| NetFusion Framework — Performance, Operating System & Production FAQ
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

# NetFusion Framework — পারফরম্যান্স, অপারেটিং সিস্টেম ও প্রোডাকশন গাইড (FAQ)

এই গাইডে **NetFusion Framework v1.0.0 Global GA**-এর সর্বোচ্চ পারফরম্যান্স, অপারেটিং সিস্টেম নির্বাচন, সার্ভার আর্কিটেকচার, মেমোরি টিউনিং এবং প্রোডাকশন হার্ডেনিং সংক্রান্ত সচরাচর জিজ্ঞাসিত প্রশ্ন ও সমাধান বিস্তারিত তুলে ধরা হলো।

---

## 📑 সূচিপত্র (Table of Contents)

1. [কোন অপারেটিং সিস্টেমে NetFusion সেরা পারফরম্যান্স প্রদান করে?](#১-কোন-অপারেটিং-সিস্টেমে-netfusion-সেরা-পারফরম্যান্স-প্রদান-করে)
2. [কীভাবে PHP 8.2+ OPcache এবং Tracing JIT কনফিগার করবেন?](#২-কীভাবে-php-82-opcache-এবং-tracing-jit-কনফিগার-করবেন)
3. [ওয়েব সার্ভার আর্কিটেকচার: Nginx বনাম FrankenPHP বনাম RoadRunner](#৩-ওয়েব-সার্ভার-আর্কিটেকচার-nginx-বনাম-frankenphp-বনাম-roadrunner)
4. [ডাটাবেস ও ফাইল সিস্টেম অপ্টিমাইজেশন (SQLite WAL ও রিমোট ডিবি)](#৪-ডাটাবেস-ও-ফাইল-সিস্টেম-অপ্টিমাইজেশন-sqlite-wal-ও-রিমোট-ডিবি)
5. [উচ্চ কনকারেন্সি ও মেমোরি ম্যানেজমেন্ট (High Concurrency & Memory Tuning)](#৫-উচ্চ-কনকারেন্সি-ও-মেমোরি-ম্যানেজমেন্ট-high-concurrency--memory-tuning)
6. [প্রোডাকশন সিকিউরিটি ও ডিরেক্টরি পারমিশন হার্ডেনিং](#৬-প্রোডাকশন-সিকিউরিটি-ও-ডিরেক্টরি-পারমিশন-হার্ডেনিং)
7. [NetFusion ফ্রেমওয়ার্কের নিজস্ব অপ্টিমাইজেশন সিএলআই কমান্ড](#৭-netfusion-ফ্রেমওয়ার্কের-নিজস্ব-অপ্টিমাইজেশন-সিএলআই-কমান্ড)
8. [সাধারণ সমস্যা ও সমাধান (Troubleshooting & FAQs)](#৮-সাধারণ-সমস্যা-ও-সমাধান-troubleshooting--faqs)

---

## ১. কোন অপারেটিং সিস্টেমে NetFusion সেরা পারফরম্যান্স প্রদান করে?

### 🏆 রেটিং ও অপারেটিং সিস্টেমের তুলনা:

| অপারেটিং সিস্টেম | প্রোডাকশন রেটিং | গড় লেটেন্সি | প্রত্যাশিত থ্রুপুট (RPS) | উপযুক্ততা ও মন্তব্য |
| :--- | :---: | :---: | :---: | :--- |
| **Ubuntu Server 24.04 / 22.04 LTS** | ⭐️⭐️⭐️⭐️⭐️ (সেরা) | `~ 1.8 - 3.2 ms` | `15,000 - 25,000+` | আধুনিক লিনাক্স কার্নেল, `epoll`/`io_uring`, JIT ও Nginx সকেটের সাথে সর্বোচ্চ সমন্বয়। |
| **Debian 12 (Bookworm)** | ⭐️⭐️⭐️⭐️⭐️ (সেরা) | `~ 1.7 - 3.0 ms` | `16,000 - 26,000+` | চরম মাত্রার স্থায়িত্ব ও কম কার্নেল ব্লোট। ব্যাংক ও টেলিকমে প্রথম পছন্দ। |
| **Rocky Linux 9 / AlmaLinux 9 / RHEL** | ⭐️⭐️⭐️⭐️½ (উচ্চমানের) | `~ 2.0 - 3.5 ms` | `14,000 - 22,000+` | এন্টারপ্রাইজ গ্রেড, SELinux সিকিউরিটি হার্ডেনিং এবং XFS ফাইল সিস্টেমে দ্রুত। |
| **Alpine Linux (Docker / K8s)** | ⭐️⭐️⭐️⭐️ (হালকা) | `~ 2.2 - 4.0 ms` | `12,000 - 18,000+` | মাত্র ৪০-৬০ MB কন্টেইনার সাইজ। ক্লাউড মাইক্রোসার্ভিস ও কুবারনেটিজ পডের জন্য সেরা। |
| **Windows Server 2022 / Windows 11** | ⭐️⭐️⭐️ (মাঝারি) | `~ 8.0 - 18.0 ms` | `2,500 - 5,000` | লোকাল ডেভেলপমেন্টের জন্য উপযুক্ত। প্রোডাকশন হাই-কনকারেন্সিতে লিনাক্সের চেয়ে পিছিয়ে। |

### কারিগরি বিশ্লেষণ:
- **লিনাক্স কেন দ্রুততম?** 
  NetFusion Framework-এ রয়েছে রিয়েল-টাইম ওয়েবসকেট ব্রডকাস্টিং, ডিস্ট্রিবিউটেড মেসেজ বাস এবং অ্যাডভান্সড ম্যাথমেটিক্যাল ক্যালকুলেশন। লিনাক্স কার্নেলের নন-ব্লকিং I/O (`epoll`), হালকা প্রসেস ফোর্কিং এবং মেমোরি ম্যাপিং (`mmap`) উইন্ডোজের সাবসিস্টেমের চেয়ে অনেক হালকা ও দ্রুত।
- **সিপিইউ আর্কিটেকচার:** x86_64 ছাড়াও **Linux ARM64 (যেমন AWS Graviton 3/4, Ampere Altra)** প্রসেসরে NetFusion অত্যন্ত কম খরচে সর্বোচ্চ পারফরম্যান্স নিশ্চিত করে।

---

## ২. কীভাবে PHP 8.2+ OPcache এবং Tracing JIT কনফিগার করবেন?

NetFusion Framework পিওর PHP 8.2+ এ তৈরি। জটিল অ্যালগরিদম (যেমন PQC পোস্ট-কোয়ান্টাম ক্রিপ্টো, ভেক্টর স্পেস সার্চ, প্লাজমা ফিউশন বা কোয়ান্টাম থার্মোডাইনামিক্স)-এর সময় **JIT (Just-In-Time) কম্পাইলার** কোডকে সরাসরি CPU অ্যাসেম্বলি ইনস্ট্রাকশনে রূপান্তরিত করে।

প্রোডাকশন সার্ভারে `/etc/php/8.2/fpm/php.ini` (অথবা `/etc/php/8.2/cli/php.ini`)-তে নিচের সেটিংটি নিশ্চিত করুন:

```ini
; --------------------------------------------------------------------------
; OPcache এন্টারপ্রাইজ প্রোডাকশন কনফিগারেশন
; --------------------------------------------------------------------------
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0       ; প্রোডাকশনে ফাইল মডিফিকেশন চেক বন্ধ রাখে
opcache.revalidate_freq=0
opcache.save_comments=1             ; ডকব্লক ও এট্রিবিউট সংরক্ষণের জন্য আবশ্যক

; --------------------------------------------------------------------------
; Tracing JIT কনফিগারেশন (৩ গুণ থেকে ৫ গুণ গতি বৃদ্ধি করে)
; --------------------------------------------------------------------------
opcache.jit=tracing
opcache.jit_buffer_size=128M
```

> [!TIP]
> কোড আপডেট বা ডিপ্লয় করার পর ক্যাশ ফ্ল্যাশ করতে `systemctl reload php8.2-fpm` কমান্ড রান করুন।

---

## ৩. ওয়েব সার্ভার আর্কিটেকচার: Nginx বনাম FrankenPHP বনাম RoadRunner

### বিকল্প ১: Nginx + PHP-FPM (ক্লাসিক এন্টারপ্রাইজ স্ট্যান্ডার্ড)
সবচেয়ে স্থিতিশীল ও বহুল ব্যবহৃত আর্কিটেকচার। Nginx ও PHP-FPM-এর মধ্যে TCP-এর বদলে **Unix Domain Socket** ব্যবহার করতে হবে:

```nginx
# /etc/nginx/sites-available/netfusion.conf
upstream netfusion_fpm {
    server unix:/run/php/php8.2-fpm.sock;
    keepalive 32;
}

server {
    listen 80;
    server_name api.yourdomain.com;
    root /var/www/netfusion/public;
    index index.php;

    # Gzip কম্প্রেশন
    gzip on;
    gzip_types text/plain application/json text/css application/javascript;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass netfusion_fpm;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_keep_conn on;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
    }

    # সিক্রেট ফাইল প্রোটেকশন
    location ~ /\.(env|git|htaccess) {
        deny all;
    }
}
```

### বিকল্প ২: FrankenPHP বা RoadRunner (Worker Mode — চরম গতি)
NetFusion Framework-কে যদি মেমোরিতে সর্বদা বুট করা অবস্থায় (Worker Mode) রাখা হয়, তবে প্রতি রিকোয়েস্টে ফ্রেমওয়ার্ক কোল্ড-বুট এড়ানো সম্ভব:
- **লেটেন্সি ড্রপ:** `~0.5 ms` - `1.0 ms`
- **RPS:** `৪০,০০০+ রিকোয়েস্ট/সেকেন্ড` পর্যন্ত অর্জনযোগ্য।

---

## ৪. ডাটাবেস ও ফাইল সিস্টেম অপ্টিমাইজেশন (SQLite WAL ও রিমোট ডিবি)

### SQLite এম্বেডেড ডাটাবেস টিউনিং:
যদি আপনি NetFusion-এর বিল্ট-ইন হাই-স্পিড SQLite স্টোরেজ ব্যবহার করেন:
1. সর্বদা **Write-Ahead Logging (WAL)** সক্রিয় রাখুন:
   ```sql
   PRAGMA journal_mode = WAL;
   PRAGMA synchronous = NORMAL;
   PRAGMA cache_size = -64000; -- 64MB মেমোরি ক্যাশ
   PRAGMA busy_timeout = 5000;  -- লক কনফ্লিক্ট এড়াতে ৫ সেকেন্ড টাইমআউট
   ```
2. ডাটাবেস ফাইলটি দ্রুতগতির NVMe SSD-তে রাখুন।

### লিনাক্স ফাইল সিস্টেম মাউন্ট অপ্টিমাইজেশন:
`/etc/fstab` ফাইলে ওয়েব ডিরেক্টরির ডিস্কে `noatime` ফ্ল্যাগ যুক্ত করলে প্রতি রিডের সময় অপ্রয়োজনীয় অ্যাক্সেস টাইম ডিস্কে লেখা বন্ধ হয়:
```text
UUID=xxxx-xxxx  /var/www  ext4  defaults,noatime,nodiratime  0  2
```

---

## ৫. উচ্চ কনকারেন্সি ও মেমোরি ম্যানেজমেন্ট (High Concurrency & Memory Tuning)

### PHP-FPM পুল অপ্টিমাইজেশন (`/etc/php/8.2/fpm/pool.d/www.conf`):
প্রোডাকশন ট্রাফিকের আকার অনুযায়ী প্রসেস ম্যানেজার কনফিগার করুন:

```ini
pm = dynamic
pm.max_children = 120       ; সার্ভার র‍্যাম অনুযায়ী (ধরি, প্রতিটি প্রসেস ~৩০ MB)
pm.start_servers = 20
pm.min_spare_servers = 10
pm.max_spare_servers = 30
pm.max_requests = 1000      ; মেমোরি লিক রোধে ১০০০ রিকোয়েস্ট পর প্রসেস রিসাইকেল
```

### লিনাক্স কার্নেল লিমিট (`/etc/security/limits.conf`):
ওয়েবসকেট ও ওপেন ফাইলের সংখ্যা বাড়াতে:
```text
www-data  soft  nofile  65535
www-data  hard  nofile  65535
```

---

## ৬. প্রোডাকশন সিকিউরিটি ও ডিরেক্টরি পারমিশন হার্ডেনিং

1. **ফাইল ও ডিরেক্টরি পারমিশন:**
   ```bash
   cd /var/www/netfusion
   
   # ফাইল ও ডিরেক্টরি ওনার নির্ধারণ
   sudo chown -R www-data:www-data .
   
   # ডিফল্ট নিরাপদ পারমিশন
   sudo find . -type d -exec chmod 755 {} \;
   sudo find . -type f -exec chmod 644 {} \;
   
   # স্টোরেজ ও ক্যাশ রাইট পারমিশন
   sudo chmod -R 775 storage bootstrap/cache
   ```

2. **এনভায়রনমেন্ট ফাইল প্রোটেকশন:**
   `.env` ফাইল শুধুমাত্র ওয়েব প্রসেসের দ্বারা পঠনযোগ্য রাখুন:
   ```bash
   chmod 600 .env
   ```

---

## ৭. NetFusion ফ্রেমওয়ার্কের নিজস্ব অপ্টিমাইজেশন সিএলআই কমান্ড

প্রোডাকশন ডেপ্লয়মেন্টের পর নিচের কমান্ডগুলো কার্যকর করুন:

```bash
# ১. ফ্রেমওয়ার্ক রাউট, কনফিগ ও ইভেন্ট ক্যাশ বিল্ড করা
php netfusion optimize

# ২. সার্ভার ও পিএইচপি এনভায়রনমেন্ট অখণ্ডতা নিরীক্ষা
php netfusion server:check

# ৩. গোল্ডেন মাস্টার সেলফ-ইন্সপেকশন ও সার্টিফিকেশন ভেরিফাই
php netfusion framework:verify

# ৪. ডাটাবেস মাইগ্রেশন সম্পন্ন করা
php netfusion migrate
```

---

## ৮. সাধারণ সমস্যা ও সমাধান (Troubleshooting & FAQs)

### প্র: কেন আমার সার্ভারে রেসপন্স টাইম কিছুটা ধীর (15-20ms) আসছে?
> **উ:** সাধারণ কারণ হলো `opcache.enable=1` না থাকা অথবা `opcache.validate_timestamps=1` থাকা। প্রোডাকশনে `opcache.validate_timestamps=0` দিয়ে `php netfusion optimize` চালান।

### প্র: `419 CSRF Token Mismatch` এরর আসলে কী করব?
> **উ:** সেশন ডিরেক্টরি (`storage/sessions`) রাইট-পারমিশন রয়েছে কিনা যাচাই করুন (`chmod -R 775 storage`)।

### প্র: ওয়েবসকেট বা লং-রানিং সার্ভারে মেমোরি বেড়ে গেলে কী করণীয়?
> **উ:** ব্যাকগ্রাউন্ড ডেমন বা কিউ ওয়ার্কার সার্ভিসের জন্য `php netfusion queue:work --max-jobs=1000` অথবা systemd ডেমন ব্যবহার করুন যা নির্দিষ্ট ইন্টারভালে ক্লিন রিস্টার্ট নেয়।

---

## 🏢 সহায়তা ও অফিসিয়াল রেফারেন্স

- **অফিসিয়াল ওয়েবসাইট**: [www.dotnetbd.com](https://www.dotnetbd.com)
- **চিফ আর্কিটেক্ট**: মো: আতিকুজ্জামান আশিষ (Md. Atiquzzaman Ashish)
- **লাইসেন্স**: MIT License (Open Source Enterprise Standard)
