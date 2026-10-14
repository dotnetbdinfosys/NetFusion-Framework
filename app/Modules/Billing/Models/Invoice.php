<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Models/Invoice.php
| Module    : Modules\Billing
| Namespace : NetFusion\Modules\Billing\Models
| Purpose   : Active Record Model representing tenant invoices and payment records.
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

namespace NetFusion\Modules\Billing\Models;

use NetFusion\Core\Database\Model;

/**
 * NetFusion Invoice Model
 *
 * Represents an invoice issued to a tenant for subscription or add-on charges.
 *
 * @property int         $id
 * @property string      $tenant_id
 * @property string      $invoice_number
 * @property float       $amount
 * @property string      $currency
 * @property string      $status
 * @property string|null $paid_at
 *
 * @package NetFusion\Modules\Billing\Models
 * @version 1.0.0
 */
class Invoice extends Model
{
    protected string $table = 'billing_invoices';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'tenant_id',
        'subscription_id',
        'invoice_number',
        'amount',
        'currency',
        'status',
        'due_date',
        'paid_at',
    ];

    /**
     * Generate unique sequential invoice number.
     */
    public static function generateNumber(): string
    {
        $prefix = (string) config('billing.invoice_prefix', 'INV-');
        $random = strtoupper(bin2hex(random_bytes(4)));
        return $prefix . date('Ymd') . '-' . $random;
    }

    /**
     * Get all invoices belonging to a specific tenant.
     *
     * @return static[]
     */
    public static function forTenant(string $tenantId): array
    {
        $rows = static::query()->where('tenant_id', $tenantId)->latest('id')->get();
        return array_map([static::class, 'newFromBuilder'], $rows);
    }

    /**
     * Check if invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Mark this invoice as paid.
     */
    public function markAsPaid(): bool
    {
        $this->status  = 'paid';
        $this->paid_at = date('Y-m-d H:i:s');
        return $this->save();
    }
}