<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Models/Subscription.php
| Module    : Modules\Billing
| Namespace : NetFusion\Modules\Billing\Models
| Purpose   : Active Record Model managing tenant subscription status, trials, and renewals.
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
 * NetFusion Subscription Model
 *
 * Represents an active, trialing, or canceled tenant subscription.
 *
 * @property int         $id
 * @property string      $tenant_id
 * @property int         $plan_id
 * @property string      $status
 * @property string|null $trial_ends_at
 * @property string|null $current_period_start
 * @property string|null $current_period_end
 * @property string|null $canceled_at
 *
 * @package NetFusion\Modules\Billing\Models
 * @version 1.0.0
 */
class Subscription extends Model
{
    protected string $table = 'billing_subscriptions';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'canceled_at',
    ];

    /**
     * Get the currently active subscription for a given tenant.
     */
    public static function forTenant(string $tenantId): ?static
    {
        $data = static::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'trialing'])
            ->latest('id')
            ->first();

        return $data ? static::newFromBuilder($data) : null;
    }

    /**
     * Check if subscription is currently valid (active or within trial).
     */
    public function isValid(): bool
    {
        if ($this->status === 'active') {
            return true;
        }

        if ($this->status === 'trialing') {
            return $this->onTrial();
        }

        return false;
    }

    /**
     * Check if subscription is currently on a trial.
     */
    public function onTrial(): bool
    {
        if (!$this->trial_ends_at) {
            return false;
        }

        return strtotime($this->trial_ends_at) > time();
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(): bool
    {
        $this->status      = 'canceled';
        $this->canceled_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * Get the associated Plan instance.
     */
    public function plan(): ?Plan
    {
        return Plan::find((int) $this->plan_id);
    }
}
