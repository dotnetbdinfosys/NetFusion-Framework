<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Dynamic Forms Configuration
|--------------------------------------------------------------------------
|
| File      : config/forms.php
| Module    : Forms
| Purpose   : Configuration for Form Engine, custom field builders and rendering styles.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Default CSS Framework Styling
    |--------------------------------------------------------------------------
    |
    | Options: "bootstrap5", "tailwind", "plain"
    |
    */
    'style' => env('FORM_STYLE', 'bootstrap5'),

    /*
    |--------------------------------------------------------------------------
    | Supported Entities for Dynamic Custom Fields
    |--------------------------------------------------------------------------
    |
    | কাস্টম ফিল্ড সমর্থিত এন্টারপ্রাইজ মডেলসমূহ
    |
    */
    'entities' => [
        'User'     => \NetFusion\App\Models\User::class,
        'Lead'     => 'NetFusion\App\Modules\CRM\Models\Lead',
        'Deal'     => 'NetFusion\App\Modules\CRM\Models\Deal',
        'Customer' => 'NetFusion\App\Modules\Commerce\Models\Customer',
        'Order'    => 'NetFusion\App\Modules\Commerce\Models\Order',
        'Product'  => 'NetFusion\App\Modules\Inventory\Models\Product',
    ],

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    |
    | সমস্ত POST/PUT/PATCH/DELETE ফর্মে স্বয়ংক্রিয়ভাবে CSRF ফিল্ড ইনজেক্ট করবে কিনা।
    |
    */
    'csrf_protection' => true,

    /*
    |--------------------------------------------------------------------------
    | Default Field Classes
    |--------------------------------------------------------------------------
    |
    | ফিল্ড টাইপ অনুযায়ী ডিফল্ট ক্লাস ম্যাপিং
    |
    */
    'field_types' => [
        'text'     => \NetFusion\Core\Forms\Fields\TextField::class,
        'number'   => \NetFusion\Core\Forms\Fields\NumberField::class,
        'select'   => \NetFusion\Core\Forms\Fields\SelectField::class,
        'textarea' => \NetFusion\Core\Forms\Fields\TextareaField::class,
        'date'     => \NetFusion\Core\Forms\Fields\DateField::class,
        'checkbox' => \NetFusion\Core\Forms\Fields\CheckboxField::class,
        'file'     => \NetFusion\Core\Forms\Fields\FileField::class,
        'json'     => \NetFusion\Core\Forms\Fields\JsonField::class,
    ],

];