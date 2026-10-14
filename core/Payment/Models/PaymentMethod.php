<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Payment\Models;

class PaymentMethod
{
    public const TYPE_CARD = 'card';
    public const TYPE_MFS = 'mfs'; // Mobile Financial Services (bKash, Nagad)
    public const TYPE_BANK = 'bank_account';

    /**
     * @param string $type ('card', 'mfs', 'bank_account')
     * @param string|null $number Raw PAN / Account Number (masked in memory or ephemeral)
     * @param string|null $holderName
     * @param int|null $expMonth
     * @param int|null $expYear
     * @param string|null $cvv Ephemeral only, never saved
     * @param string|null $token Vault or gateway token
     * @param string|null $brand (e.g. 'Visa', 'Mastercard', 'Amex', 'bKash')
     * @param string|null $last4
     */
    public function __construct(
        protected string $type = self::TYPE_CARD,
        protected ?string $number = null,
        protected ?string $holderName = null,
        protected ?int $expMonth = null,
        protected ?int $expYear = null,
        protected ?string $cvv = null,
        protected ?string $token = null,
        protected ?string $brand = null,
        protected ?string $last4 = null
    ) {
        if ($this->number !== null) {
            $cleaned = preg_replace('/\D/', '', $this->number);
            $this->last4 = substr($cleaned, -4);
            if ($this->brand === null && $this->type === self::TYPE_CARD) {
                $this->brand = self::detectBrand($cleaned);
            }
        }
    }

    public static function card(
        string $number,
        string $holderName,
        int $expMonth,
        int $expYear,
        string $cvv
    ): static {
        return new static(
            type: self::TYPE_CARD,
            number: $number,
            holderName: $holderName,
            expMonth: $expMonth,
            expYear: $expYear,
            cvv: $cvv
        );
    }

    public static function token(string $token, string $type = self::TYPE_CARD, ?string $brand = null, ?string $last4 = null): static
    {
        return new static(
            type: $type,
            token: $token,
            brand: $brand,
            last4: $last4
        );
    }

    public static function mfs(string $walletNumber, string $provider = 'bKash'): static
    {
        return new static(
            type: self::TYPE_MFS,
            number: $walletNumber,
            brand: $provider,
            last4: substr($walletNumber, -4)
        );
    }

    public static function bankAccount(string $accountNumber, string $bankName = '', string $holderName = ''): static
    {
        return new static(
            type: self::TYPE_BANK,
            number: $accountNumber,
            holderName: $holderName,
            brand: $bankName,
            last4: substr($accountNumber, -4)
        );
    }

    public static function detectBrand(string $cleanNumber): string
    {
        if (preg_match('/^4/', $cleanNumber)) {
            return 'Visa';
        }
        if (preg_match('/^(5[1-5]|2[2-7])/', $cleanNumber)) {
            return 'Mastercard';
        }
        if (preg_match('/^3[47]/', $cleanNumber)) {
            return 'Amex';
        }
        if (preg_match('/^6(?:011|5)/', $cleanNumber)) {
            return 'Discover';
        }
        return 'Unknown';
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function getMaskedNumber(): string
    {
        if ($this->last4 !== null) {
            return '•••• •••• •••• ' . $this->last4;
        }
        return '••••';
    }

    public function getHolderName(): ?string
    {
        return $this->holderName;
    }

    public function getExpMonth(): ?int
    {
        return $this->expMonth;
    }

    public function getExpYear(): ?int
    {
        return $this->expYear;
    }

    public function getCvv(): ?string
    {
        return $this->cvv;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function getLast4(): ?string
    {
        return $this->last4;
    }

    public function isExpired(): bool
    {
        if ($this->expYear === null || $this->expMonth === null) {
            return false;
        }
        $curYear = (int)date('Y');
        $curMonth = (int)date('n');

        if ($this->expYear < $curYear) {
            return true;
        }
        if ($this->expYear === $curYear && $this->expMonth < $curMonth) {
            return true;
        }
        return false;
    }
}
