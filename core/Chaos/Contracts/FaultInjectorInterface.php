<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Contracts;

/**
 * Interface FaultInjectorInterface
 *
 * Contract for all Chaos Engineering fault injectors.
 */
interface FaultInjectorInterface
{
    /**
     * Get the unique name / identifier of the fault injector.
     */
    public function getName(): string;

    /**
     * Determine whether the fault should be injected given the context and probability.
     */
    public function shouldTrigger(array $context = []): bool;

    /**
     * Inject the simulated fault.
     *
     * @return mixed Return value or outcome description
     */
    public function inject(array $context = []): mixed;

    /**
     * Rollback or clean up any side effects of the injected fault.
     */
    public function rollback(): void;

    /**
     * Check if this injector is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Enable or disable this injector.
     */
    public function setEnabled(bool $enabled): static;

    /**
     * Get injection probability (0.0 to 1.0).
     */
    public function getProbability(): float;

    /**
     * Set injection probability (0.0 to 1.0).
     */
    public function setProbability(float $probability): static;

    /**
     * Get execution statistics and metadata.
     */
    public function getStats(): array;

    /**
     * Reset execution statistics.
     */
    public function resetStats(): void;
}
