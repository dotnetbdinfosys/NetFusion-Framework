<!--
|--------------------------------------------------------------------------
| NetFusion Framework Documentation Index
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

# NetFusion Framework — Official Documentation

স্বাগতম **NetFusion Framework**-এর অফিসিয়াল ডকুমেন্টেশনে। নেটফিউশন হলো পিএইচপি ৮.২+ (Core PHP) ভিত্তিক একটি সম্পূর্ণ জিরো-ডিপেন্ডেন্সি, হাই-পারফরম্যান্স এবং এন্টারপ্রাইজ-গ্রেড **Hierarchical Model-View-Controller (HMVC)** ফ্রেমওয়ার্ক।

---

## 📚 ডকুমেন্টেশন সূচিপত্র (Table of Contents)

1. **[ইনস্টলেশন গাইড (Installation Guide)](INSTALLATION.md)**
   - সিস্টেমের পূর্বশর্ত (System Requirements & PHP Extensions)
   - সার্বজনীন অটো-ইনস্টলার স্ক্রিপ্ট (`bin/install.sh`)
   - উবুন্টু ও ডেবিয়ান ইনস্টলেশন (Ubuntu / Debian Setup)
   - সেন্টওএস, আরএইচইএল ও রকি লিনাক্স ইনস্টলেশন (CentOS / RHEL / Alma / Rocky Setup & SELinux)
   - আলপাইন লিনাক্স ইনস্টলেশন (Alpine Linux Setup)
   - উইন্ডোজ ডেভেলপমেন্ট এনভায়রনমেন্ট (Windows Local Setup)
   - ডকার ও ডকার কম্পোজ ডেপ্লয়মেন্ট (Docker & Docker Compose)
   - সার্ভার হেলথ চেক ও অপ্টিমাইজেশন (`php netfusion server:check` & `optimize`)

2. **[চেঞ্জলগ ও রিলিজ হিস্ট্রি (Changelog & Version History)](CHANGELOG.md)**
   - সংস্করণ ১.০.০ (Version 1.0.0 Global GA) — সম্পূর্ণ ১২০টি ফেজের পুঙ্খানুপুঙ্খ বিবরণ
   - সকল মডিউল, সাবসিস্টেম, ও টেস্ট কভারেজ মেট্রিক্স (১,১৩৫/১,১৩৫ টেস্ট পাস — ১০০%)

3. **[প্রোডাকশন ডেপ্লয়মেন্ট গাইড (Deployment & Server Setup)](DEPLOYMENT.md)**
   - Nginx ভার্চুয়াল হোস্ট কনফিগারেশন (`deploy/nginx/netfusion.conf`)
   - Apache 2.4 ভার্চুয়াল হোস্ট কনফিগারেশন (`deploy/apache/netfusion.conf`)
   - Systemd কিউ ওয়ার্কার ডেমন (`deploy/systemd/netfusion-queue.service`)
   - Systemd ক্রন শিডিউলার টাইমার (`deploy/systemd/netfusion-scheduler.timer`)
   - ক্রনট্যাব কনফিগারেশন (`deploy/cron/netfusion.cron`)
   - সিকিউরিটি হার্ডেনিং ও ডিরেক্টরি পারমিশন গাইডলাইন

4. **[সিএলআই কমান্ড রেফারেন্স (CLI Commands Reference)](CLI_REFERENCE.md)**
   - ফ্রেমওয়ার্কের কনসোল কমান্ডসমূহের সম্পূর্ণ সিনট্যাক্স, ফ্ল্যাগ এবং ব্যবহারিক উদাহরণ

5. **[পারফরম্যান্স, অপারেটিং সিস্টেম ও প্রোডাকশন গাইড (Performance & Production FAQ)](FAQ.md)**
   - অপারেটিং সিস্টেম তুলনা (Ubuntu/Debian vs RHEL vs Windows) ও লেটেন্সি চার্ট
   - PHP 8.2+ OPcache ও Tracing JIT কনফিগারেশন নির্দেশিকা
   - ওয়েব সার্ভার (Nginx + PHP-FPM vs FrankenPHP/RoadRunner Worker Mode)
   - SQLite WAL ও ডাটাবেস টিউনিং, মেমোরি ম্যানেজমেন্ট এবং সিকিউরিটি হার্ডেনিং

---

## 🌟 ফ্রেমওয়ার্কের সম্পূর্ণ ফিচার ক্যাটালগ (Comprehensive Feature Catalog — All 120 Phases)

NetFusion Framework v1.0.0-এ অন্তর্ভুক্ত মোট ১২০টি পর্বের সকল ফিচার ও সাবসিস্টেমের বিষয়ভিত্তিক তালিকা:

### ১. কোর ইঞ্জিন ও এইচএমভিসি ফ্যাব্রিক (Core & HMVC Architecture — Phases 1–20)
- **Dependency Injection (DI) Container**: অটো-রিফ্লেকশন, সিঙ্গলটন, ইন্টারফেস বাইন্ডিং ও অটো-ওয়্যারিং।
- **Fast Pattern Router**: রেস্টফুল (RESTful) ভার্বস, ডাইনামিক রেজেক্স প্যারামিটারস, নেইমড রুটস ও রুট গ্রুপিং।
- **PSR-15 Middleware Pipeline**: চেইনেবল মিডলওয়্যার (CSRF, থ্রটলিং, সেশন, সিকিউরিটি গার্ড)।
- **Zero-Config Dynamic HMVC**: মডিউলার আর্কিটেকচার (`app/Modules/`), সার্ভিস প্রোভাইডার অটো-ডিসকভারি।
- **Active Record ORM & QueryBuilder**: ফ্লুয়েন্ট এসকিউএল বিল্ডার, রিলেশনস (`hasOne`, `hasMany`, `belongsTo`, `belongsToMany`), সফট ডিলিট ও মাল্টি-টেন্যান্সি।
- **Database Migrations & Seeding Engine**: ব্যাচ রোলব্যাক, ভার্শনিং ও ডেটা সিডার সাপোর্ট।
- **ক্যাশ, কিউ ও ইভেন্টস**: ফাইল/রেডিস ক্যাশ ড্রাইভার, অ্যাসিঙ্ক কিউ ওয়ার্কার ও ইভেন্ট লিসনার বাস।
- **নিরাপত্তা ও অথেন্টিকেশন**: পাসওয়ার্ড হ্যাশিং (Argon2id/Bcrypt), JWT টোকেন জেনারেটর ও সেশন সিকিউরিটি।
- **টেমপ্লেট ও ভিউ ইঞ্জিন**: লাইটওয়েট ক্যাশড ভিউ রেন্ডারার, কম্পোনেন্ট ও লেআউট ইনহেরিটেন্স।

### ২. মাইক্রোসার্ভিসেস ও ডিস্ট্রিবিউটেড সিস্টেমস (Microservices & Distributed Systems — Phases 21–40, 51–59)
- **Event Sourcing & CQRS Engine**: ইমিউটেবল ইভেন্ট স্টোর, স্ন্যাপশট ও রিড-মডেল প্রজেকশন।
- **gRPC/JSON-RPC Microservices Client & Server**: ডিস্ট্রিবিউটেড মাইক্রোসার্ভিস আরপিসি কমিউনিকেশন।
- **Distributed Saga Orchestrator**: LIFO কম্পেনসেশন কোঅর্ডিনেটর ও ডিস্ট্রিবিউটেড ট্রানজ্যাকশন রোলব্যাক।
- **W3C Distributed Tracing & APM**: ট্রেস কনটেক্সট প্রোপাগেশন, স্প্যান বাফার ও স্লো-কুয়েরি ডিটেকশন।
- **Resilience Primitives**: সার্কিট ব্রেকার (Circuit Breaker), রেট লিমিটার ও বাল্কহেড প্যাটার্ন।
- **Real-Time Event Broadcasting & SSE**: সার্ভার-সেন্ট ইভেন্টস (SSE) ও প্রাইভেট চ্যানেল ব্রডকাস্টিং।
- **Native WebSockets Server (RFC 6455)**: পিওর পিএইচপি বাইনারি ফ্রেম পার্সার, মাস্কিং ও চ্যানেল রুমস।
- **Native GraphQL Engine**: SDL স্কিমা পার্সার, টাইপ রেজোলিউশন ও নেস্টেড কুয়েরি এক্সিকিউশন।
- **Distributed Locks & In-Memory Data Grid (IMDG)**: L1/L2 নিয়ার-ক্যাশ, কনসিস্টেন্ট হ্যাশিং ও রাইট-থ্রু মেমোরি।
- **Complex Event Processing (CEP)**: রিয়েল-টাইম স্ট্রিম প্যাটার্ন এনএফএ (NFA) ম্যাচিং ও স্লাইডিং উইন্ডো।

### ৩. এন্টারপ্রাইজ সিকিউরিটি, জিরো-ট্রাস্ট ও ক্রিপ্টোগ্রাফি (Security, Zero-Trust & PQC — Phases 41–50, 60–70)
- **Web Application Firewall (WAF Layer 7)**: OWASP Core Rule Set (CRS) ইন্সপেকশন, এসকিউএলআই/এক্সএসএস ব্লকিং ও JA3 ফিঙ্গারপ্রিন্টিং।
- **Secrets Vault & Envelope Encryption**: ডাইনামিক ডেটা এনক্রিপশন কি (DEK) ও কী-এনক্রিপশন কি (KEK) রোটেশন।
- **Zero-Trust Service Mesh & SPIFFE/SPIRE**: mTLS আইডেন্টিটি সার্টিফিকেট রোটেশন ও X.509 SVID অটেন্টিনেশন।
- **Post-Quantum Cryptography (PQC)**: ক্রিপ্টোগ্রাফিক ল্যাটিস ম্যাথমেটিক্স, ML-KEM কি এনক্যাপসুলেশন ও ML-DSA ডিজিটাল সিগনেচার।
- **OAuth2 & OpenID Connect (OIDC)**: অথরাইজেশন কোড, ক্লায়েন্ট ক্রেডেনশিয়ালস ও পিকেসিই (PKCE) সাপোর্ট।

### ৪. হাই-পারফরম্যান্স ডেটা, সার্চ ও অ্যানালিটিক্স (Data, Search & Graph — Phases 60–65, 74, 91)
- **Semantic Vector Search & HNSW Graph**: ইউক্লিডিয়ান/কোসাইন সিমিলারিটি, হাই-ডাইমেনশনাল ভেক্টর ইনডেক্সিং ও RAG পাইপলাইন।
- **OLAP Columnar Storage & Data Warehouse**: Run-Length/Dictionary/Bit-Packing কম্প্রেশন ও স্টার স্কিমা SCD Type-2।
- **Vector + BM25 Hybrid Search**: আরসিএফ (RRF) রির‍্যাঙ্কিং, ফেসেটেড ট্যাক্সোনমি ও রিয়েল-টাইম অটো-সাজেস্ট।
- **Multi-Model Graph Database & Cypher Parser**: নোড-এজ প্রোপার্টি গ্রাফ, Dijkstra শর্টেস্ট পাথ ও নলেজ গ্রাফ রিজনিং।
- **Schema Registry & Protocol Buffers / Avro SerDe**: বাইনারি সিরিয়ালাইজেশন ও স্কিমা কম্প্যাটিবিলিটি ট্র্যাকার।
- **GraphRAG Multi-Modal Knowledge Engine**: হাইব্রিড নলেজ গ্রাফ ও সেমান্টিক আরএজি রির‍্যাঙ্কিং।

### ৫. অটোনোমাস কম্পিউটিং ও রেজিলিয়েন্স ফ্যাব্রিক (Autonomous Resiliency & Chaos — Phases 71–73, 92, 99, 103)
- **Autonomous Disaster Recovery (Zero-RPO CDP)**: কন্টিনিউয়াস ডেটা প্রোটেকশন, কোরাম কনসেনসাস ও অটোমেটেড মাল্টি-ক্লাউড ডিএনএস ফেইলওভার।
- **Chaos Engineering (GameDay Simulator)**: লেটেন্সি/সিপিইউ/মেমোরি/এক্সেপশন ইনজেকশন, ব্লাস্ট রেডিয়াস গার্ডরেল ও কিল-সুইচ।
- **Native OpenTelemetry Observability**: W3C ট্রেসার, মেট্রিক্স অ্যাগ্রিগেটর ও কন্টিনিউয়াস ওয়াল-ক্লক/মেমোরি প্রোফাইলার।
- **Collaborative CRDTs & OT Synchronizer**: কনফ্লিক্ট-ফ্রি রেপ্লিকেটেড ডেটা টাইপস (State-CRDT/Op-CRDT) ও রিয়েল-টাইম কোলাবোরেশন।
- **Autonomous Self-Healing Infrastructure**: স্বয়ংক্রিয় রুট-কজ অ্যানালাইসিস (RCA) ও ক্যানারি রিমেডিয়েশন কন্ট্রোলার।
- **Multi-Agent Swarm Intelligence**: বাইজেন্টাইন ফল্ট টলারেন্ট (BFT) কনসেনসাস, ডিজিটাল ফেরোমোন ও ডাইনামিক টাস্ক অ্যালোকেশন।

### ৬. নেক্সট-জেন কম্পিউটিং ও ইন্টারফেস ইঞ্জিন (Next-Gen Compute & Formal SMT — Phases 85–90, 94–98)
- **Apache ZooKeeper Style Coordination**: হায়ারার্কিকাল ZNode নেমস্পেস, এফিমেরাল লিজ ও লিডার ইলেকশন।
- **Micro-Frontend (MFE) Orchestration**: সার্ভার-সাইড ফ্র্যাগমেন্ট স্টিচিং (Edge SSI) ও মডিউল ফেডারেশন।
- **Edge WebAssembly (Wasm) Engine**: সার্ভারলেস ডিস্ট্রিবিউটেড স্যান্ডবক্সিং ও WASI ইন্টারফেস।
- **WebRTC SFU Media Server**: অ্যাডাপ্টিভ বিটরেট (ABR) অডিও/ভিডিও মিডিয়া পাইপলাইন ও আরটিপি প্যাকেট রাউটিং।
- **Geospatial H3 & 2D R-Tree Indexing**: হেক্সাগোনাল গ্রিড স্প্যাশিয়াল ক্লাস্টারিং ও জিও-ফেন্সিং।
- **Formal Verification & SMT Solver (DPLL/T)**: স্যাটিসফায়াবিলিটি মডিউলো থিওরি, সিম্বলিক এক্সিকিউশন ও বাউন্ডেড মডেল চেকিং।
- **eBPF-Style Programmable Packet Filter & XDP**: কার্নেল-স্পিড নেটওয়ার্ক প্যাকেট ইন্সপেকশন ও L4/L7 ট্রাফিক অবজারভেবিলিটি।
- **Differential Privacy & Federated Learning**: ল্যাপ্লাস/গাউসিয়ান নয়েজ মেকানিজম, প্রাইভেসি বাজেট $(\epsilon, \delta)$ ও সিকিউর অ্যাগ্রিগেশন।
- **Homomorphic Encryption (BFV/CKKS) & SMPC**: এনক্রিপ্টেড ডাটার ওপর সরাসরি পাটিগাণিতিক যোগ/গুণ এবং শামির'স সিক্রেট শেয়ারিং।

### ৭. ফ্রন্টিয়ার ফিজিক্যাল, বায়োলজিক্যাল ও কোয়ান্টাম ইন্টেলিজেন্স (Frontier Physics & Quantum — Phases 101–120)
- **Quantum Key Distribution (QKD)**: BB84 ও E91 প্রোটোকল, কোয়ান্টাম এন্ট্যাঙ্গলমেন্ট বেল-স্টেট ও কিউবিট পোলারাইজেশন।
- **Neuromorphic Computing & SNN**: স্পাইকিং নিউরাল নেটওয়ার্ক, Leaky Integrate-and-Fire (LIF) ও স্পাইক-টাইমিং সিন্যাপ্স (STDP)।
- **Post-Quantum DID & BBS+ ZKP**: W3C ভেরিফায়েবল ক্রেডেনশিয়ালস ২.০ ও জিরো-নলেজ মাল্টি-মেসেজ সিগনেচার।
- **WebXR Spatial 3D Gaussian Splatting**: স্প্যাশিয়াল ডিজিটাল টুইন, সিন-গ্রাফ ও রিয়েল-টাইম ফিজিক্স সিমুলেশন।
- **DNA Molecular Storage & Reed-Solomon**: ডিএনএ বেস কোডিং ($A,C,G,T$) ও ভলিউমেট্রিক হলোগ্রাফিক মেমোরি।
- **Brain-Computer Interface (BCI)**: ইইজি (EEG) সিগন্যাল ডিকোডিং, ব্যান্ডপাস ফিল্টারিং ও ক্লোজড-লুপ নিউরো-ফিডব্যাক।
- **Optical Photonic Computing**: ম্যাক-জেন্ডার ইন্টারফেরোমিটার (MZI Mesh) ও লাইট-স্পিড অপটিক্যাল ম্যাট্রিক্স মাল্টিপ্লায়ার।
- **6G Reconfigurable Intelligent Surfaces (RIS)**: টেরাহার্টজ বিমফর্মিং, হলোগ্রাফিক MIMO ও মেটামেটেরিয়াল চ্যানেল।
- **Gravitational Waves & Numerical Relativity**: স্পেসটাইম মেট্রিক টেনসর ও ব্ল্যাকহোল বাইনারি মার্জার সিমুলেশন।
- **Topological Data Analysis (TDA)**: ভিয়েতোরিস-রিপ্স কমপ্লেক্স, পারসিস্টেন্ট হোমোলজি ও বেটি নম্বর ($\beta_0, \beta_1$) ম্যানিফোল্ড।
- **Cognitive Architecture (ACT-R / SOAR)**: ডিক্লারেটিভ/এপিসোডিক মেমোরি চাঙ্ক গ্রাফ ও প্রডাকশন রুল ইঞ্জিন।
- **Zero-Knowledge Rollup (ZK-Rollup) & MMR**: মার্কেল-মাউন্টেন রেঞ্জ, স্টেট ট্রানজিশন প্রুফ ও এল২ স্কেলিং।
- **Lattice Boltzmann Method (LBM) CFD**: D2Q9 ডিসক্রিট ভেলোসিটি গ্রিড ও হাইড্রোডাইনামিক ফ্লুইড সিমুলেশন।
- **Synthetic Biology (BioCAD)**: ক্রিস্পার (CRISPR-Cas) অফ-টার্গেট প্রেডিকশন, কোডন অপ্টিমাইজেশন ও জেনেটিক সার্কিট সিমুলেশন।
- **Celestial Mechanics & Symplectic Integrators**: কেপলারিয়ান অরবিটাল এলিমেন্টস, হোহম্যান ট্রান্সফার ও সিম্প্লেকটিক এন-বডি ইন্টিগ্রেশন।
- **Plasma Physics & Tokamak Fusion Confinement**: Grad-Shafranov MHD ইকুইলিব্রিয়াম, Bosch-Hale ফিউশন ক্রস-সেকশন ও লসন ক্রাইটেরিয়ন।
- **Superconductivity, Abrikosov Vortices & SQUID**: গিনজবার্গ-ল্যান্ডাউ থিওরি, ভর্টেক্স ল্যাটিস ও ডিসি-স্কুইড কোয়ান্টাম ম্যাগনেটোমেট্রি।
- **Quantum Thermodynamics & Open Systems**: লিন্ডব্লাড GKLS মাস্টার ইকুয়েশন (RK4), কোয়ান্টাম অটো হিট ইঞ্জিন ও ল্যান্ডাওয়ার ইরেজার লিমিট।
- **Golden Master Release Fabric**: অটোমেটেড সেলফ-ইন্সপেকশন ডায়াগনস্টিকস (`framework:verify`, `framework:info`) ও ১০০/১০০ সার্টিফিকেশন।

---

## 🚀 দ্রুত শুরু করুন (Quick Start)

যেকোনো লিনাক্স সার্ভারে স্বয়ংক্রিয়ভাবে ফ্রেমওয়ার্কটি ইনস্টল ও কনফিগার করতে:

```bash
# ১. রিপোজিটরিতে প্রবেশ করে এক্সিকিউট পারমিশন দিন
chmod +x bin/install.sh

# ২. অটো-ইনস্টলার রান করুন
./bin/install.sh

# ৩. সার্ভারের স্বাস্থ্য ও পোর্টাবিলিটি নিশ্চিত করুন
php netfusion server:check

# ৪. প্রোডাকশন অপ্টিমাইজেশন সক্রিয় করুন
php netfusion optimize
```

---

## 🏢 যোগাযোগ ও সহায়তা (Support & Credits)

- **ডেভেলপার ও আর্কিটেক্ট**: মো: আতিকুজ্জামান আশিষ (Md. Atiquzzaman Ashish)
- **প্রতিষ্ঠান**: ডটনেটবিডি (DotNetBD)
- **ওয়েবসাইট**: [www.dotnetbd.com](https://www.dotnetbd.com)
- **লাইসেন্স**: [MIT License](https://opensource.org/licenses/MIT)
