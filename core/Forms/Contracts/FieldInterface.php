<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Forms/Contracts/FieldInterface.php
| Module    : Forms
| Namespace : NetFusion\Core\Forms\Contracts
| Purpose   : Standard contract for form field definitions and rendering.
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

namespace NetFusion\Core\Forms\Contracts;

/**
 * FieldInterface
 *
 * প্রতিটি ফর্ম ফিল্ড এই কন্ট্রাক্ট ইমপ্লিমেন্ট করবে।
 */
interface FieldInterface
{
    /**
     * ফিল্ডের টেকনিক্যাল নাম পান।
     */
    public function getName(): string;

    /**
     * ফিল্ডের লেবেল পান।
     */
    public function getLabel(): string;

    /**
     * ফিল্ডের টাইপ স্ট্রিং পান (e.g. 'text', 'select', 'number')।
     */
    public function getType(): string;

    /**
     * ফিল্ডের ভ্যালু সেট করুন।
     */
    public function setValue(mixed $value): static;

    /**
     * ফিল্ডের বর্তমান ভ্যালু পান।
     */
    public function getValue(): mixed;

    /**
     * ফিল্ডের ডিফল্ট ভ্যালু পান।
     */
    public function getDefaultValue(): mixed;

    /**
     * ভ্যালিডেশন রুলস পান।
     *
     * @return array<int, string>
     */
    public function getRules(): array;

    /**
     * ফিল্ডের এইচটিএমএল অ্যাট্রিবিউটস সেট বা পান।
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array;

    /**
     * ফিল্ডটি প্রয়োজনীয় (required) কিনা।
     */
    public function isRequired(): bool;

    /**
     * ফিল্ডের ভ্যালু ভ্যালিডেট করুন।
     *
     * @return array<int, string> ভ্যালিডেশন এরর মেসেজের অ্যারে (খালি হলে ভ্যালিড)
     */
    public function validate(mixed $value): array;

    /**
     * ফিল্ডটি HTML এলিমেন্ট হিসেবে রেন্ডার করুন।
     */
    public function render(): string;

    /**
     * ফিল্ডের স্কিমা অ্যারে হিসেবে সিরিয়ালাইজ করুন (JSON Form Builder এর জন্য)।
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}