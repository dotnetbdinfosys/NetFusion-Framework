<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos;

use NetFusion\Core\Chaos\Experiment\ChaosExperiment;
use NetFusion\Core\Chaos\Experiment\ExperimentResult;
use NetFusion\Core\Chaos\Contracts\FaultInjectorInterface;
use NetFusion\Core\Chaos\Injectors\LatencyInjector;
use NetFusion\Core\Chaos\Injectors\HttpFaultInjector;
use NetFusion\Core\Chaos\Injectors\ExceptionInjector;

/**
 * Class ChaosMonkey
 *
 * Autonomous random fault injector that schedules and executes randomized chaos during maintenance windows.
 */
class ChaosMonkey
{
    protected ChaosOrchestrator $orchestrator;
    protected bool $enabled = true;
    protected array $allowedDays = [1, 2, 3, 4, 5]; // Mon to Fri
    protected int $startHour = 9;   // 09:00
    protected int $endHour = 17;    // 17:00
    /** @var array<int, ChaosExperiment> */
    protected array $candidateExperiments = [];
    /** @var array<int, FaultInjectorInterface> */
    protected array $candidateInjectors = [];
    protected array $attackHistory = [];

    public function __construct(?ChaosOrchestrator $orchestrator = null, bool $enabled = true)
    {
        $this->orchestrator = $orchestrator ?? new ChaosOrchestrator();
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function setWindow(int $startHour, int $endHour, array $allowedDays = [1, 2, 3, 4, 5]): static
    {
        $this->startHour = $startHour;
        $this->endHour = $endHour;
        $this->allowedDays = $allowedDays;
        return $this;
    }

    public function getWindow(): array
    {
        return [
            'start_hour' => $this->startHour,
            'end_hour' => $this->endHour,
            'allowed_days' => $this->allowedDays,
        ];
    }

    public function isWithinWindow(?int $dayOfWeek = null, ?int $hour = null): bool
    {
        $currentDay = $dayOfWeek ?? (int) date('N');
        $currentHour = $hour ?? (int) date('G');

        if (!in_array($currentDay, $this->allowedDays, true)) {
            return false;
        }

        return $currentHour >= $this->startHour && $currentHour < $this->endHour;
    }

    public function registerExperiment(ChaosExperiment $experiment): static
    {
        $this->candidateExperiments[] = $experiment;
        return $this;
    }

    public function registerInjector(FaultInjectorInterface $injector): static
    {
        $this->candidateInjectors[] = $injector;
        return $this;
    }

    /**
     * Launch an autonomous attack.
     */
    public function attack(array $context = []): ?ExperimentResult
    {
        if (!$this->enabled) {
            return null;
        }

        $forceWindow = (bool) ($context['force_window'] ?? false);
        if (!$forceWindow && !$this->isWithinWindow()) {
            return null;
        }

        // Choose experiment or generate random one
        $experiment = $this->selectExperiment();
        if ($experiment === null) {
            return null;
        }

        $result = $this->orchestrator->run($experiment, $context);
        $this->attackHistory[] = [
            'experiment_id' => $experiment->getId(),
            'status' => $result->getStatus(),
            'score' => $result->getResiliencyScore(),
            'timestamp' => microtime(true),
        ];

        return $result;
    }

    protected function selectExperiment(): ?ChaosExperiment
    {
        if (!empty($this->candidateExperiments)) {
            $idx = array_rand($this->candidateExperiments);
            return $this->candidateExperiments[$idx];
        }

        // Synthesize dynamic experiment with registered or default injectors
        $exp = new ChaosExperiment('Autonomous Monkey Strike #' . uniqid(), 'Randomized chaos monkey injection');
        $exp->getPolicy()->setAllowedEnvironments(['development', 'testing', 'staging', 'production']);

        if (!empty($this->candidateInjectors)) {
            $idx = array_rand($this->candidateInjectors);
            $exp->addInjector($this->candidateInjectors[$idx]);
        } else {
            // Default random pool
            $pool = [
                new LatencyInjector('monkey_latency', 200, 50, 1.0, true),
                new HttpFaultInjector('monkey_http', 503, 'Monkey Service Outage', false, [], 1.0),
                new ExceptionInjector('monkey_exception', \RuntimeException::class, 'Monkey strike exception', 500, false, 1.0),
            ];
            $idx = array_rand($pool);
            $exp->addInjector($pool[$idx]);
        }

        return $exp;
    }

    public function getAttackHistory(): array
    {
        return $this->attackHistory;
    }
}
