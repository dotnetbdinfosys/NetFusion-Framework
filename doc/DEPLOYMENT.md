<!--
|--------------------------------------------------------------------------
| NetFusion Framework Deployment & Web Server Configuration
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

# NetFusion Framework — প্রোডাকশন ডেপ্লয়মেন্ট ও ওয়েব সার্ভার গাইড

এই ডকুমেন্টে **NetFusion Framework**-কে প্রোডাকশন সার্ভারে Nginx, Apache, Systemd ডেমন এবং ক্রন শিডিউলারের মাধ্যমে অত্যন্ত সুরক্ষিত এবং স্কেলেবল উপায়ে পরিচালনার নির্দেশিকা প্রদান করা হলো।

---

## ১. Nginx ভার্চুয়াল হোস্ট কনফিগারেশন

ফাইল লোকেশন: `/etc/nginx/sites-available/netfusion.conf` (Ubuntu/Debian) অথবা `/etc/nginx/conf.d/netfusion.conf` (CentOS/RHEL)

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name example.com www.example.com;

    # HTTPS রিডাইরেক্ট (উৎপাদন পরিবেশের জন্য)
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name example.com www.example.com;

    # SSL সার্টিফিকেট ও কি
    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # ফ্রেমওয়ার্কের পাবলিক ডিরেক্টরি
    root /var/www/netfusion/public;
    index index.php index.html;

    charset utf-8;
    client_max_body_size 64M;

    # সিকিউরিটি হেডার্স
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # রুট হ্যান্ডলিং ও ফ্রন্ট কন্ট্রোলার রিরাইট
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # স্ট্যাটিক এসেট ক্যাশিং
    location ~* \.(jpg|jpeg|gif|png|css|js|ico|svg|eot|ttf|woff|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
        access_log off;
    }

    # PHP-FPM প্রক্সি হ্যান্ডলিং
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock; # অথবা 127.0.0.1:9000
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    # সংবেদনশীল ফাইল প্রোটেকশন (.env, .git ইত্যাদি)
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

কনফিগারেশন সক্রিয় করুন:
```bash
sudo ln -s /etc/nginx/sites-available/netfusion.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## ২. Apache 2.4 ভার্চুয়াল হোস্ট কনফিগারেশন

ফাইল লোকেশন: `/etc/apache2/sites-available/netfusion.conf` (Ubuntu) অথবা `/etc/httpd/conf.d/netfusion.conf` (CentOS/RHEL)

```apache
<VirtualHost *:80>
    ServerName example.com
    ServerAlias www.example.com
    DocumentRoot /var/www/netfusion/public

    <Directory /var/www/netfusion/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # RewriteEngine রুলস
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^ index.php [L]
        </IfModule>
    </Directory>

    # PHP-FPM প্রক্সি
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.2-fpm.sock|fcgi://localhost/"
    </FilesMatch>

    # সিকিউরিটি হেডার্স
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"

    ErrorLog ${APACHE_LOG_DIR}/netfusion_error.log
    CustomLog ${APACHE_LOG_DIR}/netfusion_access.log combined
</VirtualHost>
```

কনফিগারেশন সক্রিয় করুন:
```bash
sudo a2enmod rewrite headers proxy proxy_fcgi
sudo a2ensite netfusion.conf
sudo apache2ctl configtest
sudo systemctl restart apache2
```

---

## ৩. ব্যাকগ্রাউন্ড কিউ য়ার্কার (Systemd Daemon)

ফাইলটি কপি করুন:
```bash
sudo cp deploy/systemd/netfusion-queue.service /etc/systemd/system/
```

সার্ভিসের ভেতরের কনফিগারেশন (`/etc/systemd/system/netfusion-queue.service`):
```ini
[Unit]
Description=NetFusion Queue Worker
After=network.target mysql.service redis-server.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/netfusion
ExecStart=/usr/bin/php /var/www/netfusion/netfusion queue:work --queue=default --sleep=3 --tries=3 --memory=128
Restart=always
RestartSec=5
KillMode=process

[Install]
WantedBy=multi-user.target
```

সার্ভিস চালু ও এনেবল করুন:
```bash
sudo systemctl daemon-reload
sudo systemctl enable --now netfusion-queue
sudo systemctl status netfusion-queue
```

---

## ৪. শিডিউলার ও ক্রন ইঞ্জিন (Scheduler Engine)

### বিকল্প ১: Systemd Timer (সর্বোত্তম পদ্ধতি)
```bash
sudo cp deploy/systemd/netfusion-scheduler.service /etc/systemd/system/
sudo cp deploy/systemd/netfusion-scheduler.timer /etc/systemd/system/

sudo systemctl daemon-reload
sudo systemctl enable --now netfusion-scheduler.timer
```

### বিকল্প ২: স্ট্যান্ডার্ড ক্রনট্যাব
```bash
sudo cp deploy/cron/netfusion.cron /etc/cron.d/netfusion
sudo chmod 644 /etc/cron.d/netfusion
```
অথবা `crontab -e -u www-data` ওপেন করে নিচের লাইনটি যুক্ত করুন:
```cron
* * * * * cd /var/www/netfusion && php netfusion schedule:run >> /dev/null 2>&1
```

---

## ৫. প্রোডাকশন সিকিউরিটি হার্ডেনিং চেকলিস্ট

1. **ফাইল পারমিশন নীতি**:
   - সকল ফাইল ও ফোল্ডারের মালিকানা উপযুক্ত ওয়েব ইউজারকে দিন:
     ```bash
     sudo chown -R www-data:www-data /var/www/netfusion
     ```
   - এক্সিকিউটেবল ও সোর্স ফাইলের জন্য রিড-অনলি মোড:
     ```bash
     find /var/www/netfusion -type f -exec chmod 644 {} \;
     find /var/www/netfusion -type d -exec chmod 755 {} \;
     ```
   - শুধুমাত্র রাইটেবল ডিরেক্টরিগুলোতে রাইট পারমিশন দিন:
     ```bash
     chmod -R 775 /var/www/netfusion/storage
     chmod -R 775 /var/www/netfusion/bootstrap/cache
     ```
2. **ডকুমেন্ট রুট আইসোলেশন**:
   - ওয়েব সার্ভার (Nginx/Apache)-এর DocumentRoot সর্বদা `/var/www/netfusion/public`-এ পয়েন্ট করুন। কোনো অবস্থাতেই মূল প্রজেক্ট রুট ফোল্ডারকে DocumentRoot বানাবেন না।
3. **প্রোডাকশন এনভায়রনমেন্ট ফ্ল্যাগ**:
   - `.env` ফাইলে সেট করুন:
     ```env
     APP_ENV=production
     APP_DEBUG=false
     ```
4. **অপ্টিমাইজেশন ক্যাশ চালান**:
   ```bash
   php netfusion optimize
   ```
