<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos;

use NetFusion\Core\Chaos\Contracts\FaultInjectorInterface;
use NetFusion\Core\Chaos\Experiment\ChaosExperiment;
use NetFusion\Core\Chaos\Experiment\ExperimentResult;
use NetFusion\Core\Chaos\Guardrails\EmergencyKillSwitch;
use NetFusion\Core\Chaos\Injectors\LatencyInjector;
use NetFusion\Core\Chaos\Injectors\HttpFaultInjector;
use NetFusion\Core\Chaos\Injectors\DatabaseFaultInjector;
use NetFusion\Core\Chaos\Injectors\ExceptionInjector;
use NetFusion\Core\Chaos\Injectors\MemoryPressureInjector;
use NetFusion\Core\Chaos\Injectors\CpuStressInjector;
use NetFusion\Core\Chaos\Resiliency\ResiliencyScorer;

/**
 * Class ChaosManager
 *
 * Central facade for Chaos Engineering, Fault Injection, GameDay simulations, and Resiliency Auditing.
 */
class ChaosManager
{
    protected static ?self $instance = null;
    protected array $config = [];
    protected ChaosOrchestrator $orchestrator;
    protected ChaosMonkey $monkey;
    protected EmergencyKillSwitch $killSwitch;
    protected ResiliencyScorer $scorer;
    /** @var array<string, FaultInjectorInterface> */
    protected array $injectors = [];
    /** @var array<string, ChaosExperiment> */
    protected array $experiments = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->killSwitch = EmergencyKillSwitch::getInstance($config['kill_switch_path'] ?? null);
        $this->scorer = new ResiliencyScorer();
        $this->orchestrator = new ChaosOrchestrator($this->killSwitch, $this->scorer);
        $this->monkey = new ChaosMonkey($this->orchestrator, (bool) ($config['monkey']['enabled'] ?? true));

        $this->registerDefaultInjectors();
    }

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        EmergencyKillSwitch::resetInstance();
        self::$instance = null;
    }

    protected function registerDefaultInjectors(): void
    {
        $this->registerInjector(new LatencyInjector('latency', 100, 20, 1.0, true));
        $this->registerInjector(new HttpFaultInjector('http_500', 500, 'Chaos Internal Server Error', false));
        $this->registerInjector(new HttpFaultInjector('http_503', 503, 'Chaos Service Unavailable', false));
        $this->registerInjector(new DatabaseFaultInjector('db_deadlock', DatabaseFaultInjector::TYPE_DEADLOCK, 250, false));
        $this->registerInjector(new DatabaseFaultInjector('db_timeout', DatabaseFaultInjector::TYPE_TIMEOUT, 250, false));
        $this->registerInjector(new ExceptionInjector('exception', \RuntimeException::class, 'Injected Chaos Exception', 500, false));
        $this->registerInjector(new MemoryPressureInjector('memory_pressure', 1024 * 1024, 16 * 1024 * 1024));
        $this->registerInjector(new CpuStressInjector('cpu_stress', 20, 500, true));
    }

    public function registerInjector(FaultInjectorInterface $injector): static
    {
        $this->injectors[$injector->getName()] = $injector;
        $this->monkey->registerInjector($injector);
        return $this;
    }

    public function getInjector(string $name): ?FaultInjectorInterface
    {
        return $this->injectors[$name] ?? null;
    }

    public function hasInjector(string $name): bool
    {
        return isset($this->injectors[$name]);
    }

    /**
     * @return array<string, FaultInjectorInterface>
     */
    public function getInjectors(): array
    {
        return $this->injectors;
    }

    public function registerExperiment(ChaosExperiment $experiment): static
    {
        $this->experiments[$experiment->getName()] = $experiment;
        $this->monkey->registerExperiment($experiment);
        return $this;
    }

    public function getExperiment(string $name): ?ChaosExperiment
    {
        return $this->experiments[$name] ?? null;
    }

    /**
     * @return array<string, ChaosExperiment>
     */
    public function getExperiments(): array
    {
        return $this->experiments;
    }

    public function getOrchestrator(): ChaosOrchestrator
    {
        return $this->orchestrator;
    }

    public function getMonkey(): ChaosMonkey
    {
        return $this->monkey;
    }

    public function getKillSwitch(): EmergencyKillSwitch
    {
        return $this->killSwitch;
    }

    public function getScorer(): ResiliencyScorer
    {
        return $this->scorer;
    }

    /**
     * Trigger a single fault injection directly.
     */
    public function inject(string $injectorName, array $context = []): mixed
    {
        $this->killSwitch->assertSafe();

        $injector = $this->getInjector($injectorName);
        if ($injector === null) {
            throw new \InvalidArgumentException("Fault injector '{$injectorName}' is not registered.");
        }

        if ($injector->shouldTrigger($context)) {
            return $injector->inject($context);
        }

        return null;
    }

    /**
     * Run an experiment by name or instance.
     */
    public function run(string|ChaosExperiment $experiment, array $context = []): ExperimentResult
    {
        if (is_string($experiment)) {
            $expInstance = $this->getExperiment($experiment);
            if ($expInstance === null) {
                throw new \InvalidArgumentException("Chaos experiment '{$experiment}' not found.");
            }
            $experiment = $expInstance;
        }

        return $this->orchestrator->run($experiment, $context);
    }

    /**
     * Trigger emergency abort across all active chaos tests.
     */
    public function abort(string $reason = 'Manual Chaos Abort', ?string $initiatedBy = 'operator'): void
    {
        $this->killSwitch->activate($reason, $initiatedBy);
        // Rollback any registered injectors
        foreach ($this->injectors as $injector) {
            $injector->rollback();
        }
    }

    /**
     * Reset / deactivate emergency kill switch.
     */
    public function resume(): void
    {
        $this->killSwitch->deactivate();
    }

    /**
     * Generate comprehensive resiliency summary report.
     */
    public function generateReport(): array
    {
        $history = $this->orchestrator->getExecutionHistory();
        $total = count($history);
        $successful = 0;
        $failed = 0;
        $aborted = 0;
        $scores = [];

        foreach ($history as $result) {
            $scores[] = $result->getResiliencyScore();
            match ($result->getStatus()) {
                ExperimentResult::STATUS_SUCCESS => $successful++,
                ExperimentResult::STATUS_FAILED => $failed++,
                default => $aborted++,
            };
        }

        $avgScore = !empty($scores) ? round(array_sum($scores) / count($scores), 2) : 100.0;

        return [
            'kill_switch_active' => $this->killSwitch->isActive(),
            'total_experiments_run' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'aborted' => $aborted,
            'average_resiliency_score' => $avgScore,
            'resiliency_grade' => $this->scorer->getGrade($avgScore),
            'registered_injectors' => array_keys($this->injectors),
            'registered_experiments' => array_keys($this->experiments),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
}
