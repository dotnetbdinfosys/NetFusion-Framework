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

namespace NetFusion\Core\Auth\Policy;

use InvalidArgumentException;
use NetFusion\Core\Application;
use NetFusion\Core\Auth\Policy\Contracts\PolicyStoreInterface;
use NetFusion\Core\Auth\Policy\Contracts\RebacStoreInterface;
use NetFusion\Core\Auth\Policy\Rebac\RebacManager;
use NetFusion\Core\Auth\Policy\Store\ArrayPolicyStore;
use NetFusion\Core\Auth\Policy\Store\ArrayRebacStore;
use NetFusion\Core\Auth\Policy\Store\DatabasePolicyStore;
use NetFusion\Core\Auth\Policy\Store\DatabaseRebacStore;
use NetFusion\Core\Config\Config;
use NetFusion\Core\Database\DatabaseManager;

class AccessManager
{
    protected ?PolicyEngine $policyEngine = null;
    protected ?RebacManager $rebacManager = null;
    /** @var array<string, PolicyStoreInterface> */
    protected array $policyStores = [];
    /** @var array<string, RebacStoreInterface> */
    protected array $rebacStores = [];

    public function __construct(
        protected ?Application $app = null,
        ?PolicyEngine $policyEngine = null,
        ?RebacManager $rebacManager = null
    ) {
        $this->policyEngine = $policyEngine;
        $this->rebacManager = $rebacManager;
    }

    /**
     * Get or initialize the PolicyEngine.
     */
    public function engine(): PolicyEngine
    {
        if ($this->policyEngine === null) {
            $this->policyEngine = new PolicyEngine($this->policyStore());
        }

        return $this->policyEngine;
    }

    /**
     * Get or initialize the RebacManager.
     */
    public function rebac(): RebacManager
    {
        if ($this->rebacManager === null) {
            $this->rebacManager = new RebacManager($this->rebacStore());
        }

        return $this->rebacManager;
    }

    /**
     * Define and register an authorization policy.
     */
    public function define(string $id, string|callable|null $nameOrBuilder = null, ?callable $builder = null, int $priority = 0): Policy
    {
        $name = is_string($nameOrBuilder) ? $nameOrBuilder : $id;
        $cb = is_callable($nameOrBuilder) ? $nameOrBuilder : $builder;

        $policy = new Policy($id, $name, [], $priority);
        if ($cb !== null) {
            $cb($policy);
        }

        $this->policyStore()->save($policy);
        return $policy;
    }

    /**
     * Check if a subject is authorized to perform action on resource.
     */
    public function can(mixed $subject, string $action, mixed $resource, array $environment = []): bool
    {
        return $this->engine()->allows($subject, $action, $resource, $environment);
    }

    /**
     * Check if access is denied.
     */
    public function cannot(mixed $subject, string $action, mixed $resource, array $environment = []): bool
    {
        return $this->engine()->denies($subject, $action, $resource, $environment);
    }

    /**
     * Evaluate context and return rich Decision object.
     */
    public function evaluate(EvaluationContext $context): Decision
    {
        return $this->engine()->evaluate($context);
    }

    /**
     * ReBAC Zanzibar relation check helper.
     */
    public function checkRelation(string $entity, string $relation, string $subject): bool
    {
        return $this->rebac()->check($entity, $relation, $subject);
    }

    /**
     * Get or resolve a Policy store.
     */
    public function policyStore(?string $name = null): PolicyStoreInterface
    {
        if ($name === null && $this->policyEngine !== null) {
            return $this->policyEngine->getStore();
        }

        $config = $this->getConfig();
        $name = $name ?? (string)($config['policy_store']['default'] ?? 'array');

        if (!isset($this->policyStores[$name])) {
            $this->policyStores[$name] = match ($name) {
                'array' => new ArrayPolicyStore(),
                'database' => new DatabasePolicyStore($this->app?->make(DatabaseManager::class)),
                default => throw new InvalidArgumentException("Unsupported Policy store: [{$name}]"),
            };
        }

        return $this->policyStores[$name];
    }

    /**
     * Get or resolve a ReBAC store.
     */
    public function rebacStore(?string $name = null): RebacStoreInterface
    {
        if ($name === null && $this->rebacManager !== null) {
            return $this->rebacManager->getResolver()->getStore();
        }

        $config = $this->getConfig();
        $name = $name ?? (string)($config['rebac_store']['default'] ?? 'array');

        if (!isset($this->rebacStores[$name])) {
            $this->rebacStores[$name] = match ($name) {
                'array' => new ArrayRebacStore(),
                'database' => new DatabaseRebacStore($this->app?->make(DatabaseManager::class)),
                default => throw new InvalidArgumentException("Unsupported ReBAC store: [{$name}]"),
            };
        }

        return $this->rebacStores[$name];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getConfig(): array
    {
        if ($this->app->has(Config::class)) {
            $config = $this->app->make(Config::class)->get('auth_policy', []);
            if (is_array($config)) {
                return $config;
            }
        }

        return [
            'policy_store' => ['default' => 'array'],
            'rebac_store' => ['default' => 'array'],
        ];
    }
}
