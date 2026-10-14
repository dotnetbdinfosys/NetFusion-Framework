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

namespace NetFusion\Core\Payment\Contracts;

use NetFusion\Core\Payment\Models\PaymentMethod;

interface TokenizationVaultInterface
{
    /**
     * Tokenize raw card/bank data with AES-256-GCM encryption and generate PCI-compliant token.
     */
    public function tokenize(PaymentMethod $method, string $customerId = ''): string;

    /**
     * Retrieve decrypted card/bank data for processing (restricted access).
     */
    public function detokenize(string $token): ?PaymentMethod;

    /**
     * Delete/Revoke a stored payment token.
     */
    public function revoke(string $token): bool;

    /**
     * Check if a token exists and is valid.
     */
    public function isValid(string $token): bool;
}
