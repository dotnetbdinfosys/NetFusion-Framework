<!--
|--------------------------------------------------------------------------
| NetFusion Framework CLI Commands Reference Guide
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

# NetFusion Framework — সিএলআই কমান্ড রেফারেন্স গাইড (CLI Commands Reference)

**NetFusion CLI** হলো ফ্রেমওয়ার্কের সেন্ট্রাল কমান্ড লাইন ইন্টারফেস। এটি ব্যবহার করে মডিউল জেনারেশন, ডাটাবেস মাইগ্রেশন, ক্যাশ ম্যানেজমেন্ট, কিউ প্রসেসিং এবং সার্ভার ডায়াগনস্টিকস পরিচালনা করা যায়।

---

## ১. গ্লোবাল কমান্ড (Global Commands)

### `list`
রেজিস্টার্ড সকল কমান্ডের তালিকা এবং তাদের বিবরণ প্রদর্শন করে।
```bash
php netfusion list
```

---

## ২. সিস্টেম ডায়াগনস্টিকস ও পারফরম্যান্স (System & Performance)

### `server:check`
সার্ভারের স্বাস্থ্য, অপারেটিং সিস্টেম, পিএইচপি কোর এক্সটেনশন, মেমোরি লিমিট, ফাইলসিস্টেম পারমিশন, ডাটাবেস হ্যান্ডশেক ও সিমলিংক যাচাই করে বিস্তারিত রিপোর্ট দেয়।
```bash
php netfusion server:check
```

### `security:audit`
সার্ভার এনভায়রনমেন্ট, ক্রিপ্টোগ্রাফিক কি, ডিরেক্টরি পারমিশন এবং পিএইচপি ডিরেক্টিভের সার্বিক দুর্বলতা স্ক্যান করে এবং স্কোর ও রেটিং সহ অডিট রিপোর্ট তৈরি করে।
```bash
php netfusion security:audit
```

### `audit:prune`
ডাটাবেসের আকার নিয়ন্ত্রণে রাখতে নির্দিষ্ট দিনের পুরনো অডিট লগ ও হিস্ট্রি স্বয়ংক্রিয়ভাবে ডিলিট করে।
```bash
# ডিফল্ট ৯০ দিনের পুরানো লগ মুছুন
php netfusion audit:prune

# নির্দিষ্ট দিনের পুরানো লগ মুছুন
php netfusion audit:prune --days=60
```

### `optimize`
প্রোডাকশন এনভায়রনমেন্টের জন্য কনফিগারেশন, রুট ও মডিউল ক্যাশিং সক্রিয় করে এবং স্টোরেজ সিমলিংক পরীক্ষা করে।
```bash
# প্রোডাকশন অপ্টিমাইজেশন চালান
php netfusion optimize

# ক্যাশ মুছে ফ্রেশ স্টেটে ফিরে যান
php netfusion optimize --clear
```

### `storage:link`
`storage/app/public` ডিরেক্টরিটিকে `public/storage`-এর সাথে সিম্বলিক লিংক (Symlink) করে যাতে আপলোডকৃত ফাইলসমূহ ব্রাউজার থেকে অ্যাক্সেস করা যায়।
```bash
php netfusion storage:link
```

---

## ৩. এইচএমভিসি কোড জেনারেটরস (HMVC Generators)

### `make:module`
একটি সম্পূর্ণ নতুন প্লাগ-অ্যান্ড-প্লে HMVC মডিউল স্কেলিটন (`module.json`, Controllers, Models, Views, Routes, Migrations, ServiceProvider) তৈরি করে।
```bash
php netfusion make:module School
```

### `make:controller`
নির্দিষ্ট মডিউলের ভেতরে অথবা গ্লোবাল অ্যাপে কন্ট্রোলার তৈরি করে।
```bash
# মডিউলের অধীনে
php netfusion make:controller StudentController --module=School

# গ্লোবাল অ্যাপে
php netfusion make:controller HomeController
```

### `make:model`
অ্যাক্টিভ রেকর্ড ওআরএম মডেল ক্লাস তৈরি করে।
```bash
php netfusion make:model Student --module=School
```

### `make:migration`
নির্দিষ্ট মডিউলের জন্য নতুন ডাটাবেস মাইগ্রেশন ফাইল তৈরি করে।
```bash
php netfusion make:migration create_students_table --module=School
```

### `make:mail`
নতুন মেল ক্লাস (Mailable) তৈরি করে।
```bash
php netfusion make:mail WelcomeEmail --module=Auth
```

### `make:notification`
মাল্টি-চ্যানেল (Mail, Database, SMS) নোটিফিকেশন ক্লাস তৈরি করে।
```bash
php netfusion make:notification OrderShipped --module=Commerce
```

---

## ৪. ডাটাবেস মাইগ্রেশন ও সিডিং (Database & Migrations)

### `migrate`
সকল পেন্ডিং ডাটাবেস মাইগ্রেশন ক্রমানুসারে এক্সিকিউট করে।
```bash
# মাইগ্রেশন চালান
php netfusion migrate

# সর্বশেষ মাইগ্রেশন ব্যাচ রোলব্যাক করুন
php netfusion migrate --rollback
```

### `db:seed`
ডাটাবেসে প্রাথমিক বা ডামি ডাটা ইনসার্ট করার জন্য সিডার রান করে।
```bash
# ডিফল্ট ডাটাবেস সিডার
php netfusion db:seed

# নির্দিষ্ট ক্লাস ও মডিউলের সিডার
php netfusion db:seed --class=BillingDatabaseSeeder --module=Billing
```

---

## ৫. ব্যাকগ্রাউন্ড কিউ ম্যানেজমেন্ট (Queue Management)

### `queue:work`
ব্যাকগ্রাউন্ড কিউ য়ার্কার ডেমন চালু করে যা জব প্রসেস করে।
```bash
# সাধারণ কিউ য়ার্কার
php netfusion queue:work

# শুধুমাত্র একটি জব প্রসেস করে প্রস্থান করা (পরীক্ষার জন্য)
php netfusion queue:work --once

# নির্দিষ্ট কিউ, স্লিপ ও মেমোরি সিলিং নির্ধারণ
php netfusion queue:work --queue=emails --sleep=3 --memory=128 --tries=3
```

### `queue:failed`
স্থায়ীভাবে ব্যর্থ (Failed) হওয়া জবগুলোর তালিকা ও এরর স্ট্যাক ট্রেস প্রদর্শন করে।
```bash
php netfusion queue:failed
```

### `queue:retry`
ব্যর্থ জব পুনরায় কিউতে যুক্ত করে প্রসেস করার নির্দেশ দেয়।
```bash
# নির্দিষ্ট জব রিট্রাই (আইডি দ্বারা)
php netfusion queue:retry 5

# সকল ব্যর্থ জব রিট্রাই
php netfusion queue:retry all
```

---

## ৬. ক্যাশ ম্যানেজমেন্ট (Cache Management)

### `cache:clear`
অ্যাপ্লিকেশন ক্যাশ ফ্লাশ বা মুছে ফেলে।
```bash
# ডিফল্ট স্টোর পরিষ্কার
php netfusion cache:clear

# নির্দিষ্ট স্টোর পরিষ্কার
php netfusion cache:clear --store=redis

# নির্দিষ্ট ট্যাগ ভিত্তিক ক্যাশ ইনভ্যালিডেশন
php netfusion cache:clear --tags=billing,invoices
```

### `cache:forget`
ক্যাশ থেকে একটি নির্দিষ্ট কি (Key) মুছে ফেলে।
```bash
php netfusion cache:forget user_profile_123
```

---

## ৭. টাস্ক শিডিউলার (Task Scheduler)

### `schedule:run`
বর্তমান মিনিটে কার্যকর হওয়ার জন্য অপেক্ষারত শিডিউলড কমান্ড ও টাস্কসমূহ রান করে (ক্রন ইঞ্জিন দ্বারা কল করা হয়)।
```bash
php netfusion schedule:run
```

### `schedule:list`
সিস্টেমে রেজিস্টার্ড সকল শিডিউলড জব এবং তাদের পুনরাবৃত্তি ফ্রিকোয়েন্সি (Frequency) তালিকাভুক্ত করে।
```bash
php netfusion schedule:list
```

---

## ৮. ওয়েবহুক রিট্রাই ইঞ্জিন (Webhook Management)

### `webhook:retry`
ব্যর্থ আউটগোয়িং ওয়েবহুক ডেলিভারি পুনরায় নির্দিষ্ট রিমোট ইউআরএলে পাঠানোর প্রচেষ্টা চালায়।
```bash
# সকল ব্যর্থ ডেলিভারি পুনরায় চেষ্টা
php netfusion webhook:retry

# নির্দিষ্ট ডেলিভারি আইডি রিট্রাই
php netfusion webhook:retry --id=42
```

---

## ৯. ব্যাকআপ ও ডিজাস্টার রিকভারি (Backup & Disaster Recovery)

### `backup:run`
ডাটাবেস ও ফাইলের সম্পূর্ণ জিপ স্ন্যাপশট আর্কাইভ এবং SHA-256 চেকসাম ম্যানিফেস্ট তৈরি করে।
```bash
# ডিফল্ট ব্যাকআপ স্ন্যাপশট
php netfusion backup:run

# শুধুমাত্র ডাটাবেস ব্যাকআপ
php netfusion backup:run --only-db

# কাস্টম ফাইলের নাম সহ ব্যাকআপ
php netfusion backup:run --filename=production_db_snap.zip
```

### `backup:list`
`storage/backups/` ডিরেক্টরিতে সংরক্ষিত সকল ব্যাকআপ ফাইল, তাদের সাইজ ও তৈরির সময় তালিকাভুক্ত করে।
```bash
php netfusion backup:list
```

### `backup:clean`
রিটেনশন পলিসি অনুযায়ী নির্দিষ্ট সংখ্যার পুরনো ব্যাকআপ আর্কাইভ নিরাপদভাবে মুছে ফেলে।
```bash
# সাম্প্রতিক ৭টি রেখে পুরনো সব আর্কাইভ ডিলিট করুন
php netfusion backup:clean --keep=7
```

---

## ১০. ভাষা ও লোকালাইজেশন (Translation & Localization)

### `lang:missing`
দুইটি ভাষার ডিকশনারির মধ্যে অনুপস্থিত (missing) কী তুলনা করে চিহ্নিত করে।
```bash
# ইংরেজি ডিকশনারির সাপেক্ষে বাংলা ডিকশনারি পরীক্ষা
php netfusion lang:missing --source=en --target=bn
```

---

## ১১. ইনকামিং ওয়েবহুক গেটওয়ে ও রিপ্লে (Incoming Webhooks & Replay)

### `webhook:replay`
ডাটাবেসে সংরক্ষিত ইনকামিং ওয়েবহুক ইভেন্ট পুনরায় অভ্যন্তরীণ ইভেন্ট বাসের মাধ্যমে প্রসেস করে।
```bash
# নির্দিষ্ট আইডি বিশিষ্ট ওয়েবহুক রিপ্লে
php netfusion webhook:replay 1
php netfusion webhook:replay --id=1

# সকল ব্যর্থ ইনকামিং ওয়েবহুক পুনরায় রিপ্লে
php netfusion webhook:replay --failed
```

---

## ১২. পার্সোনাল অ্যাক্সেস টোকেন ও এপিআই গেটওয়ে (Personal Access Tokens & API Security)

### `token:create`
নির্দিষ্ট ব্যবহারকারী বা সত্তার জন্য ক্রিপ্টোগ্রাফিক পার্সোনাল অ্যাক্সেস টোকেন (PAT) ইস্যু করে।
```bash
# ইউজারের জন্য ডিফল্ট টোকেন তৈরি
php netfusion token:create 1

# নাম, স্কোপ এবং মেয়াদের দিন উল্লেখ করে টোকেন তৈরি
php netfusion token:create 1 --name="Mobile App" --scopes=users:read,orders:read --expires=30
```

### `token:list`
নির্দিষ্ট ব্যবহারকারী বা সত্তার সকল অ্যাক্টিভ এবং এক্সপায়ার্ড পার্সোনাল অ্যাক্সেস টোকেন তালিকা প্রদর্শন করে।
```bash
php netfusion token:list 1
```

### `token:revoke`
নির্দিষ্ট টোকেন আইডি অথবা ব্যবহারকারীর সকল পার্সোনাল অ্যাক্সেস টোকেন অবিলম্বে বাতিল করে।
```bash
# নির্দিষ্ট টোকেন আইডি বাতিল
php netfusion token:revoke 5

# একজন ব্যবহারকারীর সকল টোকেন একবারে বাতিল
php netfusion token:revoke --all --user=1
```

---

## ১৩. অ্যাপ্লিকেশন পারফরম্যান্স মনিটরিং ও ট্রেসিং (APM & Tracing)

### `apm:summary`
অ্যাপ্লিকেশন পারফরম্যান্স মনিটরিং (APM) মেট্রিক্স, রিকোয়েস্ট ল্যাটেন্সি পারসেন্টাইল (p50, p95, p99), ডাটাবেস কুয়েরি এক্সিকিউশন টাইম, মেমরি পিক এবং স্লো কুয়েরি রিপোর্ট প্রদর্শন করে।
```bash
# সার্বিক এপিএম পারফরম্যান্স ওভারভিউ
php netfusion apm:summary

# স্লো কুয়েরিসমূহ বিস্তারিত প্রদর্শন
php netfusion apm:summary --slow

# কাস্টম স্লো কুয়েরি থ্রেশহোল্ড (যেমন >= ৩০ms) উল্লেখ করে পরীক্ষা
php netfusion apm:summary --slow --threshold=30
```

---

## ১৪. ফিচার ফ্ল্যাগ ও A/B টেস্টিং (Feature Flags & A/B Testing)

### `feature:list`
সিস্টেমে রেজিস্টার্ড সকল ফিচার ফ্ল্যাগ, তাদের সক্রিয়তা স্ট্যাটাস, ক্যানারি রোলআউট শতাংশ এবং টার্গেটেড ইউজারদের তালিকা প্রদর্শন করে।
```bash
php netfusion feature:list
```

### `feature:enable`
একটি নির্দিষ্ট ফিচার ফ্ল্যাগ গ্লোবালি, নির্দিষ্ট শতাংশের ব্যবহারকারীদের জন্য অথবা সুনির্দিষ্ট ইউজার আইডির জন্য সক্রিয় করে।
```bash
# গ্লোবালি সক্রিয়করণ (সকল ব্যবহারকারীর জন্য)
php netfusion feature:enable beta_checkout

# ক্যানারি রিলিজ: মোট ট্রাফিকের ২৫% ব্যবহারকারীর জন্য সক্রিয়
php netfusion feature:enable new_dashboard --percentage=25

# শুধুমাত্র নির্দিষ্ট ইউজার আইডির জন্য সক্রিয়
php netfusion feature:enable vip_rewards --users=1,42,105
```

### `feature:disable`
একটি ফিচার ফ্ল্যাগ গ্লোবালি অথবা নির্দিষ্ট ইউজারদের জন্য নিষ্ক্রিয় করে।
```bash
# গ্লোবালি সম্পূর্ণ বন্ধ
php netfusion feature:disable beta_checkout

# নির্দিষ্ট ইউজারদের জন্য নিষ্ক্রিয়
php netfusion feature:disable vip_rewards --users=42
```

---

## ১৫. ফুল-টেক্সট সার্চ ও ইনডেক্সিং (Full-Text Search & Indexing)

### `search:index`
একটি `Searchable` মডেলের ডাটাবেসের সমস্ত রেকর্ড ব্যাচ আকারে সার্চ ইঞ্জিনে পুশ করে ইনডেক্স তৈরি করে।
```bash
# নির্দিষ্ট মডেলের সকল রেকর্ড ইনডেক্স করা
php netfusion search:index User

# পূর্ণাঙ্গ নেইমস্পেস সহ মডেল ইনডেক্স করা
php netfusion search:index App\Models\Article
```

### `search:flush`
একটি নির্দিষ্ট সার্চ ইনডেক্স অথবা মডেলের সমস্ত সংরক্ষিত ইনডেক্স ডাটা সম্পূর্ণভাবে ফ্লাশ/ক্লিয়ার করে।
```bash
# নির্দিষ্ট ইনডেক্স ফ্লাশ করা
php netfusion search:flush articles
```

### `search:query`
টার্মিনাল থেকে সরাসরি কোনো ইনডেক্সে ফুল-টেক্সট সার্চ কুয়েরি চালিয়ে র‍্যাঙ্কড প্রাসঙ্গিকতা স্কোর ও রেজাল্ট প্রদর্শন করে।
```bash
# ইনডেক্সে সার্চ চালানো
php netfusion search:query articles "modern php"

# ফলাফলের লিমিট নির্ধারণ করে সার্চ
php netfusion search:query articles "netfusion framework" --limit=5
```

---

## ১৬. মাল্টি-চ্যানেল নোটিফিকেশন ইঞ্জিন (Multi-Channel Notifications)

### `notification:send`
টার্মিনাল থেকে সরাসরি কোনো নির্দিষ্ট মডেল এন্টিটিতে (যেমন User, Customer) একটি নোটিফিকেশন ক্লাস টেস্ট ডেলিভারি পাঠায়।
```bash
# ইউজার আইডি ৪২-এ অর্ডার শিপমেন্ট নোটিফিকেশন প্রেরণ
php netfusion notification:send User 42 OrderShippedNotification

# পূর্ণাঙ্গ নেইমস্পেস সহ নোটিফিকেশন প্রেরণ
php netfusion notification:send App\Models\User 42 App\Notifications\OrderShippedNotification
```

### `notification:list`
একটি নির্দিষ্ট এন্টিটির ডাটাবেসে সংরক্ষিত ইন-অ্যাপ নোটিফিকেশন তালিকা (আইডি, টাইপ, রিড/আনরিড স্ট্যাটাস, তৈরির সময়) প্রদর্শন করে।
```bash
# ইউজারের সমস্ত নোটিফিকেশন দেখা
php netfusion notification:list User 42

# শুধুমাত্র না পড়া (Unread) নোটিফিকেশন দেখা
php netfusion notification:list User 42 --unread
```

---

## ১৭. মাল্টি-কারেন্সি ও এফএক্স রেট ইঞ্জিন (Multi-Currency & FX Rates)

### `currency:update-rates`
ডাটাবেসে ম্যানুয়ালি নতুন এক্সচেঞ্জ রেট সেট বা আপডেট করে অথবা বর্তমান সমস্ত রেটের তালিকা প্রদর্শন করে।
```bash
# নতুন রেট সেট করা (অটোমেটিক রিভার্স রেট সহ)
php netfusion currency:update-rates --from=USD --to=BDT --rate=110.50

# রেটের সোর্স মেটাডাটা সহ আপডেট
php netfusion currency:update-rates --from=USD --to=EUR --rate=0.92 --source=api

# সিস্টেমে সংরক্ষিত সকল এক্সচেঞ্জ রেটের তালিকা প্রদর্শন
php netfusion currency:update-rates --list
```

### `currency:convert`
একটি নির্দিষ্ট পরিমাণ অর্থ এক কারেন্সি থেকে অন্য কারেন্সিতে এক্সচেঞ্জ রেট অনুযায়ী রূপান্তর করে এবং ফরম্যাটেড আউটপুট ও ইনটিজার মাইনর ইউনিটস প্রদর্শন করে।
```bash
# ১০০ ইউএস ডলারকে বাংলাদেশি টাকায় রূপান্তর
php netfusion currency:convert 100 USD BDT

# নির্দিষ্ট ড্রাইভার ব্যবহার করে কনভার্সন
php netfusion currency:convert 50 EUR USD --driver=array

# শূন্য ডেসিম্যাল কারেন্সিতে কনভার্সন (যেমন JPY)
php netfusion currency:convert 100 USD JPY
```

---

## ১৮. ডাইনামিক ফর্ম ও কাস্টম ফিল্ড ইঞ্জিন (Dynamic Forms & Custom Fields)

### `form:fields`
নির্দিষ্ট কোনো মডেল এন্টিটির (যেমন User, Lead, Product, Customer) জন্য ডাটাবেসে সংজ্ঞায়িত সকল ডাইনামিক কাস্টম ফিল্ডের তালিকা টেবিল আকারে প্রদর্শন করে।
```bash
# User মডেলের কাস্টম ফিল্ড তালিকা দেখা
php netfusion form:fields User

# Lead মডেলের কাস্টম ফিল্ড তালিকা দেখা
php netfusion form:fields Lead
```

### `form:add-field`
যেকোনো মডেল এন্টিটিতে রানটাইমে নতুন একটি কাস্টম ফিল্ড ডেফিনিশন যুক্ত বা আপডেট করে।
```bash
# User এ ডিপার্টমেন্ট টেক্সট ফিল্ড যোগ করা (Required)
php netfusion form:add-field User department text --label="Department" --required

# Lead এ বাজেট ফিল্ড যোগ করা (min:0 ভ্যালিডেশন সহ)
php netfusion form:add-field Lead budget number --label="Estimated Budget" --rules="min:0"

# Product এ ড্রপডাউন ক্যাটাগরি অপশন সহ ফিল্ড যোগ করা
php netfusion form:add-field Product category select --label="Product Category" --options='{"1":"Electronics","2":"Fashion"}'
```

---

## ১৯. ডকুমেন্ট জেনারেশন ও এক্সপোর্টার ইঞ্জিন (Document Generation & Exporters)

### `document:export`
ডাটাবেসের যেকোনো মডেলের রেকর্ড সরাসরি CSV অথবা Microsoft Excel XML ফাইলে এক্সপোর্ট করে নির্দিষ্ট আউটপুট পাথে সংরক্ষণ করে।
```bash
# User মডেলের ডাটা CSV ফাইলে এক্সপোর্ট
php netfusion document:export User csv --output=storage/users_export.csv

# Lead মডেলের প্রথম ৫০০টি রেকর্ড Excel ফাইলে এক্সপোর্ট
php netfusion document:export Lead excel --output=storage/leads.xls --limit=500
```

---

## ২০. ওয়ার্কফ্লো ও স্টেট মেশিন অটোমেশন ইঞ্জিন (Workflow & Approval Engine)

### `workflow:list`
সিস্টেমে সংজ্ঞায়িত সমস্ত ফাইনাইট স্টেট মেশিন, তাদের অনুমোদিত স্টেট ও ট্রানজিশনসমূহ এবং মাল্টি-স্টেজ অ্যাপ্রুভাল পাইপলাইনের তালিকা প্রদর্শন করে।
```bash
# সমস্ত ওয়ার্কফ্লো ও পাইপলাইন দেখা
php netfusion workflow:list
```

### `workflow:apply`
টার্মিনাল থেকে সরাসরি নির্দিষ্ট মডেলের একটি রেকর্ডে স্টেট ট্রানজিশন প্রয়োগ করে এবং ডাটাবেস অডিট ট্রেইলে লগ সংরক্ষণ করে।
```bash
# Invoice #42 এ 'pay' ট্রানজিশন প্রয়োগ করা
php netfusion workflow:apply Invoice 42 pay --actor=1 --comment="Paid via Stripe"

# Order #105 এ 'ship' ট্রানজিশন প্রয়োগ করা
php netfusion workflow:apply Order 105 ship --actor=2 --comment="Handed to courier"
```

### `approval:pending`
মাল্টি-স্টেজ অ্যাপ্রুভাল পাইপলাইনের পেন্ডিং রিকোয়েস্টসমূহ (রেফারেন্স আইডি, স্টেজ নাম, এবং অনুমোদকের তথ্য) তালিকা আকারে প্রদর্শন করে।
```bash
# সকল পেন্ডিং অনুমোদন রিকোয়েস্ট দেখা
php netfusion approval:pending

# নির্দিষ্ট ইউজারের জন্য পেন্ডিং রিকোয়েস্ট দেখা
php netfusion approval:pending --user=101
```

---

## ২১. ইভেন্ট সোর্সিং ও CQRS ইঞ্জিন (Event Sourcing & CQRS Engine)

### `event:streams`
ডাটাবেস ইভেন্ট স্টোরে সংরক্ষিত সমস্ত ডোমেন ইভেন্ট স্ট্রিম, তাদের সংশ্লিষ্ট Aggregate Type, বর্তমান ভার্সন এবং আপডেটের সময় প্রদর্শন করে।
```bash
# সমস্ত ইভেন্ট স্ট্রিম তালিকা দেখা
php netfusion event:streams

# নির্দিষ্ট Aggregate টাইপের স্ট্রিমসমূহ ফিল্টার করা
php netfusion event:streams --aggregate="App\Aggregates\BankAccountAggregate"
```

### `event:replay`
ইভেন্ট স্টোরে জমা থাকা হিস্টোরিক্যাল ইভেন্টসমূহ শুরু থেকে পুনরায় প্লে করে Read Model Projections স্টেট পুনর্নির্মাণ করে।
```bash
# সকল স্ট্রিমের সমস্ত ইভেন্ট রিপ্লে করে রিড মডেল প্রজেকশন পুনর্নির্মাণ
php netfusion event:replay

# নির্দিষ্ট একটি স্ট্রিমের ইভেন্ট রিপ্লে করা
php netfusion event:replay acc-101

# নির্দিষ্ট একটি প্রজেকশন সিলেক্ট করে রিপ্লে করা
php netfusion event:replay --projection=AccountSummaryProjection
```

---

## ২২. ডিস্ট্রিবিউটেড লক ও কনকারেন্সি গার্ড ইঞ্জিন (Distributed Lock & Mutex Engine)

### `lock:list`
বর্তমানে সিস্টেমে সক্রিয় থাকা সমস্ত ডিস্ট্রিবিউটেড লক, তাদের মালিকানা (Owner), অবশিষ্ট সময় (TTL) এবং তৈরির সময়সূচি প্রদর্শন করে।
```bash
# সকল সক্রিয় লক দেখা
php netfusion lock:list

# নির্দিষ্ট ড্রাইভারের (database / file / array) লক দেখা
php netfusion lock:list --driver=file
```

### `lock:release`
টার্মিনাল থেকে সরাসরি একটি সক্রিয় বা আটকে থাকা (stuck) ডিস্ট্রিবিউটেড লককে জোরপূর্বক মুক্ত (force release) করে।
```bash
# নির্দিষ্ট রিসোর্স কী এর লক মুক্ত করা
php netfusion lock:release process-payouts

# ফাইল ড্রাইভারের নির্দিষ্ট লক মুক্ত করা
php netfusion lock:release invoice-batch-101 --driver=file
```

### `lock:clean`
ডাটাবেস বা ফাইল সিস্টেমে মেয়াদোত্তীর্ণ হয়ে পড়ে থাকা অপ্রয়োজনীয় লক রেকর্ডসমূহ এক ক্লিকে প্রুন ও ক্লিনআপ করে।
```bash
# মেয়াদোত্তীর্ণ সকল লক পরিষ্কার করা
php netfusion lock:clean
```

---

## ২৩. মাল্টি-টেন্যান্সি ও ডাটা আইসোলেশন ইঞ্জিন (Multi-Tenancy Engine)

### `tenant:list`
সিস্টেমে নিবন্ধিত সমস্ত টেন্যান্টের তালিকা, তাদের সাবডোমেন, কাস্টম ডোমেন, ডাটাবেস নাম এবং সক্রিয় অবস্থা (Active/Suspended) টেবিল ফরম্যাটে প্রদর্শন করে।
```bash
# সমস্ত টেন্যান্ট তালিকা প্রদর্শন
php netfusion tenant:list

# শুধুমাত্র সক্রিয় টেন্যান্টসমূহ প্রদর্শন
php netfusion tenant:list --active-only
```

### `tenant:create`
সিস্টেমে নতুন একটি টেন্যান্ট অ্যাকাউন্ট এবং তার সংশ্লিষ্ট সাবডোমেন, ডোমেন ও ডাটাবেস ম্যাপিং তৈরি করে।
```bash
# বেসিক টেন্যান্ট তৈরি
php netfusion tenant:create acme "ACME Corporation"

# সম্পূর্ণ কাস্টম কনফিগারেশন সহ টেন্যান্ট তৈরি
php netfusion tenant:create globex "Globex Corp" --subdomain=globex --domain=portal.globex.com --database=tenant_db_globex
```

### `tenant:migrate`
টেন্যান্ট-স্পেসিফিক ডাটাবেসগুলোতে ফ্রেমওয়ার্কের মাইগ্রেশনসমূহ এক্সিকিউট করে।
```bash
# সকল সক্রিয় টেন্যান্টের ডাটাবেসে মাইগ্রেশন রান করা
php netfusion tenant:migrate

# নির্দিষ্ট একটি টেন্যান্টের জন্য মাইগ্রেশন রান করা
php netfusion tenant:migrate acme
```

---

## ২৪. রিয়েল-টাইম ওয়েবসকেট ও সকেট চ্যানেল ইঞ্জিন (Real-Time WebSockets Engine)

### `socket:serve`
নেটফিউশনের উচ্চ-পারফরম্যান্সসম্পন্ন পিওর-পিএইচপি স্ট্যান্ডঅ্যালোন ওয়েবসকেট ডেমন সার্ভার চালু করে।
```bash
# ডিফল্ট কনফিগারেশনে সার্ভার চালু করা (ws://0.0.0.0:8080)
php netfusion socket:serve

# কাস্টম হোস্ট ও পোর্টে সার্ভার চালু করা
php netfusion socket:serve --host=127.0.0.1 --port=9000 --push-port=9001
```

### `socket:push`
টার্মিনাল, ক্রন টাস্ক বা সিআই পাইপলাইন থেকে সরাসরি চলমান ওয়েবসকেট সার্ভারের নির্দিষ্ট চ্যানেলে রিয়েল-টাইম ইভেন্ট ও পেলোড পুশ করে।
```bash
# 'orders' চ্যানেলে 'OrderShipped' ইভেন্ট পুশ করা
php netfusion socket:push orders OrderShipped '{"order_id":102,"status":"shipped"}'

# 'presence-chat' চ্যানেলে মেসেজ পুশ করা
php netfusion socket:push presence-chat message '{"user":"Ashish","text":"System Maintenance at 12:00"}'
```

---

## ২৫. এন্টারপ্রাইজ গ্রাফকিউএল ইঞ্জিন (Enterprise GraphQL Engine)

### `graphql:schema`
সিস্টেমে রেজিস্টার্ড গ্রাফকিউএল স্কিমার ফুল SDL (Schema Definition Language) টার্মিনালে প্রদর্শন করে অথবা স্পেসিফিক ফাইলে এক্সপোর্ট করে।
```bash
# ডিফল্ট স্কিমার SDL প্রিন্ট করা
php netfusion graphql:schema

# কাস্টম স্কিমার SDL প্রিন্ট করা
php netfusion graphql:schema --schema=admin

# নির্দিষ্ট .graphql ফাইলে স্কিমা এক্সপোর্ট করা
php netfusion graphql:schema --out=schema.graphql
```

### `graphql:query`
টার্মিনাল বা সিআই/সিডি অটোমেশন স্ক্রিপ্ট থেকে সরাসরি গ্রাফকিউএল কোয়েরি ও মিউটেশন এক্সিকিউট করে কালারাইজড JSON রেসপন্স প্রদর্শন করে।
```bash
# সরাসরি কোয়েরি এক্সিকিউট করা
php netfusion graphql:query "{ ping, version }"

# ভেরিয়েবল সহ কোয়েরি এক্সিকিউট করা
php netfusion graphql:query 'query GetUser($id: ID!) { user(id: $id) { name, email } }' --variables='{"id": 1}'

# নির্দিষ্ট স্কিমার বিপরীতে কোয়েরি রান করা
php netfusion graphql:query "{ analytics { activeUsers } }" --schema=analytics
```

---

## ২৬. ডাইনামিক মাইক্রোসার্ভিসেস সার্ভিস মেশ ও আরপিসি ইঞ্জিন (Microservices Service Mesh & RPC Engine)

### `rpc:serve`
নেটফিউশনের হাই-স্পিড পিউর-পিএইচপি নন-ব্লকিং স্ট্রিম সকেট আরপিসি মাইক্রোসার্ভিস ডেমন চালু করে এবং সার্ভিস মেশ রেজিস্ট্রিতে নোড অটো-রেজিস্টার করে।
```bash
# ডিফল্ট কনফিগারেশনে আরপিসি ডেমন চালু করা (tcp://0.0.0.0:9500)
php netfusion rpc:serve

# কাস্টম হোস্ট ও পোর্টে আরপিসি ডেমন চালু করা
php netfusion rpc:serve --host=127.0.0.1 --port=9600
```

### `rpc:call`
টার্মিনাল বা সিআই অটোমেশন থেকে সরাসরি চলমান মাইক্রোসার্ভিসেস ক্লাস্টারে আরপিসি মেথড ইনভোক করে এবং স্ট্রাকচার্ড JSON রেসপন্স প্রিন্ট করে।
```bash
# সরাসরি আরপিসি মেথড কল করা
php netfusion rpc:call AuthService verifyToken '{"token":"jwt-token-string"}'

# পজিশনাল আর্গুমেন্ট সহ মেথড কল করা
php netfusion rpc:call Calculator add '[15, 27]'

# নির্দিষ্ট নোড হোস্ট ও পোর্টে সরাসরি আরপিসি কল পাঠানো
php netfusion rpc:call OrderService createOrder '{"customer_id":10,"items":[1,2]}' --host=10.0.0.5 --port=9500
```

### `rpc:registry`
সিস্টেম মেশ রেজিস্ট্রিতে রেজিস্টার্ড সমস্ত সক্রিয় মাইক্রোসার্ভিস নোড, তাদের নোড আইডি, এন্ডপয়েন্ট এবং হেলথ স্ট্যাটাস প্রদর্শন করে।
```bash
# সার্ভিস মেশের সমস্ত নোডের তালিকা ও হেলথ দেখা
php netfusion rpc:registry
```

---

## ২৭. এন্টারপ্রাইজ এআই এজেন্ট ইঞ্জিন ও ভেক্টর মেমোরি (Enterprise AI Agent Engine & Vector Memory)

### `ai:chat`
টার্মিনাল থেকে সরাসরি কনফিগার করা AI LLM প্রোভাইডারকে (OpenAI / Claude / Gemini / Mock) প্রম্পট পাঠিয়ে রেসপন্স ও টোকেন ইউসেজ মেট্রিক্স প্রদর্শন করে।
```bash
# সাধারণ চ্যাট প্রম্পট পাঠানো
php netfusion ai:chat "Explain distributed caching in 2 sentences"

# নির্দিষ্ট এলএলএম ড্রাইভার ও মডেল ব্যবহার করে প্রম্পট পাঠানো
php netfusion ai:chat "Generate a PHP 8.2 match expression" --driver=openai --model=gpt-4o-mini
```

### `ai:agent`
টুলস (যেমন: ক্যালকুলেটর, ডাটাবেস কুয়েরি) ও কনভারসেশন মেমোরি সমৃদ্ধ অটোনোমাস রিঅ্যাক্ট (ReAct) এআই এজেন্ট এক্সিকিউট করে এবং প্রতি স্টেপের টুল কল ও ফলাফল প্রদর্শন করে।
```bash
# রিঅ্যাক্ট এআই এজেন্টকে গাণিতিক ও টুল-নির্ভর কাজ প্রদান
php netfusion ai:agent "What is 45 multiplied by 89?"

# কাস্টম সিস্টেম ইন্সট্রাকশন ও স্টেপস লিমিট সহ এজেন্ট রান করা
php netfusion ai:agent "Calculate sqrt(144) + 25" --steps=5 --system="You are a precise math assistant."
```

### `ai:embed`
ইনপুট টেক্সটের জন্য হাই-ডাইমেনশনাল ভেক্টর এমবেডিং জেনারেট করে এবং ঐচ্ছিকভাবে সরাসরি ভেক্টর ডাটাবেসে ইনডেক্স করে।
```bash
# টেক্সট থেকে ভেক্টর এমবেডিং জেনারেট করা
php netfusion ai:embed "NetFusion Enterprise Framework"

# ভেক্টর তৈরি করে নির্দিষ্ট কালেকশনে ডাটাবেসে সেভ করা
php netfusion ai:embed "PHP 8.2 High Performance Architecture" --store --collection=articles --id=doc_101
```

---

## ২৮. সাগা অর্কেস্ট্রেটর ও বিতরণকৃত লেনদেন (Saga Orchestrator & Distributed Transactions)

### `saga:list`
সিস্টেমে চলমান ও সম্পন্ন হওয়া সমস্ত ডিস্ট্রিবিউটেড সাগা ট্রানজ্যাকশন ইনস্ট্যান্সের তালিকা, বর্তমান স্টেট (`COMPLETED`, `COMPENSATED`, `FAILED`), মোট সময় এবং শুরুর সময় প্রদর্শন করে।
```bash
# সমস্ত সাগা তালিকা প্রদর্শন
php netfusion saga:list

# নির্দিষ্ট স্ট্যাটাস বা সাগা নামে ফিল্টার করা
php netfusion saga:list --status=COMPLETED --name=order_checkout
```

### `saga:show`
নির্দিষ্ট একটি সাগা ইনস্ট্যান্সের গভীর অডিট ট্রেল, কনটেক্সট ডাটা এবং প্রতিটি স্টেপের ফরোয়ার্ড ও ব্যাকওয়ার্ড কমপেনসেশন ফলাফল প্রদর্শন করে।
```bash
# নির্দিষ্ট সাগা আইডি এর স্টেপ ট্রেস দেখা
php netfusion saga:show saga_66ed0f2a91b4
```

### `saga:retry`
ব্যর্থ বা সাময়িক নেটওয়ার্ক ত্রুটির কারণে আটকে থাকা কোনো সাগা ট্রানজ্যাকশনকে পুনরায় এক্সিকিউট করে।
```bash
# সাগা রিট্রাই করা
php netfusion saga:retry saga_66ed0f2a91b4
```

### `saga:compensate`
টার্মিনাল থেকে সরাসরি একটি সাগা ইনস্ট্যান্সের সমস্ত সফল স্টেপের বিপরীতে বিপরীত ক্রমানুসারে (reverse order) ব্যাকওয়ার্ড কমপেনসেশন রোলব্যাক সঞ্চালন করে।
```bash
# জোরপূর্বক সাগা কমপেনসেশন রোলব্যাক চালু করা
php netfusion saga:compensate saga_66ed0f2a91b4
```

---

## ২৯. এক্সেস কন্ট্রোল ও জাঞ্জিবার রিলেশনশিপ ইঞ্জিন (PBAC, ABAC & ReBAC - Google Zanzibar)

### `policy:check`
টার্মিনাল থেকে সরাসরি একটি নির্দিষ্ট সাবজেক্ট, অ্যাকশন এবং রিসোর্সের জন্য পিবিএসি ও এব্যাক পলিসি মূল্যায়ন সিমুলেট ও যাচাই করে।
```bash
# পলিসি ডিসিশন চেক করা
php netfusion policy:check --subject="101" --action="documents:update" --resource="documents:50"

# কাস্টম এনভায়রনমেন্ট সহ ড্রাই-রান
php netfusion policy:check --subject="42" --action="invoices:approve" --resource="invoices:100" --env="ip=127.0.0.1"
```

### `policy:list`
সিস্টেমে নিবন্ধিত এবং ডাটাবেসে সংরক্ষিত সমস্ত পিবিএসি অথোরাইজেশন পলিসি ও রুলসের তালিকা এবং তাদের প্রায়োরিটি প্রদর্শন করে।
```bash
# নিবন্ধিত সমস্ত পলিসির তালিকা দেখা
php netfusion policy:list
```

### `rebac:tuple`
গুগল জাঞ্জিবার স্টাইলের রিলেশনশিপ টিউপল যুক্ত, তালিকা প্রদর্শন, যাচাই এবং বাতিল করে।
```bash
# নতুন রিলেশনশিপ টিউপল যোগ করা (entity#relation@subject)
php netfusion rebac:tuple add "folder:f1#owner@user:alice"
php netfusion rebac:tuple add "document:d1#viewer@group:engineering#member"

# রিলেশনশিপ যাচাই করা (Recursive Graph Traversal Check)
php netfusion rebac:tuple check document:d1 viewer user:alice

# নির্দিষ্ট এনটিটির রিলেশনশিপ তালিকা দেখা
php netfusion rebac:tuple list --entity="folder:f1"

# রিলেশনশিপ বাতিল/ডিলিট করা
php netfusion rebac:tuple delete "folder:f1#owner@user:alice"
```

---

## ৩০. ডিস্ট্রিবিউটেড ট্রেসিং ও প্রমিথিউস মেট্রিক্স ইঞ্জিন (OpenTelemetry & Prometheus Metrics)

### `telemetry:metrics`
ফ্রেমওয়ার্কের মেমোরিতে সংগৃহীত সমস্ত কাউন্টার, গেজ এবং হিস্টোগ্রাম মেট্রিক আউটপুট প্রদর্শন বা নির্দিষ্ট ফরম্যাটে এক্সপোর্ট করে।
```bash
# প্রমিথিউস এক্সপোজিশন ফরম্যাটে মেট্রিক্স আউটপুট দেখা (ডিফল্ট)
php netfusion telemetry:metrics

# জেসন ফরম্যাটে মেট্রিক্স এক্সপোর্ট
php netfusion telemetry:metrics --format=json

# টার্মিনাল টেবিল ফরম্যাটে মেট্রিক্স সামারি দেখা
php netfusion telemetry:metrics --format=table
```

### `telemetry:trace`
একটি নতুন W3C TraceContext তৈরি বা নির্দিষ্ট ডিস্ট্রিবিউটেড ট্রেস আইডি ও ব্যাগেজ হেডার অনুকরণ/টেস্ট করে।
```bash
# নতুন র্যান্ডম W3C Traceparent হেডার ও স্প্যান তৈরি করা
php netfusion telemetry:trace

# নির্দিষ্ট Trace ID এবং W3C Baggage সহ ট্রেস কনটেক্সট তৈরি করা
php netfusion telemetry:trace --trace-id="4bf92f3577b34da6a3ce929d0e0e4736" --baggage="userId=101,tier=gold"
```

---

## ৩১. ওয়েবঅথেন ও বায়োমেট্রিক পাসকি ইঞ্জিন (WebAuthn, Passkeys & FIDO2)

### `webauthn:credentials`
ডাটাবেস বা মেমোরি স্টোরে সংরক্ষিত সমস্ত বায়োমেট্রিক পাসকি ক্রেডেনশিয়ালস (Touch ID, Face ID, Windows Hello, YubiKey) তালিকা আকারে প্রদর্শন করে অথবা নির্দিষ্ট ক্রেডেনশিয়াল মুছে ফেলে।
```bash
# সিস্টেমে সংরক্ষিত সমস্ত পাসকি ক্রেডেনশিয়ালস দেখা
php netfusion webauthn:credentials

# নির্দিষ্ট ইউজারের পাসকি ক্রেডেনশিয়াল তালিকা প্রদর্শন
php netfusion webauthn:credentials --user=42

# একটি নির্দিষ্ট পাসকি ক্রেডেনশিয়াল ডিলিট/মুছে ফেলা
php netfusion webauthn:credentials delete 042a5bc78d...
```

### `webauthn:prune`
মেয়াদোত্তীর্ণ (expired) ওয়েবঅথেন রেজিস্ট্রেশন ও লগইন ক্রিপ্টোগ্রাফিক চ্যালেঞ্জ বা ননসসমূহ ডাটাবেস বা মেমোরি থেকে নিরাপদভাবে মুছে ফেলে।
```bash
# মেয়াদোত্তীর্ণ সকল পাসকি চ্যালেঞ্জ প্রুন করা
php netfusion webauthn:prune
```

---

## ৩২. মাল্টি-রিজিয়ন এজ রেপ্লিকেশন ও সিআরডিটি ইঞ্জিন (Edge Replication & CRDT Engine)

### `replication:sync`
মাল্টি-রিজিয়ন নোড সমূহের মধ্যে লোকাল চেঞ্জলগ ডেল্টা পুশ এবং রিমোট নোড থেকে পেন্ডিং ডেল্টা পুল করে ডাটাবেস ও সিআরডিটি স্টেটে মার্জ করে।
```bash
# সমস্ত কনফিগার করা পিয়ার নোডের সাথে এজ ডাটা সিঙ্ক করা
php netfusion replication:sync --all

# নির্দিষ্ট একটি ভৌগোলিক রিজিয়নের সাথে ডেল্টা সিঙ্ক করা
php netfusion replication:sync --region=eu-west
```

### `replication:status`
সিস্টেমের লোকাল রিজিয়ন, সমস্ত রেজিস্টার্ড ক্লাস্টার নোডস, তাদের এন্ডপয়েন্ট, কনফিগার করা দেশসমূহ, নেটওয়ার্ক লেটেন্সি, হেলথ স্ট্যাটাস এবং পেন্ডিং চেঞ্জলগ ইভেন্ট কাউন্ট প্রদর্শন করে।
```bash
# ক্লাস্টার রেপ্লিকেশন ও নোড হেলথ স্ট্যাটাস দেখা
php netfusion replication:status
```

### `replication:crdt`
টার্মিনাল থেকে সরাসরি ডিস্ট্রিবিউটেড CRDT (PNCounter, GSet, ORSet, LWWRegister, MVRegister, LWWMap) স্টেট ও কনভার্জড ভ্যালু পরিদর্শন, মিউটেশন এবং টেস্ট করে।
```bash
# একটি নির্দিষ্ট CRDT অবজেক্টের বর্তমান মান ও স্টেট দেখা
php netfusion replication:crdt inspect global_cart

# PNCounter এ মান বৃদ্ধি (increment) করা
php netfusion replication:crdt increment page_views 5

# ORSet এ নতুন আইটেম যুক্ত করা
php netfusion replication:crdt add active_nodes "node-frankfurt"

# LWWRegister এ মান আপডেট করা
php netfusion replication:crdt set site_banner "Special Weekend Sale"
```

---

## ৩৩. রিয়েল-টাইম কোলাবোরেটিভ ডকুমেন্ট ও ওয়েবআরটিসি ইঞ্জিন (Real-Time Collab & WebRTC Engine)

### `collab:session`
চলমান সমস্ত সক্রিয় কোলাবোরেটিভ এডিটিং সেশন, কার্সর পজিশন ও মেম্বার কাউন্ট এবং সক্রিয় WebRTC সিগনালিং রুমসমূহের ওভারভিউ প্রদর্শন করে।
```bash
# সমস্ত চলমান কোলাবোরেটিভ সেশন ও ওয়েবআরটিসি রুম তালিকা প্রদর্শন
php netfusion collab:session

# নির্দিষ্ট একটি ডকুমেন্টের সক্রিয় সেশন, ব্যবহারকারী ও কার্সর তালিকা দেখা
php netfusion collab:session doc_project_specs
```

### `collab:inspect`
ডাটাবেস থেকে একটি নির্দিষ্ট ডকুমেন্টের বর্তমান কন্টেন্ট, মেটাডাটা এবং সম্পূর্ণ Operational Transformation (OT) অপারেশন হিস্ট্রি স্ট্রিম ও রিভিশন বিস্তারিত প্রদর্শন করে।
```bash
# ডকুমেন্টের কন্টেন্ট ও অপারেশন হিস্ট্রি বিস্তারিত ডাম্প করা
php netfusion collab:inspect doc_project_specs
```

---

## ৩৪. টাইম-সিরিজ ডাটাবেস ও অ্যানোমালি ডিটেকশন (Time-Series Database & Anomaly Detection)

### `tsdb:query`
টাইম-সিরিজ ডাটাবেস থেকে নির্দিষ্ট টাইম রেঞ্জে মেট্রিক্স কুয়েরি করে এবং মিন, ম্যাক্স, গড়, P95/P99 পরিসংখ্যান ও সাম্প্রতিক ডাটা পয়েন্ট প্রদর্শন করে।
```bash
# নির্দিষ্ট মেট্রিকের গত ১ ঘণ্টার ডাটা ও পরিসংখ্যান দেখা
php netfusion tsdb:query cpu_usage

# নির্দিষ্ট টাইম রেঞ্জ ও অ্যাগ্রিগেশন উইন্ডো সহ কুয়েরি করা
php netfusion tsdb:query http_requests --from=1700000000 --to=1700003600 --agg=avg --step=60
```

### `tsdb:downsample`
হাই-রেজোলিউশন র ডাটা পয়েন্টসমূহকে নির্দিষ্ট উইন্ডো সাইজে (যেমন: ৫ মিনিট, ১ ঘণ্টা) রোল-আপ টেবিলে ডাউনস্যাম্পল করে এবং ঐচ্ছিকভাবে মেয়াদোত্তীর্ণ র ডাটা মুছে ফেলে।
```bash
# সমস্ত মেট্রিক্সের জন্য ৫ মিনিটের রোল-আপ তৈরি করা
php netfusion tsdb:downsample --window=300

# রোল-আপ তৈরির পর ৭ দিনের পুরোনো র ডাটা প্রুন/মুছে ফেলা
php netfusion tsdb:downsample --metric=api_latency --window=3600 --prune
```

### `tsdb:anomaly`
টাইম-সিরিজ মেট্রিক্সের ওপর Z-Score, IQR, EWMA বা Ensemble অ্যালগরিদম চালিয়ে ট্রাফিক স্পাইক, লেটেন্সি অ্যানোমালি ও আউটলায়ার শনাক্ত করে।
```bash
# ডিফল্ট Z-Score পদ্ধতিতে অ্যানোমালি ডিটেকশন রান করা
php netfusion tsdb:anomaly api_latency

# IQR বা EWMA অ্যালগরিদম এবং কাস্টম থ্রেশহোল্ড সহ অ্যানোমালি যাচাই করা
php netfusion tsdb:anomaly queue_depth --method=ewma --threshold=2.5

# সমস্ত অ্যালগরিদমের মেজরিটি ভোটিং (Ensemble) সহ বিশ্লেষণ
php netfusion tsdb:anomaly server_cpu --method=ensemble
```

---

## ৩৫. জিরো-ট্রাস্ট সিক্রেট ভল্ট ও এনভেলপ এনক্রিপশন (Zero-Trust Secret Vault & Encryption)

### `vault:set`
টার্মিনাল থেকে সরাসরি একটি সিক্রেট ভ্যালু বা ডিকোডযোগ্য JSON অবজেক্টকে এফিমেরাল DEK এবং মাস্টার KEK দিয়ে এনভেলপ এনক্রিপ্ট করে নিরাপদভাবে ভল্টে সংরক্ষণ করে।
```bash
# সাধারণ স্ট্রিং সিক্রেট এনক্রিপ্ট করে সেভ করা
php netfusion vault:set stripe/live_key "sk_live_998877665544332211"

# মেটাডাটা ট্যাগ সহ সংরক্ষণ করা
php netfusion vault:set database/master_password "TopSecretPassw0rd!" --meta='{"env":"production","owner":"devops"}'

# স্ট্রাকচার্ড JSON ডাটা ভল্টে এনক্রিপ্ট করা
php netfusion vault:set aws/s3_creds '{"key":"AKIA...","secret":"wJalr..."}'
```

### `vault:get`
ভল্ট থেকে এনভেলপ এনক্রিপ্টেড সাইফারটেক্সট ফেচ করে, উপযুক্ত KEK ভার্শন দিয়ে DEK আনর‍্যাপ করে এবং প্লেইনটেক্সট বা স্ট্রাকচার্ড JSON আউটপুট ডিক্রিপ্ট করে প্রদর্শন করে।
```bash
# সিক্রেট ডিক্রিপ্ট করে টার্মিনালে দেখা
php netfusion vault:get stripe/live_key

# মেটাডাটা ও এনক্রিপশন ভার্শন বিবরণ সহ বিস্তারিত দেখা
php netfusion vault:get database/master_password --details

# JSON ফরম্যাটে আউটপুট প্রদর্শন
php netfusion vault:get aws/s3_creds --json
```

### `vault:rotate`
মাস্টার কি প্রোভাইডারে নতুন মাস্টার কি (KEK) ভার্শন তৈরি করে এবং ডাটাবেসের সমস্ত এনক্রিপ্টেড সিক্রেটের DEK-কে নতুন KEK দিয়ে জিরো-ডাউনটাইমে রির‍্যাপ (`rewrap()`) করে।
```bash
# মাস্টার কি রোটেশন ও ডাটাবেস সিক্রেট রির‍্যাপিং সম্পন্ন করা
php netfusion vault:rotate

# নির্দিষ্ট কি ভার্শনে ফোর্স রির‍্যাপ করা
php netfusion vault:rotate --version=3
```

### `vault:lease`
অন-ডিমান্ড ডায়নামিক ডাটাবেস ক্রেডেনশিয়াল বা এপিআই এক্সেস টোকেন লিজ তৈরি, তালিকা প্রদর্শন, রিনিউয়াল এবং রিভোক করে।
```bash
# ১ ঘণ্টার জন্য ডায়নামিক ডাটাবেস ইউজার ও পাসওয়ার্ড লিজ তৈরি করা
php netfusion vault:lease create database/app_pool --ttl=3600

# বিদ্যমান একটি সক্রিয় লিজের সময়সীমা বৃদ্ধি (Renew) করা
php netfusion vault:lease renew lease_66ed0f2a91b4 --ttl=7200

# একটি লিজ অবিলম্বে বাতিল/রিভোক (Revoke) করা
php netfusion vault:lease revoke lease_66ed0f2a91b4

# সিস্টেমে সক্রিয় সমস্ত লিজ ও তাদের মেয়াদ তালিকা দেখা
php netfusion vault:lease list
```

---

## ৩৬. মাল্টিভেরিয়েট এক্সপেরিমেন্টেশন ও ক্যানারি রোলআউট ইঞ্জিন (Multivariate Experiments & Canary Rollouts)

### `experiment:list`
সিস্টেমে রেজিস্টার্ড সমস্ত মাল্টিভেরিয়েট A/B/n এক্সপেরিমেন্ট, তাদের বর্তমান স্ট্যাটাস (`RUNNING`, `PAUSED`, `CONCLUDED`), ট্রাফিক পার্সেন্টেজ এবং ভ্যারিয়েন্ট তালিকা প্রদর্শন করে।
```bash
# নিবন্ধিত সমস্ত এক্সপেরিমেন্টের তালিকা দেখা
php netfusion experiment:list
```

### `experiment:eval`
নির্দিষ্ট কোনো ইউজার বা কাস্টম JSON কনটেক্সট অ্যাট্রিবিউটসের জন্য এক্সপেরিমেন্টের ভ্যারিয়েন্ট অ্যাসাইনমেন্ট এবং ডায়নামিক পেলোড টার্মিনালে টেস্ট ও সিমুলেট করে।
```bash
# নির্দিষ্ট ইউজারের জন্য ভ্যারিয়েন্ট অ্যাসাইনমেন্ট দেখা
php netfusion experiment:eval pricing_tier_2026 user_101

# কাস্টম জিও ও মেম্বারশিপ অ্যাট্রিবিউট সহ মূল্যায়ন
php netfusion experiment:eval header_cta_exp user_404 --attr='{"plan":"enterprise","country":"BD"}'
```

### `experiment:report`
একটি এক্সপেরিমেন্টের সমস্ত ভ্যারিয়েন্টের এক্সপোজার কাউন্ট, কনভার্সন রেট, রেভিনিউ এবং কন্ট্রোলের বিপরীতে স্ট্যাটিস্টিক্যাল Two-Sample Z-Test ($p$-value, $Z$-score, $95\%$ Confidence, Significant Winner) বিস্তারিত অ্যানালিটিক্স রিপোর্ট প্রিন্ট করে।
```bash
# এক্সপেরিমেন্টের পূর্ণাঙ্গ স্ট্যাটিস্টিক্যাল রিপোর্ট দেখা
php netfusion experiment:report pricing_tier_2026

# নির্দিষ্ট কনভার্সন গোলের জন্য রিপোর্ট বিশ্লেষণ
php netfusion experiment:report checkout_redesign --goal=purchase --alpha=0.01
```

### `canary:eval`
টার্মিনাল থেকে প্রোগ্রেসিভ ক্যানারি রোলআউট ফ্ল্যাগ টেস্ট করে অথবা কোনো ত্রুটির ক্ষেত্রে তাৎক্ষণিকভাবে ইমার্জেন্সি কিল-সুইচ (`kill-switch`) চালু বা রিসেট করে।
```bash
# নির্দিষ্ট ইউজারের জন্য ক্যানারি রোলআউট স্টেট পরীক্ষা করা
php netfusion canary:eval payment_v2 user_50

# জরুরি মুহূর্তে ক্যানারি ফ্ল্যাগের ট্রাফিক সম্পূর্ণ বন্ধ (Kill Switch) করা
php netfusion canary:eval payment_v2 dummy --kill

# কিল-সুইচ রিসেট করে ট্রাফিক পুনরুদ্ধার করা
php netfusion canary:eval payment_v2 dummy --reset
```

---

## Phase 54: Distributed Actor Model & Message Mesh

### `actor:tree`
সিস্টেমের চলমান এক্টর হায়ারার্কি ট্রি (Root, User, System Guardian ও চাইল্ড এক্টরদের পথ, স্টেট এবং হ্যান্ডেলড মেসেজ কাউন্ট) ভিজ্যুয়াল গ্রাফ আকারে প্রদর্শন করে।
```bash
# সমগ্র এক্টর সিস্টেমের হায়ারার্কি ট্রি দেখা
php netfusion actor:tree

# নির্দিষ্ট পাথের সাব-ট্রি পরিদর্শন করা
php netfusion actor:tree --path="/user/orders"
```

### `actor:send`
টার্মিনাল বা ব্যাচ প্রসেস থেকে সরাসরি যেকোনো এক্টর অ্যাড্রেসে অ্যাসিনক্রোনাস মেসেজ (`tell` বা `ask` মোডে) প্রেরণ ও রেসপন্স যাচাই করে।
```bash
# একটি এক্টরে ফায়ার-অ্যান্ড-ফরগেট মেসেজ পাঠানো
php netfusion actor:send "/user/order_processor" '{"type":"process_order","order_id":"ORD-992"}'

# রিকোয়েস্ট-রেসপন্স (ask) প্যাটার্নে সিঙ্ক্রোনাস রিটার্ন প্রাপ্তি
php netfusion actor:send "/user/inventory" '{"check_stock":"SKU-102"}' --ask --timeout=3
```

### `actor:dead-letters`
আনডেলিভার্ড মেসেজ বা ডেড লেটার কিউ (Dead Letter Queue) মনিটর, ইনস্পেক্ট ও প্রয়োজনে ক্লিয়ার করে।
```bash
# সর্বশেষ ৫০টি ডেড লেটার মেসেজ ও ফেইলিউর কারণ দেখা
php netfusion actor:dead-letters --limit=50

# ডেড লেটার কিউ খালি করা
php netfusion actor:dead-letters --clear
```

---

## Phase 55: BPMN 2.0 Workflow Engine & Saga Orchestrator

### `workflow:start`
একটি রেজিস্টার্ড BPMN 2.0 ওয়ার্কফ্লো ডেফিনিশন শুরু করে এবং প্রসেস ইন্সট্যান্স নির্বাহ করে।
```bash
# ডিফল্ট ভ্যারিয়েবল সহ একটি ওয়ার্কফ্লো শুরু করা
php netfusion workflow:start order_fulfillment '{"subtotal":500,"customer_id":"CUST-10"}'

# নির্দিষ্ট বিজনেস কী সহ ওয়ার্কফ্লো শুরু করা
php netfusion workflow:start loan_approval '{"amount":25000}' --key="LOAN-2026-009"
```

### `workflow:list`
সিস্টেমে চলমান, স্থগিত, সম্পূর্ণ বা ব্যর্থ সকল ওয়ার্কফ্লো ইন্সট্যান্সের লাইফসাইকেল স্ট্যাটাস ও সামারি তালিকা প্রদর্শন করে।
```bash
# সকল ওয়ার্কফ্লো ইন্সট্যান্সের তালিকা দেখা
php netfusion workflow:list

# শুধুমাত্র স্থগিত (SUSPENDED) বা অনুমোদন অপেক্ষমাণ ইন্সট্যান্স দেখা
php netfusion workflow:list --status=SUSPENDED

# নির্দিষ্ট ডেফিনিশনের ইন্সট্যান্স ফিল্টার করা
php netfusion workflow:list --def=order_fulfillment --limit=50
```

### `workflow:signal`
স্থগিত থাকা কোনো হিউম্যান টাস্ক (UserTask) বা এক্সটার্নাল ইভেন্টে সিগন্যাল পাঠিয়ে পুনরায় প্রসেস নির্বাহ শুরু করে।
```bash
# ইউজার টাস্কে অনুমোদন সিগন্যাল ও পেলোড পাঠানো
php netfusion workflow:signal wfi_9a2b8c1d manager_review '{"approved":true,"notes":"Approved by VP"}'
```

### `workflow:history`
একটি নির্দিষ্ট ওয়ার্কফ্লো ইন্সট্যান্সের প্রতিটি নোড এক্সিকিউশনের বিস্তারিত অডিট ট্রেল, সময়কাল এবং ইনপুট/আউটপুট হিস্ট্রি প্রদর্শন করে।
```bash
# প্রসেস ইন্সট্যান্সের এক্সিকিউশন টাইমলাইন ও অডিট ট্রেল দেখা
php netfusion workflow:history wfi_9a2b8c1d
```

---

## Phase 56: In-Memory Data Grid (IMDG) & Near-Cache Cluster

### `grid:nodes`
ইন-মেমরি ডাটা গ্রিড ক্লাস্টারের সকল সক্রিয় মেম্বার নোড, তাদের হোস্ট, পোর্ট, ওয়েট, স্টেটাস এবং হার্টবিট টাইম প্রদর্শন করে।
```bash
# ক্লাস্টার মেম্বার নোডস ও টপোলজি তালিকা দেখা
php netfusion grid:nodes

# নির্দিষ্ট ক্লাস্টারের টপোলজি পরিদর্শন করা
php netfusion grid:nodes --cluster=prod-grid
```

### `grid:get`
একটি ডিস্ট্রিবিউটেড ম্যাপ থেকে নির্দিষ্ট কী-এর মান (Value), পার্টিশন আইডি, প্রাইমারি ওনার নোড এবং টিয়ার ল্যাটেন্সি সহ টার্মিনালে তুলে আনে।
```bash
# ম্যাপ থেকে মান রিড করা
php netfusion grid:get users u100

# ক্যাশ মেমরি থেকে সেশন ডেটা দেখা
php netfusion grid:get user_sessions sess_99a8b7
```

### `grid:put`
ইন-মেমরি ডিস্ট্রিবিউটেড ম্যাপে একটি কী-ভ্যালু বা JSON অবজেক্ট সংরক্ষণ করে (ঐচ্ছিক TTL সহ)।
```bash
# সাধারণ স্ট্রিং বা ভ্যালু রাইট করা
php netfusion grid:put settings site_name "NetFusion Enterprise"

# JSON অবজেক্ট ও ৬০ সেকেন্ড TTL সহ সংরক্ষণ
php netfusion grid:put user_tokens tok_1 '{"user_id":101,"scope":"admin"}' --ttl=60
```

### `grid:stats`
সমগ্র IMDG ক্লাস্টারের L1 Near-Cache হিট, L2 পার্টিশন হিট, মিস রেট, মোট এন্ট্রি কাউন্ট এবং পারফরম্যান্স রেশিও সমৃদ্ধ ড্যাশবোর্ড টেবিল প্রিন্ট করে।
```bash
# ক্লাস্টারের সামগ্রিক পারফরম্যান্স ও হিট রেশিও দেখা
php netfusion grid:stats
```

---

## Phase 57: Real-Time Complex Event Processing (CEP) & Streaming Engine

### `cep:rules`
রেজিস্টার্ড সকল CEP রুলস, তাদের সোর্স স্ট্রিম টপিক, সক্রিয়/নিষ্ক্রিয় স্টেটাস, প্রসেস করা ইভেন্টের সংখ্যা, ডিটেক্টেড প্যাটার্ন ও ট্রিগার হওয়া অ্যালার্টের বিস্তারিত টেবিল প্রদর্শন করে।
```bash
# সকল সিইপি রুলসের তালিকা দেখা
php netfusion cep:rules

# নির্দিষ্ট স্ট্রিমের রুলস ফিল্টার করা
php netfusion cep:rules --stream=iot_sensor

# শুধুমাত্র সক্রিয় রুলস দেখা
php netfusion cep:rules --enabled
```

### `cep:ingest`
টার্মিনাল থেকে সরাসরি একটি ইভেন্ট বা JSON পেলোড CEP ইঞ্জিন ও রুল পাইপলাইনে ইনজেস্ট করে এবং তাৎক্ষণিক ফলাফল/ম্যাচ দেখে।
```bash
# সাধারণ ইভেন্ট ইনজেস্ট করা
php netfusion cep:ingest orders '{"order_id":"ORD-101","amount":7500.0,"user_id":"usr_42"}'

# আইওটি সেন্সর মেট্রিক ইনজেস্ট করা
php netfusion cep:ingest iot_sensor '{"sensor_id":"s1","temperature":62.5,"unit":"C"}'
```

### `cep:stats`
CEP ইঞ্জিনের মোট আপটাইম, মোট ইনজেস্টেড ইভেন্ট, ম্যাচ/অ্যালার্টের সংখ্যা, অ্যাক্টিভ উইন্ডোজ এবং NFA কম্পিউটেশন পাথের রিয়েল-টাইম মেট্রিক্স টেবিল প্রদর্শন করে।
```bash
# সিইপি ইঞ্জিনের সামগ্রিক মেট্রিক্স ও থ্রুপুট দেখা
php netfusion cep:stats
```

### `cep:alerts`
ডিটেক্টেড প্যাটার্ন অ্যানোমালি, থ্রেশহোল্ড ভায়োলেশন এবং রিসেন্ট ইনসিডেন্ট অ্যালার্ট হিস্ট্রি ডাটাবেস থেকে তুলে আনে।
```bash
# রিসেন্ট অ্যালার্টের তালিকা দেখা
php netfusion cep:alerts

# নির্দিষ্ট রুলের অ্যালার্ট দেখা
php netfusion cep:alerts --rule=fraud_detection_rule --limit=50
```

---

## Phase 58: Enterprise Zero-Trust Service Mesh & Sidecar Proxy

### `mesh:services`
সার্ভিস মেশে নিবন্ধিত সকল মাইক্রোসার্ভিস ইনস্ট্যান্স, হোস্ট আইপি, পোর্ট, সাবসেট/ভার্শন (v1/v2), SPIFFE আইডেন্টিটি, অ্যাক্টিভ কানেকশন সংখ্যা এবং তাদের হেলথ স্ট্যাটাস (HEALTHY/EJECTED) টেবিল আকারে প্রদর্শন করে।
```bash
# সকল রেজিস্টার্ড সার্ভিস ও ইনস্ট্যান্সের তালিকা দেখা
php netfusion mesh:services

# নির্দিষ্ট সার্ভিসের ইনস্ট্যান্স ফিল্টার করা
php netfusion mesh:services payment-service
```

### `mesh:routes`
সার্ভিস মেশে কনফিগার করা ট্রাফিক রাউটস, পাথ প্রিফিক্স, মেথডস, ক্যানারি সাবসেট স্প্লিটিং ওয়েটস (যেমন: v1: 80%, v2: 20%), রিট্রাই পলিসি ও সার্কিট ব্রেকার কনফিগারেশন প্রদর্শন করে।
```bash
# সকল ট্রাফিক রাউট রুলস দেখা
php netfusion mesh:routes

# নির্দিষ্ট ডেস্টিনেশন সার্ভিসের রাউট দেখা
php netfusion mesh:routes --service=order-service
```

### `mesh:certs`
মেশ সার্টিফিকেট অথরিটি (Internal Root CA) এবং ওয়ার্কলোডসমূহকে ইস্যু করা mTLS X.509 সার্টিফিকেট, SPIFFE ID, ইস্যুর সময় ও এক্সপায়ারেশন টাইম প্রদর্শন করে।
```bash
# সকল ইস্যুকৃত mTLS সার্টিফিকেটের তালিকা দেখা
php netfusion mesh:certs

# নির্দিষ্ট সার্ভিসের সার্টিফিকেট দেখা
php netfusion mesh:certs --service=billing-service
```

### `mesh:stats`
সার্ভিস মেশের সামগ্রিক রিকোয়েস্ট কাউন্ট, সাকসেস/এরর রেট, সার্কিট ব্রেকার স্টেটাস, কোয়ারেন্টাইন/ইজেকশন কাউন্ট এবং আপটাইম মেট্রিক্স ড্যাশবোর্ড প্রদর্শন করে।
```bash
# মেশ ক্লাস্টার মেট্রিক্স ও থ্রুপুট দেখা
php netfusion mesh:stats
```

---

## Phase 59: Dynamic Business Rule Engine & DMN 1.3 Decision Tables

### `rule:list`
সিস্টেমে রেজিস্টার্ড সমস্ত বিজনেস রুলস, তাদের Salience (অগ্রাধিকার), প্যাটার্ন প্রকারভেদ, সক্রিয়/নিষ্ক্রিয় স্টেটাস এবং ডেসক্রিপশন টেবিল আকারে প্রদর্শন করে।
```bash
# সকল বিজনেস রুলের তালিকা দেখা
php netfusion rule:list

# শুধুমাত্র সক্রিয় রুলস প্রদর্শন
php netfusion rule:list --enabled
```

### `rule:eval`
টার্মিনাল থেকে সরাসরি একটি ফ্যাক্ট (JSON পেলোড সহ) ওয়ার্কিং মেমরিতে ইনজেস্ট করে এবং RETE নেটওয়ার্কে ফরোয়ার্ড চেইনিং এক্সিকিউশন ট্রিগার করে।
```bash
# সাধারণ অর্ডার ফ্যাক্ট ইনজেস্ট ও রুল ফায়ারিং
php netfusion rule:eval Order '{"id":"ORD-501","customerId":"CUST-10","total":750.0}'

# ইউজার প্রোফাইল ফ্যাক্ট ইনপুট
php netfusion rule:eval Customer '{"id":"CUST-10","tier":"VIP","spending":5000}'
```

### `decision:tables`
সিস্টেমে রেজিস্টার্ড সমস্ত DMN 1.3 Decision Table, তাদের Hit Policy (`FIRST`, `UNIQUE`, `COLLECT_SUM`, ইত্যাদি), ইনপুট/আউটপুট ক্লজ এবং রুল কাউন্ট প্রদর্শন করে।
```bash
# সকল ডিসিশন টেবিলের তালিকা দেখা
php netfusion decision:tables
```

### `decision:eval`
নির্দিষ্ট একটি DMN Decision Table-এর ওপর JSON ইনপুট ভ্যারিয়েবল প্রদান করে FEEL রুল ম্যাচিং ও আউটপুট রেজাল্ট কম্পিউটেশন চালায়।
```bash
# লোন এলিজিবিলিটি ডিসিশন টেবিল টেস্ট করা
php netfusion decision:eval loan_eligibility '{"creditScore":780,"annualIncome":85000}'

# ডিসকাউন্ট রুল ম্যাট্রিক্স ইভ্যালুয়েশন
php netfusion decision:eval master_tier_pricing '{"tier":"VIP","volume":1500}'
```

---

## Phase 60: Enterprise Semantic Vector Search, HNSW Indexing & RAG Pipeline

### `vector:collections`
সিস্টেমের সমস্ত ভেক্টর কালেকশন, তাদের ডাইমেনশন, ডিস্ট্যান্স মেট্রিক (`cosine`, `euclidean`, `dot_product`) এবং মোট ডকুমেন্টস/চ্যাঙ্ক সংখ্যা প্রদর্শন করে। নতুন কালেকশন তৈরির অপশনও প্রদান করে।
```bash
# সকল ভেক্টর কালেকশনের তালিকা দেখা
php netfusion vector:collections

# নতুন ৬৪-ডাইমেনশন কোসাইন কালেকশন তৈরি
php netfusion vector:collections --create=support_kb --dim=64 --metric=cosine
```

### `vector:index`
টার্মিনাল থেকে সরাসরি টেক্সট বা ফাইল ইনজেস্ট করে স্বয়ংক্রিয়ভাবে চাংকিং, এম্বেডিং জেনারেশন ও HNSW গ্রাফ ইনডেক্সে যুক্ত করে।
```bash
# সরাসরি টেক্সট ইনডেক্স করা
php netfusion vector:index support_kb --id=doc_01 --text="Refund policy allows full returns within 30 days of purchase."

# সম্পূর্ণ ফাইল ইনজেস্ট ও চাংকিং
php netfusion vector:index support_kb --file=docs/architecture.md
```

### `vector:search`
নির্দিষ্ট কালেকশনের ওপর ন্যাচারাল ল্যাঙ্গুয়েজ বা সেমান্টিক কুয়েরি চালিয়ে Top-K প্রাসঙ্গিক ডকুমেন্টস ও তাদের রেলেভ্যান্স স্কোর প্রদর্শন করে।
```bash
# হাইব্রিড সার্চ (Dense Vector + Sparse BM25)
php netfusion vector:search support_kb "How do I request a refund?" --k=5

# শুধুমাত্র ডেন্স ভেক্টর বা স্পার্স সার্চ
php netfusion vector:search support_kb "security architecture" --type=dense --k=3
```

### `rag:query`
Retrieval-Augmented Generation (RAG) পাইপলাইনের মাধ্যমে নলেজ বেস থেকে প্রাসঙ্গিক তথ্য সংগ্রহ করে গ্রাউন্ডেড উত্তর ও সোর্স সাইটেশন তৈরি করে।
```bash
# নলেজ বেসের ওপর RAG কুয়েরি
php netfusion rag:query support_kb "What is the return window for products?"

# ৩টি টপ কনটেক্সট চ্যাঙ্ক সহ কুয়েরি
php netfusion rag:query support_kb "Explain zero trust architecture" --k=3
```

---

## Phase 61: Enterprise Web Application Firewall (WAF) & L7 Security Gateway

### `waf:stats`
ডাব্লিউএএফ ট্র্যাফিক মেট্রিক্স এবং অ্যাটাক সামারি প্রদর্শন করে। মোট ইন্সপেক্ট করা রিকোয়েস্ট, পাস করা ট্র্যাফিক, ব্লক করা অ্যাটাক এবং চ্যালেঞ্জ করা রিকোয়েস্টের সংখ্যা প্রদর্শন করে।
```bash
# WAF ট্র্যাফিক মেট্রিক্স এবং অ্যাটাক ড্যাশবোর্ড
php netfusion waf:stats
```

### `waf:events`
সাম্প্রতিক সিকিউরিটি থ্রেট ইভেন্ট লগ, অ্যাটাক টাইপ (SQLi, XSS, RCE, Bad Bot, Rate Limit), ট্রিগার হওয়া রুল আইডি এবং ক্লায়েন্ট আইপির ফরেনসিক লগ প্রদর্শন করে।
```bash
# সর্বশেষ ২০টি থ্রেট ইভেন্ট দেখা
php netfusion waf:events

# নির্দিষ্ট সংখ্যক (যেমন ৫০টি) থ্রেট ইভেন্ট দেখা
php netfusion waf:events --limit=50
```

### `waf:block`
সিস্টেম অ্যাডমিনিস্ট্রেটরদের জন্য নির্দিষ্ট আইপি অ্যাড্রেস বা ক্ষতিকারক ক্লায়েন্টকে কাস্টম টিটিএল (TTL) এবং কারণ সহ তাৎক্ষণিকভাবে ব্ল্যাকলিস্টে যুক্ত করার সুবিধা।
```bash
# ১ ঘণ্টার জন্য নির্দিষ্ট আইপি ব্লক করা
php netfusion waf:block 198.51.100.45 --reason="Repeated Port Scanning" --ttl=3600

# স্থায়ীভাবে কোনো আইপি ব্যান করা
php netfusion waf:block 203.0.113.88 --reason="DDoS Attack Vector" --ttl=0
```

### `waf:unblock`
ব্ল্যাকলিস্ট বা অটো-ব্যান তালিকায় থাকা কোনো আইপি অ্যাড্রেসকে আনব্লক করে স্বাভাবিক এক্সেস পুনরায় প্রদান করে।
```bash
# আইপি আনব্লক করা
php netfusion waf:unblock 198.51.100.45
```

---

## Phase 62: Enterprise Data Warehouse, OLAP Columnar Storage & Dynamic BI Pivot Reporting

### `dw:status`
ডাটা ওয়্যারহাউসের সামগ্রিক স্বাস্থ্য ও মেট্রিক্স প্রদর্শন করে। রেজিস্টার্ড ডাইমেনশন, ফ্যাক্ট টেবিল, কিউব মেটেরিয়ালাইজেশন এবং স্টোরেজ কনফিগারেশনের বিস্তারিত তথ্য টার্মিনালে টেবিল আকারে প্রদর্শন করে।
```bash
# ডাটা ওয়্যারহাউস স্ট্যাটাস ও মেট্রিক্স প্রদর্শন
php netfusion dw:status
```

### `dw:pivot`
ター্মিনাল থেকে সরাসরি যেকোনো নিবন্ধিত OLAP কিউবের ওপর ডায়নামিক পিভট কুয়েরি এক্সিকিউট করে এবং ক্রস-ট্যাবুলার মার্কডাউন টেবিল আউটপুট প্রদান করে।
```bash
# কিউবের ওপর পিভট টেবিল জেনারেট করা
php netfusion dw:pivot sales_cube --rows=region --cols=category --measure=revenue --agg=SUM
```

---

## Phase 63: Enterprise Payment Gateway Orchestrator, Smart Routing & PCI Vault

### `payment:status`
সকল রেজিস্টার্ড পেমেন্ট গেটওয়ের রিয়েল-টাইম স্বাস্থ্য, সার্কিট ব্রেকার স্টেট (HEALTHY / CIRCUIT_TRIPPED), সাকসেস রেট এবং PCI-DSS টোকেনাইজেশন ভল্ট স্ট্যাটাস প্রদর্শন করে।
```bash
# পেমেন্ট গেটওয়ে এবং ভল্ট স্ট্যাটাস পরীক্ষা
php netfusion payment:status
```

### `payment:reconcile`
ব্যাংক অথবা পেমেন্ট প্রসেসর সেটেলমেন্ট স্টেটমেন্টের সাথে ফ্রেমওয়ার্কের ইন্টারনাল ট্রানজ্যাকশন লেজার রিকনসিলিয়েশন রান করে এবং মিসম্যাচ ও ফি ভ্যারিয়েন্স সামারি তৈরি করে।
```bash
# নির্দিষ্ট প্রসেসরের সেটেলমেন্ট স্টেটমেন্ট রিকনসিলিয়েশন
php netfusion payment:reconcile stripe

# অপশন ফ্ল্যাগ ব্যবহার করে চালানো
php netfusion payment:reconcile --processor=bkash
```

---

## Phase 64: Enterprise Search & Discovery Engine, Vector+BM25 Hybrid Reranker & Auto-Suggest

### `search:index`
সার্চ ইনডেক্সের সামগ্রিক স্বাস্থ্য ও পরিসংখ্যান প্রদর্শন করে। ইনডেক্স করা ডকুমেন্টের মোট সংখ্যা, পোস্টিং টার্ম কাউন্ট এবং এভারেজ ডকুমেন্ট লেন্থ টার্মিনালে প্রদর্শন করে।
```bash
# সার্চ ইনডেক্স পরিসংখ্যান প্রদর্শন
php netfusion search:index products
```

### `search:query`
টার্মিনাল থেকে সরাসরি সার্চ ইনডেক্সের ওপর ফুল-টেক্সট / হাইব্রিড কুয়েরি এক্সিকিউট করে এবং রিলেভেন্স স্কোর সহ সার্চ রেজাল্ট প্রদর্শন করে।
```bash
# সার্চ কুয়েরি রান করা
php netfusion search:query products "Dell Laptop"
```

### `search:suggest`
নির্দিষ্ট প্রিফিক্স কিওয়ার্ডের ওপর ভিত্তি করে রিয়েল-টাইম অটো-কমপ্লিট সাজেশন ও ফ্রিকোয়েন্সি স্কোর প্রদর্শন করে।
```bash
# প্রিফিক্স অটো-সাজেস্ট প্রদর্শন
php netfusion search:suggest products "app"
```

---

## Phase 65: Enterprise Multi-Model Graph Database Engine, Cypher Parser & Knowledge Graph Reasoner

### `graph:query`
টার্মিনাল থেকে সরাসরি গ্রাফ ডাটাবেসের ওপর ডিক্লারেটিভ ওপেন-সাইফার (Cypher) কুয়েরি এক্সিকিউট করে এবং ফিল্টার্ড রেকর্ডসমূহ টেবিল আকারে প্রদর্শন করে।
```bash
# সাইফার কুয়েরি রান করা
php netfusion graph:query "MATCH (u:User)-[:FRIEND_OF]->(f:User) WHERE u.name = 'Alice' RETURN f.name, f.age"

# নির্দিষ্ট গ্রাফের ওপর কুয়েরি রান করা
php netfusion graph:query "MATCH (n) RETURN count(n) AS total_nodes" knowledge_graph
```

### `graph:traverse`
গ্রাফের যেকোনো দুটি নোডের মধ্যকার শর্টেস্ট পাথ (Dijkstra) বা পাথ সিকোয়েন্স ও টোটাল ওয়েট টার্মিনালে প্রদর্শন করে।
```bash
# নোড u1 থেকে u3 এর শর্টেস্ট পাথ প্রদর্শন
php netfusion graph:traverse u1 u3

# নির্দিষ্ট গ্রাফে পাথ অনুসন্ধান
php netfusion graph:traverse startNode targetNode social_network
```

### `graph:stats`
নির্দিষ্ট গ্রাফ ডাটাবেসের নোড সংখ্যা, এজ সংখ্যা, কানেক্টেড কম্পোনেন্টস এবং টপ পেইজর‍্যাঙ্ক (PageRank) ইনফ্লুয়েন্সার নোডসমূহের মেট্রিক্স প্রদর্শন করে।
```bash
# গ্রাফ মেট্রিক্স ও পেইজর‍্যাঙ্ক প্রদর্শন
php netfusion graph:stats

# নির্দিষ্ট গ্রাফের পরিসংখ্যান
php netfusion graph:stats social_network
```

---

## Phase 66: Enterprise Distributed Task Scheduler, Quartz Cron Engine & DAG Pipeline Engine

### `schedule:run`
সার্ভার ক্রন্ট্যাব বা টাস্ক শিডিউলার দ্বারা প্রতি মিনিটে নির্বাহের জন্য ডিজাইন করা হয়েছে। এটি বর্তমান সময়ে ডিউ (due) থাকা সমস্ত শিডিউলড কাজ, কনসোল কমান্ড এবং ক্লোজার টাস্ক নির্বাহ করে এবং এক্সিকিউশন টাইম ও স্টেটাস প্রদর্শন করে।
```bash
# নির্ধারিত সময়ে ডিউ থাকা সমস্ত টাস্ক এক্সিকিউট করা
php netfusion schedule:run
```

### `schedule:list`
ফ্রেমওয়ার্কে নিবন্ধিত সমস্ত ক্রন জব, তাদের ফ্রিকোয়েন্সি/ক্রন এক্সপ্রেশন এবং ওভারল্যাপিং লক স্টেটাস একটি সুন্দর ফরম্যাটেড টেবিলে প্রদর্শন করে।
```bash
# নিবন্ধিত সকল শিডিউলড টাস্কের তালিকা দেখা
php netfusion schedule:list
```

### `schedule:dag`
টার্মিনাল থেকে সরাসরি একটি নির্দিষ্ট নামাঙ্কিত ডাইরেক্টেড অ্যাসাইক্লিক গ্রাফ (DAG) ডিপেন্ডেন্সি ওয়ার্কফ্লো পাইপলাইন এক্সিকিউট করে এবং প্রতিটি স্টেজের নোড স্টেটাস ও এক্সিকিউশন টাইম প্রিন্ট করে।
```bash
# ডিফল্ট বা নির্দিষ্ট DAG পাইপলাইন রান করা
php netfusion schedule:dag etl_pipeline

# কাস্টম ডেটা প্রসেসিং ওয়ার্কফ্লো নির্বাহ করা
php netfusion schedule:dag nightly_data_sync
```



---

## ২৪. এন্টারপ্রাইজ API গেটওয়ে কমান্ড (Enterprise API Gateway Commands)

API গেটওয়ের রুট এবং আপস্ট্রিম ব্যাকেন্ডের হেলথ পরিচালনার জন্য কমান্ড গ্রুপ।

---

### `gateway:routes`

কনফিগার করা সমস্ত গেটওয়ে রুটের ট্যাবুলার ডিসপ্লে — প্রতিটি রুটের ID, path prefix, hostname, পদ্ধতি, আপস্ট্রিম পুল এবং স্বাস্থ্য অবস্থা সহ।

```bash
php netfusion gateway:routes
```

**উদাহরণ আউটপুট:**
```
NetFusion Enterprise API Gateway — Routes
=========================================

ID              PATH PREFIX    HOST        METHODS     UPSTREAMS   HEALTHY
---------------------------------------------------------------------------
users_route     /api/users     *           GET,POST    3           3/3 ✓
orders_route    /api/orders    *           GET         2           2/2 ✓
admin_route     /admin         admin.host  *           1           1/1 ✓

Total: 3 routes configured
```

**বিকল্প (Options):**

| Option | বিবরণ |
|--------|-------|
| `--format=json` | JSON ফরম্যাটে আউটপুট (CI/CD pipeline integration) |
| `--pool=<pool_name>` | নির্দিষ্ট upstream pool-এর রুট ফিল্টার করে দেখায় |

**ব্যবহারের ক্ষেত্র:**
- Production-এ active routes verify করা
- Load balancer configuration অডিট করা
- API endpoint documentation generation

---

### `gateway:health`

সমস্ত কনফিগার করা আপস্ট্রিম ব্যাকেন্ডের সক্রিয় হেলথ প্রোব চালায় এবং ফলাফল রিপোর্ট করে। সমস্ত আপস্ট্রিম healthy হলে exit code `0`, অন্যথায় `1` দেয় (monitoring integration-এর জন্য উপযোগী)।

```bash
php netfusion gateway:health
```

**উদাহরণ আউটপুট:**
```
NetFusion Enterprise API Gateway — Health Check
===============================================

Checking upstream health across 3 pools...

Pool: users_pool
  ✓ http://users-svc:8001    [180ms]  HEALTHY
  ✓ http://users-svc:8002    [95ms]   HEALTHY
  ✓ http://users-svc:8003    [210ms]  HEALTHY

Pool: orders_pool
  ✓ http://orders-svc:9001   [120ms]  HEALTHY
  ✗ http://orders-svc:9002   [timeout] UNHEALTHY

Pool: admin_pool
  ✓ http://admin-svc:7001    [55ms]   HEALTHY

Summary: 5/6 upstreams healthy
```

**বিকল্প (Options):**

| Option | বিবরণ |
|--------|-------|
| `--pool=<pool_name>` | নির্দিষ্ট pool-এর জন্য হেলথ চেক করে |
| `--timeout=<seconds>` | প্রতিটি probe-এর connect timeout (default: 5) |
| `--path=<uri>` | Custom health check URI path (default: config মান) |
| `--format=json` | JSON ফরম্যাটে আউটপুট |

**Exit Codes:**

| Code | অর্থ |
|------|-----|
| `0`  | সমস্ত আপস্ট্রিম সুস্থ |
| `1`  | এক বা একাধিক আপস্ট্রিম unhealthy |

**ব্যবহারের ক্ষেত্র:**
- Kubernetes readiness/liveness probe integration
- Deployment pipeline-এ upstream verification
- Cron-based health monitoring alerting
- Circuit breaker state pre-warm

---

**সংশ্লিষ্ট কনফিগারেশন:** [`config/gateway.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/gateway.php)

```php
return [
    'default_load_balancer' => 'round_robin',  // round_robin, weighted, least_connections, ip_hash, latency_aware
    'timeout'               => ['connect' => 5, 'read' => 30],
    'circuit_breaker'       => ['failure_threshold' => 5, 'cooldown_seconds' => 30],
    'rate_limit'            => ['enabled' => true, 'default_limit' => 1000, 'window_seconds' => 60],
    'health_check'          => ['path' => '/health', 'interval_seconds' => 30],
    'cors'                  => ['enabled' => false, 'origins' => ['*']],
];
```

---

## 68. Double-Entry Accounting Engine Commands

### `accounting:report`

ফিন্যান্সিয়াল রিপোর্ট (Trial Balance, Income Statement/P&L, Balance Sheet) তৈরি করে।

```bash
# Trial balance রিপোর্ট
php netfusion accounting:report --type=trial

# নির্দিষ্ট তারিখ পর্যন্ত Trial balance
php netfusion accounting:report --type=trial --to=2026-12-31

# Income Statement (Profit & Loss)
php netfusion accounting:report --type=pl --from=2026-01-01 --to=2026-12-31

# Balance Sheet
php netfusion accounting:report --type=balance --to=2026-12-31
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--type=<trial\|pl\|balance>` | রিপোর্টের ধরন | `trial` |
| `--from=<YYYY-MM-DD>` | রিপোর্টের শুরু তারিখ (P&L এর জন্য) | চলতি বছরের ০১ জানুয়ারি |
| `--to=<YYYY-MM-DD>` | রিপোর্টের শেষ তারিখ | আজকের তারিখ |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/accounting.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/accounting.php)

---

## 69. Enterprise Zero-Trust Service Mesh Commands

### `mesh:topology`

সার্ভিস মেশের টপোলজি, সক্রিয় ওয়ার্কলোড নোডস এবং সার্ভিস ডিপেনডেন্সি গ্রাফ প্রদর্শন করে। এটি টেক্সট টেবিল, JSON অথবা Graphviz DOT ফরম্যাটে আউটপুট প্রদান করতে পারে।

```bash
# টেক্সট টেবিল ফরম্যাটে টপোলজি ও ডিপেনডেন্সি তালিকা
php netfusion mesh:topology

# Graphviz DOT ফরম্যাটে এক্সপোর্ট (visual graph তৈরির জন্য)
php netfusion mesh:topology --format=dot

# JSON ফরম্যাটে ফুল মেশ স্টেট এক্সপোর্ট
php netfusion mesh:topology --format=json
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--format=<text\|json\|dot>` | আউটপুট ফরম্যাট নির্ধারণ | `text` |

---

### `mesh:cert`

সার্ভিস মেশ ওয়ার্কলোডের জন্য SPIFFE X.509 SVID সার্টিফিকেট ইস্যু, স্ট্যাটাস পর্যবেক্ষণ বা ম্যানুয়াল রোটেশন সম্পন্ন করে।

```bash
# ডিফল্ট সার্ভিস সার্টিফিকেট স্ট্যাটাস ও ভ্যালিডিটি প্রদর্শন
php netfusion mesh:cert

# নির্দিষ্ট সার্ভিসের সার্টিফিকেট পরীক্ষা বা নতুন ইস্যু
php netfusion mesh:cert --service=order-service

# নির্দিষ্ট সার্ভিসের সার্টিফিকেট তাৎক্ষণিক ফোর্স রোটেট করা
php netfusion mesh:cert --service=payment-service --rotate
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--service=<name>` | টার্গেট সার্ভিস বা ওয়ার্কলোডের নাম | `app` |
| `--rotate` | সার্টিফিকেট অবিলম্বে পুনর্নবায়ন (force rotate) করার ফ্ল্যাগ | - |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/service_mesh.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/service_mesh.php)

---

## 70. Enterprise Post-Quantum Cryptography (PQC) Commands

### `pqc:benchmark`

পোস্ট-কোয়ান্টাম ক্রিপ্টোগ্রাফির মূল প্রিমেটিভস (ML-KEM-768 কি-এনক্যাপসুলেশন, ML-DSA-44 ডিজিটাল সিগনেচার, এবং X25519+ML-KEM হাইব্রিড কি-এক্সচেঞ্জ)-এর পারফরম্যান্স বেঞ্চমার্ক ও মিলি-সেকেন্ড ল্যাটেন্সি প্রদর্শন করে।

```bash
# টেক্সট টেবিল ফরম্যাটে PQC বেঞ্চমার্ক মেট্রিক্স প্রদর্শন
php netfusion pqc:benchmark

# JSON ফরম্যাটে ফুল পারফরম্যান্স রিপোর্ট এক্সপোর্ট
php netfusion pqc:benchmark --format=json
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--format=<text\|json>` | আউটপুট ফরম্যাট নির্ধারণ | `text` |

---

### `pqc:key`

পোস্ট-কোয়ান্টাম ও হাইব্রিড কিপেয়ার জেনারেট এবং পাবলিক/সিক্রেট কি ইন্সপেক্ট করে।

```bash
# ডিফল্ট হাইব্রিড কিপেয়ার (X25519 + ML-KEM-768) জেনারেট করা
php netfusion pqc:key

# পিওর ল্যাটিস ML-KEM কিপেয়ার তৈরি
php netfusion pqc:key --type=kem --alg=ml-kem-768

# পিওর ল্যাটিস ML-DSA ডিজিটাল সিগনেচার কিপেয়ার তৈরি
php netfusion pqc:key --type=dsa --alg=ml-dsa-44
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--type=<hybrid\|kem\|dsa>` | ক্রিপ্টোগ্রাফিক কিপেয়ারের ধরন | `hybrid` |
| `--alg=<name>` | অ্যালগরিদম ভ্যারিয়েন্ট (যেমন `ml-kem-512`, `ml-kem-768`, `ml-dsa-44`) | ডিফল্ট অ্যালগরিদম |

---

### `pqc:vault`

কোয়ান্টাম-নিরাপদ এনভেলপ ভল্টের সাহায্যে যেকোনো সংবেদনশীল স্ট্রিং বা ডেটা এনক্রিপ্ট এবং ডিক্রিপ্ট করে।

```bash
# সিক্রেট স্ট্রিং এনভেলপ এনক্রিপ্ট করা
php netfusion pqc:vault --encrypt="My Super Secret Password"

# এনক্রিপ্টেড JSON এনভেলপ ডিক্রিপ্ট করা
php netfusion pqc:vault --decrypt='{"v":1,"alg":"HYBRID-X25519-MLKEM768-AES256GCM",...}'
```

**Options:**

| Option | বিবরণ |
|--------|-------|
| `--encrypt="<text>"` | এনক্রিপ্ট করার জন্য প্লেইনটেক্সট স্ট্রিং |
| `--decrypt="<json>"` | ডিক্রিপ্ট করার জন্য ভল্ট এনভেলপ JSON |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/pqc.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/pqc.php)

---

## 71. Enterprise Autonomous Disaster Recovery (DR) Commands

### `dr:status`

মাল্টি-রিজিয়ন ক্লাস্টার নোডস, বর্তমান প্রাইমারি রিজিয়ন, ক্লাস্টার ইপক, মেজরিটি কোরাম স্টেটাস এবং কন্টিনিউয়াস ডেটা প্রোটেকশন (CDP) আরপিও রেপ্লিকেশন ল্যাগ প্রদর্শন করে।

```bash
# টেক্সট টেবিল ফরম্যাটে ডিআর ক্লাস্টার স্টেটাস প্রদর্শন
php netfusion dr:status

# JSON ফরম্যাটে ফুল ক্লাস্টার টেলিমেট্রি এক্সপোর্ট
php netfusion dr:status --format=json
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--format=<text\|json>` | আউটপুট ফরম্যাট নির্ধারণ | `text` |

---

### `dr:failover`

স্বয়ংক্রিয় বা নিয়ন্ত্রিতভাবে স্ট্যান্ডবাই নোডকে প্রাইমারি পদে উন্নীত (promote) করে, পুরনো প্রাইমারিকে স্প্লিট-ব্রেন প্রতিরোধে ফেন্স (fence) করে এবং ডিএনএস ট্রাফিক ডাইভারশন সম্পন্ন করে।

```bash
# উপলব্ধ প্রথম উপযুক্ত স্ট্যান্ডবাই নোডে স্বয়ংক্রিয় ফেইলওভার
php netfusion dr:failover

# নির্দিষ্ট স্ট্যান্ডবাই নোডে ফেইলওভার
php netfusion dr:failover --target=node-eu-west-1

# ইমার্জেন্সি ফোর্স ফেইলওভার (কোরাম বাইপাস)
php netfusion dr:failover --target=node-eu-west-1 --force
```

**Options:**

| Option | বিবরণ |
|--------|-------|
| `--target=<node_id>` | টার্গেট স্ট্যান্ডবাই নোড আইডি |
| `--force` | কোরাম মেজরিটি বাইপাস করার ফ্ল্যাগ (জরুরি দুর্যোগে) |

---

### `dr:drill`

প্রডাকশন ট্রাফিকে কোনো বিঘ্ন না ঘটিয়ে সম্পূর্ণ আইসোলেটেড স্যান্ডবক্সে একটি এন্ড-টু-এন্ড ডিআর ড্রিল সিমুলেশন পরিচালনা করে এবং অর্জিত RTO/RPO এসএলএ অডিট রিপোর্ট প্রদর্শন করে।

```bash
# ডিফল্ট স্ট্যান্ডবাই রিজিয়নে ড্রিল পরিচালনা
php netfusion dr:drill

# নির্দিষ্ট রিজিয়নে ড্রিল পরিচালনা
php netfusion dr:drill --target=eu-west-1
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--target=<region>` | টার্গেট স্ট্যান্ডবাই রিজিয়ন | `eu-west-1` |

---

### `dr:restore`

কন্টিনিউয়াস ডেটা প্রোটেকশন (CDP) জার্নাল ব্যবহার করে অতীতে যেকোনো সুনির্দিষ্ট মাইক্রোসেকেন্ড টাইমস্ট্যাম্প বা ট্রানজ্যাকশন আইডিতে ডাটাবেস রিকনস্টিটিউট (Point-in-Time Recovery) করে।

```bash
# সুনির্দিষ্ট টাইমস্ট্যাম্পে ডাটাবেস রিস্টোর
php netfusion dr:restore --to-timestamp="1727448000.123456"

# সুনির্দিষ্ট ট্রানজ্যাকশন আইডিতে রিস্টোর
php netfusion dr:restore --to-tx="tx_abcdef123456"
```

**Options:**

| Option | বিবরণ |
|--------|-------|
| `--to-timestamp="<ts>"` | টার্গেট ইউনিক্স টাইমস্ট্যাম্প বা তারিখ |
| `--to-tx="<txId>"` | টার্গেট ট্রানজ্যাকশন আইডি |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/disaster_recovery.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/disaster_recovery.php)

---

## Phase 72: Enterprise Chaos Engineering & System Resiliency Assurance Engine (GameDay Simulator)

### `chaos:run`

একটি রেজিস্টার্ড বা ডিফল্ট Chaos GameDay এক্সপেরিমেন্ট নির্বাহ করে, স্ট্যাডি-স্টেট হাইপোথিসিস ও ব্লাস্ট রেডিয়াস গার্ডরেইল যাচাই করে, ফল্ট ইনজেকশন পরিচালনা করে এবং সিস্টেম রেজিলিয়েন্সি স্কোর (0–100) পরিমাপ করে।

```bash
# ডিফল্ট রেজিলিয়েন্সি বেসলাইন ড্রিল পরিচালনা
php netfusion chaos:run

# নির্দিষ্ট এক্সপেরিমেন্ট পরিচালনা
php netfusion chaos:run "Database Latency GameDay Drill"

# JSON ফরম্যাটে আউটপুট প্রদর্শন
php netfusion chaos:run --format=json
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `[experiment_name]` | নির্বাহযোগ্য এক্সপেরিমেন্টের নাম (ঐচ্ছিক) | ডিফল্ট বেসলাইন |
| `--force` | প্রোবে জোরপূর্বক ফল্ট ইনজেকশন সক্রিয় করার ফ্ল্যাগ | `false` |
| `--format=<text\|json>` | আউটপুট ফরম্যাট | `text` |

---

### `chaos:abort`

সিস্টেমের গ্লোবাল Emergency Kill Switch সক্রিয় করে যেকোনো অনাকাঙ্ক্ষিত পরিস্থিতিতে সমস্ত সক্রিয় ফল্ট ইনজেক্টর নিমেষে রোলব্যাক করে এবং ক্যাওস কার্যকলাপ বন্ধ করে।

```bash
# জরুরি স্থগিতাদেশ প্রদান
php netfusion chaos:abort --reason="Uncontrolled latency spike detected in production"

# পুনরায় স্বাভাবিক ক্যাওস অপারেশন সচল করা
php netfusion chaos:abort --resume
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--reason="<text>"` | জরুরি স্থগিতাদেশের কারণ বা কারণ ব্যাখ্যা | `Operator invoked chaos:abort` |
| `--initiated-by="<user>"` | নির্দেশ প্রদানকারীর পরিচয় | `CLI` |
| `--resume` | ইমার্জেন্সি কিল সুইচ নিষ্ক্রিয় করে পুনরায় স্বাভাবিক অবস্থা ফিরিয়ে আনা | `false` |

---

### `chaos:report`

সাম্প্রতিক সমস্ত ক্যাওস এক্সপেরিমেন্টের টেলিমেট্রি অডিট, গড় রেজিলিয়েন্সি স্কোর, সফল ও ব্যর্থ ড্রিলের সংখ্যা এবং সামগ্রিক রেজিলিয়েন্সি গ্রেড (A+ থেকে F) প্রদর্শন করে।

```bash
# টেক্সট ফরম্যাটে রেজিলিয়েন্সি অডিট রিপোর্ট দেখা
php netfusion chaos:report

# সিআই/সিডি অটোমেশনের জন্য JSON ফরম্যাটে রিপোর্ট
php netfusion chaos:report --format=json
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--format=<text\|json>` | আউটপুট ফরম্যাট | `text` |

---

### `chaos:monkey`

অনুমোদিত মেইনটেন্যান্স উইন্ডোর মধ্যে স্বায়ত্তশাসিতভাবে একটি এলোমেলো টার্গেটে ফল্ট ইনজেকশন স্ট্রাইক পরিচালনা করে সার্কিট ব্রেকার ও রিট্রাই মেকানিজমের কার্যকারিতা যাচাই করে।

```bash
# স্বায়ত্তশাসিত ক্যাওস মাঙ্কি স্ট্রাইক চালানো
php netfusion chaos:monkey

# মেইনটেন্যান্স উইন্ডো বাইপাস করে জোরপূর্বক স্ট্রাইক চালানো
php netfusion chaos:monkey --force

# JSON ফরম্যাটে টেলিমেট্রি রেজাল্ট গ্রহণ
php netfusion chaos:monkey --force --format=json
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--force` | মেইনটেন্যান্স উইন্ডো সীমাবদ্ধতা বাইপাস করার ফ্ল্যাগ | `false` |
| `--format=<text\|json>` | আউটপুট ফরম্যাট | `text` |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/chaos.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/chaos.php)

---

## Phase 73: Enterprise OpenTelemetry (OTel) Native Observability Fabric

### `otel:export`

বাফার্ড ডিস্ট্রিবিউটেড স্প্যান ও ট্রেস ডেটা নির্দিষ্ট এক্সপোর্টার ফরম্যাটে (OTLP HTTP/JSON, Zipkin v2, Prometheus, বা Text) এক্সপোর্ট করে অথবা ব্যাকগ্রাউন্ড কালেক্টরে পুশ করে।

```bash
# ডিফল্ট OTLP JSON ফরম্যাটে স্প্যান এক্সপোর্ট
php netfusion otel:export

# Zipkin v2 ফরম্যাটে স্প্যান এক্সপোর্ট
php netfusion otel:export --format=zipkin

# ডাটাবেস বা ফাইলে পারসিস্ট করার জন্য এক্সপোর্ট
php netfusion otel:export --persist
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--format=<otlp|zipkin|prometheus|text>` | এক্সপোর্ট টেলিমেট্রি ফরম্যাট নির্ধারণ | `otlp` |
| `--persist` | সংগৃহীত স্প্যান ডাটাবেসে পারসিস্ট করার ফ্ল্যাগ | `false` |

---

### `otel:metrics`

সিস্টেমের সমস্ত রেজিস্টার্ড OTel মেট্রিক ইন্সট্রুমেন্টস (Counter, UpDownCounter, Gauge, Histogram)-এর রিয়েল-টাইম স্ন্যাপশট ও এগ্রিগেশন প্রদর্শন করে।

```bash
# টেক্সট টেবিল ফরম্যাটে সমস্ত মেট্রিক্স সামারি প্রদর্শন
php netfusion otel:metrics

# Prometheus 0.0.4 text exposition ফরম্যাটে মেট্রিক্স এক্সপোর্ট
php netfusion otel:metrics --format=prometheus

# JSON ফরম্যাটে ফুল মেট্রিক্স স্ন্যাপশট এক্সপোর্ট
php netfusion otel:metrics --format=json
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--format=<text|prometheus|json>` | আউটপুট ফরম্যাট নির্ধারণ | `text` |

---

### `otel:profile`

কন্টিনিউয়াস ওয়াল-ক্লক ও মেমরি অ্যালোকেটর প্রোফাইলার সেশন পরিচালনা করে, মেমরি ও CPU হটস্পট ডিটেক্ট করে এবং FlameGraph-কম্প্যাটিবল ফল্ডেড স্ট্যাক ফরম্যাট তৈরি করে।

```bash
# রিসেন্ট প্রোফাইলিং সেশনের টেক্সট সামারি প্রদর্শন
php netfusion otel:profile

# FlameGraph-রেডি ফল্ডেড স্ট্যাক আউটপুট তৈরি
php netfusion otel:profile --format=flamegraph

# JSON ফরম্যাটে পূর্ণ প্রোফাইল স্যাম্পলস এক্সপোর্ট
php netfusion otel:profile --format=json
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--format=<text|flamegraph|json>` | আউটপুট ফরম্যাট নির্ধারণ | `text` |
| `--session=<id>` | নির্দিষ্ট সেশনের প্রোফাইল ফিল্টার করা | সর্বশেষ সেশন |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/observability.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/observability.php)

---

## Phase 74: Enterprise Schema Registry, Protocol Buffers / Apache Avro Binary SerDe & CloudEvents 1.0 Distributed Event Mesh Fabric

### `schema:register`

নির্দিষ্ট সাবজেক্টের অধীনে একটি নতুন স্কিমা (Apache Avro, Protocol Buffers, বা JSON Schema) রেজিস্টার করে। সিস্টেমের অ্যাক্টিভ কম্প্যাটিবিলিটি মোড ভঙ্গের আশঙ্কা থাকলে তাৎক্ষণিক এরর ছুড়ে ব্রেকিং চেঞ্জ রোধ করে।

```bash
# JSON বা Avro ফাইল থেকে স্কিমা রেজিস্টার করা
php netfusion schema:register user-events schemas/user.avsc --type=AVRO

# সরাসরি ইনলাইন JSON স্ট্রিং দিয়ে স্কিমা রেজিস্ট্রেশন
php netfusion schema:register order-events '{"type":"record","name":"Order","fields":[{"name":"id","type":"int"}]}' --type=AVRO

# প্রোটোকল বাফার্স (Protobuf) স্কিমা রেজিস্ট্রেশন
php netfusion schema:register telemetry-events schemas/telemetry.proto --type=PROTOBUF
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `subject` | স্কিমা সাবজেক্টের নাম (প্রথম পজিশনাল আর্গুমেন্ট) | - |
| `schema` | ফাইল পাথ অথবা ইনলাইন ডেফিনিশন (দ্বিতীয় পজিশনাল আর্গুমেন্ট) | - |
| `--type=<AVRO|PROTOBUF|JSON_SCHEMA>` | স্কিমার টাইপ নির্ধারণ | `AVRO` |

---

### `schema:check`

কোনো স্কিমাকে রেজিস্ট্রি ডাটাবেসে স্থায়ীভাবে রেজিস্টার না করে পূর্ববর্তী ভার্শনসমূহের সাথে এর কম্প্যাটিবিলিটি (`BACKWARD`, `FORWARD`, `FULL`) প্রি-ফ্লাইট ভ্যালিডেশন করে।

```bash
# ডিফল্ট BACKWARD মোডে কম্প্যাটিবিলিটি পরীক্ষা করা
php netfusion schema:check user-events schemas/user_v2.avsc

# কঠোর FULL মোডে পরীক্ষা করা
php netfusion schema:check user-events schemas/user_v2.avsc --mode=FULL
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--mode=<BACKWARD|FORWARD|FULL|NONE>` | কম্প্যাটিবিলিটি যাচাইয়ের ধরন | `BACKWARD` |
| `--type=<AVRO|PROTOBUF|JSON_SCHEMA>` | স্কিমা ফরম্যাট | `AVRO` |

---

### `cloudevent:publish`

CNCF CloudEvents v1.0 স্পেসিফিকেশন অনুযায়ী একটি স্ট্যান্ডার্ড ইভেন্ট মেসেজ তৈরি করে এবং ডিস্ট্রিবিউটেড ইভেন্ট মেশে ব্রডকাস্ট করে।

```bash
# ডিফল্ট পেলোড সহ ইভেন্ট পাবলিশ করা
php netfusion cloudevent:publish user.signup /services/auth

# কাস্টম JSON ডেটা ও সাবজেক্ট সহ পাবলিশ করা
php netfusion cloudevent:publish order.created /services/orders --data='{"order_id":"ORD-99","amount":450}' --subject=order_99
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--data=<json>` | ইভেন্ট বিজনেস ডেটা পেলোড | `{}` |
| `--subject=<name>` | ইভেন্ট কনটেক্সট সাবজেক্ট ফিল্টার | `null` |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/schema_registry.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/schema_registry.php)

---

## ৭৪. এন্টারপ্রাইজ এআই ফাংশন কলিং ইঞ্জিন ও এজেনটিক মেমোরি ফ্যাব্রিক (Enterprise AI Agentic Runtime)

### `agent:run`

স্বয়ংক্রিয় ReAct (Reasoning + Acting) আর্কিটেকচারের মাধ্যমে যেকোনো জটিল টাস্ক সম্পাদন করে। টুল কলিং, পর্যবেক্ষণ এবং পুনরাবৃত্তিমূলক যুক্তির মাধ্যমে চূড়ান্ত সিদ্ধান্ত প্রদান করে।

```bash
# সরাসরি টাস্ক এক্সিকিউট করা
php netfusion agent:run "Calculate 42 * 10 and return the result."

# কাস্টম এজেন্ট নাম ও সর্বোচ্চ পুনরাবৃত্তি সীমা নির্ধারণ
php netfusion agent:run "Analyze warehouse inventory for product 99" --agent=LogisticsAgent --max-iter=10
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `task` | এজেন্টের জন্য উদ্দেশ্য বা নির্দেশিকা (প্রথম আর্গুমেন্ট) | - |
| `--agent=<name>` | এজেন্টের শনাক্তকারী নাম | `NetFusionAgent` |
| `--max-iter=<N>` | সর্বোচ্চ ReAct পুনরাবৃত্তি সংখ্যা | `15` |

---

### `agent:tools`

সিস্টেমে রেজিস্টার্ড এবং পিএইচপি ক্লাস মেথড থেকে রিফ্লেকশন দ্বারা সিন্থেসাইজড সমস্ত এআই টুলের তালিকা এবং তাদের JSON Schema প্যারামিটার প্রদর্শন করে।

```bash
# টেবিল ফরম্যাটে টুল তালিকা দেখা
php netfusion agent:tools

# OpenAI / Gemini সামঞ্জস্যপূর্ণ JSON Schema ফরম্যাটে আউটপুট নেওয়া
php netfusion agent:tools --format=json
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--format=<table|json>` | আউটপুটের প্রদর্শন ফরম্যাট | `table` |

---

### `agent:memory`

এজেন্টের শর্ট-টার্ম কনভার্সেশন বাফার, ওয়ার্কিং মেমোরি স্ক্র্যাচপ্যাড ও চেকলিস্ট, এবং লং-টার্ম টিএফ-আইডিএফ সিম্যান্টিক ভেক্টর মেমোরি ইন্সপেক্ট, সংরক্ষণ, রিকল ও মুছে ফেলার কাজ করে।

```bash
# এজেন্টের মেমোরির বর্তমান অবস্থা পরিদর্শন
php netfusion agent:memory inspect

# সিম্যান্টিক ও ওয়ার্কিং মেমোরিতে নতুন তথ্য মনে রাখা
php netfusion agent:memory remember speed_note "NetFusion delivers sub-millisecond execution speeds."

# প্রাসঙ্গিকতার ভিত্তিতে মেমোরি থেকে তথ্য রিকল করা
php netfusion agent:memory recall "execution speed"

# এজেন্টের সমস্ত মেমোরি মুছে ফেলা
php netfusion agent:memory clear
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `action` | মেমোরি অ্যাকশন (`inspect`, `remember`, `recall`, `clear`) | `inspect` |
| `--agent=<name>` | লক্ষ্যযুক্ত এজেন্টের নাম | `NetFusionAgent` |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/agent.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/agent.php)

---

## ৭৫. রিয়েল-টাইম কোলাবোরেটিভ সিআরডিটি, ওয়েবআরটিসি মেশ ও ওটি ইঞ্জিন (Real-Time Collaborative CRDT & WebRTC Mesh Engine)

### `collab:session`

একটি রিয়েল-টাইম কোলাবোরেটিভ ডকুমেন্ট সেশন পরিদর্শন বা ইনিশিয়ালাইজ করে। ডকুমেন্টের বর্তমান রিভিশন, দৃশ্যমান দৈর্ঘ্য, মেটাডাটা এবং সক্রিয় পিয়ার উপস্থিতির তালিকা প্রদর্শন করে।

```bash
# ডিফল্ট ডকুমেন্ট সেশন পরিদর্শন
php netfusion collab:session

# নির্দিষ্ট ডকুমেন্ট ও পিয়ার আইডি সহ সেশন শুরু করা
php netfusion collab:session doc-team-sprint --peer=lead-dev --text="Sprint Planning Agenda"
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `doc_id` | ডকুমেন্টের স্বতন্ত্র আইডেন্টিফায়ার (প্রথম আর্গুমেন্ট) | `doc-general` |
| `--peer=<id>` | স্থানীয় সংযোগকারী পিয়ারের আইডি | `cli-peer` |
| `--text=<content>` | ডকুমেন্টের প্রারম্ভিক টেক্সট বিষয়বস্তু | `""` |

---

### `collab:prune`

নির্ধারিত টাইমআউট সময়সীমার অধিক সময় ধরে নিষ্ক্রিয় থাকা পিয়ার উপস্থিতি (stale presence) এবং ড্রপ করা সংযোগ মেমরি থেকে স্বয়ংক্রিয়ভাবে মুক্ত করে।

```bash
# ডিফল্ট ৩০ সেকেন্ডের নিষ্ক্রিয় পিয়ার উপস্থিতি পরিষ্কার করা
php netfusion collab:prune

# কাস্টম ১০ সেকেন্ডের টাইমআউট থ্রেশহোল্ড সহ প্রুন করা
php netfusion collab:prune --timeout=10
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--timeout=<seconds>` | নিষ্ক্রিয় হিসেবে বিবেচনার সময়সীমা (সেকেন্ড) | `30.0` |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/collaboration.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/collaboration.php)

---

## Phase 77: Enterprise Geo-Distributed Multi-Master, Raft & Distributed SQL Engine

### `raft:status`

Raft ক্লাস্টারের সামগ্রিক স্বাস্থ্য, নোড সংখ্যা, কোরাম থ্রেশহোল্ড, বর্তমান সক্রিয় লিডার, এবং প্রতিটি নোডের ভূমিকা, টার্ম ও লগ কমিট ইনডেক্স প্রদর্শন করে।

```bash
# ডিফল্ট ক্লাস্টারের স্ট্যাটাস যাচাই
php netfusion raft:status

# নির্দিষ্ট ক্লাস্টারের নাম উল্লেখ করে স্ট্যাটাস যাচাই
php netfusion raft:status analytics_cluster
```

**Arguments:**

| Argument | বিবরণ | ডিফল্ট |
|----------|-------|--------|
| `cluster` | Raft ক্লাস্টারের নাম (ঐচ্ছিক প্রথম আর্গুমেন্ট) | `default` |

---

### `db:replicate`

জিও-ডিস্ট্রিবিউটেড মাল্টি-মাস্টার রেপ্লিকেশন কিউ, নোডদের মধ্যকার রেপ্লিকেশন ল্যাগ, ভেক্টর ক্লক স্ট্যাটাস প্রদর্শন করে এবং ব্যাকলগ সিঙ্ক্রোনাইজেশন নির্বাহ করে।

```bash
# রেপ্লিকেশন কিউ ও ভেক্টর ক্লক পর্যবেক্ষণ
php netfusion db:replicate

# সমস্ত নোডের মধ্যে পেন্ডিং মিউটেশন সিঙ্ক করা
php netfusion db:replicate --sync

# নির্দিষ্ট রিজিয়ন থেকে অন্য রিজিয়নে সিঙ্ক পরিচালনা
php netfusion db:replicate --sync --source=us-east-1 --target=eu-west-1
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--sync` | পেন্ডিং আউটবক্স কিউ সিঙ্ক্রোনাইজেশন নির্বাহ করার ফ্ল্যাগ | `false` |
| `--source=<region>` | নির্দিষ্ট সোর্স রিজিয়ন | সমস্ত রিজিয়ন |
| `--target=<region>` | নির্দিষ্ট টার্গেট রিজিয়ন | সমস্ত রিজিয়ন |

---

### `dsql:query`

ডিস্ট্রিবিউটেড এসকিউএল কোয়েরি পার্স করে, পার্টিশন কিউ ম্যাপিং অনুযায়ী শার্ড প্ল্যান (Point-lookup বনাম Scatter-Gather) তৈরি করে এবং সমান্তরাল শার্ডে এক্সিকিউট করে চূড়ান্ত ফলাফল ও গ্লোবাল অ্যাগ্রিগেশন প্রদর্শন করে।

```bash
# পয়েন্ট-লুকআপ ডিস্ট্রিবিউটেড কোয়েরি (নির্দিষ্ট শার্ডে রাউট হবে)
php netfusion dsql:query "SELECT * FROM users WHERE user_id = 42"

# স্ক্যাটার-গ্যাদার গ্লোবাল অ্যাগ্রিগেশন কোয়েরি (সমস্ত শার্ডে সমান্তরাল নির্বাহ ও মার্জ)
php netfusion dsql:query "SELECT COUNT(*) AS total, AVG(salary) AS avg_sal FROM employees"
```

**Arguments:**

| Argument | বিবরণ | ডিফল্ট |
|----------|-------|--------|
| `query` | এক্সিকিউট করার জন্য ডিস্ট্রিবিউটেড এসকিউএল কোয়েরি স্ট্রিং | `SELECT * FROM users` |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/distributed_db.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/distributed_db.php)

---

## Phase 78: Multi-Cloud IaC, Terraform State Machine & DAG Engine

### `iac:plan`

ডিক্লেয়ারেটিভ HCL / TF কনফিগারেশন ফাইল পার্স করে এবং বর্তমান স্টেট ফাইলের সাথে তুলনা করে রিসোর্স পরিবর্তন (CREATE, UPDATE, REPLACE, DESTROY, NOOP) ও ডিপেন্ডেন্সি DAG ডিফারেনশিয়াল প্ল্যান প্রদর্শন করে।

```bash
# স্পেসিফিকেশন ফাইল থেকে প্ল্যান জেনারেট করা
php netfusion iac:plan main.tf

# ভেরিয়েবল ওভাররাইড সহ প্ল্যান যাচাই
php netfusion iac:plan infra.tf --var=env=production --var=instance_type=t3.medium
```

**Arguments & Options:**

| Argument / Option | বিবরণ | ডিফল্ট |
|-------------------|-------|--------|
| `file` | HCL / TF কনফিগারেশন ফাইল পাথ | ডিফল্ট স্যাম্পল টেমপ্লেট |
| `--var=<key=val>` | ইনপুট ভেরিয়েবল প্যারামিটার | খালি |

---

### `iac:apply`

ডিপেন্ডেন্সি গ্রাফ (DAG) এর Kahn টপোলজিক্যাল সর্টিং অনুসারে রিসোর্স প্রভিশনিং ও মিউটেশন এক্সেকিউট করে এবং সফল পরিবর্তনের পর স্টেট ফাইলে অ্যাট্রিবিউট সিঙ্ক ও লক রিলিজ করে।

```bash
# স্বয়ংক্রিয় কনফার্মেশন সহ ইনফ্রাস্ট্রাকচার প্রভিশন
php netfusion iac:apply main.tf --auto-approve

# নির্দিষ্ট ড্রাইভারে রান করা
php netfusion iac:apply main.tf --auto-approve --var=region=us-east-1
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--auto-approve` | সরাসরি ইন্টারঅ্যাক্টিভ কনফার্মেশন ছাড়া প্রভিশন কার্যকর করা | `false` |
| `--var=<key=val>` | ইনপুট ভেরিয়েবল প্যারামিটার | খালি |

---

### `iac:destroy`

স্টেট ফাইলে সংরক্ষিত সমস্ত ম্যানেজড ক্লাউড রিসোর্স ডিপেন্ডেন্সির বিপরীত টপোলজিক্যাল ক্রমে (Reverse Topological Order) টার্মিনেট বা ডিলিট করে এবং স্টেট ফাইল ক্লিন করে।

```bash
# পরিচালিত সমস্ত ক্লাউড রিসোর্স টার্মিনেট করা
php netfusion iac:destroy --auto-approve
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `--auto-approve` | নিশ্চিতকরণ প্রম্পট ছাড়া সরাসরি টার্মিনেশন নির্বাহ করা | `false` |

---

### `iac:state`

বর্তমান Terraform v4 স্টেট ফাইলের রিসোর্স তালিকা প্রদর্শন, রিসোর্স ড্রপ বা এক্সটার্নাল রিসোর্স স্টেটে ইমপোর্ট করার কমান্ড।

```bash
# স্টেটের সমস্ত ম্যানেজড রিসোর্স তালিকাভুক্ত করা
php netfusion iac:state list

# স্টেটে থাকা রিসোর্সের মেটাডাটা ও অ্যাট্রিবিউট পর্যবেক্ষণ করা
php netfusion iac:state show aws_vpc.main

# স্টেট ফাইল আনলক করা (জরুরি ক্ষেত্রে)
php netfusion iac:state unlock
```

**Sub-commands:**

| Sub-command | বিবরণ |
|-------------|-------|
| `list` | স্টেটে নিবন্ধিত সমস্ত রিসোর্স অ্যাড্রেসের তালিকা প্রদর্শন |
| `show <address>` | নির্দিষ্ট রিসোর্সের বিস্তারিত অ্যাট্রিবিউট ও মেটাডাটা প্রদর্শন |
| `unlock` | ফোর্স আনলক করে স্টেট লক রিলিজ করা |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/iac.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/iac.php)

---

## Phase 79: Enterprise WebAssembly (Wasm) Runtime, Sandboxed VM & Edge Functions

### `wasm:run`

একটি কম্পাইল্ড WebAssembly (`.wasm`) বাইনারি ফাইল লোড করে এর নির্দিষ্ট এক্সপোর্টেড ফাংশন স্যান্ডবক্সড ভার্চুয়াল মেশিনের ভেতরে এক্সিকিউট করে। গ্যাস/ফুয়েল কনজাম্পশন, এক্সিকিউশন টাইম এবং রিটার্ন ভ্যালু টার্মিনালে প্রদর্শন করে।

```bash
# ওয়াসম মডিউলের ডিফল্ট এক্সপোর্ট বা নির্দিষ্ট ফাংশন রান করা
php netfusion wasm:run app.wasm --func=add --args=10,20

# কাস্টম মেমরি এবং ফুয়েল লিমিট সহ রান করা
php netfusion wasm:run plugin.wasm --func=process --fuel=500000 --timeout=5
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `module` | Wasm বাইনারি ফাইল পাথ (প্রথম আর্গুমেন্ট) | - |
| `--func=<name>` | এক্সিকিউট করার জন্য এক্সপোর্টেড ফাংশনের নাম | `main` |
| `--args=<v1,v2,...>` | ফাংশনে প্রেরিত কমা-সেপারেটেড আর্গুমেন্ট তালিকা | খালি |
| `--fuel=<count>` | সর্বোচ্চ গ্যাস/ফুয়েল ইন্সট্রাকশন লিমিট | `1000000` |
| `--timeout=<seconds>` | সর্বোচ্চ এক্সিকিউশন টাইম লিমিট (সেকেন্ডে) | `10` |

---

### `wasm:inspect`

একটি WebAssembly বাইনারি ফাইলের ইন্টারনাল সেকশন, টাইপ সিগনেচার, এক্সপোর্ট, ইম্পোর্ট, মেমরি সাইজ এবং ডিসঅ্যাসেম্বল্ড মেটাডাটা ট্যাবুলার ফরম্যাটে প্রদর্শন করে।

```bash
# ওয়াসম মডিউলের সেকশন ও মেটাডাটা পরিদর্শন
php netfusion wasm:inspect app.wasm

# সরাসরি JSON আউটপুট সংগ্রহ
php netfusion wasm:inspect app.wasm --format=json
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `module` | Wasm বাইনারি ফাইল পাথ (প্রথম আর্গুমেন্ট) | - |
| `--format=<table\|json>` | আউটপুট প্রদর্শন ফরম্যাট | `table` |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/wasm.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/wasm.php)

---

## Phase 80: Enterprise Kubernetes-Compatible Container Orchestration & Pod Fabric Engine

### `kube:apply`

একটি ডিক্লারেটিভ কুবারনেটিস ম্যানিফেস্ট ফাইল (YAML বা JSON) পার্স করে ক্লাস্টারে কাঙ্ক্ষিত রিসোর্স (Pod, Deployment, Service, Node) কনফিগার ও শিডিউল করে।

```bash
# কুবারনেটিস ম্যানিফেস্ট ফাইল ক্লাস্টারে অ্যাপ্লাই করা
php netfusion kube:apply -f deployment.yaml

# সরাসরি ফাইল পাথ প্রদান করে প্রয়োগ করা
php netfusion kube:apply pod.json
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `-f, --filename` | ম্যানিফেস্ট ফাইল পাথ (YAML/JSON) | - |

---

### `kube:get`

ক্লাস্টারের নির্দিষ্ট ধরণের সক্রিয় রিসোর্সসমূহ (Pods, Nodes, Deployments, Services) টেবিল আকারে টার্মিনালে প্রদর্শন করে।

```bash
# সমস্ত সক্রিয় পড প্রদর্শন
php netfusion kube:get pods

# নির্দিষ্ট নেমস্পেসের পডসমূহ প্রদর্শন
php netfusion kube:get pods -n prod

# ক্লাস্টার ওয়ার্কার নোডসমূহ ও রিসোর্স ক্যাপাসিটি প্রদর্শন
php netfusion kube:get nodes

# ডিপ্লয়মেন্ট ও রেপ্লিকা স্ট্যাটাস প্রদর্শন
php netfusion kube:get deployments

# ক্লাস্টার সার্ভিস ও ভার্চুয়াল ClusterIP প্রদর্শন
php netfusion kube:get services
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `resource` | রিসোর্সের ধরন (`pods`, `nodes`, `deployments`, `services`) | `pods` |
| `-n, --namespace` | ফিল্টার করার জন্য নেমস্পেস | সমস্ত |

---

### `kube:scale`

একটি কুবারনেটিস ডিপ্লয়মেন্টের রেপ্লিকা কাউন্ট তাৎক্ষণিক বৃদ্ধি (Scale Up) বা হ্রাস (Scale Down) করে এবং রিকনসিলিয়েশন লুপের মাধ্যমে পড প্রভিশন বা টার্মিনেট করে।

```bash
# ডিপ্লয়মেন্ট স্কেল আপ করে ৪টি রেপ্লিকা করা
php netfusion kube:scale deployment web-api --replicas=4

# নির্দিষ্ট নেমস্পেসে ডিপ্লয়মেন্ট স্কেল ডাউন করা
php netfusion kube:scale deployment payment-svc --replicas=2 -n prod
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `name` | ডিপ্লয়মেন্টের নাম (প্রথম পজিশনাল আর্গুমেন্ট) | - |
| `--replicas=<count>` | কাঙ্ক্ষিত রেপ্লিকা সংখ্যা | আবশ্যক |
| `-n, --namespace` | ডিপ্লয়মেন্টের নেমস্পেস | `default` |

---

### `kube:logs`

নির্দিষ্ট পডের কন্টেইনার লগ স্ট্রিমিং ও স্টেটাস পরিদর্শন করে।

```bash
# নির্দিষ্ট পডের সমস্ত কন্টেইনার লগ দেখা
php netfusion kube:logs web-api-7b89f

# সুনির্দিষ্ট কন্টেইনার ফিল্টার করে লগ দেখা
php netfusion kube:logs web-api-7b89f -c nginx -n prod
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|--------|-------|--------|
| `pod-name` | লক্ষ্যযুক্ত পডের নাম (প্রথম আর্গুমেন্ট) | - |
| `-c, --container` | নির্দিষ্ট কন্টেইনার ফিল্টার | সমস্ত |
| `-n, --namespace` | পডের নেমস্পেস | `default` |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/kube.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/kube.php)

---

## ৮২. এন্টারপ্রাইজ ডিস্ট্রিবিউটেড কি-ভ্যালু স্টোর (etcd v3 Architecture)

`etcd` কমান্ডসমূহ ক্লাউড-নেটিভ কনকারেন্ট কি-ভ্যালু স্টোর, MVCC রিভিশন হিস্ট্রি, ডিস্ট্রিবিউটেড লিজ এবং ইভেন্ট ওয়াচ স্ট্রিম পরিচালনা করে।

### `etcd:put`

MVCC স্টোরে একটি কি-ভ্যালু পেয়ার সংরক্ষণ বা আপডেট করে।

```bash
# একটি সাধারণ কি-ভ্যালু রাইট
php netfusion etcd:put /config/database/host 192.168.1.100

# একটি ডিস্ট্রিবিউটেড লিজ আইডি সংযুক্ত করে রাইট
php netfusion etcd:put /services/web/node-1 10.0.0.5:8080 --lease=123456
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<key>` | রিভিশন কি (প্রথম আর্গুমেন্ট) | আবশ্যক |
| `<value>` | মান বা পেলোড (দ্বিতীয় আর্গুমেন্ট) | আবশ্যক |
| `--lease=<id>` | সংযুক্ত লিজ আইডি | `0` (কোন লিজ নেই) |

---

### `etcd:get`

MVCC স্টোর থেকে একটি নির্দিষ্ট কি অথবা প্রিফিক্স রেঞ্জ কুয়েরি করে। পয়েন্ট-ইন-টাইম টাইম ট্রাভেল কুয়েরি সাপোর্ট করে।

```bash
# বর্তমান লেটেস্ট ভ্যালু পড়া
php netfusion etcd:get /config/database/host

# একটি প্রিফিক্সের অধীনে সকল কি সার্চ করা
php netfusion etcd:get /services/web/ --prefix

# নির্দিষ্ট ঐতিহাসিক রিভিশন N এ কি-এর মান দেখা
php netfusion etcd:get /config/database/host --rev=5
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<key>` | কি অথবা প্রিফিক্স পাথ | আবশ্যক |
| `--prefix` | প্রিফিক্স ভিত্তিক রেঞ্জ স্ক্যান | `false` |
| `--rev=<number>` | ঐতিহাসিক রিভিশন সংখ্যা | `0` (লেটেস্ট) |

---

### `etcd:watch`

নির্দিষ্ট কি অথবা প্রিফিক্সে আসা সকল মিউটেশন ইভেন্ট (PUT, DELETE) রিয়েল-টাইমে স্ট্রিম ও পর্যবেক্ষণ করে।

```bash
# নির্দিষ্ট কি-এর রিয়েল-টাইম ইভেন্ট ওয়াচ করা
php netfusion etcd:watch /config/app/title

# একটি সম্পূর্ণ প্রিফিক্স ডিরেক্টরি ওয়াচ করা
php netfusion etcd:watch /services/ --prefix

# নির্দিষ্ট ঐতিহাসিক রিভিশন থেকে ইভেন্ট রিপ্লে করে ওয়াচ শুরু করা
php netfusion etcd:watch /config/ --prefix --rev=10
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<key>` | ওয়াচ করার কি অথবা প্রিফিক্স | আবশ্যক |
| `--prefix` | প্রিফিক্স ফিল্টারিং সক্রিয় করা | `false` |
| `--rev=<number>` | যে রিভিশন থেকে ঐতিহাসিক ইভেন্ট রিপ্লে হবে | `0` (শুধুমাত্র নতুন ইভেন্ট) |

---

### `etcd:lease`

ডিস্ট্রিবিউটেড লিজ মঞ্জুর (grant), বাতিল (revoke), হার্টবিট রিনিউ (keep-alive) এবং অ্যাক্টিভ লিজ তালিকা প্রদর্শন করে।

```bash
# ৬০ সেকেন্ডের নতুন লিজ তৈরি
php netfusion etcd:lease grant 60

# সক্রিয় সকল লিজ ও সংযুক্ত কি-এর তালিকা প্রদর্শন
php netfusion etcd:lease list

# লিজের মেয়াদ হার্টবিট রিনিউ করা
php netfusion etcd:lease keep-alive 123456

# লিজ বাতিল করে সমস্ত সংযুক্ত কি ক্যাসকেড ডিলিট করা
php netfusion etcd:lease revoke 123456
```

**Options & Subcommands:**

| Subcommand | আর্গুমেন্ট | বিবরণ |
|---|---|---|
| `grant` | `<ttl>` | নতুন লিজ তৈরি ও ইউনিক আইডি প্রদান |
| `list` | - | সক্রিয় সকল লিজের আইডি, TTL ও সংযুক্ত কি প্রদর্শন |
| `keep-alive` | `<lease_id>` | লিজের TTL রিনিউ করা |
| `revoke` | `<lease_id>` | লিজ প্রত্যাহার এবং সংযুক্ত সকল কি মুছে ফেলা |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/etcd.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/etcd.php)

---

## ৮৩. এন্টারপ্রাইজ ডিস্ট্রিবিউটেড ইভেন্ট স্ট্রিমিং প্লাটফর্ম (Apache Kafka Architecture)

`kafka` কমান্ডসমূহ পার্টিশনযুক্ত কমিট লগ, ইভেন্ট পাবলিশিং, কনজিউমার গ্রুপ রিব্যালেন্সিং এবং অফসেট ট্র্যাকিং পরিচালনা করে।

### `kafka:topic`

টপিক তৈরি, তালিকা প্রদর্শন, বিবরণ এবং ডিলিট করে।

```bash
# ৪টি পার্টিশন ও ৩টি রেপ্লিকা সহ নতুন টপিক তৈরি
php netfusion kafka:topic create orders-stream --partitions=4 --replication=3

# সকল টপিকের তালিকা দেখা
php netfusion kafka:topic list

# নির্দিষ্ট টপিকের বিশদ মেটাডাটা ও কনফিগারেশন পরিদর্শন
php netfusion kafka:topic describe orders-stream

# অপ্রয়োজনীয় টপিক ও তার লগ ডিলিট করা
php netfusion kafka:topic delete old-topic
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<action>` | `create`, `list`, `describe`, `delete` | `list` |
| `[name]` | টপিকের নাম | - |
| `--partitions=<count>` | পার্টিশন সংখ্যা | `1` |
| `--replication=<factor>` | রেপ্লিকা ফ্যাক্টর | `1` |

---

### `kafka:produce`

নির্দিষ্ট টপিক ও পার্টিশনে মেসেজ রেকর্ড পাবলিশ করে।

```bash
# সাধারণ মেসেজ পাবলিশ (রাউন্ড-রবিন বা পার্টিশনার ভিত্তিক)
php netfusion kafka:produce orders-stream '{"order_id": 101, "amount": 2500}'

# কি (Key) ভিত্তিক হ্যাশিং পার্টিশনিং সহ মেসেজ প্রেরণ
php netfusion kafka:produce orders-stream '{"user": "u1"}' --key=user_101

# নির্দিষ্ট সুনির্দিষ্ট পার্টিশনে সরাসরি মেসেজ রাইট
php netfusion kafka:produce orders-stream 'urgent_alert' --partition=2
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<topic>` | লক্ষ্যযুক্ত টপিকের নাম | আবশ্যক |
| `<message>` | মেসেজ পেলোড | আবশ্যক |
| `--key=<string>` | পার্টিশনিং কি | `null` |
| `--partition=<id>` | সুনির্দিষ্ট পার্টিশন নম্বর | অটোমেটিক পার্টিশনার |

---

### `kafka:consume`

টপিক অথবা কনজিউমার গ্রুপ থেকে মেসেজ পোল ও রিড করে।

```bash
# একক কনজিউমার হিসেবে শুরু থেকে মেসেজ পড়া
php netfusion kafka:consume orders-stream --offset=earliest --max=20

# কনজিউমার গ্রুপের অংশ হিসেবে মেসেজ প্রসেস করা
php netfusion kafka:consume orders-stream --group=order-processors
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<topic>` | যে টপিক থেকে কনজিউম করা হবে | আবশ্যক |
| `--group=<id>` | কনজিউমার গ্রুপ আইডি | `standalone` |
| `--offset=<strategy>` | অফসেট স্ট্র্যাটেজি (`earliest`, `latest`) | `earliest` |
| `--max=<count>` | প্রতি পোলে সর্বোচ্চ রেকর্ড সংখ্যা | `10` |

---

### `kafka:group`

কনজিউমার গ্রুপের মেম্বারশিপ, রি-ব্যালেন্স স্টেট এবং কমিটেড অফসেট প্রদর্শন করে।

```bash
# সকল সক্রিয় কনজিউমার গ্রুপের তালিকা দেখা
php netfusion kafka:group list

# সুনির্দিষ্ট কনজিউমার গ্রুপের মেম্বার ও অফসেট পরিদর্শন
php netfusion kafka:group describe order-processors
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<action>` | `list` অথবা `describe` | `list` |
| `[group_id]` | কনজিউমার গ্রুপ আইডি | - |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/kafka.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/kafka.php)

---

## 44. এন্টারপ্রাইজ সার্ভিস ডিসকভারি, হেলথ প্রোবিং ও ডায়নামিক ডিএনএস (HashiCorp Consul Architecture)

### `consul:service`

সার্ভিস ক্যাটালগে সার্ভিস ইনস্ট্যান্স রেজিস্টার, তালিকাভুক্ত, ডি-রেজিস্টার এবং ডিসকভার করে।

```bash
# নিবন্ধিত সমস্ত সার্ভিসের তালিকা প্রদর্শন
php netfusion consul:service list

# নতুন সার্ভিস ইনস্ট্যান্স নিবন্ধন করা
php netfusion consul:service register web --address=10.0.0.1 --port=8080 --tags=production,v1

# সার্ভিস ডিসকভারি (সুনির্দিষ্ট ট্যাগ ফিল্টার সহ)
php netfusion consul:service discover web --tags=production

# সার্ভিস ইনস্ট্যান্স ডি-রেজিস্টার করা
php netfusion consul:service deregister web-1
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<action>` | `list`, `register`, `deregister`, অথবা `discover` | `list` |
| `[name]` | সার্ভিস নাম অথবা ইনস্ট্যান্স আইডি | - |
| `--address=<ip>` | সার্ভিস হোস্টার আইপি অ্যাড্রেস | `127.0.0.1` |
| `--port=<port>` | সার্ভিস লিসেনিং পোর্ট | `80` |
| `--id=<id>` | কাস্টম ইনস্ট্যান্স আইডি | অটো-জেনারেটেড |
| `--tags=<tag1,tag2>` | কমা দ্বারা পৃথকীকৃত মেটাডাটা ট্যাগস | - |

---

### `consul:members`

SWIM গসিপ ক্লাস্টার মেশের সক্রিয় মেম্বার নোড পরিদর্শন ও ক্লাস্টারে যোগদানে সহায়তা করে।

```bash
# গসিপ মেশ ক্লাস্টারের সদস্যদের অবস্থা দেখা
php netfusion consul:members list

# নতুন পিয়ার নোডে যোগদান (Join)
php netfusion consul:members join 192.168.1.50:8301 --name=agent-2
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<action>` | `list` অথবা `join` | `list` |
| `[address]` | টার্গেট নোডের আইপি অথবা আইপি:পোর্ট | - |
| `--port=<port>` | গসিপ লিসেনিং পোর্ট | `8301` |
| `--name=<name>` | ক্লাস্টার নোডের নাম | - |

---

### `consul:dns`

ডায়নামিক কনসাল ডিএনএস সার্ভার কোয়েরি সিমুলেশন ও আরএফসি ১০৩৫ রেকর্ড রেজোলিউশন।

```bash
# যেকোনো সার্ভিসের A রেকর্ড রেজোলিউশন
php netfusion consul:dns web.service.consul --type=A

# SRV রেকর্ড কুয়েরি (পোর্ট ও টার্গেট নোড ম্যাপিং)
php netfusion consul:dns _web._tcp.service.consul --type=SRV

# মেটাডাটা ও ট্যাগ সহ টেক্সট রেকর্ড কুয়েরি
php netfusion consul:dns web.service.consul --type=TXT
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<query>` | সম্পূর্ণ ডোমেইন কুয়েরি স্ট্রিং | আবশ্যক |
| `--type=<type>` | ডিএনএস রেকর্ড টাইপ (`A`, `SRV`, `TXT`, `ANY`) | `ANY` |

---

### `consul:check`

মাল্টি-প্রোটোকল হেলথ চেক মূল্যায়ন এবং টিটিএল (TTL) হার্টবিট প্রেরণ করে।

```bash
# সমস্ত সার্ভিসের হেলথ চেক চালানো ও মৃত নোড এভিক্ট করা
php netfusion consul:check run

# নির্দিষ্ট সার্ভিস ইনস্ট্যান্সের টিটিএল পাস হার্টবিট পাঠানো
php netfusion consul:check pass web-1 --check_id=web-1-check --note="Heartbeat OK"
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<action>` | `run` অথবা `pass` | `run` |
| `[instance_id]` | সার্ভিস ইনস্ট্যান্স আইডি | - |
| `--check_id=<id>` | সুনির্দিষ্ট হেলথ চেক আইডি | - |
| `--note=<note>` | স্ট্যাটাস নোট অথবা আউটপুট বার্তা | - |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/consul.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/consul.php)

---

## 45. এন্টারপ্রাইজ রিয়েল-টাইম ওল্যাপ ও অ্যানালিটিক্যাল ইঞ্জিন (Apache Druid & ClickHouse Architecture)

### `druid:ingest`

রিয়েল-টাইম স্ট্রিমিং অ্যানালিটিক্যাল ইভেন্ট ওল্যাপ ইঞ্জিনে গ্রহণ (ইনজেস্ট) করে।

```bash
# নতুন অ্যানালিটিক্যাল ইভেন্ট ইনজেস্ট করা
php netfusion druid:ingest events --dims="country=BD,browser=Chrome" --metrics="revenue=150,duration=2.5"
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<datasource>` | ডাটা সোর্স টেবিল নাম | আবশ্যক |
| `--dims=<key=val,...>` | কমা দ্বারা পৃথকীকৃত ডাইমেনশন কি-ভ্যালু জোড়া | - |
| `--metrics=<key=val,...>` | কমা দ্বারা পৃথকীকৃত নিউমেরিক মেট্রিক কি-ভ্যালু জোড়া | - |

---

### `druid:query`

রিয়েল-টাইম ইন-মেমরি বাফার এবং ইমিউটেবল কলামনার সেগমেন্টের উপর স্কেটার-গ্যাদার কুয়েরি চালায়।

```bash
# ডাইমেনশন অনুযায়ী গ্রুপ বাই এবং সাম/কাউন্ট অ্যাগ্রিগেশন
php netfusion druid:query events --groupby=country --sum=revenue --count

# নির্দিষ্ট লিমিট সহ কুয়েরি এক্সিকিউট করা
php netfusion druid:query events --groupby=browser --count --limit=10
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<datasource>` | ডাটা সোর্স টেবিল নাম | আবশ্যক |
| `--groupby=<dim1,dim2>` | গ্রুপিংয়ের জন্য ডাইমেনশন নাম | - |
| `--sum=<metric1,metric2>` | যোগফল গণনার জন্য মেট্রিক নাম | - |
| `--count` | মোট ইভেন্ট সংখ্যা গণনা করে | false |
| `--limit=<n>` | সর্বোচ্চ ফলাফল সংখ্যা | 50 |

---

### `druid:segments`

সিস্টেমে সংরক্ষিত সক্রিয় হিস্টোরিক্যাল এবং রিয়েল-টাইম কলামনার সেগমেন্টের মেটাডাটা পরিদর্শন করে।

```bash
# সমস্ত ডাটা সোর্সের সেগমেন্ট তালিকা প্রদর্শন
php netfusion druid:segments

# সুনির্দিষ্ট ডাটা সোর্সের সেগমেন্ট দেখা
php netfusion druid:segments events
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `[datasource]` | নির্দিষ্ট ডাটা সোর্স নাম | সমস্ত ডাটা সোর্স |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/olap.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/olap.php)

---

## 46. এন্টারপ্রাইজ ডিস্ট্রিবিউটেড কোঅর্ডিনেশন ও কনসেনসাস ইঞ্জিন (Apache ZooKeeper Architecture)

### `zk:create`

হায়ারারকিক্যাল নেমস্পেসে পারসিস্টেন্ট, এফিমিরাল অথবা সিকোয়েনশিয়াল ZNode তৈরি করে।

```bash
# সাধারণ পারসিস্টেন্ট নোড তৈরি
php netfusion zk:create /app/config "db_host=localhost"

# এফিমিরাল নোড তৈরি (সেশন শেষ হলে অটোমেটিক ডিলিট)
php netfusion zk:create /services/worker-1 "10.0.0.1:8080" --ephemeral

# সিকোয়েনশিয়াল নোড তৈরি (মোনোটোনিক অটো-ইনক্রিমেন্ট আইডি সহ)
php netfusion zk:create /locks/lock- --sequential
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<path>` | ZNode এর হায়ারারকিক্যাল পাথ | আবশ্যক |
| `[data]` | নোডে সংরক্ষিত পেলোড ডাটা | null |
| `--ephemeral` / `-e` | এফিমিরাল নোড ফ্ল্যাগ | false |
| `--sequential` / `-s` | সিকোয়েনশিয়াল অটো-ইনক্রিমেন্ট ফ্ল্যাগ | false |

---

### `zk:get`

একটি নির্দিষ্ট ZNode এর ডাটা এবং Stat মেটাডাটা (czxid, mzxid, ctime, version, ephemeralOwner ইত্যাদি) প্রদর্শন করে।

```bash
# নোড ডাটা এবং স্ট্যাট পরিদর্শন
php netfusion zk:get /app/config
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<path>` | ZNode এর হায়ারারকিক্যাল পাথ | আবশ্যক |

---

### `zk:ls`

একটি প্যারেন্ট ZNode এর অধীনে বিদ্যমান সমস্ত চাইল্ড নোডের নামের তালিকা প্রদর্শন করে।

```bash
# রুট নোডের চাইল্ড তালিকা দেখা
php netfusion zk:ls /

# নির্দিষ্ট পাথের চাইল্ড তালিকা দেখা
php netfusion zk:ls /services
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `[path]` | ZNode পাথ | `/` |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/zookeeper.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/zookeeper.php)

---

## 47. এন্টারপ্রাইজ মাইক্রো-ফ্রন্টেন্ড ও মডিউল ফেডারেশন ইঞ্জিন (Webpack 5 Architecture)

### `mfe:remotes`

সিস্টেমে রেজিস্টার্ড সমস্ত রিমোট কন্টেইনার, তাদের রিমোট এন্ট্রি ইউআরএল, এক্সপোজড মডিউল এবং শেয়ার্ড ডিপেন্ডেন্সির তালিকা প্রদর্শন করে।

```bash
# সমস্ত রিমোট কন্টেইনারের তালিকা ও মেটাডাটা দেখা
php netfusion mfe:remotes

# নির্দিষ্ট রিমোট কন্টেইনারের বিস্তারিত তথ্য দেখা
php netfusion mfe:remotes checkoutApp
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `[container]` | নির্দিষ্ট রিমোট কন্টেইনারের নাম | সমস্ত কন্টেইনার |

---

### `mfe:manifest`

একটি নির্দিষ্ট রিমোট কন্টেইনারের স্ট্যান্ডার্ড Webpack 5 / Vite Federation সামঞ্জস্যপূর্ণ `mf-manifest.json` স্কিমা ফরম্যাট করে টার্মিনালে প্রদর্শন করে।

```bash
# রিমোট কন্টেইনারের mf-manifest.json স্কিমা প্রদর্শন
php netfusion mfe:manifest checkoutApp
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<container>` | টার্গেট রিমোট কন্টেইনারের নাম | আবশ্যক |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/mfe.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/mfe.php)

---

## 48. এন্টারপ্রাইজ এজ কম্পিউটিং ও সার্ভারলেস ওয়ার্কার ফ্যাব্রিক (V8 Isolate & WebAssembly)

### `edge:deploy`

একটি সার্ভারলেস এজ ওয়ার্কার স্ক্রিপ্ট (PHP বা Wasm) এবং তার রাউটিং কনফিগারেশন গেটওয়েতে ডিপ্লয় করে।

```bash
# সাধারণ এজ ওয়ার্কার ডিপ্লয় করা
php netfusion edge:deploy api-gateway path/to/worker.php --route="/api/v1/*"

# Wasm কম্পাইল্ড বাইনারি ওয়ার্কার ডিপ্লয় করা
php netfusion edge:deploy wasm-compute path/to/module.wasm --type=wasm --route="/compute/*" --memory=256 --timeout=100
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<name>` | ওয়ার্কারের ইউনিক নাম | আবশ্যক |
| `[file]` | ওয়ার্কার স্ক্রিপ্ট বা Wasm বাইনারি ফাইলের পাথ | - |
| `--route=<pattern>` | এজ রাউটিং ওয়াইল্ডকার্ড প্যাটার্ন | - |
| `--type=<php\|wasm>` | ওয়ার্কার কোড টাইপ | `php` |
| `--memory=<mb>` | মেমরি কোটা লিমিট (MB) | 128 |
| `--timeout=<ms>` | সিপিইউ এক্সিকিউশন টাইমআউট (ms) | 50 |

---

### `edge:invoke`

একটি এজ ওয়ার্কারকে আইসোলেটেড স্যান্ডবক্সে সরাসরি টেস্ট-ইনভোক করে এবং রেসপন্স প্রদর্শন করে।

```bash
# ডিফল্ট গেট রিকোয়েস্ট দিয়ে ইনভোক করা
php netfusion edge:invoke api-gateway

# কাস্টম মেথড, URL এবং JSON পেলোড দিয়ে ইনভোক করা
php netfusion edge:invoke api-gateway --url="https://edge.local/api/v1/auth" --method=POST --payload='{"user":"admin"}'
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `<name>` | টার্গেট এজ ওয়ার্কারের নাম | আবশ্যক |
| `--url=<url>` | সিমুলেটেড এজ রিকোয়েস্ট URL | `https://edge.local/api/test` |
| `--method=<method>` | এইচটিটিপি মেথড (GET, POST ইত্যাদি) | `GET` |
| `--payload=<json>` | রিকোয়েস্ট বডি পেলোড | - |

---

### `edge:stats`

এজ কম্পিউটিং গেটওয়ের সামগ্রিক মেট্রিক্স, V8 Isolate পুল এবং রেসপন্স ক্যাশের স্ট্যাটিস্টিক্স প্রদর্শন করে।

```bash
# এজ টেলিমেট্রি ও আইসোলেট পুল মেট্রিক্স পরিদর্শন
php netfusion edge:stats
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/edge_worker.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/edge_worker.php)

---

## Phase 88: Real-Time WebRTC Media Server, SFU & Adaptive Bitrate Transcoding

### `media:rooms`

WebRTC SFU কনফারেন্স রুম তালিকা প্রদর্শন, তৈরি ও ডিলিট করার জন্য ব্যবহৃত হয়।

```bash
# সক্রিয় কনফারেন্স রুমের তালিকা ও পার্টিসিপেন্ট পরিসংখ্যান দেখা
php netfusion media:rooms

# নতুন SFU কনফারেন্স রুম তৈরি করা
php netfusion media:rooms --create=conf-101 --name="Sprint Review"

# বিদ্যমান কনফারেন্স রুম মুছে ফেলা
php netfusion media:rooms --delete=conf-101
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `--create=<id>` | নতুন রুমের ইউনিক আইডি | - |
| `--name=<name>` | কনফারেন্স রুমের নাম | `Conference` |
| `--delete=<id>` | মুছে ফেলার টার্গেট রুম আইডি | - |

---

### `media:stats`

ওয়েবআরটিসি মিডিয়া সার্ভারের গ্লোবাল ব্যান্ডউইথ মেট্রিক্স অথবা নির্দিষ্ট রুমের লাইভ স্ট্যাটিস্টিক্স প্রদর্শন করে।

```bash
# সমগ্র মিডিয়া সার্ভারের গ্লোবাল মেট্রিক্স দেখা
php netfusion media:stats

# নির্দিষ্ট কোনো রুমের লাইভ পার্টিসিপেন্ট ও অ্যাক্টিভ স্পিকার মেট্রিক্স দেখা
php netfusion media:stats --room=conf-101
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `--room=<id>` | নির্দিষ্ট কনফারেন্স রুম আইডি | - |

---

### `media:transcode`

RFC 8216 মানসম্মত এইচএলএস (HLS) অ্যাডাপ্টিভ বিটরেট (ABR) মাস্টার ও মিডিয়া প্লেলিস্ট জেনারেট করে।

```bash
# স্ট্যান্ডার্ড মাল্টি-বিটরেট (1080p, 720p, 480p, 360p) মাস্টার প্লেলিস্ট তৈরি করা
php netfusion media:transcode

# সিডিএন বেস ইউআরএল এবং ফাইল আউটপুট পাথ সহ জেনারেট করা
php netfusion media:transcode --prefix="https://cdn.example.com/hls/" --out="public/master.m3u8"
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `--prefix=<url>` | প্লেলিস্টের সেগমেন্ট/ভ্যারিয়েন্ট বেস ইউআরএল প্রিফিক্স | `""` |
| `--out=<file>` | প্লেলিস্ট সেভ করার ফাইল পাথ | কনসোল আউটপুট |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/media_server.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/media_server.php)

---

## ৯১. এন্টারপ্রাইজ জিওস্প্যাশিয়াল ইঞ্জিন, স্প্যাশিয়াল ইনডেক্সিং ও জিও-ফেন্সিং (Geospatial & Geo-Fencing)

### `geo:fence`

জিও-ফেন্স বাউন্ডারি তালিকাভুক্ত করা, নতুন সার্কুলার বা পলিগনাল জিও-ফেন্স তৈরি করা এবং নির্দিষ্ট কোঅর্ডিনেট জিও-ফেন্সের মধ্যে অবস্থান করছে কি না তা পয়েন্ট-ইন-পলিগন/রে-কাস্টিং অ্যালগরিদমের মাধ্যমে পরীক্ষা করা।

```bash
# নিবন্ধিত সকল জিও-ফেন্স বাউন্ডারির তালিকা দেখা
php netfusion geo:fence --list

# নতুন সার্কুলার জিও-ফেন্স তৈরি করা (নাম, কেন্দ্র ও ব্যাসার্ধ মিটার এককে)
php netfusion geo:fence --create="Dhaka Airport" --lat=23.8433 --lng=90.3978 --radius=2500

# কোনো কোঅর্ডিনেট জিও-ফেন্সের ভিতরে রয়েছে কি না যাচাই করা
php netfusion geo:fence --check="Dhaka Airport" --lat=23.8450 --lng=90.3980
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `--list` | সিস্টেমে সংরক্ষিত সকল জিও-ফেন্সের তালিকা প্রদর্শন করে | - |
| `--create=<name>` | নতুন সার্কুলার জিও-ফেন্স তৈরি করার নাম | - |
| `--check=<name>` | নির্দিষ্ট জিও-ফেন্সে কোঅর্ডিনেট ইনক্লুশন পরীক্ষা করার টার্গেট নাম | - |
| `--lat=<float>` | অক্ষাংশ (Latitude -90 to 90) | - |
| `--lng=<float>` | দ্রাঘিমাংশ (Longitude -180 to 180) | - |
| `--radius=<meters>`| সার্কুলার জিও-ফেন্সের ব্যাসার্ধ (মিটারে) | `1000` |

---

### `geo:h3`

উবার এইচ৩ (Uber H3) হেক্সাগোনাল হায়ারার্কিক্যাল স্প্যাশিয়াল ইন্ডেক্সিং এনকোডিং, ডিকোডিং এবং কে-রিং (k-Ring) নেইবারিং সেলস জেনারেশন পরিচালনা করে।

```bash
# WGS84 কোঅর্ডিনেটকে H3 হেক্সাগোনাল ইন্ডেক্সে কনভার্ট করা (রেজোলিউশন ০-১৫)
php netfusion geo:h3 --encode --lat=23.8103 --lng=90.4125 --res=8

# H3 হেক্স কোড ডিকোড করে সেন্ট্রয়েড অক্ষাংশ-দ্রাঘিমাংশ বের করা
php netfusion geo:h3 --decode=8894e57529fffff

# নির্দিষ্ট H3 হেক্সাগনের চারপাশের ১-রিং সমকেন্দ্রিক প্রতিবেশী সেলসমূহ বের করা
php netfusion geo:h3 --neighbors=8894e57529fffff --k=1
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `--encode` | প্রদত্ত কোঅর্ডিনেটকে H3 হেক্সাগোনাল ইনডেক্সে এনকোড করে | - |
| `--decode=<hex>` | H3 হেক্স ইনডেক্স ডিকোড করে সেন্ট্রয়েড ও বাউন্ডারি দেখায় | - |
| `--neighbors=<hex>` | সন্নিহিত সমকেন্দ্রিক হেক্সাগোনাল সেলের তালিকা (k-Ring) তৈরি করে | - |
| `--lat=<float>` | অক্ষাংশ (Latitude) | - |
| `--lng=<float>` | দ্রাঘিমাংশ (Longitude) | - |
| `--res=<0-15>` | H3 হায়ারার্কিক্যাল রেজোলিউশন লেভেল (০ থেকে ১৫) | `8` |
| `--k=<int>` | k-রিং রিং ডিস্ট্যান্স ধাপ | `1` |

---

### `geo:index`

২ডি আর-ট্রি (2D R-Tree) স্প্যাশিয়াল ইনডেক্স তৈরি, বাউন্ডিং বক্স রেঞ্জ ফিল্টারিং এবং কে-নিয়ারেস্ট নেইবারস (KNN) প্রায়োরিটি কিউ অনুসন্ধান পরিচালনা করে।

```bash
# ডেমো স্প্যাশিয়াল ডেটাসেট ইনডেক্স করে বাউন্ডিং বক্স রেঞ্জ সার্চ চালনা করা
php netfusion geo:index --demo --search="23.7,90.3,23.9,90.5"

# কোনো নির্দিষ্ট রেফারেন্স কোঅর্ডিনেটের সবচেয়ে নিকটবর্তী ৩টি লোকেশন বের করা (KNN)
php netfusion geo:index --demo --knn --lat=23.8103 --lng=90.4125 --k=3
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `--demo` | স্যাম্পল জিওস্প্যাশিয়াল ডাটা দিয়ে R-Tree তৈরি করে টেস্ট চালানো | - |
| `--search=<minLat,minLng,maxLat,maxLng>` | বাউন্ডিং বক্সের অভ্যন্তরীণ জিওমেট্রি খোঁজা | - |
| `--knn` | কে-নিয়ারেস্ট নেইবারস (KNN) সার্চ সক্রিয় করা | - |
| `--lat=<float>` | রেফারেন্স পয়েন্টের অক্ষাংশ | - |
| `--lng=<float>` | রেফারেন্স পয়েন্টের দ্রাঘিমাংশ | - |
| `--k=<int>` | নিকটতম ফলাফল সংখ্যা | `5` |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/geospatial.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/geospatial.php)

---

## ৯২. এন্টারপ্রাইজ সার্ভার-ড্রিভেন ইউআই (SDUI), ডায়নামিক কম্পোনেন্ট স্কিমা ও রিয়েল-টাইম ক্লায়েন্ট হাইড্রেশন (Server-Driven UI)

### `sdui:screens`

রেজিস্টার্ড সার্ভার-ড্রিভেন UI (SDUI) স্ক্রিন তালিকা প্রদর্শন করে এবং নির্দিষ্ট স্ক্রিনের লেআউট টেমপ্লেট স্কিমা বিস্তারিতভাবে পরিদর্শন করে।

```bash
# রেজিস্টার্ড সকল SDUI স্ক্রিনের অবস্থা দেখা
php netfusion sdui:screens

# নির্দিষ্ট কোনো স্ক্রিনের লেআউট স্কিমা JSON আকারে পরিদর্শন করা
php netfusion sdui:screens --info=home_dashboard
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `--info=<screen_id>` | নির্দিষ্ট স্ক্রিন আইডির সম্পূর্ণ লেআউট ট্রি প্রদর্শন করে | - |

---

### `sdui:validate`

সার্ভার-ড্রিভেন UI এর কম্পোনেন্ট বা স্ক্রিন JSON স্কিমা স্পেসিফিকেশন অনুযায়ী কাঠামোগত শুদ্ধতা ও প্রপস ভ্যালিডেশন করে।

```bash
# ফাইল থেকে JSON স্কিমা ভ্যালিডেট করা
php netfusion sdui:validate --file="storage/schemas/checkout.json"

# ইনলাইন JSON স্ট্রিং ভ্যালিডেট করা
php netfusion sdui:validate --json='{"id":"btn_submit","type":"button","children":[]}'
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `--file=<path>` | ভ্যালিডেশনের জন্য স্কিমা ফাইলের পাথ | - |
| `--json=<string>` | সরাসরি ইনলাইন JSON স্কিমা স্ট্রিং | - |

---

### `sdui:render`

ক্লায়েন্ট প্ল্যাটফর্ম (iOS, Android, Web, Flutter) এবং ব্যবহারকারীর প্রেক্ষাপট অনুযায়ী সার্ভার-ড্রিভেন স্ক্রিন কম্পোনেন্ট ট্রি বা পূর্ণাঙ্গ হাইড্রেশন পেলোড প্রস্তুত করে প্রদর্শন করে।

```bash
# নির্দিষ্ট স্ক্রিনের ক্লায়েন্ট স্কিমা রেন্ডার করা
php netfusion sdui:render --screen=home_dashboard

# নির্দিষ্ট মোবাইল প্ল্যাটফর্ম (iOS) ফিল্টারিং সহ রেন্ডার করা
php netfusion sdui:render --screen=home_dashboard --platform=ios

# চেকসাম ও HMAC সিগনেচার সমৃদ্ধ সম্পূর্ণ হাইড্রেশন পেলোড রেন্ডার করা
php netfusion sdui:render --screen=home_dashboard --hydrate
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `--screen=<id>` | রেন্ডার করার জন্য নির্ধারিত স্ক্রিন আইডি (আবশ্যক) | - |
| `--platform=<name>` | লক্ষ্যবস্তু ক্লায়েন্ট প্ল্যাটফর্ম (`all`, `web`, `ios`, `android`, `flutter`) | `all` |
| `--hydrate` | চেকসাম ও সিগনেচার সহ হাইড্রেশন এনভেলপ জেনারেট করে | `false` |

**সংশ্লিষ্ট কনফিগারেশন:** [`config/sdui.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/sdui.php)

---

## ৯৩. এন্টারপ্রাইজ মাল্টি-মোডাল ভেক্টর নলেজ গ্রাফ (GraphRAG), হাইব্রিড সিম্যান্টিক RAG ও ডায়নামিক গ্রাফ রি-র‍্যাঙ্কিং

### `graphrag:ingest`

ডকুমেন্ট বা টেক্সট চাঙ্ক ইনজেস্ট করে স্বয়ংক্রিয়ভাবে মাল্টি-মোডাল নলেজ গ্রাফে সেগমেন্ট করা, এনটিটি লিঙ্ক তৈরি করা এবং ভেক্টর/BM25 ইনডেক্সিং পরিচালনা করা।

```bash
# টেক্সট স্ট্রিং সরাসরি নলেজ গ্রাফে ইনজেস্ট করা
php netfusion graphrag:ingest --doc=manual_v1 --text="NetFusion is an enterprise PHP framework."

# টেক্সট বা মার্কডাউন ফাইল থেকে ইনজেস্ট করা
php netfusion graphrag:ingest --doc=whitepaper --file="storage/docs/rag_guide.txt"
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `--doc=<id>` | প্যারেন্ট ডকুমেন্ট আইডেন্টিফায়ার | `doc_<timestamp>` |
| `--text=<string>` | ইনজেস্ট করার জন্য সরাসরি টেক্সট কন্টেন্ট | - |
| `--file=<path>` | ইনজেস্ট করার জন্য সোর্স ফাইলের পাথ | - |

---

### `graphrag:query`

ট্রাই-ওয়ে হাইব্রিড সিম্যান্টিক রিট্রিভাল (Dense Vector + Sparse BM25 + Graph Traversal) চালনা করে এবং এলএলএম প্রম্পটের জন্য গ্রাউন্ডেড সিন্থেসাইজড কনটেক্সট প্রস্তুত করে।

```bash
# হাইব্রিড সিম্যান্টিক সার্চ ও রি-র‍্যাঙ্কিং ফলাফল প্রদর্শন
php netfusion graphrag:query --q="neural networks deep learning" --limit=3

# এলএলএম প্রম্পটের জন্য সম্পূর্ণ গ্রাউন্ডেড কনটেক্সট সিন্থেসিস জেনারেট করা
php netfusion graphrag:query --q="quantum computing" --limit=3 --synthesize
```

**Options:**

| Option | বিবরণ | ডিফল্ট |
|---|---|---|
| `--q=<query>` | অনুসন্ধান কুয়েরি টেক্সট (আবশ্যক) | - |
| `--limit=<int>` | শীর্ষ ফলাফলের সর্বোচ্চ সংখ্যা | `5` |
| `--synthesize` | এলএলএম এর জন্য গ্রাউন্ডেড কনটেক্সট ব্লক ফরম্যাট করে প্রদর্শন করে | `false` |

---

### `graphrag:stats`

মাল্টি-মোডাল ভেক্টর নলেজ গ্রাফের সামগ্রিক টপোলজি, সংরক্ষিত নোড ও এজের সংখ্যা এবং BM25 ইনভার্টেড ইনডেক্সের পরিসংখ্যান প্রদর্শন করে।

```bash
# গ্রাফ ও ভেক্টর ইনডেক্সের সামগ্রিক মেট্রিক্স প্রদর্শন
php netfusion graphrag:stats
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/graph_rag.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/graph_rag.php)

---

## ৯৪. রিয়েল-টাইম কোলাবোরেটিভ CRDT ও অপারেশনাল ট্রান্সফর্মেশন (OT) ইঞ্জিন (CRDT & OT Synchronizer)

### `crdt:sync`

দুটি সিমুলেটেড রেপ্লিকা নোডের মধ্যে স্টেট-বেসড বা ডেল্টা-বেসড সিঙ্ক্রোনাইজেশন চালিয়ে কনভার্জেন্স পরীক্ষা করে।

```bash
# টেক্সট ডকুমেন্টের জন্য দুই রেপ্লিকা সিঙ্ক পরীক্ষা
php netfusion crdt:sync demo_doc rga_text

# PN-কাউন্টারের জন্য রেপ্লিকা মার্জিং পরীক্ষা
php netfusion crdt:sync site_hits pn_counter
```

---

### `crdt:inspect`

একটি নির্দিষ্ট CRDT ডকুমেন্টের অভ্যন্তরীণ স্টেট, ভেক্টর ক্লক এবং চূড়ান্ত কনভার্জড মান বিস্তারিত প্রদর্শন করে।

```bash
# টেক্সট ডকুমেন্টের ইন্টারনাল আরজিএ স্টেট পরিদর্শন
php netfusion crdt:inspect shared_notes rga_text

# JSON-CRDT ডকুমেন্টের নেস্টেড ফিল্ড পরিদর্শন
php netfusion crdt:inspect user_profile json_crdt
```

---

### `crdt:resolve`

অপারেশনাল ট্রান্সফর্মেশন (OT) সার্ভার ইঞ্জিনের মাধ্যমে একই সময়ে একাধিক ক্লায়েন্টের কনকারেন্ট এডিটের ট্রান্সফর্মেশন ও লিনিয়ারাইজেশন প্রদর্শন করে।

```bash
# কনকারেন্ট এডিট রেজোলিউশন ডেমো প্রদর্শন
php netfusion crdt:resolve collab_doc
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/crdt.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/crdt.php)

---

## ৯৫. জিরো-নলেজ প্রুফ (ZKP) ইঞ্জিন ও ভেরিফায়েবল ক্রেডেনশিয়ালস (Zero-Knowledge Proof Engine)

### `zkp:setup`

নির্দিষ্ট পাটিগাণিতিক সার্কিটের (R1CS) জন্য ক্রিপ্টোগ্রাফিক ট্রাস্টেড সেটআপ সম্পন্ন করে প্রোভিং কি (Proving Key) এবং ভেরিফাইং কি (Verifying Key) তৈরি ও সংরক্ষণ করে।

```bash
# ফ্যাক্টর মাল্টিপ্লায়ার সার্কিটের ট্রাস্টেড সেটআপ রান করা
php netfusion zkp:setup factor_multiplier factor

# কিউবিক পলিনোমিয়াল সার্কিটের সেটআপ রান করা
php netfusion zkp:setup cubic_circuit cubic

# ৮-বিট রেঞ্জ প্রুফ সার্কিটের সেটআপ রান করা
php netfusion zkp:setup range_circuit range
```

---

### `zkp:prove`

প্রদত্ত প্রাইভেট উইটনেস এবং প্রাক-সংরক্ষিত প্রোভিং কি ব্যবহার করে Groth16 জিরো-নলেজ সাকসিন্ট প্রুফ (Proof) জেনারেট করে।

```bash
# পাবলিক ইনপুট n=77 ও গোপন ফ্যাক্টর x=7, y=11 এর জন্য প্রুফ তৈরি করা
php netfusion zkp:prove factor_multiplier '{"x":7,"y":11,"n":77}'

# প্রুফ ডেটাবেসে পারসিস্ট করা
php netfusion zkp:prove factor_multiplier '{"x":7,"y":11,"n":77}' --save
```

---

### `zkp:verify`

ভেরিফাইং কি ও পাবলিক সিগন্যাল ব্যবহার করে উইটনেস প্রকাশ না করেই প্রুফের সত্যতা তাৎক্ষণিক ক্রিপ্টোগ্রাফিক সমীকরণের মাধ্যমে যাচাই করে।

```bash
# নির্দিষ্ট প্রুফ আইডি যাচাই করা
php netfusion zkp:verify proof_68f8e02d factor_multiplier

# ইনলাইন JSON প্রুফ অবজেক্ট সরাসরি যাচাই করা
php netfusion zkp:verify '{"pi_a":"...","pi_b":"...","pi_c":"...","public_inputs":{"n":"77"}}' factor_multiplier
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/zkp.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/zkp.php)

---

## ৯৬. ফরমাল ভেরিফিকেশন, SMT সলভার (DPLL/T) ও সিম্বলিক এক্সিকিউশন (Formal Verification & SMT Solver)

### `formal:verify`

মেথডের ডিজাইন-বাই-কন্ট্রাক্ট (Hoare Logic: Precondition `@requires`, Postcondition `@ensures`) সমীকরণ এবং নিরাপত্তা শর্তাবলী গাণিতিকভাবে প্রমাণ বা অপ্রমাণ করে।

```bash
# নির্দিষ্ট মেথডের ফরমাল কন্ট্রাক্ট প্রমাণ করা
php netfusion formal:verify divide_safe

# সম্পূর্ণ ক্লাসের মেথড কন্ট্রাক্ট যাচাই করা
php netfusion formal:verify App\\Services\\PaymentService
```

---

### `formal:solve`

SMT-LIB 2.6 স্ট্যান্ডার্ড স্ক্রিপ্ট সরাসরি পার্স করে DPLL(T) স্যাটিসফায়াবিলিটি মডুলো থিওরি সলভারের (EUF, LRA, Bit-Vectors) মাধ্যমে সূত্র সমাধান ও ভ্যালুয়েশন মডেল এক্সট্র্যাক্ট করে।

```bash
# ইনলাইন SMT-LIB 2.6 স্ক্রিপ্ট সমাধান করা
php netfusion formal:solve "(declare-const x Int) (declare-const y Int) (assert (> (+ x y) 10)) (check-sat) (get-model)"

# SMT-LIB ফাইল পাস করে যাচাই করা
php netfusion formal:solve constraints.smt2
```

---

### `formal:symbolic`

পিএইচপি মেথড ও এক্সপ্রেশনের সিম্বলিক স্টেট এক্সপ্লোরেশন চালিয়ে আনকনস্ট্রেইন্ড ডিভিশন বাই জিরো, বাফার আউট-অব-বাউন্ডস এবং অ্যাসার্শন ভায়োলেশন শনাক্ত করে।

```bash
# মেথডের সিম্বলিক এক্সিকিউশন ও নিরাপত্তা ভালনারেবিলিটি ট্রেস করা
php netfusion formal:symbolic process_order
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/formal.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/formal.php)

---

## ৯৭. eBPF কার্নেল ফিল্টার, XDP ও নেটওয়ার্ক অবজারভেবিলিটি (eBPF, XDP & Observability)

### `ebpf:load`

eBPF বাইটকোড প্রোগ্রাম লোড করে এবং কার্নেল-লেভেল ভেরিফায়ারের মাধ্যমে DAG অ্যাক্লিক্লিসিটি ও মেমোরি সেফটি গ্যারান্টি প্রদান করে রানটাইমে রেজিস্টার করে।

```bash
# eBPF প্রোগ্রাম বাইটকোড লোড ও ভেরিফিকেশন করা
php netfusion ebpf:load --name=syn_filter

# বিস্তারিত ডাম্প সহ লোড করা
php netfusion ebpf:load --name=packet_counter --dump
```

---

### `ebpf:map`

eBPF শেয়ার্ড মেমোরি ম্যাপ (Array, Hash, LRU, RingBuffer) তৈরি, পরিদর্শন এবং ডাটা কাউন্টার রিড/ক্লিয়ার করে।

```bash
# নির্দিষ্ট ম্যাপের উপাদান পরিদর্শন করা
php netfusion ebpf:map --name=ip_blacklist --action=dump

# ম্যাপ এন্ট্রি ক্লিয়ার করা
php netfusion ebpf:map --name=flow_metrics --action=clear
```

---

### `xdp:attach`

XDP (eXpress Data Path) ফিল্টার প্রোগ্রামকে নেটওয়ার্ক ইন্টারফেসে এটাচ করে যাতে কার্নেল নেটওয়ার্ক ড্রাইভার লেভেলে সাব-মাইক্রোসেকেন্ডে `XDP_PASS` অথবা `XDP_DROP` কার্যকর করা যায়।

```bash
# eth0 ইন্টারফেসে XDP ফিল্টার কার্যকর করা
php netfusion xdp:attach --interface=eth0 --program=syn_filter

# স্ট্যাটাস এবং ড্রপ কাউন্টার পরীক্ষা করা
php netfusion xdp:attach --interface=eth0 --status
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/ebpf.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/ebpf.php)

---

## ৯৮. WebAssembly ইন্টারফেস টাইপ (WIT) ও কম্পোনেন্ট মডেল (WebAssembly Component Model & WIT)

### `wit:parse`

WebAssembly Interface Type (`.wit`) স্পেসিফিকেশন ফাইল অথবা ইনলাইন কোড পার্স করে ইন্টারফেস, ওয়ার্ল্ড, ফাংশন সিগনেচার এবং ক্যানোনিকাল এবিআই টাইপ লেআউট ভ্যালিডেট করে।

```bash
# WIT ফাইল পার্স ও পরিদর্শন করা
php netfusion wit:parse path/to/service.wit

# ইনলাইন WIT স্পেসিফিকেশন যাচাই করা
php netfusion wit:parse --source="package my:pkg@0.1.0; interface ops { run: func(n: u32) -> u32; } world w { export ops; }"
```

---

### `wasm:compose`

একাধিক ইন্ডিপেন্ডেন্ট WebAssembly কম্পোনেন্টকে (প্রোডিউসার এবং কনজিউমার) লিঙ্কর লেভেলে মার্জ করে একক কম্পোজড এক্সিকিউশন মডিউল বা পাইপলাইন তৈরি করে।

```bash
# দুটি কম্পোনেন্ট লিঙ্ক করে কম্পোজড পাইপলাইন তৈরি
php netfusion wasm:compose --producer=data_generator --consumer=data_processor --output=pipeline_app
```

---

### `wasm:component-run`

কম্পোনেন্ট মডিউল রানটাইমে ইনস্ট্যানশিয়েট করে এবং ক্যানোনিকাল এবিআই টাইপ লিফটিং/লোয়ারিংয়ের মাধ্যমে এক্সপোর্টেড ফাংশন এক্সিকিউট করে।

```bash
# কম্পোনেন্ট ফাংশন এক্সিকিউট করা
php netfusion wasm:component-run calculator --func=add --args=10 --args=20
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/wasm_component.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/wasm_component.php)

---

## ৯৯. ডিফারেনশিয়াল প্রাইভেসি, নয়েজ মেকানিজম ও ফেডারেটেড লার্নিং (Differential Privacy & Federated Aggregation)

### `dp:query`

ব্যবহারকারীদের ব্যক্তিগত গোপনীয়তা অক্ষুণ্ণ রেখে গাণিতিকভাবে ক্যালিব্রেটেড নয়েজ ইনজেকশনের মাধ্যমে ডিফারেনশিয়ালি প্রাইভেট কোয়েরি (COUNT, SUM, MEAN) এক্সিকিউট করে।

```bash
# কাউন্ট কোয়েরিতে ডিফারেনশিয়াল প্রাইভেসি নয়েজ ইনজেকশন
php netfusion dp:query count --val=1500 --eps=1.0 --budget=analyst_1

# বাউন্ডেড সাম কোয়েরি এক্সিকিউশন
php netfusion dp:query sum --val=45000 --eps=0.5
```

---

### `dp:budget`

নির্দিষ্ট এনালিস্ট, টেন্যান্ট বা ক্লায়েন্টের প্রাইভেসি বাজেট $(\epsilon, \delta)$ খরচ পর্যবেক্ষণ ও প্রয়োজনে রিসেট করে।

```bash
# প্রাইভেসি বাজেট খরচ ও অবশিষ্ট বাজেট পরীক্ষা
php netfusion dp:budget analyst_1

# ব্যয়িত বাজেট রিসেট করা
php netfusion dp:budget analyst_1 --reset
```

---

### `dp:federate`

ডিস্ট্রিবিউটেড ফেডারেটেড লার্নিং নোডগুলোর লোকাল মডেল ওয়েট বা গ্রেডিয়েন্টসমূহ $L_2$-নর্ম ক্লিপিং ও গাউসিয়ান নয়েজের মাধ্যমে সুরক্ষিতভাবে এগ্রিগেট (DP-FedAvg) করে।

```bash
# ফেডারেটেড অ্যাগ্রিগেশন সিমুলেশন
php netfusion dp:federate --nodes=5 --eps=1.5
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/privacy.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/privacy.php)

---

## ১০০. হোমোরফিক এনক্রিপশন ও সিকিউর মাল্টি-পার্টি কম্পিউটেশন (Homomorphic Encryption & SMPC)

### `he:encrypt`

হোমোরফিক এনক্রিপশন স্কিম (BFV বা CKKS) ব্যবহার করে ইনপুট ভ্যালুকে সাইফারটেক্সটে এনক্রিপ্ট করে যার উপর পরবর্তীতে ডিক্রিপ্ট না করেই গাণিতিক কম্পিউটেশন চালানো যায়।

```bash
# BFV স্কিমে পূর্ণসংখ্যা এনক্রিপ্ট করা
php netfusion he:encrypt 42 --scheme=bfv

# CKKS স্কিমে ফ্লোটিং পয়েন্ট সংখ্যা এনক্রিপ্ট করা
php netfusion he:encrypt 3.1415 --scheme=ckks
```

---

### `he:compute`

দুটি এনক্রিপ্টেড সাইফারটেক্সটের মধ্যে সিকিউর হোমোরফিক অপারেশন (যোগ, বিয়োগ বা স্কেলার গুণ) সম্পাদন করে।

```bash
# দুটি মানের এনক্রিপ্টেড হোমোরফিক যোগফল
php netfusion he:compute 10 25 --op=add

# এনক্রিপ্টেড বিয়োগফল
php netfusion he:compute 50 18 --op=sub
```

---

### `smpc:share`

শামিরের থ্রেশহোল্ড সিক্রেট শেয়ারিং $(k, n)$ ব্যবহার করে সংবেদনশীল সিক্রেটকে $n$ টি ভিন্ন শেয়ারে বিভক্ত করে, যেখানে যেকোনো $k$ সংখ্যক শেয়ার একত্রিত করে মূল সিক্রেট পুনরুদ্ধার করা যায়।

```bash
# সিক্রেট ভ্যালু 999 কে k=3, n=5 শেয়ারে বিভক্ত ও যাচাই করা
php netfusion smpc:share 999 --k=3 --n=5
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/homomorphic.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/homomorphic.php)

---

## ১০১. অটোনোমাস সেলফ-হিলিং ইনফ্রাস্ট্রাকচার, আরসিএ ও ক্যানারি রেমিডিয়েশন (Autonomous Self-Healing Infrastructure)

### `heal:diagnose`

মাইক্রোসার্ভিসের রিয়েল-টাইম মেট্রিক্স এবং ডিপেন্ডেন্সি গ্রাফ বিশ্লেষণ করে রুট-কজ অ্যানালাইসিস (RCA) রিপোর্ট তৈরি করে।

```bash
# নির্দিষ্ট সার্ভিসের মেট্রিক স্পাইক ও রুট-কজ ডায়াগনোসিস
php netfusion heal:diagnose order-service --metric=memory_usage --val=98.5
```

---

### `heal:remediate`

ব্লাস্ট রেডিয়াস পলিসি ও হেলথ প্রব ভেরিফিকেশন সহকারে ক্যানারি অনুপাতে (১০% -> ৫০% -> ১০০%) প্রগ্রেসিভ সেলফ-হিলিং রেমিডিয়েশন কার্যকর করে।

```bash
# পড রিস্টার্ট ক্যানারি রেমিডিয়েশন কার্যকর করা
php netfusion heal:remediate payment-service --action=restart_pod --canary=10

# স্কেল আউট রেমিডিয়েশন কার্যকর করা
php netfusion heal:remediate auth-service --action=scale_out
```

---

### `heal:status`

সেলফ-হিলিং ইঞ্জিনের বর্তমান লাইভ স্ট্যাটাস, অ্যাক্টিভ অ্যানোমালি, মোট আরসিএ রিপোর্ট এবং ব্লাস্ট রেডিয়াস গার্ডরেইল প্রদর্শন করে।

```bash
# সেলফ-হিলিং সিস্টেম স্ট্যাটাস পরীক্ষা
php netfusion heal:status
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/selfhealing.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/selfhealing.php)

---

## ১০২. কনফিডেন্সিয়াল কম্পিউটিং এনক্লেভ, রিমোট অ্যাটেস্টেশন ও মেমোরি সিলিং (Confidential Computing & Hardware Enclaves)

### `enclave:attest`

হার্ডওয়্যার-আইসোলেটেড এনক্লেভের প্ল্যাটফর্ম কনফিগারেশন রেজিস্টার (PCR) এবং মেমোরি মেজারমেন্টের জন্য ক্রিপ্টোগ্রাফিক রিমোট অ্যাটেস্টেশন কোট জেনারেট ও ভেরিফাই করে।

```bash
# Intel SGX এনক্লেভের জন্য রিমোট অ্যাটেস্টেশন সম্পাদন
php netfusion enclave:attest vault-enclave-01 --type=intel_sgx

# AMD SEV-SNP এনক্লেভ কোট ভেরিফিকেশন
php netfusion enclave:attest vault-enclave-01 --type=amd_sev_snp --nonce=challenge_nonce_1234
```

---

### `enclave:seal`

সংবেদনশীল সিক্রেট ডাটাকে হার্ডওয়্যার প্ল্যাটফর্ম মেজারমেন্টের (MRENCLAVE/MRSIGNER) সাথে সংযুক্ত করে AES-256-GCM ও HKDF এর মাধ্যমে এনক্লেভ মেমোরিতে সিল করে।

```bash
# এনক্লেভে সিক্রেট ডাটা ক্রিপ্টোগ্রাফিক্যালি সিল করা
php netfusion enclave:seal "Confidential-Database-Master-Password" --enclave=vault-enclave-01
```

---

### `enclave:unseal`

সিলকৃত সিক্রেট ডাটা আনসিল করে, যা শুধুমাত্র বর্তমান এনক্লেভ মেমোরি স্টেট এবং পিসিআর মান সিল করার সময়ের মেজারমেন্টের সাথে শতভাগ হুবহু মিললেই সম্ভব।

```bash
# এনক্লেভ থেকে সিলকৃত ডাটা ডিক্রিপ্ট ও আনসিল করা
php netfusion enclave:unseal --enclave=vault-enclave-01
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/enclave.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/enclave.php)

---

## ১০৩. কোয়ান্টাম কি ডিস্ট্রিবিউশন ও এনট্যাঙ্গেলমেন্ট মেশ (Quantum Key Distribution - QKD / BB84 & E91)

### `qkd:exchange`

নির্দিষ্ট পিয়ার নোডের সাথে কোয়ান্টাম কি ডিস্ট্রিবিউশন (BB84 Prepare-and-Measure অথবা E91 Entanglement) প্রোটোকল পরিচালনা করে এবং সিকিউর সিমেট্রিক কি ডিস্টিল করে।

```bash
# BB84 প্রোটোকলে ৫১২ ফোটন ট্রান্সমিট করে কি ডিস্ট্রিবিউশন
php netfusion qkd:exchange satellite-node-01 --photons=512 --protocol=bb84

# E91 কোয়ান্টাম এনট্যাঙ্গেলমেন্ট এবং বেল অসমতা যাচাইকরণ সহ কি এক্সচেঞ্জ
php netfusion qkd:exchange data-center-mesh-02 --photons=256 --protocol=e91
```

---

### `qkd:status`

সিস্টেমের সকল QKD পিয়ার নোড, সক্রিয় কি পুল সাইজ, কোয়ান্টাম বিট এরর রেট (QBER) মেজারমেন্ট এবং চ্যানেলের নিরাপত্তা স্ট্যাটাস প্রদর্শন করে।

```bash
# QKD মেশ নোড এবং কি পুলের বর্তমান অবস্থা পরীক্ষা
php netfusion qkd:status
```

---

### `qkd:benchmark`

কোয়ান্টাম কি ডিস্টিলেশন থ্রুপুট, এরর এনালাইসিস এবং প্রাইভেসি অ্যাম্প্লিফিকেশনের গাণিতিক পারফরম্যান্স বেঞ্চমার্ক পরিচালনা করে কি জেনারেশন রেট (bps) পরিমাপ করে।

```bash
# ৫ রাউন্ডে QKD পারফরম্যান্স বেঞ্চমার্ক চালনা
php netfusion qkd:benchmark --rounds=5 --photons=512
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/qkd.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/qkd.php)

---

## ১০৪. নিউরোমরফিক ইভেন্ট-চালিত কম্পিউটিং ও স্পাইকিং নিউরাল নেটওয়ার্ক (Neuromorphic Event-Driven Computing & Spiking Neural Network - SNN)

### `neuromorphic:train`

সারোগেট ডেল্টা লার্নিং নিয়মে স্পাইকিং নিউরাল নেটওয়ার্কের সিন্যাপটিক ওয়েট ট্রেনিং করে।

```bash
# স্পাইকিং নিউরাল নেটওয়ার্ক ট্রেনিং পরিচালনা
php netfusion neuromorphic:train cortex_classifier --epochs=5 --lr=0.05
```

---

### `neuromorphic:infer`

স্পাইকিং নিউরাল নেটওয়ার্কের মাধ্যমে রিয়েল-টাইম ইভেন্ট-ড্রিভেন ক্লাসিফিকেশন ইনফারেন্স সম্পাদন করে এবং সিন্যাপটিক অপারেশনস (SOPs) পরিমাপ করে।

```bash
# কন্টিনিউয়াস ফিচার ভেক্টরের ওপর SNN ইনফারেন্স চালনা
php netfusion neuromorphic:infer cortex_classifier --features=0.8,0.2,0.9,0.7 --timesteps=30
```

---

### `neuromorphic:benchmark`

নিউরোমরফিক কম্পিউটিং হার্ডওয়্যার সিমুলেশনে সিন্যাপটিক অপারেশনস পার সেকেন্ড (SOPs/sec) ও স্পাইক স্পারসিটি বেঞ্চমার্ক পরিমাপ করে।

```bash
# ৫০ নিউরন ও ৫০ টাইমস্টেপে নিউরোমরফিক বেঞ্চমার্ক
php netfusion neuromorphic:benchmark --neurons=50 --timesteps=50
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/neuromorphic.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/neuromorphic.php)

---

## ১০৫. অটোনোমাস মাল্টি-এজেন্ট সোয়ার্ম ইন্টেলিজেন্স ও বিএফটি সোয়ার্ম মেশ (Autonomous Multi-Agent Swarm Intelligence & BFT-Swarm Mesh)

### `swarm:status`

নিবন্ধিত সোয়ার্ম কলোনি, সক্রিয় এজেন্টস, ফেরোমোন গ্রিড এনার্জি, কনট্রাক্ট নেট অকশন এবং BFT কনসেনসাস স্টেটাস পর্যবেক্ষণ করে।

```bash
# ডিফল্ট সোয়ার্ম কলোনির বর্তমান অবস্থা পর্যবেক্ষণ
php netfusion swarm:status

# নির্দিষ্ট কলোনির স্ট্যাটাস দেখা
php netfusion swarm:status alpha_colony
```

---

### `swarm:auction`

কনট্রাক্ট নেট প্রোটোকল (CNP) ব্যবহার করে বিকেন্দ্রীভূত টাস্ক অকশন ব্রডকাস্ট করে এবং বিডিং এর মাধ্যমে সর্বোত্তম এজেন্টের নিকট দায়িত্ব অর্পণ করে।

```bash
# কলোনিতে একটি নতুন টাস্ক অকশন চালনা
php netfusion swarm:auction alpha_colony "data_indexing" --payout=50.0 --ttl=30
```

---

### `swarm:simulate`

সোয়ার্ম এজেন্টস ফ্লকিং ডাইনামিকস (Reynolds Flocking: Cohesion, Separation, Alignment) এবং টু-ডি ফেরোমোন ডিফিউশন সিমুলেশন চালনা করে।

```bash
# কলোনিতে ২০টি স্টেপ ফ্লকিং ও ফেরোমোন সিমুলেশন এক্সিকিউট করা
php netfusion swarm:simulate alpha_colony --steps=20 --evap=0.05
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/swarm.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/swarm.php)

---

## ১০৬. পোস্ট-কোয়ান্টাম ডিসেন্ট্রালাইজড আইডেন্টিটি (DID) ও ভেরিফায়েবল ক্রিডেনশিয়ালস (Decentralized Identity & Verifiable Credentials 2.0)

### `did:resolve`

নির্দিষ্ট Decentralized Identifier (DID) রেজলভ করে এবং W3C DID Document (পাবলিক কী, ভেরিফিকেশন মেথডস ও সার্ভিসেস) প্রদর্শন করে।

```bash
# did:key ফরম্যাটের ডিসেন্ট্রালাইজড আইডেন্টিটি রেজলভ করা
php netfusion did:resolve did:key:z6MkhaXgBZDvotDkL5257faiztiGiC2QtKLGpbnnEGta2doK

# did:netfusion এন্টারপ্রাইজ আইডেন্টিটি রেজলভ করা
php netfusion did:resolve did:netfusion:816cdb475aad1e3deb77db7ec86f7a5c
```

---

### `vc:issue`

W3C Verifiable Credentials 2.0 ডেটা মডেলে একটি নতুন ক্রিডেনশিয়াল তৈরি এবং ক্রিপ্টোগ্রাফিক সিগনেচার দিয়ে সাইন করে।

```bash
# নির্দিষ্ট সাবজেক্ট DID এর জন্য মেম্বারশিপ বা আর্কিটেক্ট ক্রিডেনশিয়াল ইস্যু করা
php netfusion vc:issue did:key:z6MkhaXgBZDvotDkL5257faiztiGiC2QtKLGpbnnEGta2doK --type=EnterpriseArchitectCredential --claims='{"name":"Ashish","role":"Lead Engineer"}'
```

---

### `vc:verify`

একটি Verifiable Credential এর ক্রিপ্টোগ্রাফিক সিগনেচার, এক্সপায়ারি এবং StatusList2021 বিটস্ট্রিং রিভোকেশন স্ট্যাটাস যাচাই করে।

```bash
# সরাসরি JSON স্ট্রিং বা ফাইলের পাথের সাহায্যে ভেরিফাই করা
php netfusion vc:verify '{"@context":["https://www.w3.org/2018/credentials/v1"],"id":"urn:uuid:...","issuer":"...","proof":{...}}'
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/identity.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/identity.php)

---

## ১০৭. স্পেশিয়াল কম্পিউটিং, ওয়েব-এক্সআর ডিজিটাল টুইন ও ৩ডি গাউসিয়ান স্প্ল্যাটিং (Spatial Computing & WebXR Digital Twin)

### `spatial:scene`

হায়ারার্কিক্যাল ৩ডি সিন গ্রাফ, স্পেশিয়াল নোডস ট্রি এবং প্যারেন্ট-চাইল্ড ওয়ার্ল্ড ট্রান্সফর্ম মেট্রিক্স প্রদর্শন করে।

```bash
# ডিফল্ট সিন গ্রাফের হায়ারার্কি পর্যবেক্ষণ
php netfusion spatial:scene

# নির্দিষ্ট ডিজিটাল টুইন সিন পর্যবেক্ষণ
php netfusion spatial:scene factory_floor_01
```

---

### `spatial:splat`

3D Gaussian Splatting (3DGS) পয়েন্ট ক্লাউড, অপাসিটি, কালার এবং ভিউ-স্পেস ডেবথ-সর্টিং এর জন্য AABB বাউন্ডিং বক্স পরিমাপ করে।

```bash
# স্প্ল্যাট ক্লাউডের বাউন্ডিং বক্স এবং র্যাডিয়েন্স সেন্টার পরিদর্শন
php netfusion spatial:splat plant_scan --count=200
```

---

### `spatial:simulate`

নিউটনিয়ান মেকানিক্স, গ্র্যাভিটেশনাল অ্যাক্সিলারেশন, ড্র্যাগ এবং গ্রাউন্ড রিবাউন্ড সহ ৩ডি রিজিড বডি ফিজিক্স সিমুলেশন এক্সিকিউট করে।

```bash
# একটি অবজেক্টের গ্র্যাভিটি ড্রপ ও রিবাউন্ড ৩০ স্টেপে সিমুলেট করা
php netfusion spatial:simulate factory_floor_01 --steps=30 --dt=0.016
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/spatial.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/spatial.php)

---

## ১০৮. এন্টারপ্রাইজ হলোগ্রাফিক স্টোরেজ ও ডিএনএ মলিকুলার ডেটা এনকোডিং (Holographic Storage, DNA Molecular Encoding & Cold-Vault Mesh Fabric)

`molecular` কমান্ডসমূহ দীর্ঘমেয়াদী কোল্ড-ভল্ট ব্যাকআপ, ডিএনএ সিন্থেসিস অলিগো পুল ও রিড-সলোমন এরর কারেকশন সহ হলোগ্রাফিক ক্রিস্টাল ব্র্যাগ রিডিং পরিচালনা করে।

### `molecular:encode`

বাইনারি বা টেক্সট ডেটাকে গোল্ডম্যান ট্রাইট রোটেশনাল ম্যাপিং ও রিড-সলোমন প্যারিটি সহ বায়োলজিক্যাল অলিগো ফ্র্যাগমেন্ট পুলে এনকোড করে।

```bash
# টেক্সট ডেটাকে ডিএনএ অলিগো পুলে রূপান্তর
php netfusion molecular:encode "NetFusion Enterprise Archival Data 2026" --chunk=16 --rs=6
```

---

### `molecular:decode`

মিউটেশন/ডিকে সিমুলেটেড বা সিকোয়েন্সড অলিগো পুল থেকে রিড-সলোমন এরর কারেকশন (Berlekamp-Massey & Forney algorithm) প্রয়োগ করে মূল বাইনারি ডেটা পুনরুদ্ধার করে।

```bash
# ডিএনএ পুল থেকে ডেটা ডিকোড ও ত্রুটি সংশোধন
php netfusion molecular:decode doc_archive_01 --rs=6
```

---

### `molecular:holograph`

ফটোরিফ্র্যাক্টিভ ক্রিস্টালে নির্দিষ্ট ব্র্যাগ অ্যাঙ্গেল সহ ২ডি স্পেশাল লাইট মডুলেটর (SLM) বিট-প্যাটার্ন পেজ রেকর্ড অথবা অপটিক্যাল রি-কনস্ট্রাকশন পরিচালনা করে।

```bash
# হলোগ্রাফিক ক্রিস্টালে অপটিক্যাল পেজ সংরক্ষণ
php netfusion molecular:holograph lithium_niobate_01 "LaserEncryptedBlock" --page=1 --angle=14.50

# নির্দিষ্ট ব্র্যাগ অ্যাঙ্গেল ইন্টারোগেশনের মাধ্যমে পেজ রিকনস্ট্রাকশন
php netfusion molecular:holograph lithium_niobate_01 --read --angle=14.50
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/molecular.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/molecular.php)

---

## ১০৯. এন্টারপ্রাইজ ব্রেন-কম্পিউটার ইন্টারফেস ও নিউরাল সিগন্যাল ডিকোডিং (Brain-Computer Interface, Neural Signal Decoding & Closed-Loop Neuro-Feedback Fabric)

`bci` কমান্ডসমূহ রিয়েল-টাইম ইইজি মাল্টি-চ্যানেল স্ট্রিম ইনজেশন, ডিজিটাল ফিল্টারিং (Notch, Butterworth, CAR), কমন স্পেশাল প্যাটার্ন (CSP) ও এলডিএ (LDA) মোটর ইমেজারি ইনটেন্ট ক্লাসিফিকেশন পরিচালনা করে।

### `bci:stream`

সক্রিয় ইইজি সেশনের মাল্টি-চ্যানেল টেলিমিতি স্ট্রিম এবং ইলেকট্রোড ইম্পিড্যান্স সেফটি মনিটর করে।

```bash
# C3, Cz, C4 চ্যানেলে ২৫০ স্যাম্পল স্ট্রিম ইনজেস্ট ও মনিটর করা
php netfusion bci:stream session_alpha_01 --channels=C3,Cz,C4 --samples=250
```

---

### `bci:filter`

নচ ফিল্টার (৫০Hz/৬০Hz), বাটারওয়ার্থ ব্যান্ডপাস (মিউ/বিটা রিদম ৮-৩০Hz) এবং কমন এভারেজ রেফারেন্স (CAR) স্পেশাল ফিল্টারিং পাইপলাইন পরিচালনা করে।

```bash
# ৮-৩০ Hz ব্যান্ডপাস এবং ৫০ Hz নচ ফিল্টার এক্সিকিউট করা
php netfusion bci:filter --low=8.0 --high=30.0 --rate=250
```

---

### `bci:decode`

মোটর ইমেজারি ট্রায়াল থেকে কমন স্পেশাল প্যাটার্ন (CSP) ফিল্টার ও লিনিয়ার ডিসক্রিমিন্যান্ট অ্যানালাইসিস (LDA) ট্রেইনিং করে নিউরাল ইনটেন্ট ডিকোড করে।

```bash
# ১০টি সিন্থেটিক ট্রায়ালে মডেল ক্যালিব্রেশন এবং কগনিটিভ ইনটেন্ট ডিকোড
php netfusion bci:decode --trials=10
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/bci.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/bci.php)

---

## ১১০. এন্টারপ্রাইজ অপটিক্যাল ফটোনিক কম্পিউটিং ও টেনসর লাইট-ইন্টারফেরোমিটার (Optical Photonic Computing, Tensor MZI Mesh & Coherent Neural Accelerator Fabric)

`photonic` কমান্ডসমূহ ম্যাক-জেন্ডার ইন্টারফেরোমিটার (MZI) মেশ সিমুলেশন, অপটিক্যাল টেনসর ম্যাট্রিক্স-ভেক্টর গুণন (Optical GEMV) এবং আল্ট্রা-লো লেটেন্সি ফটোনিক থ্রুপুট বেঞ্চমার্কিং পরিচালনা করে।

### `photonic:mzi`

কোহেরেন্ট অপটিক্যাল ফিল্ড ও ফেজ প্রোফাইল সহ N-পোর্ট MZI ইন্টারফেরোমিটার মেশের ট্রান্সফার ম্যাট্রিক্স ও পোর্ট পাওয়ার ডিস্ট্রিবিউশন প্রদর্শন করে।

```bash
# ৪-পোর্ট ক্লিমেন্টস আয়তাকার মেশ সিমুলেশন
php netfusion photonic:mzi 4 --topology=clements
```

---

### `photonic:gemm`

আলোর গতিতে (Speed of Light, ~২০ পিকোসেকেন্ড) অপটিক্যাল টেনসর ম্যাট্রিক্স-ভেক্টর গুণন এবং নন-লিনিয়ার অপটিক্যাল অ্যাক্টিভেশন এক্সিকিউট করে।

```bash
# ৪x৪ টেনসরে অপটিক্যাল ReLU অ্যাক্টিভেশন সহ GEMV এক্সিকিউট করা
php netfusion photonic:gemm --dim=4 --act=relu
```

---

### `photonic:benchmark`

ফটোনিক টেনসর প্রসেসিং থ্রুপুট (Giga-OPS/sec), পিকোসেকেন্ড লেটেন্সি এবং এনার্জি এফিশিয়েন্সি (TOPS/Watt) বেঞ্চমার্ক পরিমাপ করে।

```bash
# ৮x৮ ম্যাট্রিক্স ৫০০ ব্যাচে অপটিক্যাল থ্রুপুট বেঞ্চমার্ক করা
php netfusion photonic:benchmark --dim=8 --batches=500
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/photonic.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/photonic.php)

---

## Phase 109: Enterprise Reconfigurable Intelligent Surfaces (RIS) & 6G THz Metamaterial Fabric

### `ris:steer`

একটি Reconfigurable Intelligent Surface (RIS) মেটাম্যাটেরিয়াল অ্যারেকে নির্দিষ্ট ইনসিডেন্ট এবং রিফ্লেক্টেড অ্যাঙ্গেলের ত্রিমাত্রিক স্ফেরিক্যাল কোঅর্ডিনেটসে বিম স্টিয়ারিং করার জন্য ফেজ প্রোফাইল কনফিগার করে।

```bash
# ৩০০ গিগাহার্টজ টেরাহার্টজ অ্যারেকে নির্দিষ্ট দিকে স্টিয়ার করা
php netfusion ris:steer ris_thz_01 --band=terahertz --res=continuous --rows=8 --cols=8 --in-azimuth=0.0 --in-elevation=30.0 --out-azimuth=45.0 --out-elevation=15.0

# ২-বিট ডিসক্রিট কোয়ান্টাইজেশনে স্টিয়ার করা
php netfusion ris:steer ris_thz_02 --band=terahertz --res=2_bit --in-azimuth=10.0 --out-azimuth=60.0
```

---

### `ris:channel`

টেরাহার্টজ ও মিলিমিটারওয়েভ ফ্রিকোয়েন্সিতে ফ্রি-স্পেস প্যাথ লস ও আণবিক শোষণ সহ ক্যাসকেডেড RIS ওয়্যারলেস চ্যানেল ক্যাপাসিটি (bps/Hz) এবং SNR গেইন সিমুলেট করে।

```bash
# ম্যাক্সিমাম রেশিও ট্রান্সমিশন (MRT) অপ্টিমাইজেশন দিয়ে ক্যাসকেডেড চ্যানেল সিমুলেশন
php netfusion ris:channel ris_thz_01 --band=terahertz --tx-dist=15.0 --rx-dist=10.0 --direct-dist=20.0 --algo=mrt
```

---

### `ris:benchmark`

কন্টিনিউয়াস ফেজ শিফট বনাম ১-বিট, ২-বিট এবং ৩-বিট ডিসক্রিট কোয়ান্টাইজেশনের ফেজ এরর, এসএনআর গেইন এবং শ্যানন ক্যাপাসিটি পারফরম্যান্স তুলনা ও বেঞ্চমার্ক করে।

```bash
# ৮x৮ গ্রিডে কোয়ান্টাইজেশন বেঞ্চমার্ক রান করা
php netfusion ris:benchmark --rows=8 --cols=8 --band=terahertz
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/ris.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/ris.php)

---

## Phase 110: Enterprise Gravitational Waveform Simulation, Spacetime Metric Tensor & Numerical Relativity Mesh Fabric

### `relativity:chirp`

কম্প্যাক্ট বাইনারি ব্ল্যাকহোল অথবা নিউট্রন স্টার মার্জারের পোস্ট-নিউটনিয়ান Chirp Mass ($\mathcal{M}$), ISCO অরবিটাল ফ্রিকোয়েন্সি, কোয়াসি-নরমাল রিংডাউন (QNM) এবং পিক স্ট্রেন ($h$) সিমুলেট করে।

```bash
# GW150914 সাদৃশ্যপূর্ণ ৩৬ + ২৯ সৌর ভরের বাইনারি ব্ল্যাকহোল ওয়েভফর্ম তৈরি করা
php netfusion relativity:chirp --m1=36.0 --m2=29.0 --dist=410.0 --duration=0.2 --rate=2048.0
```

---

### `relativity:metric`

মিনকোভস্কি, সোয়ার্জশিল্ড অথবা স্পিনিং কার (Kerr) ব্ল্যাকহোল ব্যাকগ্রাউন্ডে ৪-মাত্রিক স্পেসটাইম মেট্রিক টেনসর $g_{\mu\nu}$, কার্ভেচার ডিটারমিন্যান্ট এবং ইভেন্ট হরাইজন ব্যাসার্ধ ($r_s$) গণনা করে।

```bash
# ১০ সৌর ভরের সোয়ার্জশিল্ড ব্ল্যাকহোলের ১০০ কিমি দূরত্বে মেট্রিক কম্পিউট করা
php netfusion relativity:metric --type=schwarzschild --mass=10.0 --radius=100.0

# স্পিনিং কার (Kerr) ব্ল্যাকহোলের মেট্রিক গণনা করা
php netfusion relativity:metric --type=kerr --mass=15.0 --radius=150.0 --spin=0.85
```

---

### `relativity:detect`

LIGO (Hanford H1, Livingston L1), Virgo (V1) অথবা LISA মহাকাশ ইন্টারফেরোমিটার ডিটেক্টর স্ট্রিমে ম্যাচড ফিল্টারিং ক্রস-কোরিলেশন রান করে এবং অপ্টিমাল সিগন্যাল-টু-নয়েজ রেশিও (SNR) নির্ণয় করে।

```bash
# লাইগো হ্যানফোর্ড (H1) ইন্টারফেরোমিটারে ম্যাচড ফিল্টার ডিটেকশন রান করা
php netfusion relativity:detect --detector=H1 --snr-thresh=8.0
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/relativity.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/relativity.php)

---

## Phase 111: Enterprise Topological Data Analysis (TDA), Persistent Homology & Vietoris-Rips Mesh Fabric

### `tda:homology`

উচ্চ-মাত্রিক পয়েন্ট ক্লাউড ডেটাসেট থেকে ফিল্টার্ড ভিয়েটরিস-রিপ্স কমপ্লেক্স তৈরি করে $\mathbb{Z}_2$ কলাম রিডাকশনের মাধ্যমে পারসিস্টেন্ট হোমোলজি বারকোড এবং বেত্তি নাম্বার ($\beta_0, \beta_1, \beta_2$) গণনা করে।

```bash
# ৮-পয়েন্ট সার্কেল ম্যানিফোল্ডে টপোলজিক্যাল হোমোলজি রান করা
php netfusion tda:homology --points=circle --max-filtration=2.5 --dim=2

# ক্লাস্টার্ড পয়েন্ট ক্লাউডে কানেক্টেড কম্পোনেন্ট ট্র্যাকিং করা
php netfusion tda:homology --points=clusters --max-filtration=1.5
```

---

### `tda:rips`

নির্দিষ্ট ফিল্ট্রেশন রেডিয়াস ($\epsilon$) পর্যন্ত ০-সিমপ্লেক্স (শীর্ষবিন্দু), ১-সিমপ্লেক্স (প্রান্ত), এবং ২-সিমপ্লেক্স (ত্রিভুজ) বিন্যাস বিশ্লেষণ করে ফিল্টার্ড ভিয়েটরিস-রিপ্স সিম্প্লিসিয়াল কমপ্লেক্স পরিসংখ্যান প্রদর্শন করে।

```bash
# ফিল্ট্রেশন রেডিয়াস ১.৫ পর্যন্ত সিমপ্লেক্স পরিসংখ্যান দেখা
php netfusion tda:rips --max-filtration=1.5 --dim=2
```

---

### `tda:barcode`

টার্মিনাল আসকি (ASCII) ইন্টারফেসে পারসিস্টেন্ট হোমোলজি বারকোড ও প্রতিটি টপোলজিক্যাল ফিচারের বার্থ (Birth), ডেথ (Death) এবং লাইফস্প্যান (Lifespan) ভিজ্যুয়ালাইজ করে।

```bash
# টপোলজিক্যাল বারকোড ব্যবধান টার্মিনালে দেখা
php netfusion tda:barcode --max-filtration=2.0
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/tda.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/tda.php)

---

## Phase 112: Enterprise Cognitive Architecture & Synthetic Memory (ACT-R / SOAR Engine)

### `cognitive:cycle`

সক্রিয় গোল (Goal), রিট্রিভাল (Retrieval) ও অ্যাকশন (Action) বাফারসমূহের ওপর ভিত্তি করে ACT-R কগনিটিভ Recognize-Act সাইকেল নির্বাহ করে এবং সর্বোচ্চ ইউটিলিটি রুল ফায়ার করে।

```bash
# ৩টি পর্যায়ক্রমিক কগনিটিভ সাইকেল এক্সিকিউট করা
php netfusion cognitive:cycle --cycles=3
```

---

### `cognitive:recall`

ACT-R পাওয়ার-ল অব ডিকে ($A_i = \ln \sum t_k^{-d}$) এবং রিহার্সাল ফ্রিকোয়েন্সি বিবেচনা করে ডিক্লারেটিভ মেমরি থেকে সর্বোচ্চ অ্যাক্টিভেশন সম্পন্ন ফ্যাক্ট চাঙ্ক রিকল করে।

```bash
# নির্দিষ্ট কনসেপ্টে ডিক্লারেটিভ মেমরি চাঙ্ক রিকল করা
php netfusion cognitive:recall --concept=server_metric
```

---

### `cognitive:learn`

এজেন্টের অভিজ্ঞতা ও কর্মপ্রবাহকে সময়ানুক্রমিক আত্মজীবনীমূলক এপিসোডিক মেমরিতে (Episodic Memory) সংরক্ষণ করে এবং রিফ্লেকশন প্যাটার্ন তৈরি করে।

```bash
# নতুন এপিসোডিক এক্সপেরিয়েন্স রেকর্ড করা
php netfusion cognitive:learn --action=deploy_service --outcome=success
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/cognitive.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/cognitive.php)

---

## Phase 113: Enterprise Zero-Knowledge Rollup (ZK-Rollup), Merkle-Mountain Range (MMR) & State Transition Execution Fabric

### `rollup:batch`

পেন্ডিং স্টেট ট্রানজিশনসমূহ মেমপুল থেকে সংগ্রহ করে অফ-চেইনে এক্সিকিউট করে, স্পার্স মার্কেল ট্রি স্টেট রুট আপডেট করে এবং হিস্টোরিক্যাল MMR-এ নতুন ZK-Rollup ব্যাচ কমিট করে।

```bash
# ডিফল্ট ব্যাচ সাইজে (৫০টি ট্রানজিশন) রোলআপ ব্যাচ প্রসেস ও সিল করা
php netfusion rollup:batch

# সর্বোচ্চ ১০০টি ট্রানজিশন দিয়ে ব্যাচ প্রসেস করা
php netfusion rollup:batch --max=100
```

---

### `rollup:verify`

স্পার্স মার্কেল ট্রি (SMT) স্টেট ইনক্লুশন প্রুফ এবং হিস্টোরিক্যাল মারকেল মাউন্টেন রেঞ্জ (MMR) লগারিদমিক ইনক্লুশন প্রুফ ক্রিপ্টোগ্রাফিকভাবে যাচাই করে।

```bash
# নির্দিষ্ট অ্যাকাউন্টের স্টেট ইনক্লুশন প্রুফ ও ব্যাচ #১ এর MMR ইনক্লুশন ভেরিফাই করা
php netfusion rollup:verify --account=alice --batch=1
```

---

### `rollup:state`

স্পার্স মার্কেল ট্রি লিভস, প্রতিটি অ্যাকাউন্টের ব্যালেন্স ও ট্রানজিশন ননস, এবং গ্লোবাল স্টেট রুট ইন্সপেকশন করে।

```bash
# গ্লোবাল স্টেট রুট ও সকল অ্যাকাউন্টের ব্যালেন্স ওভারভিউ প্রদর্শন
php netfusion rollup:state

# নির্দিষ্ট অ্যাকাউন্টের স্টেট ও ননস বিস্তারিত দেখা
php netfusion rollup:state --account=alice
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/rollup.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/rollup.php)

---

## Phase 114: Enterprise Lattice Boltzmann Method (LBM), Computational Fluid Dynamics (CFD) & D2Q9 Velocity Grid Fabric

### `lbm:cavity`

দ্বিমাত্রিক লিড-ড্রিভেন ক্যাভিটি (2D Lid-Driven Cavity) তরল সঞ্চালন সিমুলেশন নির্বাহ করে এবং টার্মিনালে ভেলোসিটি ম্যাগনিটিউড আসকি (ASCII) হিটম্যাপ রেন্ডার করে।

```bash
# ৩০x৩০ গ্রিডে ৪০ টাইমস্টেপ লিড-ড্রিভেন ক্যাভিটি ফ্লো রান করা
php netfusion lbm:cavity --size=30 --steps=40 --lid=0.08 --tau=0.6
```

---

### `lbm:flow`

চ্যানেল ফ্লো এবং সিলিন্ডার বাধার চারপাশে তরল প্রবাহ সিমুলেশন করে, বাউন্স-ব্যাক নো-স্লিপ বাউন্ডারি প্রয়োগ করে এবং ভরটিসিটি কার্ল ($\omega = \nabla \times \mathbf{u}$) বিশ্লেষণ করে।

```bash
# সিলিন্ডার বাধার চারপাশে তরল প্রবাহ ও ভর্টেক্স বিশ্লেষণ
php netfusion lbm:flow --width=50 --height=25 --steps=35 --u=0.06
```

---

### `lbm:benchmark`

ল্যাটিস বোল্টজম্যান মেথড ইঞ্জিনের কম্পিউটেশনাল স্পিড প্রতি সেকেন্ডে মিলিয়ন ল্যাটিস আপডেট (MLUPS) স্কেলে পরিমাপ করে।

```bash
# ৪০x৪০ ল্যাটিস গ্রিডে ২৫ টাইমস্টেপের থ্রুপুট ঘনত্ব বেঞ্চমার্ক করা
php netfusion lbm:benchmark --size=40 --steps=25
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/lbm.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/lbm.php)

---

## Phase 115: Enterprise Synthetic Biology, BioCAD, CRISPR Screening & Gene Circuit Simulator Fabric

### `biocad:crispr`

টার্গেট ডিএনএ লোকাস স্ক্যান করে প্রোটোস্পেসার অ্যাডজাসেন্ট মোটিফ (PAM) শনাক্ত করে এবং উচ্চ-নির্দিষ্টতাসম্পন্ন CRISPR গাইড আরএনএ (gRNA) ক্যান্ডিডেট ডিজাইন ও র্যাংক করে।

```bash
# ডিএনএ সিকোয়েন্সে SpCas9 (NGG PAM) গাইড স্ক্রিনিং
php netfusion biocad:crispr "ATGCGATCGATCGATCGATCGATCGATCGATCGGATCGATCGATCGT"

# SaCas9 ভ্যারিয়েন্ট ও সর্বোচ্চ ৩টি ক্যান্ডিডেট প্রদর্শন
php netfusion biocad:crispr --cas=SaCas9 --limit=3
```

---

### `biocad:optimize`

নির্দিষ্ট হোস্ট অর্গানিজমের (যেমন: *E. coli*, *S. cerevisiae*, Human, CHO) কোডন ব্যবহার পক্ষপাতিত্বের ওপর ভিত্তি করে কোডিং সিকোয়েন্স (CDS) সিনোনিমাস সাবস্টিটিউশন করে এবং কোডন অ্যাডাপ্টেশন ইনডেক্স (CAI) সর্বোচ্চ করে।

```bash
# E. coli হোস্টের জন্য কোডিং সিকোয়েন্স অপটিমাইজেশন
php netfusion biocad:optimize "ATGGCCATGGCGCCCAGAACCTGAGTA"

# মানব কোষের জন্য অপটিমাইজেশন
php netfusion biocad:optimize "ATGGCCATGGCGCCCAGAACCTGAGTA" --host=h_sapiens
```

---

### `biocad:circuit`

সিন্থেটিক জিন সার্কিটের গতিশীল আচরণ ও কাইনেটিক্স ৪র্থ-অর্ডার রুঙ্গে-কুট্টা (RK4) নিউমেরিক্যাল ইন্টিগ্রেশন ব্যবহার করে সিমুলেট করে (গার্ডনার টগল সুইচ কিংবা ইলোভিটজ রিপ্রেসিল্যাটার অসিলেটর)।

```bash
# বাইস্টেবল জেনেটিক টগল সুইচ সিমুলেশন
php netfusion biocad:circuit --type=toggle --steps=10

# রিপ্রেসিল্যাটার ৩-জিন সাইক্লিক অসিলেটর সিমুলেশন
php netfusion biocad:circuit --type=repressilator --steps=15
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/biocad.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/biocad.php)

---

## Phase 116: Enterprise Astro-Informatics, Symplectic N-Body Integrators & Orbital Mechanics Fabric

### `astro:orbit`

কেপলারীয় অরবিটাল এলিমেন্টস ($a, e, i, \Omega, \omega, \nu$) থেকে অরবিটাল পিরিয়ড, অ্যাপোঅ্যাপসিস, পেরিঅ্যাপসিস এবং ত্রিমাত্রিক হেলিওসেন্ট্রিক কার্টেসিয়ান অবস্থান ও গতিবেগ ভেক্টর ($\mathbf{r}, \mathbf{v}$) গণনা করে।

```bash
# পৃথিবীর ডিফল্ট কক্ষপথের অরবিটাল মেকানিক্স বিশ্লেষণ
php netfusion astro:orbit

# মঙ্গল গ্রহের কক্ষপথ প্যারামিটার (a = 1.524 AU, e = 0.0934)
php netfusion astro:orbit 1.524 --e=0.0934 --i=1.85
```

---

### `astro:nbody`

লিপফ্রগ (২য় অর্ডার) অথবা ইয়োশিদা (৪র্থ অর্ডার) সিম্প্লেকটিক ইন্টিগ্রেটর ব্যবহার করে মহাকর্ষীয় এন-বডি সিস্টেম (যেমন সূর্য-পৃথিবী-চাঁদ) সিমুলেশন রান করে এবং নিখুঁত মেকানিক্যাল এনার্জি সংরক্ষণ যাচাই করে।

```bash
# ১০ দিনব্যাপী সিম্প্লেকটিক লিপফ্রগ এন-বডি সিমুলেশন
php netfusion astro:nbody --steps=10 --dt=86400

# ৪র্থ-অর্ডার ইয়োশিদা সিম্প্লেকটিক ইন্টিগ্রেটর ব্যবহার
php netfusion astro:nbody --steps=20 --dt=86400 --integrator=yoshida_4th
```

---

### `astro:transfer`

দুটি বৃত্তাকার সমতলীয় কক্ষপথের মধ্যে ইন্টার-প্ল্যানেটারি হোহম্যান অরবিটাল ট্রান্সফারের ডেল্টা-ভি ($\Delta v_1, \Delta v_2, \Delta v_{total}$) এবং যাত্রার সময়কাল (Time of Flight) গণনা করে।

```bash
# পৃথিবী থেকে মঙ্গল গ্রহের হোহম্যান ট্রান্সফার (১.০ AU থেকে ১.৫২৪ AU)
php netfusion astro:transfer --from=1.0 --to=1.524

# পৃথিবী থেকে বৃহস্পতি গ্রহের হোহম্যান ট্রান্সফার (১.০ AU থেকে ৫.২০৪ AU)
php netfusion astro:transfer --from=1.0 --to=5.204
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/astro.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/astro.php)

---

## Phase 117: Enterprise Plasma Physics, Magnetohydrodynamics & Fusion Confinement Fabric

### `plasma:tokamak`

টোকামাক টোরয়ডাল ম্যাগনেটিক কনফাইনমেন্ট জিওমেট্রি ($R_0, a, B_0, I_p$), প্লাজমা ভলিউম, সেফটি ফ্যাক্টর $q$-প্রোফাইল এবং ক্রুসকল-শাফরানভ স্টেবিলিটি লিমিট ($q_{edge} > 1.0$) বিশ্লেষণ করে।

```bash
# ডিফল্ট আইটার (ITER) টোকামাক কনফিগারেশন বিশ্লেষণ
php netfusion plasma:tokamak

# কাস্টম টোকামাক প্যারামিটার (R0 = 3.0 m, a = 1.0 m, B0 = 4.0 T, Ip = 5.0 MA)
php netfusion plasma:tokamak --r0=3.0 --a=1.0 --b0=4.0 --ip=5.0
```

---

### `plasma:fusion`

নির্দিষ্ট আয়ন তাপমাত্রায় ($T_i$) ম্যাক্সওয়েলিয়ান থার্মোনিউক্লিয়ার রিঅ্যাক্টিভিটি $\langle \sigma v \rangle$, মোট ফিউশন শক্তি ঘনত্ব ($kW/m^3$), আলফা সেলফ-হিটিং ঘনত্ব এবং ব্রেমস্ট্রালুং রেডিয়েশন লস পরিমাপ করে।

```bash
# ১৫ keV তাপমাত্রায় ডিউটেরিয়াম-ট্রিটিয়াম (D-T) বিক্রিয়া বিশ্লেষণ
php netfusion plasma:fusion 15.0

# প্রোটন-বোরন-১১ (p-B11) অ্যানিউট্রনিক ফিউশন ২০০ keV তাপমাত্রায়
php netfusion plasma:fusion 200.0 --fuel=p_b11
```

---

### `plasma:lawson`

লসন ক্রাইটেরিয়ন ট্রিপল প্রোডাক্ট ($n \cdot T \cdot \tau_E$), বৈজ্ঞানিক ব্রেক-ইভেন ($Q \ge 1.0$) এবং স্বয়ংসম্পূর্ণ থার্মোনিউক্লিয়ার ইগনিশন ($P_\alpha \ge P_{loss}$) মূল্যায়ন করে।

```bash
# ডিফল্ট আইটার স্কেল রিঅ্যাক্টর পাওয়ার ব্যালেন্স ও Q-গেইন মূল্যায়ন
php netfusion plasma:lawson --density=1.0 --temp=15.0 --tau=3.5 --aux=50.0

# উচ্চ ঘনত্বের পালসড ফিউশন মূল্যায়ন
php netfusion plasma:lawson --density=2.0 --temp=20.0 --tau=4.0 --aux=30.0
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/plasma.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/plasma.php)

---

## Phase 118: Superconductivity, Ginzburg-Landau Theory, Abrikosov Vortex Lattice & SQUID Fabric

### `supercond:gl`

নির্দিষ্ট অতিপরিবাহী পদার্থের (যেমন YBCO, BSCCO, Niobium) জন্য গিনজবার্গ-ল্যান্ডাউ (Ginzburg-Landau) ম্যাক্রোস্কোপিক কোয়ান্টাম ক্যারেক্টারিস্টিকস—কোহেরেন্স দৈর্ঘ্য $\xi(T)$, লন্ডন পেনিট্রেশন ডেপথ $\lambda(T)$, আপার ও লোয়ার ক্রিটিক্যাল ফিল্ড ($B_{c2}, B_{c1}$), এবং থার্মোডাইনামিক সুপারকন্ডাক্টিং দশা গণনা করে।

```bash
# তরল নাইট্রোজেন তাপমাত্রায় (৭৭ K) YBCO উপাদানের গিনজবার্গ-ল্যান্ডাউ বৈশিষ্ট্য
php netfusion supercond:gl YBCO 77.0

# ৪.২ K তরল হিলিয়াম তাপমাত্রায় নিওবিয়াম (Niobium) বিশ্লেষণ
php netfusion supercond:gl Niobium 4.2 --field=0.05
```

---

### `supercond:vortex`

টাইপ-২ অতিপরিবাহীতে চুম্বকীয় ফ্লাক্স কোয়ান্টাইজেশন ($h / 2e$), আব্রিকোসোভ ট্রায়াঙ্গুলার ফ্লাক্স ভর্টেক্স ল্যাটিস ডেনসিটি ($n_v$), ল্যাটিস স্পেসিং ($a_\Delta$), এবং লরেন্টজ ড্রাইভিং বল বনাম পিনিং ফোর্স ($F_L \text{ vs } F_p$) মূল্যায়ন করে ভর্টেক্স ডেপিনিং ট্রানজিশন সিমুলেট করে।

```bash
# ২.০ টেসলা চৌম্বক ক্ষেত্রে আব্রিকোসোভ ভর্টেক্স ল্যাটিস ও পিনিং ব্যালেন্স
php netfusion supercond:vortex 2.0 1000000000

# কাস্টম ক্রিটিক্যাল পিনিং ফোর্স সহ সিমুলেশন
php netfusion supercond:vortex 3.5 500000000 --pinning=2000000000
```

---

### `supercond:squid`

ডিসি-স্কুইড (DC-SQUID) সুপারকন্ডাক্টিং কোয়ান্টাম ইন্টারফারেন্স ম্যাগনেটোমিটার সিমুলেশন—ম্যাক্রোস্কোপিক কোয়ান্টাম ইন্টারফারেন্সের মাধ্যমে চৌম্বকীয় ফ্লাক্স মডুলেশন ($V-\Phi$), সুপারকারেন্ট হস্তক্ষেপ এবং অতি-সংবেদনশীল ম্যাগনেটোমেট্রি ভোল্টেজ রেসপন্স পরিমাপ করে।

```bash
# ১ পিকো-টেসলা চৌম্বক ক্ষেত্র প্রয়োগ করে স্কুইড ইন্টারফারেন্স পরিমাপ
php netfusion supercond:squid 0.000000000001

# কাস্টম লুপ এলাকা ও বায়াস কারেন্ট সহ স্কুইড সিমুলেশন
php netfusion supercond:squid 0.00000001 --area=0.00000001 --bias=0.000025
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/superconductivity.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/superconductivity.php)

---

## Phase 119: Enterprise Quantum Thermodynamics, Open Quantum Systems & Quantum Heat Engines Fabric

### `qtherm:lindblad`

লিন্ডব্লাড জিকেএলএস (GKLS) মাস্টার ইকুয়েশন ও ৪র্থ ক্রমের রুঙ্গে-কুট্টা (RK4) নিউমেরিক্যাল ইন্টিগ্রেশনের মাধ্যমে কিউবিট ওপেন কোয়ান্টাম সিস্টেমের সময়ভিত্তিক ক্ষয়শীল বিবর্তন, এমপ্লিচিউড ড্যাম্পিং, ডিপেজিং, ভন নিউম্যান এনট্রপি এবং পিউরিটি পরিমাপ করে।

```bash
# ১.০ GHz কিউবিট এবং ১০ MHz ড্যাম্পিং রেটে ১০০ স্টেপ সিমুলেশন
php netfusion qtherm:lindblad 1.0 10.0 100

# উচ্চ ফ্রিকোয়েন্সি কিউবিট ও কাস্টম প্যারামিটারে সিমুলেশন
php netfusion qtherm:lindblad 5.0 25.0 500
```

---

### `qtherm:engine`

কোয়ান্টাম অটো হিট ইঞ্জিন (Quantum Otto Cycle) সিমুলেশন—আইসেন্ট্রপিক কম্প্রেশন, হট বাথ থার্মালাইজেশন, আইসেন্ট্রপিক এক্সপানশন এবং কোল্ড বাথ থার্মালাইজেশন ৪টি স্ট্রোক পরিচালনা করে নেট ওয়ার্ক আউটপুট, অটো থার্মাল এফিসিয়েন্সি ($\eta_{Otto} = 1 - \omega_c/\omega_h$), কার্নোট বাউন্ড এবং কোয়ান্টাম রেফ্রিজারেটর মোড (COP) বিশ্লেষণ করে।

```bash
# ১০০ K কোল্ড বাথ ও ৩০০ K হট বাথে ১.০ GHz এবং ২.০ GHz হিট ইঞ্জিন
php netfusion qtherm:engine 100.0 300.0 1.0 2.0

# কোয়ান্টাম রেফ্রিজারেটর মোড বিশ্লেষণ
php netfusion qtherm:engine 250.0 300.0 1.0 2.0
```

---

### `qtherm:landauer`

কোয়ান্টাম ইনফরমেশন থার্মোডাইনামিক্স ও ল্যান্ডাওয়ার ইরেজার প্রিন্সিপল ($Q_{min} = k_B \cdot T \cdot \ln(2) \cdot n$)—নির্দিষ্ট তাপমাত্রায় ক্লাসিক্যাল/কোয়ান্টাম তথ্য বা কিউবিট মুছে ফেলার সর্বনিম্ন তাপীয় অপচয় এবং এনট্রপি ডেল্টা গণনা করে।

```bash
# ৩০০ K তাপমাত্রায় ১ বিট তথ্য মুছতে সর্বনিম্ন শক্তির অপচয়
php netfusion qtherm:landauer 1.0 300.0

# ক্রায়োজেনিক ৪ K তাপমাত্রায় ১০০০ বিট ইরেজার বিশ্লেষণ
php netfusion qtherm:landauer 1000.0 4.0
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/quantum_thermodynamics.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/quantum_thermodynamics.php)

---

## Phase 120: Official v1.0.0 Global GA Release & Enterprise Golden Master Verification Fabric

### `framework:verify`

ফ্রেমওয়ার্কের সামগ্রিক গোল্ডেন মাস্টার আর্কিটেকচারাল সেলফ-ইন্সপেকশন সম্পাদন করে। PHP 8.2+ রানটাইম কম্প্যাটিবিলিটি, জিরো এক্সটার্নাল কম্পোজার রানটাইম ডিপেন্ডেন্সি, ডাটাবেস মাইগ্রেশন স্কিমা অখণ্ডতা (৯৯টি মাইগ্রেশন ফাইল), `version.json` সিনক্রোনাইজেশন এবং DotNetBD স্ট্যান্ডার্ড ফাইল হেডার নিরীক্ষা করে ১০০/১০০ স্কোর ভেরিফিকেশন ও গোল্ডেন মাস্টার সার্টিফিকেশন রিপোর্ট প্রদর্শন করে।

```bash
# ফ্রেমওয়ার্ক গোল্ডেন মাস্টার ডায়াগনস্টিকস ও সার্টিফিকেশন যাচাই
php netfusion framework:verify
```

---

### `framework:info`

নেটফিউশন ফ্রেমওয়ার্কের অফিশিয়াল রিলিজ মেটাডাটা, সংস্করণ বিবরণী (v1.0.0 GA), রিলিজ কোডনেম, কার্নেল আর্কিটেকচার এবং ফ্রেমওয়ার্কের ১২০টি পর্যায় জুড়ে বিস্তৃত পূর্ণাঙ্গ মডিউল ক্যাটাগরি ক্যাটালগ (৭টি প্রধান ডোমেইন) টার্মিনালে প্রদর্শন করে।

```bash
# ফ্রেমওয়ার্ক রিলিজ ইনফরমেশন ও ১২০টি ফেজের মডিউল ইনভেন্টরি প্রদর্শন
php netfusion framework:info
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/release.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/release.php)

---

## DotNetBD License CMS (API Engine v5.1.8) Commands

### `license:activate`

DotNetBD সেন্ট্রাল লাইসেন্স সার্ভারের (`https://lic.dotnetbd.com/api/activate.php`) সাথে ক্লায়েন্ট সার্ভার ডোমেইন, আইপি এবং সিকিউর সার্ভার হ্যাশ `md5(domain . SALT)` বাইন্ডিং করে লাইসেন্স সক্রিয় করে এবং স্থানীয় এনক্রিপ্টেড সার্টিফিকেট তৈরি করে।

```bash
# লাইসেন্স কী এবং কোম্পানির নাম দিয়ে ফ্রেমওয়ার্ক সক্রিয়করণ
php netfusion license:activate NF-8472-9102-3847 "DotNetBD Enterprise"

# নির্দিষ্ট ডোমেইন উল্লেখ করে অ্যাক্টিভেশন
php netfusion license:activate NF-8472-9102-3847 "DotNetBD Enterprise" myclient.com
```

---

### `license:check`

বর্তমান সফটওয়্যার লাইসেন্সের বৈধতা, অনুমোদিত সার্ভার সীমা, নিবন্ধিত ডোমেইন এবং অবশিষ্ট মেয়াদ নিরীক্ষা করে। লাইসেন্স ক্যাশ (২৪ ঘণ্টা) উত্তীর্ণ হলে স্বয়ংক্রিয়ভাবে রিমোট হার্টবিট ভ্যালিডেশন সম্পাদন করে।

```bash
# বর্তমান লাইসেন্স স্ট্যাটাস ও ভ্যালিডিটি পরীক্ষা
php netfusion license:check
```

**সংশ্লিষ্ট কনফিগারেশন:** [`config/license.php`](file:///d:/SERVER/www/NetFusion%20Framework/config/license.php)













































