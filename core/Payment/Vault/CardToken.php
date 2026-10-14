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

namespace NetFusion\Core\Payment\Vault;

class CardToken
{
    /**
     * @param string $token
     * @param string $customerId
     * @param string $brand
     * @param string $last4
     * @param int|null $expMonth
     * @param int|null $expYear
     * @param string $maskedPan
     * @param string $encryptedPayload
     * @param string $iv
     * @param string $tag
     * @param string $createdAt
     */
    public function __construct(
        public string $token,
        public string $customerId,
        public string $brand,
        public string $last4,
        public ?int $expMonth,
        public ?int $expYear,
        public string $maskedPan,
        public string $encryptedPayload,
        public string $iv,
        public string $tag,
        public string $createdAt
    ) {
    }
}
