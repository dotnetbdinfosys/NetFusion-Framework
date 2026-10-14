<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\IaC\Execution;

use NetFusion\Core\IaC\Plan\ActionType;
use NetFusion\Core\IaC\Plan\ExecutionPlan;
use NetFusion\Core\IaC\Providers\Contracts\ResourceProviderInterface;
use NetFusion\Core\IaC\State\StateFile;
use NetFusion\Core\IaC\State\StateResource;
use NetFusion\Core\IaC\State\StateResourceInstance;

/**
 * Executes infrastructure mutation plans in strict topological DAG order,
 * resolving dynamic cross-resource references and updating the state store.
 */
class PlanExecutor
{
    /**
     * @var array<ResourceProviderInterface>
     */
    private array $providers = [];

    /**
     * @var array<string, array<string, mixed>> Address => computed attributes
     */
    private array $resolvedAttributes = [];

    public function __construct(array $providers = [])
    {
        foreach ($providers as $provider) {
            $this->addProvider($provider);
        }
    }

    public function addProvider(ResourceProviderInterface $provider): self
    {
        $this->providers[] = $provider;
        return $this;
    }

    /**
     * Execute plan against StateFile and commit mutations.
     *
     * @param ExecutionPlan $plan
     * @param StateFile|null $state
     * @return array{success: bool, applied: int, failed: int, state: StateFile, logs: array<string>}
     */
    public function execute(ExecutionPlan $plan, ?StateFile $state = null): array
    {
        $state ??= new StateFile();
        $this->resolvedAttributes = [];

        // Pre-populate resolved attributes with existing state
        foreach ($state->resources as $addr => $res) {
            $inst = $res->getPrimaryInstance();
            if ($inst !== null) {
                $this->resolvedAttributes[$addr] = $inst->attributes;
            }
        }

        $applied = 0;
        $failed = 0;
        $logs = [];

        // 1. Execute creates/updates/replaces in forward topological order
        foreach ($plan->topologicalOrder as $address) {
            $change = $plan->getChange($address);
            if ($change === null || $change->action === ActionType::NOOP || $change->action === ActionType::DESTROY) {
                continue;
            }

            [$type, $name] = explode('.', $address, 2);
            $provider = $this->findProviderFor($type);
            if ($provider === null) {
                $logs[] = "[ERROR] No provider registered for resource type '{$type}'";
                $failed++;
                continue;
            }

            try {
                $resolvedAttrs = $this->resolveReferences($change->after ?? []);

                if ($change->action === ActionType::CREATE) {
                    $logs[] = "[CREATE] Provisioning {$address} ...";
                    $computed = $provider->create($type, $name, $resolvedAttrs);
                    $this->resolvedAttributes[$address] = $computed;

                    $instance = new StateResourceInstance(schemaVersion: 0, attributes: $computed, dependencies: []);
                    $resource = new StateResource(
                        mode: 'managed',
                        type: $type,
                        name: $name,
                        provider: 'provider["' . $provider->getName() . '"]',
                        instances: [$instance]
                    );
                    $state->setResource($resource);
                    $applied++;
                } elseif ($change->action === ActionType::UPDATE) {
                    $logs[] = "[UPDATE] Modifying {$address} ...";
                    $existingRes = $state->getResource($address);
                    $existingId = (string) ($existingRes?->getPrimaryInstance()?->getAttribute('id') ?? $name);

                    $computed = $provider->update($type, $existingId, $resolvedAttrs);
                    $this->resolvedAttributes[$address] = $computed;

                    $instance = new StateResourceInstance(schemaVersion: 0, attributes: $computed, dependencies: []);
                    $resource = new StateResource(
                        mode: 'managed',
                        type: $type,
                        name: $name,
                        provider: 'provider["' . $provider->getName() . '"]',
                        instances: [$instance]
                    );
                    $state->setResource($resource);
                    $applied++;
                } elseif ($change->action === ActionType::REPLACE) {
                    $logs[] = "[REPLACE] Tearing down and recreating {$address} ...";
                    $existingRes = $state->getResource($address);
                    $existingId = (string) ($existingRes?->getPrimaryInstance()?->getAttribute('id') ?? $name);

                    $provider->delete($type, $existingId);
                    $computed = $provider->create($type, $name, $resolvedAttrs);
                    $this->resolvedAttributes[$address] = $computed;

                    $instance = new StateResourceInstance(schemaVersion: 0, attributes: $computed, dependencies: []);
                    $resource = new StateResource(
                        mode: 'managed',
                        type: $type,
                        name: $name,
                        provider: 'provider["' . $provider->getName() . '"]',
                        instances: [$instance]
                    );
                    $state->setResource($resource);
                    $applied++;
                }
            } catch (\Throwable $e) {
                $logs[] = "[FAIL] {$address}: {$e->getMessage()}";
                $failed++;
            }
        }

        // 2. Execute destroys in reverse topological order
        $reverseOrder = array_reverse($plan->topologicalOrder);
        foreach ($reverseOrder as $address) {
            $change = $plan->getChange($address);
            if ($change === null || $change->action !== ActionType::DESTROY) {
                continue;
            }

            [$type, $name] = explode('.', $address, 2);
            $provider = $this->findProviderFor($type);
            $existingRes = $state->getResource($address);
            $existingId = (string) ($existingRes?->getPrimaryInstance()?->getAttribute('id') ?? $name);

            try {
                $logs[] = "[DESTROY] Deleting {$address} ...";
                if ($provider !== null) {
                    $provider->delete($type, $existingId);
                }
                $state->removeResource($address);
                unset($this->resolvedAttributes[$address]);
                $applied++;
            } catch (\Throwable $e) {
                $logs[] = "[FAIL] {$address}: {$e->getMessage()}";
                $failed++;
            }
        }

        return [
            'success' => ($failed === 0),
            'applied' => $applied,
            'failed' => $failed,
            'state' => $state,
            'logs' => $logs,
        ];
    }

    private function findProviderFor(string $resourceType): ?ResourceProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($resourceType)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Resolve cross-resource references (e.g. `aws_security_group.web.id` -> `sg-12345`).
     */
    private function resolveReferences(mixed $value): mixed
    {
        if (is_string($value)) {
            // Check for exact single reference
            if (preg_match('/^(?:\$\{)?([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)(?:\})?$/', $value, $m)) {
                $addr = $m[1] . '.' . $m[2];
                $attr = $m[3];
                if (isset($this->resolvedAttributes[$addr][$attr])) {
                    return $this->resolvedAttributes[$addr][$attr];
                }
            }

            // Replace embedded references
            return preg_replace_callback('/(?:\$\{)?([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)(?:\})?/', function ($matches) {
                $addr = $matches[1] . '.' . $matches[2];
                $attr = $matches[3];
                if (isset($this->resolvedAttributes[$addr][$attr])) {
                    return (string) $this->resolvedAttributes[$addr][$attr];
                }
                return $matches[0];
            }, $value);
        }

        if (is_array($value)) {
            $resolved = [];
            foreach ($value as $k => $v) {
                $resolved[$k] = $this->resolveReferences($v);
            }
            return $resolved;
        }

        return $value;
    }
}
