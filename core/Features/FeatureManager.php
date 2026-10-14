<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Features/FeatureManager.php
| Module    : Features
| Namespace : NetFusion\Core\Features
| Purpose   : Central Feature Flag & A/B testing manager supporting percentage rollouts.
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

namespace NetFusion\Core\Features;

use Closure;
use NetFusion\Core\Application;
use NetFusion\Core\Features\Contracts\FeatureDriverInterface;
use NetFusion\Core\Features\Drivers\ArrayDriver;
use NetFusion\Core\Features\Drivers\CacheDriver;
use NetFusion\Core\Features\Drivers\DatabaseDriver;

/**
 * Class FeatureManager
 *
 * Central service for feature toggle flags, canary percentage rollouts,
 * user targeting, and A/B experiment variants.
 *
 * @package NetFusion\Core\Features
 */
class FeatureManager
{
    /**
     * Runtime defined feature callbacks or values.
     *
     * @var array<string, mixed>
     */
    protected array $definitions = [];

    /**
     * Active storage driver.
     */
    protected FeatureDriverInterface $driver;

    /**
     * @param Application|null            $app
     * @param FeatureDriverInterface|null $driver
     */
    public function __construct(
        protected ?Application $app = null,
        ?FeatureDriverInterface $driver = null
    ) {
        $this->driver = $driver ?? new ArrayDriver();
    }

    /**
     * Set the active persistence driver.
     */
    public function setDriver(FeatureDriverInterface $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Get the active persistence driver.
     */
    public function getDriver(): FeatureDriverInterface
    {
        return $this->driver;
    }

    /**
     * Define a feature with a static value or resolution callback.
     *
     * @param string                  $feature
     * @param bool|Closure|array|null $resolver
     * @return $this
     */
    public function define(string $feature, mixed $resolver = true): self
    {
        $this->definitions[$feature] = $resolver;
        return $this;
    }

    /**
     * Determine if a feature is active for the given scope (user/tenant).
     *
     * @param string $feature
     * @param mixed  $scope
     * @return bool
     */
    public function active(string $feature, mixed $scope = null): bool
    {
        // 1. Check in-memory definition closure/value
        if (isset($this->definitions[$feature])) {
            $def = $this->definitions[$feature];
            if ($def instanceof Closure) {
                return (bool) $def($scope);
            }
            if (is_bool($def)) {
                return $def;
            }
        }

        // 2. Check storage driver record
        $stored = $this->driver->get($feature);
        if ($stored === null) {
            return false;
        }

        $status = $stored['status'] ?? 'inactive';
        if ($status === 'inactive') {
            return false;
        }

        // If status is active with no extra rules or percentage, it's globally active
        $percentage = $stored['percentage'] ?? null;
        $rules = $stored['rules'] ?? [];

        // 3. User / Scope targeting
        if (! empty($rules['users']) && is_array($rules['users'])) {
            $scopeId = $this->resolveScopeIdentifier($scope);
            if ($scopeId !== null && in_array((string) $scopeId, array_map('strval', $rules['users']), true)) {
                return true;
            }

            // If user targeting rules exist and no percentage rollout is set, non-matching scopes are false
            if ($percentage === null) {
                return false;
            }
        }

        // 4. Percentage rollout evaluation (Deterministic consistent hashing)
        if ($percentage !== null && is_numeric($percentage)) {
            $scopeId = $this->resolveScopeIdentifier($scope);
            if ($scopeId === null) {
                // If percentage rollout is set and no scope is given, use default false
                return false;
            }

            // Consistently hash feature + scope identifier into 0..99
            $hash = abs(crc32($feature . ':' . $scopeId)) % 100;
            return $hash < (int) $percentage;
        }

        return $status === 'active';
    }

    /**
     * Determine if a feature is inactive.
     */
    public function inactive(string $feature, mixed $scope = null): bool
    {
        return ! $this->active($feature, $scope);
    }

    /**
     * Activate a feature globally.
     */
    public function activate(string $feature): bool
    {
        return $this->driver->set($feature, [
            'status'     => 'active',
            'percentage' => null,
            'rules'      => [],
        ]);
    }

    /**
     * Deactivate a feature globally.
     */
    public function deactivate(string $feature): bool
    {
        return $this->driver->set($feature, [
            'status'     => 'inactive',
            'percentage' => null,
            'rules'      => [],
        ]);
    }

    /**
     * Activate a feature for a specific percentage of users (0 - 100).
     */
    public function percentage(string $feature, int $percentage): bool
    {
        $percentage = max(0, min(100, $percentage));

        return $this->driver->set($feature, [
            'status'     => 'active',
            'percentage' => $percentage,
            'rules'      => [],
        ]);
    }

    /**
     * Activate a feature for specific user(s) or entity ID(s).
     *
     * @param string             $feature
     * @param int|string|array   $scope
     * @return bool
     */
    public function activateFor(string $feature, mixed $scope): bool
    {
        $existing = $this->driver->get($feature) ?? ['status' => 'active', 'rules' => []];
        $users = $existing['rules']['users'] ?? [];

        $ids = is_array($scope) ? $scope : [$scope];
        foreach ($ids as $id) {
            $val = (string) $this->resolveScopeIdentifier($id);
            if (! in_array($val, $users, true)) {
                $users[] = $val;
            }
        }

        $existing['rules']['users'] = $users;
        $existing['status'] = 'active';

        return $this->driver->set($feature, $existing);
    }

    /**
     * Deactivate a feature for specific user(s) or entity ID(s).
     */
    public function deactivateFor(string $feature, mixed $scope): bool
    {
        $existing = $this->driver->get($feature);
        if ($existing === null || empty($existing['rules']['users'])) {
            return true;
        }

        $users = $existing['rules']['users'];
        $ids = array_map('strval', is_array($scope) ? $scope : [$scope]);
        $existing['rules']['users'] = array_values(array_filter($users, fn($u) => ! in_array((string) $u, $ids, true)));

        return $this->driver->set($feature, $existing);
    }

    /**
     * Forget/Delete a feature flag.
     */
    public function forget(string $feature): bool
    {
        unset($this->definitions[$feature]);
        return $this->driver->delete($feature);
    }

    /**
     * Retrieve all configured features and their state.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $stored = $this->driver->getAll();

        foreach ($this->definitions as $name => $val) {
            if (! isset($stored[$name])) {
                $stored[$name] = [
                    'status'     => is_bool($val) && $val ? 'active' : 'defined',
                    'percentage' => null,
                    'rules'      => [],
                ];
            }
        }

        return $stored;
    }

    // =========================================================================
    //  A/B Testing & Experiment Variants
    // =========================================================================

    /**
     * Assign a deterministic variant for an A/B experiment based on scope.
     *
     * @param string              $experiment Name of the experiment (e.g. 'checkout_v2')
     * @param mixed               $scope      User ID or entity to partition by
     * @param array<string, int>  $variants   Map of variant name => weight percentage (must sum to 100)
     * @return string Selected variant name
     */
    public function variant(
        string $experiment,
        mixed $scope = null,
        array $variants = ['control' => 50, 'treatment' => 50]
    ): string {
        $scopeId = $this->resolveScopeIdentifier($scope) ?? 'guest';

        $totalWeight = array_sum($variants);
        if ($totalWeight <= 0) {
            return (string) array_key_first($variants);
        }

        // Consistent hash value between 0 and totalWeight - 1
        $hash = abs(crc32($experiment . ':ab:' . $scopeId)) % $totalWeight;

        $cumulative = 0;
        foreach ($variants as $variantName => $weight) {
            $cumulative += $weight;
            if ($hash < $cumulative) {
                return (string) $variantName;
            }
        }

        return (string) array_key_last($variants);
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    /**
     * Extract a consistent scalar identifier from various scope types.
     */
    protected function resolveScopeIdentifier(mixed $scope): ?string
    {
        if ($scope === null) {
            return null;
        }

        if (is_scalar($scope)) {
            return (string) $scope;
        }

        if (is_object($scope)) {
            if (method_exists($scope, 'getKey')) {
                return (string) $scope->getKey();
            }
            if (isset($scope->id)) {
                return (string) $scope->id;
            }
        }

        if (is_array($scope) && isset($scope['id'])) {
            return (string) $scope['id'];
        }

        return md5(serialize($scope));
    }
}
