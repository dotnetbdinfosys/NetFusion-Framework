<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Services/ModuleDataRepository.php
| Namespace : NetFusion\App\Services
| Purpose   : Universal persistent JSON data repository for all HMVC modules.
|             Provides seamless CRUD operations for invoices, items, plans,
|             bills, journal entries, and orders without hard DB requirements.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\App\Services;

class ModuleDataRepository
{
    /**
     * Get storage file path for an entity.
     */
    public static function getFilePath(string $entity): string
    {
        if (function_exists('storage_path') && function_exists('app')) {
            try {
                $app = app();
                if (is_object($app) && method_exists($app, 'storagePath')) {
                    return storage_path("framework/{$entity}.json");
                }
            } catch (\Throwable $e) {
                // fallback below
            }
        }
        $base = defined('NETFUSION_BASE_PATH') ? NETFUSION_BASE_PATH : dirname(__DIR__, 2);
        return $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . "{$entity}.json";
    }

    /**
     * Retrieve all items for an entity. Auto-seeds default dataset if missing.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(string $entity): array
    {
        $file = self::getFilePath($entity);
        if (!file_exists($file)) {
            $defaults = self::getDefaults($entity);
            self::save($entity, $defaults);
            return $defaults;
        }

        $content = (string) file_get_contents($file);
        $data = json_decode($content, true);
        if (!is_array($data)) {
            $defaults = self::getDefaults($entity);
            self::save($entity, $defaults);
            return $defaults;
        }

        return $data;
    }

    /**
     * Find a single item by key and value.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $entity, string $key, mixed $value): ?array
    {
        $items = self::all($entity);
        foreach ($items as $item) {
            if (isset($item[$key]) && (string) $item[$key] === (string) $value) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Update an item matching the key/value criteria.
     *
     * @param array<string, mixed> $newData
     */
    public static function update(string $entity, string $key, mixed $value, array $newData): bool
    {
        $items = self::all($entity);
        $found = false;

        foreach ($items as $idx => $item) {
            if (isset($item[$key]) && (string) $item[$key] === (string) $value) {
                $items[$idx] = array_merge($item, $newData);
                $items[$idx]['updated_at'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }

        if ($found) {
            return self::save($entity, $items);
        }

        return false;
    }

    /**
     * Insert a new item into the entity collection.
     *
     * @param array<string, mixed> $record
     */
    public static function insert(string $entity, array $record): bool
    {
        $items = self::all($entity);
        $record['created_at'] = $record['created_at'] ?? date('Y-m-d H:i:s');
        $record['updated_at'] = date('Y-m-d H:i:s');
        $items[] = $record;
        return self::save($entity, $items);
    }

    /**
     * Delete an item matching the key/value criteria.
     */
    public static function delete(string $entity, string $key, mixed $value): bool
    {
        $items = self::all($entity);
        $originalCount = count($items);
        $filtered = array_values(array_filter($items, function ($item) use ($key, $value) {
            return !(isset($item[$key]) && (string) $item[$key] === (string) $value);
        }));

        if (count($filtered) !== $originalCount) {
            return self::save($entity, $filtered);
        }

        return false;
    }

    /**
     * Save items to the entity JSON file.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public static function save(string $entity, array $items): bool
    {
        $file = self::getFilePath($entity);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents(
            $file,
            json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ) !== false;
    }

    /**
     * Get default initial dataset for any entity.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getDefaults(string $entity): array
    {
        return match ($entity) {
            'billing_invoices' => [
                [
                    'id' => 'INV-781',
                    'customer' => 'ঢাকা টেক সল্যুশনস লিঃ',
                    'plan' => 'Enterprise Suite (বার্ষিক)',
                    'cycle' => 'বার্ষিক',
                    'amount' => 119988.00,
                    'method' => 'সিটি ব্যাংক কার্ড',
                    'issue_date' => '2026-10-14',
                    'due_date' => '2026-10-24',
                    'status' => 'paid',
                ],
                [
                    'id' => 'INV-780',
                    'customer' => 'ব্লু-স্কাই সফটওয়্যার লিমিটেড',
                    'plan' => 'Professional Pro (মাসিক)',
                    'cycle' => 'মাসিক',
                    'amount' => 4999.00,
                    'method' => 'বিকাশ গেটওয়ে',
                    'issue_date' => '2026-10-14',
                    'due_date' => '2026-10-21',
                    'status' => 'paid',
                ],
                [
                    'id' => 'INV-779',
                    'customer' => 'প্রাইম লজিস্টিকস কর্পোরেশন',
                    'plan' => 'Enterprise Suite (মাসিক)',
                    'cycle' => 'মাসিক',
                    'amount' => 9999.00,
                    'method' => 'নগদ পেমেন্ট',
                    'issue_date' => '2026-10-13',
                    'due_date' => '2026-10-20',
                    'status' => 'pending',
                ],
                [
                    'id' => 'INV-778',
                    'customer' => 'স্মার্ট ফ্যাশনস ডট কম',
                    'plan' => 'Professional Pro (মাসিক)',
                    'cycle' => 'মাসিক',
                    'amount' => 4999.00,
                    'method' => 'ভিসা কার্ড',
                    'issue_date' => '2026-10-01',
                    'due_date' => '2026-10-08',
                    'status' => 'overdue',
                ],
                [
                    'id' => 'INV-777',
                    'customer' => 'মেঘনা গ্রুপ আইটি ডিপার্টমেন্ট',
                    'plan' => 'Enterprise Multi-Tenant',
                    'cycle' => 'মাসিক',
                    'amount' => 24999.00,
                    'method' => 'ব্যাংক ট্রান্সফার',
                    'issue_date' => '2026-10-12',
                    'due_date' => '2026-10-19',
                    'status' => 'paid',
                ],
            ],
            'billing_plans' => [
                [
                    'id' => 'starter',
                    'name' => 'Starter Tier',
                    'price' => 1999.00,
                    'interval' => 'মাসিক',
                    'users' => '৫ জন ইউজার অ্যাক্সেস',
                    'features' => '১টি টেন্যান্ট ডেটাবেস, বেসিক অ্যাকাউন্টিং ও ইনভয়েস, ইমেইল সাপোর্ট',
                    'status' => 'active',
                ],
                [
                    'id' => 'pro',
                    'name' => 'Professional Pro',
                    'price' => 4999.00,
                    'interval' => 'মাসিক',
                    'users' => '২৫ জন ইউজার অ্যাক্সেস',
                    'features' => '৫টি ব্রাঞ্চ সাপোর্ট, অ্যাকাউন্টিং, বিলিং, সিআরএম মডিউল, প্রায়োরিটি সাপোর্ট',
                    'status' => 'active',
                ],
                [
                    'id' => 'enterprise',
                    'name' => 'Enterprise Suite',
                    'price' => 9999.00,
                    'interval' => 'মাসিক',
                    'users' => 'আনলিমিটেড ইউজার ও রোলস',
                    'features' => 'মাল্টি-টেন্যান্ট আইসোলেশন ইঞ্জিন, সকল HMVC মডিউল সক্রিয়, ২৪/৭ ডেডিকেটেড এসএলএ',
                    'status' => 'active',
                ],
                [
                    'id' => 'custom',
                    'name' => 'Custom Enterprise',
                    'price' => 24999.00,
                    'interval' => 'কাস্টম',
                    'users' => 'কাস্টম ডেডিকেটেড ক্লাস্টার',
                    'features' => 'অন-প্রিমিসেস ডেডিকেটেড ক্লাস্টার, কাস্টম মডিউল ডেভেলপমেন্ট, হোয়াইট-লেবেল ব্রান্ডিং',
                    'status' => 'active',
                ],
            ],
            'billing_subscriptions' => [
                [
                    'id' => 'SUB-0101',
                    'customer' => 'ঢাকা টেক সল্যুশনস লিঃ',
                    'plan' => 'Enterprise Suite',
                    'start_date' => '2026-01-01',
                    'renewal_date' => '2027-01-01',
                    'auto_renew' => 'চালু (Yes)',
                    'status' => 'active',
                ],
                [
                    'id' => 'SUB-0102',
                    'customer' => 'ব্লু-স্কাই সফটওয়্যার লিমিটেড',
                    'plan' => 'Professional Pro',
                    'start_date' => '2026-10-14',
                    'renewal_date' => '2026-11-14',
                    'auto_renew' => 'চালু (Yes)',
                    'status' => 'active',
                ],
                [
                    'id' => 'SUB-0103',
                    'customer' => 'প্রাইম লজিস্টিকস কর্পোরেশন',
                    'plan' => 'Enterprise Suite',
                    'start_date' => '2026-10-13',
                    'renewal_date' => '2026-11-13',
                    'auto_renew' => 'চালু (Yes)',
                    'status' => 'grace_period',
                ],
                [
                    'id' => 'SUB-0104',
                    'customer' => 'গ্রীন ডেল্টা করপোরেট',
                    'plan' => 'Starter Tier',
                    'start_date' => '2026-08-01',
                    'renewal_date' => '2026-11-01',
                    'auto_renew' => 'বন্ধ (No)',
                    'status' => 'active',
                ],
            ],
            'inventory_products' => [
                [
                    'id' => 'AUD-901',
                    'sku' => 'AUD-901',
                    'name' => 'প্রিমিয়াম ওয়্যারলেস হেডসেট',
                    'category' => 'অডিও এক্সেসরিজ',
                    'unit' => 'পিস (Pcs)',
                    'cost_price' => 2400.00,
                    'selling_price' => 3200.00,
                    'stock' => 268,
                    'warehouse' => 'তেজগাঁও - A1',
                    'status' => 'in_stock',
                ],
                [
                    'id' => 'WTB-204',
                    'sku' => 'WTB-204',
                    'name' => 'স্মার্ট ট্র্যাকিং ফিটনেস ব্যান্ড',
                    'category' => 'স্মার্ট ডিভাইস',
                    'unit' => 'পিস (Pcs)',
                    'cost_price' => 1500.00,
                    'selling_price' => 2100.00,
                    'stock' => 145,
                    'warehouse' => 'তেজগাঁও - B3',
                    'status' => 'in_stock',
                ],
                [
                    'id' => 'PWR-65W',
                    'sku' => 'PWR-65W',
                    'name' => 'টাইপ-সি আল্ট্রাফাস্ট চার্জার ৬৫W',
                    'category' => 'মোবাইল পাওয়ার',
                    'unit' => 'পিস (Pcs)',
                    'cost_price' => 950.00,
                    'selling_price' => 1450.00,
                    'stock' => 8,
                    'warehouse' => 'চট্টগ্রাম - C2',
                    'status' => 'low_stock',
                ],
                [
                    'id' => 'CBL-HDMI',
                    'sku' => 'CBL-HDMI',
                    'name' => 'এইচডিএমআই আল্ট্রা ক্যাবল ৩মি',
                    'category' => 'ক্যাবল ও কানেক্টর',
                    'unit' => 'পিস (Pcs)',
                    'cost_price' => 450.00,
                    'selling_price' => 750.00,
                    'stock' => 12,
                    'warehouse' => 'তেজগাঁও - D1',
                    'status' => 'low_stock',
                ],
            ],
            'inventory_warehouses' => [
                [
                    'id' => 'WH-01',
                    'code' => 'WH-01',
                    'name' => 'তেজগাঁও সেন্ট্রাল ওয়্যারহাউস',
                    'incharge' => 'আতিকুর রহমান',
                    'location' => 'তেজগাঁও শিল্পাঞ্চল, ঢাকা',
                    'capacity' => '৮৫% পূর্ণ (৪,২০০ স্কয়ারফুট)',
                ],
                [
                    'id' => 'WH-02',
                    'code' => 'WH-02',
                    'name' => 'চট্টগ্রাম পোর্ট ডিস্ট্রিবিউশন হাব',
                    'incharge' => 'মাহমুদুল হাসান',
                    'location' => 'আগ্রাবাদ, চট্টগ্রাম',
                    'capacity' => '৬২% পূর্ণ (৩,০০০ স্কয়ারফুট)',
                ],
            ],
            'accounting_journal' => [
                [
                    'id' => 'JV-2026-092',
                    'voucher_no' => 'JV-2026-092',
                    'date' => '2026-10-15',
                    'debit_account' => '1010-01 সিটি ব্যাংক',
                    'credit_account' => '4010-01 সফটওয়্যার সেলস',
                    'description' => 'ইনভয়েস #104 এর বিপরীতে সফটওয়্যার ডেলিভারি বাবদ পূর্ণ অর্থ প্রাপ্তি।',
                    'debit_amount' => 50000.00,
                    'credit_amount' => 50000.00,
                    'status' => 'posted',
                ],
                [
                    'id' => 'JV-2026-091',
                    'voucher_no' => 'JV-2026-091',
                    'date' => '2026-10-14',
                    'debit_account' => '5010-01 সার্ভার খরচ',
                    'credit_account' => '1010-01 সিটি ব্যাংক',
                    'description' => 'AWS ক্লাউড ডেডিকেটেড সার্ভার মাসিক ইনফ্রাস্ট্রাকচার চার্জ পরিশোধ।',
                    'debit_amount' => 12400.00,
                    'credit_amount' => 12400.00,
                    'status' => 'posted',
                ],
                [
                    'id' => 'JV-2026-090',
                    'voucher_no' => 'JV-2026-090',
                    'date' => '2026-10-13',
                    'debit_account' => '5020-01 অফিস স্টেশনারি',
                    'credit_account' => '1010-03 পেটি ক্যাশ',
                    'description' => 'প্রিন্টার টোনার ও অফিস পেপার ক্রয় বাবদ ক্যাশ ভাউচার অনুমোদন।',
                    'debit_amount' => 4200.00,
                    'credit_amount' => 4200.00,
                    'status' => 'posted',
                ],
            ],
            'commerce_orders' => [
                [
                    'id' => 'ORD-904',
                    'order_code' => 'ORD-904',
                    'customer' => 'সাদিয়া আফরিন',
                    'phone' => '+880 1711-234567',
                    'items_count' => 2,
                    'total_amount' => 5700.00,
                    'gateway' => 'বিকাশ অনলাইন',
                    'shipping_status' => 'delivered',
                ],
                [
                    'id' => 'ORD-903',
                    'order_code' => 'ORD-903',
                    'customer' => 'মেহরাব হোসেন',
                    'phone' => '+880 1819-876543',
                    'items_count' => 1,
                    'total_amount' => 2200.00,
                    'gateway' => 'ভিসা কার্ড',
                    'shipping_status' => 'shipped',
                ],
                [
                    'id' => 'ORD-902',
                    'order_code' => 'ORD-902',
                    'customer' => 'রাশেদুল করিম',
                    'phone' => '+880 1912-345678',
                    'items_count' => 3,
                    'total_amount' => 4350.00,
                    'gateway' => 'ক্যাশ অন ডেলিভারি',
                    'shipping_status' => 'processing',
                ],
            ],
            'crm_leads' => [
                [
                    'id' => 'LD-101',
                    'name' => 'তানভীর আহমেদ',
                    'company' => 'ইনোভেক্স আইটি সলিউশনস',
                    'value' => 150000.00,
                    'stage' => 'Proposal Sent',
                    'phone' => '+880 1712-345678',
                    'status' => 'active',
                ],
                [
                    'id' => 'LD-102',
                    'name' => 'ফারহানা ইসলাম',
                    'company' => 'গ্লোবাল লজিস্টিকস',
                    'value' => 280000.00,
                    'stage' => 'Negotiation',
                    'phone' => '+880 1812-345678',
                    'status' => 'active',
                ],
            ],
            'hrm_employees' => [
                [
                    'id' => 'EMP-01',
                    'name' => 'আরিফুল ইসলাম',
                    'department' => 'সফটওয়্যার ইঞ্জিনিয়ারিং',
                    'designation' => 'সিনিয়র পিএইচপি ডেভেলপার',
                    'salary' => 85000.00,
                    'status' => 'active',
                ],
                [
                    'id' => 'EMP-02',
                    'name' => 'নুসরাত জাহান',
                    'department' => 'কোয়ালিটি অ্যাস্যুরেন্স',
                    'designation' => 'কিউএ লিড',
                    'salary' => 65000.00,
                    'status' => 'active',
                ],
            ],
            default => [],
        };
    }
}
