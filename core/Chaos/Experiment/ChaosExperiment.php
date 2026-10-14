<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Experiment;

use NetFusion\Core\Chaos\Contracts\FaultInjectorInterface;
use NetFusion\Core\Chaos\Guardrails\BlastRadiusPolicy;
use NetFusion\Core\Chaos\Guardrails\SteadyStateHypothesis;

/**
 * Class ChaosExperiment
 *
 * Declarative definition of a chaos engineering experiment.
 */
class ChaosExperiment
{
    protected string $id;
    protected string $name;
    protected string $description;
    /** @var array<string, FaultInjectorInterface> */
    protected array $injectors = [];
    protected ?SteadyStateHypothesis $hypothesis = null;
    protected ?BlastRadiusPolicy $policy = null;
    /** @var callable|null */
    protected $action = null;
    /** @var callable|null */
    protected $rollbackAction = null;
    protected int $durationSeconds = 60;
    protected array $tags = [];
    protected array $metadata = [];

    public function __construct(string $name, string $description = '', ?string $id = null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->id = $id ?? ('exp_' . bin2hex(random_bytes(8)));
        $this->hypothesis = new SteadyStateHypothesis("Hypothesis for {$name}");
        $this->policy = new BlastRadiusPolicy();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function addInjector(FaultInjectorInterface $injector): static
    {
        $this->injectors[$injector->getName()] = $injector;
        return $this;
    }

    /**
     * @return array<string, FaultInjectorInterface>
     */
    public function getInjectors(): array
    {
        return $this->injectors;
    }

    public function getInjector(string $name): ?FaultInjectorInterface
    {
        return $this->injectors[$name] ?? null;
    }

    public function getHypothesis(): SteadyStateHypothesis
    {
        if ($this->hypothesis === null) {
            $this->hypothesis = new SteadyStateHypothesis();
        }
        return $this->hypothesis;
    }

    public function setHypothesis(SteadyStateHypothesis $hypothesis): static
    {
        $this->hypothesis = $hypothesis;
        return $this;
    }

    public function getPolicy(): BlastRadiusPolicy
    {
        if ($this->policy === null) {
            $this->policy = new BlastRadiusPolicy();
        }
        return $this->policy;
    }

    public function setPolicy(BlastRadiusPolicy $policy): static
    {
        $this->policy = $policy;
        return $this;
    }

    public function setAction(callable $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getAction(): ?callable
    {
        return $this->action;
    }

    public function setRollbackAction(callable $rollbackAction): static
    {
        $this->rollbackAction = $rollbackAction;
        return $this;
    }

    public function getRollbackAction(): ?callable
    {
        return $this->rollbackAction;
    }

    public function getDurationSeconds(): int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(int $seconds): static
    {
        $this->durationSeconds = max(1, $seconds);
        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }

    public function addTag(string $tag): static
    {
        if (!in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }
}
