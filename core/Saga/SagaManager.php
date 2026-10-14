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

namespace NetFusion\Core\Saga;

use InvalidArgumentException;
use NetFusion\Core\Application;
use NetFusion\Core\Config\Config;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Saga\Contracts\SagaInterface;
use NetFusion\Core\Saga\Contracts\SagaStoreInterface;
use NetFusion\Core\Saga\Store\ArraySagaStore;
use NetFusion\Core\Saga\Store\DatabaseSagaStore;

class SagaManager
{
    /** @var array<string, SagaInterface|callable> */
    protected array $definitions = [];

    /** @var array<string, SagaStoreInterface> */
    protected array $stores = [];

    /** @var array<string, callable> */
    protected array $customStoreCreators = [];

    protected ?SagaCoordinator $coordinator = null;

    public function __construct(
        protected Application $app
    ) {
    }

    /**
     * Define a named Saga using a builder closure or a SagaInterface class instance.
     */
    public function define(string $name, callable|SagaInterface $definition): self
    {
        $this->definitions[$name] = $definition;
        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    /**
     * Retrieve a resolved SagaInterface instance by name.
     */
    public function get(string $name): SagaInterface
    {
        if (!isset($this->definitions[$name])) {
            throw new InvalidArgumentException("Saga definition [{$name}] is not registered.");
        }

        $def = $this->definitions[$name];
        if ($def instanceof SagaInterface) {
            return $def;
        }

        $builder = new SagaBuilder($name);
        $def($builder);
        return $builder;
    }

    /**
     * Get or resolve a SagaStore instance.
     */
    public function store(?string $name = null): SagaStoreInterface
    {
        $config = $this->getConfig();
        $name = $name ?? (string)($config['default_store'] ?? 'array');

        if (!isset($this->stores[$name])) {
            $this->stores[$name] = $this->resolveStore($name, $config['stores'][$name] ?? []);
        }

        return $this->stores[$name];
    }

    protected function resolveStore(string $name, array $config): SagaStoreInterface
    {
        if (isset($this->customStoreCreators[$name])) {
            return ($this->customStoreCreators[$name])($this->app, $config);
        }

        return match ($name) {
            'array' => new ArraySagaStore(),
            'database' => new DatabaseSagaStore($this->app->make(DatabaseManager::class)),
            default => throw new InvalidArgumentException("Unsupported Saga store driver: [{$name}]"),
        };
    }

    public function extendStore(string $name, callable $creator): self
    {
        $this->customStoreCreators[$name] = $creator;
        return $this;
    }

    /**
     * Get or initialize the SagaCoordinator.
     */
    public function coordinator(): SagaCoordinator
    {
        if ($this->coordinator === null) {
            $this->coordinator = new SagaCoordinator($this->store());
        }

        return $this->coordinator;
    }

    /**
     * Execute a Saga by name with an initial payload.
     *
     * @param string $name
     * @param array<string, mixed> $payload
     * @param string|null $storeName
     * @return SagaInstance
     */
    public function run(string $name, array $payload = [], ?string $storeName = null): SagaInstance
    {
        $saga = $this->get($name);
        $instance = SagaInstance::create($name, $payload);
        
        $coord = $this->coordinator();
        if ($storeName !== null) {
            $coord->setStore($this->store($storeName));
        }

        return $coord->run($saga, $instance);
    }

    /**
     * Manually trigger compensation on an existing SagaInstance.
     */
    public function compensate(string $sagaId): SagaInstance
    {
        $store = $this->store();
        $instance = $store->find($sagaId);

        if ($instance === null) {
            throw new InvalidArgumentException("Saga instance [{$sagaId}] not found.");
        }

        $saga = $this->get($instance->sagaName);
        $steps = $saga->getSteps();

        // Collect completed steps
        $completedSteps = [];
        foreach ($steps as $step) {
            $res = $instance->getStepResult($step->getName());
            if ($res !== null && $res->success && !$res->compensated) {
                $completedSteps[] = $step;
            }
        }

        $coord = $this->coordinator();
        $coord->executeCompensation(
            $saga,
            $instance,
            $completedSteps,
            new SagaException("Manual compensation requested for saga [{$sagaId}].")
        );

        return $instance;
    }

    /**
     * Retry a failed Saga from the beginning or from failed step.
     */
    public function retry(string $sagaId): SagaInstance
    {
        $store = $this->store();
        $instance = $store->find($sagaId);

        if ($instance === null) {
            throw new InvalidArgumentException("Saga instance [{$sagaId}] not found.");
        }

        $saga = $this->get($instance->sagaName);
        
        // Reset instance
        $instance->state = SagaState::PENDING;
        $instance->currentStepIndex = 0;
        $instance->stepResults = [];
        $instance->error = null;
        $instance->completedAt = null;

        return $this->coordinator()->run($saga, $instance);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getConfig(): array
    {
        if ($this->app->has(Config::class)) {
            $config = $this->app->make(Config::class)->get('saga', []);
            if (is_array($config)) {
                return $config;
            }
        }

        return [
            'default_store' => 'array',
            'stores' => [
                'array' => [],
                'database' => [
                    'table' => 'netfusion_saga_instances',
                ],
            ],
        ];
    }
}
