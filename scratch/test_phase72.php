<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Application;

$app = new Application(realpath(__DIR__ . '/..'));

use NetFusion\Core\Chaos\Contracts\FaultInjectorInterface;
use NetFusion\Core\Chaos\Exceptions\ChaosException;
use NetFusion\Core\Chaos\Exceptions\HttpFaultException;
use NetFusion\Core\Chaos\Exceptions\DatabaseFaultException;
use NetFusion\Core\Chaos\Exceptions\EmergencyKillSwitchActivatedException;
use NetFusion\Core\Chaos\Injectors\LatencyInjector;
use NetFusion\Core\Chaos\Injectors\HttpFaultInjector;
use NetFusion\Core\Chaos\Injectors\DatabaseFaultInjector;
use NetFusion\Core\Chaos\Injectors\ExceptionInjector;
use NetFusion\Core\Chaos\Injectors\MemoryPressureInjector;
use NetFusion\Core\Chaos\Injectors\CpuStressInjector;
use NetFusion\Core\Chaos\Guardrails\BlastRadiusPolicy;
use NetFusion\Core\Chaos\Guardrails\SteadyStateHypothesis;
use NetFusion\Core\Chaos\Guardrails\EmergencyKillSwitch;
use NetFusion\Core\Chaos\Experiment\ChaosExperiment;
use NetFusion\Core\Chaos\Experiment\ExperimentResult;
use NetFusion\Core\Chaos\ChaosOrchestrator;
use NetFusion\Core\Chaos\ChaosMonkey;
use NetFusion\Core\Chaos\Resiliency\ResiliencyScorer;
use NetFusion\Core\Chaos\ChaosManager;

$passed = 0;
$failed = 0;

function it(string $desc, callable $fn): void {
    global $passed, $failed;
    try {
        $fn();
        echo "  [PASS] {$desc}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  [FAIL] {$desc} -> " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
        $failed++;
    }
}

function expect(mixed $actual) {
    return new class($actual) {
        public function __construct(private mixed $actual) {}
        public function toBe(mixed $expected): void {
            if ($this->actual !== $expected) {
                throw new \AssertionError("Expected " . var_export($expected, true) . ", got " . var_export($this->actual, true));
            }
        }
        public function toBeTrue(): void {
            if ($this->actual !== true) {
                throw new \AssertionError("Expected true, got " . var_export($this->actual, true));
            }
        }
        public function toBeFalse(): void {
            if ($this->actual !== false) {
                throw new \AssertionError("Expected false, got " . var_export($this->actual, true));
            }
        }
        public function toBeGreaterThan(mixed $expected): void {
            if ($this->actual <= $expected) {
                throw new \AssertionError("Expected {$this->actual} to be greater than {$expected}");
            }
        }
        public function toBeGreaterThanOrEqual(mixed $expected): void {
            if ($this->actual < $expected) {
                throw new \AssertionError("Expected {$this->actual} to be >= {$expected}");
            }
        }
        public function toBeLessThanOrEqual(mixed $expected): void {
            if ($this->actual > $expected) {
                throw new \AssertionError("Expected {$this->actual} to be <= {$expected}");
            }
        }
        public function toContain(string $needle): void {
            if (!str_contains(strtolower((string) $this->actual), strtolower($needle))) {
                throw new \AssertionError("Expected string to contain '{$needle}', got '{$this->actual}'");
            }
        }
    };
}

echo "\n--- Phase 72: Chaos Engineering & Resiliency Assurance Suite ---\n\n";

// Ensure clean kill switch before testing
EmergencyKillSwitch::resetInstance();
ChaosManager::resetInstance();

// ==========================================
// 1. Latency Injector Tests
// ==========================================
it('LatencyInjector should configure base latency, jitter and simulate mode', function () {
    $injector = new LatencyInjector('test_lat', 150, 25, 1.0, true);
    expect($injector->getName())->toBe('test_lat');
    expect($injector->getLatencyMs())->toBe(150);
    expect($injector->getJitterMs())->toBe(25);
    expect($injector->isSimulateOnly())->toBeTrue();
    expect($injector->shouldTrigger())->toBeTrue();

    $result = $injector->inject();
    expect($result['injector'])->toBe('test_lat');
    expect($result['simulated'])->toBeTrue();
    expect($result['injected_latency_ms'])->toBeGreaterThanOrEqual(125);
    expect($result['injected_latency_ms'])->toBeLessThanOrEqual(175);
    expect($injector->getTotalInjectedMs())->toBe($result['injected_latency_ms']);

    $stats = $injector->getStats();
    expect($stats['invocations'])->toBe(1);
    expect($stats['injected'])->toBe(1);

    $injector->resetStats();
    expect($injector->getTotalInjectedMs())->toBe(0);
});

it('LatencyInjector probability and disable handling', function () {
    $injector = new LatencyInjector('prob_lat', 50, 0, 0.0, true);
    expect($injector->shouldTrigger())->toBeFalse();

    // Context force flag overrides probability
    expect($injector->shouldTrigger(['force' => true]))->toBeTrue();

    $injector->setEnabled(false);
    expect($injector->shouldTrigger(['force' => true]))->toBeFalse();
});

// ==========================================
// 2. HTTP Fault Injector Tests
// ==========================================
it('HttpFaultInjector throws HttpFaultException when enabled', function () {
    $injector = new HttpFaultInjector('http_fail', 503, 'Backend Overload', true, ['Retry-After' => '60']);
    $thrown = false;
    try {
        $injector->inject();
    } catch (HttpFaultException $e) {
        $thrown = true;
        expect($e->getStatusCode())->toBe(503);
        expect($e->getMessage())->toBe('Backend Overload');
        expect($e->getHeaders()['Retry-After'])->toBe('60');
        expect($e->getHeaders()['X-Chaos-Injected'])->toBe('true');
    }
    expect($thrown)->toBeTrue();
});

it('HttpFaultInjector returns structured response when throwException is false', function () {
    $injector = new HttpFaultInjector('http_soft', 502, 'Bad Gateway', false);
    $res = $injector->inject();
    expect($res['status_code'])->toBe(502);
    expect($res['message'])->toBe('Bad Gateway');
    expect($res['headers']['X-Chaos-Injected'])->toBe('true');
});

// ==========================================
// 3. Database Fault Injector Tests
// ==========================================
it('DatabaseFaultInjector simulates deadlocks, timeouts and connection refusal', function () {
    $injector = new DatabaseFaultInjector('db_fault', DatabaseFaultInjector::TYPE_DEADLOCK, 100, true);
    
    // Deadlock check
    $deadlockCaught = false;
    try {
        $injector->inject(['fault_type' => DatabaseFaultInjector::TYPE_DEADLOCK]);
    } catch (DatabaseFaultException $e) {
        $deadlockCaught = true;
        expect($e->getFaultType())->toBe('deadlock');
        expect($e->getSqlState())->toBe('40001');
    }
    expect($deadlockCaught)->toBeTrue();

    // Timeout check
    $timeoutCaught = false;
    try {
        $injector->inject(['fault_type' => DatabaseFaultInjector::TYPE_TIMEOUT]);
    } catch (DatabaseFaultException $e) {
        $timeoutCaught = true;
        expect($e->getFaultType())->toBe('timeout');
        expect($e->getSqlState())->toBe('HY000');
    }
    expect($timeoutCaught)->toBeTrue();

    // Connection refused check
    $connCaught = false;
    try {
        $injector->inject(['fault_type' => DatabaseFaultInjector::TYPE_CONNECTION_REFUSED]);
    } catch (DatabaseFaultException $e) {
        $connCaught = true;
        expect($e->getFaultType())->toBe('connection_refused');
        expect($e->getSqlState())->toBe('08006');
    }
    expect($connCaught)->toBeTrue();

    // Soft mode check
    $injector->setThrowException(false);
    $soft = $injector->inject(['fault_type' => DatabaseFaultInjector::TYPE_DEADLOCK]);
    expect($soft['fault_type'])->toBe('deadlock');
    expect($soft['sql_state'])->toBe('40001');
});

// ==========================================
// 4. Exception Injector Tests
// ==========================================
it('ExceptionInjector injects custom exception classes', function () {
    $injector = new ExceptionInjector('custom_exc', \InvalidArgumentException::class, 'Invalid parameter injected', 422, true);
    $caught = false;
    try {
        $injector->inject();
    } catch (\InvalidArgumentException $e) {
        $caught = true;
        expect($e->getMessage())->toBe('Invalid parameter injected');
        expect($e->getCode())->toBe(422);
    }
    expect($caught)->toBeTrue();

    // Soft injection mode
    $injector->setThrowException(false);
    $outcome = $injector->inject(['message' => 'Non-fatal exception']);
    expect($outcome['message'])->toBe('Non-fatal exception');
    expect($outcome['exception'] instanceof \InvalidArgumentException)->toBeTrue();
});

// ==========================================
// 5. Memory Pressure Injector Tests
// ==========================================
it('MemoryPressureInjector allocates and rolls back memory buffers', function () {
    $injector = new MemoryPressureInjector('mem_stress', 512 * 1024, 2 * 1024 * 1024);
    expect($injector->getBufferCount())->toBe(0);

    $res1 = $injector->inject();
    expect($injector->getBufferCount())->toBe(1);
    expect($injector->getAllocatedBytes())->toBe(512 * 1024);

    $res2 = $injector->inject();
    expect($injector->getBufferCount())->toBe(2);
    expect($injector->getAllocatedBytes())->toBe(1024 * 1024);

    $injector->rollback();
    expect($injector->getBufferCount())->toBe(0);
    expect($injector->getAllocatedBytes())->toBe(0);
});

// ==========================================
// 6. CPU Stress Injector Tests
// ==========================================
it('CpuStressInjector simulates CPU load safely', function () {
    $injector = new CpuStressInjector('cpu_probe', 10, 100, true);
    $res = $injector->inject();
    expect($res['simulated'])->toBeTrue();
    expect($res['target_duration_ms'])->toBe(10);

    // Live bounded test (5ms)
    $injector->setSimulateOnly(false);
    $injector->setDurationMs(5);
    $liveRes = $injector->inject();
    expect($liveRes['simulated'])->toBeFalse();
    expect($liveRes['iterations'])->toBeGreaterThan(0);
});

// ==========================================
// 7. Blast Radius Guardrails Tests
// ==========================================
it('BlastRadiusPolicy restricts environments and excludes sensitive routes', function () {
    $policy = new BlastRadiusPolicy(['development', 'staging'], ['/api/v1/*'], ['/health', '/metrics', '/auth/*']);

    // Disallowed environment
    expect($policy->isPermitted(['environment' => 'production', 'route' => '/api/v1/orders']))->toBeFalse();
    expect($policy->getLastRejectionReason())->toContain('not in allowed environments');

    // Allowed environment but excluded route
    expect($policy->isPermitted(['environment' => 'staging', 'route' => '/health']))->toBeFalse();
    expect($policy->getLastRejectionReason())->toContain('excluded by pattern');

    expect($policy->isPermitted(['environment' => 'staging', 'route' => '/auth/login']))->toBeFalse();

    // Allowed environment and matching route
    expect($policy->isPermitted(['environment' => 'staging', 'route' => '/api/v1/orders']))->toBeTrue();

    // Disallowed route (not matching allowed pattern)
    expect($policy->isPermitted(['environment' => 'staging', 'route' => '/admin/settings']))->toBeFalse();
});

it('BlastRadiusPolicy verifies mandatory canary probe header', function () {
    $policy = new BlastRadiusPolicy(['staging'], [], [], 'X-Chaos-Probe', 'enabled');

    // Missing header
    expect($policy->isPermitted(['environment' => 'staging']))->toBeFalse();
    expect($policy->getLastRejectionReason())->toContain('Missing mandatory probe header');

    // Value mismatch
    expect($policy->isPermitted(['environment' => 'staging', 'headers' => ['x-chaos-probe' => 'disabled']]))->toBeFalse();
    expect($policy->getLastRejectionReason())->toContain('probe header \'x-chaos-probe\' value mismatch');

    // Matching header
    expect($policy->isPermitted(['environment' => 'staging', 'headers' => ['X-Chaos-Probe' => 'enabled']]))->toBeTrue();
});

// ==========================================
// 8. Steady-State Hypothesis Tests
// ==========================================
it('SteadyStateHypothesis evaluates error rates, p99 latencies, and custom probes', function () {
    $hypo = new SteadyStateHypothesis('Checkout Service Health', 2.0, 300.0, 98.0);
    
    // Healthy metrics
    $healthy = $hypo->evaluate([
        'error_rate' => 0.5,
        'p99_latency_ms' => 120.0,
        'success_rate' => 99.5,
    ]);
    expect($healthy)->toBeTrue();
    expect(count($hypo->getViolations()))->toBe(0);

    // Breached error rate and p99
    $unhealthy = $hypo->evaluate([
        'error_rate' => 4.5,
        'p99_latency_ms' => 450.0,
        'success_rate' => 95.5,
    ]);
    expect($unhealthy)->toBeFalse();
    expect(count($hypo->getViolations()))->toBe(3); // error rate, p99, and success rate breaches

    // Custom probe check
    $hypo->addProbe('db_ping', function ($context, $metrics) {
        return !empty($context['db_connected']);
    });

    $probeFailed = $hypo->evaluate(['error_rate' => 0.0, 'p99_latency_ms' => 50.0, 'success_rate' => 100.0], ['db_connected' => false]);
    expect($probeFailed)->toBeFalse();
    expect($hypo->getViolations()[0])->toContain("Custom probe 'db_ping' returned false");
});

// ==========================================
// 9. Emergency Kill Switch Tests
// ==========================================
it('EmergencyKillSwitch activates, halts operations, and fires callbacks', function () {
    $killSwitch = new EmergencyKillSwitch();
    $killSwitch->deactivate();
    expect($killSwitch->isActive())->toBeFalse();

    $callbackFired = false;
    $killSwitch->onActivate(function ($details) use (&$callbackFired) {
        $callbackFired = true;
    });

    $killSwitch->activate('Critical Latency Spike in Payment Gateway', 'DrillMaster');
    expect($killSwitch->isActive())->toBeTrue();
    expect($callbackFired)->toBeTrue();
    expect($killSwitch->getActivationDetails()['reason'])->toBe('Critical Latency Spike in Payment Gateway');

    // assertSafe throws
    $thrown = false;
    try {
        $killSwitch->assertSafe();
    } catch (EmergencyKillSwitchActivatedException $e) {
        $thrown = true;
        expect($e->getMessage())->toContain('Critical Latency Spike in Payment Gateway');
    }
    expect($thrown)->toBeTrue();

    // Deactivation resets safe state
    $killSwitch->deactivate();
    expect($killSwitch->isActive())->toBeFalse();
});

// ==========================================
// 10. Chaos Experiment & Orchestrator Tests
// ==========================================
it('ChaosOrchestrator runs a successful GameDay drill with automatic rollback', function () {
    EmergencyKillSwitch::resetInstance();
    $orchestrator = new ChaosOrchestrator();

    $rolledBack = false;
    $injector = new LatencyInjector('gameday_latency', 10, 0, 1.0, true);

    $experiment = new ChaosExperiment('Database Latency GameDay Drill', 'Validates caching under DB latency');
    $experiment->addInjector($injector);
    $experiment->getPolicy()->setAllowedEnvironments(['staging']);
    $experiment->setRollbackAction(function () use (&$rolledBack) {
        $rolledBack = true;
    });
    $experiment->setAction(function () {
        return ['status' => 'OK', 'cached_hit' => true];
    });

    $context = [
        'environment' => 'staging',
        'pre_metrics' => ['error_rate' => 0.0, 'p99_latency_ms' => 20.0, 'success_rate' => 100.0],
        'post_metrics' => ['error_rate' => 0.0, 'p99_latency_ms' => 25.0, 'success_rate' => 100.0],
    ];

    $result = $orchestrator->run($experiment, $context);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getStatus())->toBe(ExperimentResult::STATUS_SUCCESS);
    expect($result->isSteadyStatePre())->toBeTrue();
    expect($result->isSteadyStatePost())->toBeTrue();
    expect($result->getResiliencyScore())->toBeGreaterThanOrEqual(90.0);
    expect($rolledBack)->toBeTrue();
    expect(count($result->getInjectedFaults()))->toBe(1);
});

it('ChaosOrchestrator halts when blast radius is violated or kill switch engaged', function () {
    $orchestrator = new ChaosOrchestrator();
    $experiment = new ChaosExperiment('Unsafe Production Drill');
    $experiment->getPolicy()->setAllowedEnvironments(['staging']);

    // Blast radius rejection (running in prod)
    $res = $orchestrator->run($experiment, ['environment' => 'production']);
    expect($res->getStatus())->toBe(ExperimentResult::STATUS_BLAST_RADIUS_VIOLATION);
    expect($res->getResiliencyScore())->toBe(0.0);

    // Emergency kill switch rejection
    $experiment->getPolicy()->setAllowedEnvironments(['staging']);
    $orchestrator->getKillSwitch()->activate('Manual Stop');

    $res2 = $orchestrator->run($experiment, ['environment' => 'staging']);
    expect($res2->getStatus())->toBe(ExperimentResult::STATUS_ABORTED);
    expect($res2->getAbortReason())->toContain('Manual Stop');

    $orchestrator->getKillSwitch()->deactivate();
});

// ==========================================
// 11. Resiliency Scorer & Telemetry Tests
// ==========================================
it('ResiliencyScorer assigns appropriate scores, grades, and recommendations', function () {
    $scorer = new ResiliencyScorer();
    $result = new ExperimentResult('exp_test', 'Resiliency Benchmark');
    $result->setSteadyStatePre(true);
    $result->setSteadyStatePost(true);
    $result->complete();

    // Perfect run
    $score = $scorer->calculateScore($result);
    expect($score)->toBeGreaterThanOrEqual(95.0);
    expect($scorer->getGrade($score))->toBe('A+');
    $recs = $scorer->generateRecommendations($result);
    expect($recs[0])->toContain('optimal');

    // Run with unhandled exception
    $unhandledEx = new \RuntimeException("Database connection timed out");
    $scoreFail = $scorer->calculateScore($result, $unhandledEx, ['expect_exception' => false]);
    expect($scoreFail)->toBeLessThanOrEqual(85.0);

    // Handled exception with circuit breaker
    $scoreHandled = $scorer->calculateScore($result, $unhandledEx, ['circuit_breaker_opened' => true]);
    expect($scoreHandled)->toBeGreaterThan($scoreFail);
});

// ==========================================
// 12. Chaos Monkey Tests
// ==========================================
it('ChaosMonkey enforces maintenance window and attacks successfully when forced', function () {
    $monkey = new ChaosMonkey(null, true);
    $monkey->setWindow(9, 17, [1, 2, 3, 4, 5]);

    // Test window check
    expect($monkey->isWithinWindow(1, 10))->toBeTrue();  // Monday 10:00 AM -> true
    expect($monkey->isWithinWindow(6, 10))->toBeFalse(); // Saturday 10:00 AM -> false
    expect($monkey->isWithinWindow(1, 20))->toBeFalse(); // Monday 08:00 PM -> false

    // Autonomous attack
    $result = $monkey->attack([
        'force_window' => true,
        'environment' => 'staging',
        'pre_metrics' => ['error_rate' => 0.0, 'p99_latency_ms' => 30.0, 'success_rate' => 100.0],
        'post_metrics' => ['error_rate' => 0.0, 'p99_latency_ms' => 35.0, 'success_rate' => 100.0],
    ]);

    expect($result instanceof ExperimentResult)->toBeTrue();
    expect($result->isSuccess())->toBeTrue();
    expect(count($monkey->getAttackHistory()))->toBe(1);
});

// ==========================================
// 13. ChaosManager Facade & Global Helpers Tests
// ==========================================
it('ChaosManager facade registers injectors, runs drills, and generates reports', function () {
    EmergencyKillSwitch::resetInstance();
    ChaosManager::resetInstance();

    $chaos = chaos();
    expect($chaos instanceof ChaosManager)->toBeTrue();
    expect($chaos->hasInjector('latency'))->toBeTrue();
    expect($chaos->hasInjector('http_500'))->toBeTrue();
    expect($chaos->hasInjector('db_deadlock'))->toBeTrue();

    // Helper inject_fault
    $latRes = inject_fault('latency', ['simulate_only' => true]);
    expect(is_array($latRes))->toBeTrue();
    expect($latRes['injector'])->toBe('latency');

    // Helper chaos_experiment
    $exp = chaos_experiment('Helper Drill');
    $exp->getPolicy()->setAllowedEnvironments(['staging']);
    $chaos->registerExperiment($exp);

    expect($chaos->getExperiment('Helper Drill') !== null)->toBeTrue();

    // Run experiment via facade
    $runRes = $chaos->run('Helper Drill', [
        'environment' => 'staging',
        'pre_metrics' => ['error_rate' => 0.0, 'p99_latency_ms' => 20.0, 'success_rate' => 100.0],
        'post_metrics' => ['error_rate' => 0.0, 'p99_latency_ms' => 20.0, 'success_rate' => 100.0],
    ]);
    expect($runRes->isSuccess())->toBeTrue();

    // Audit report
    $report = $chaos->generateReport();
    expect($report['total_experiments_run'])->toBe(1);
    expect($report['successful'])->toBe(1);
    expect($report['average_resiliency_score'])->toBeGreaterThanOrEqual(90.0);

    // Helper chaos_kill_switch
    $ks = chaos_kill_switch();
    expect($ks instanceof EmergencyKillSwitch)->toBeTrue();
    expect($ks->isActive())->toBeFalse();

    $chaos->abort('Test audit halt');
    expect($ks->isActive())->toBeTrue();
    $chaos->resume();
    expect($ks->isActive())->toBeFalse();
});

// ==========================================
// 14. DatabaseFaultInjector Slow Query Tests
// ==========================================
it('DatabaseFaultInjector handles slow query simulation', function () {
    $dbFault = new DatabaseFaultInjector('db_slow', DatabaseFaultInjector::TYPE_SLOW_QUERY, 100);
    $res = $dbFault->inject(['simulate_only' => true]);
    expect($res['fault_type'])->toBe('slow_query');
    expect($res['slow_query_ms'])->toBe(100);
});

// ==========================================
// 15. Memory & CPU Cap Boundary Tests
// ==========================================
it('Memory and CPU injectors enforce safety caps', function () {
    $mem = new MemoryPressureInjector('mem_cap', 1024, 2048);
    $mem->setSizeBytes(100000); // Exceeds cap
    expect($mem->getSizeBytes())->toBe(2048);

    $cpu = new CpuStressInjector('cpu_cap', 50, 200);
    $cpu->setDurationMs(99999); // Exceeds cap
    expect($cpu->getDurationMs())->toBe(200);
});

// ==========================================
// 16. SteadyStateHypothesis Exception Handling
// ==========================================
it('SteadyStateHypothesis catches custom probe exceptions safely', function () {
    $hypo = new SteadyStateHypothesis('Fault Tolerant Hypothesis');
    $hypo->addProbe('exploding_probe', function () {
        throw new \RuntimeException("Probe connection crashed");
    });
    $valid = $hypo->evaluate();
    expect($valid)->toBeFalse();
    expect($hypo->getViolations()[0])->toContain("Custom probe 'exploding_probe' threw exception");
});

// ==========================================
// 17. ResiliencyScorer Letter Grades
// ==========================================
it('ResiliencyScorer maps numerical ranges to letter grades accurately', function () {
    $scorer = new ResiliencyScorer();
    expect($scorer->getGrade(98.0))->toBe('A+');
    expect($scorer->getGrade(92.0))->toBe('A');
    expect($scorer->getGrade(85.0))->toBe('B');
    expect($scorer->getGrade(75.0))->toBe('C');
    expect($scorer->getGrade(65.0))->toBe('D');
    expect($scorer->getGrade(40.0))->toBe('F');
});

// ==========================================
// 18. Facade Exception Guards
// ==========================================
it('ChaosManager guards against unregistered injectors and experiments', function () {
    $chaos = chaos();
    $caughtInjector = false;
    try {
        $chaos->inject('non_existent_injector');
    } catch (\InvalidArgumentException $e) {
        $caughtInjector = true;
    }
    expect($caughtInjector)->toBeTrue();

    $caughtExp = false;
    try {
        $chaos->run('non_existent_experiment');
    } catch (\InvalidArgumentException $e) {
        $caughtExp = true;
    }
    expect($caughtExp)->toBeTrue();
});

// ==========================================
// 19. CLI Commands Execution Tests
// ==========================================
it('Chaos CLI Commands execute with zero errors', function () {
    require_once __DIR__ . '/../core/Console/Command.php';
    require_once __DIR__ . '/../core/Console/Commands/ChaosRunCommand.php';
    require_once __DIR__ . '/../core/Console/Commands/ChaosAbortCommand.php';
    require_once __DIR__ . '/../core/Console/Commands/ChaosReportCommand.php';
    require_once __DIR__ . '/../core/Console/Commands/ChaosMonkeyCommand.php';

    global $app;
    // 1. chaos:run in json mode
    $runCmd = new \NetFusion\Core\Console\Commands\ChaosRunCommand($app);
    ob_start();
    $runCode = $runCmd->handle(['--format=json']);
    $runOut = ob_get_clean();
    expect($runCode)->toBe(0);
    $runData = json_decode($runOut, true);
    expect(is_array($runData))->toBeTrue();
    expect($runData['status'])->toBe('SUCCESS');

    // 2. chaos:abort activate
    $abortCmd = new \NetFusion\Core\Console\Commands\ChaosAbortCommand($app);
    ob_start();
    $abortCode = $abortCmd->handle(['--reason=GameDay Drill End']);
    $abortOut = ob_get_clean();
    expect($abortCode)->toBe(0);
    expect(chaos_kill_switch()->isActive())->toBeTrue();

    // 3. chaos:abort resume
    ob_start();
    $resumeCode = $abortCmd->handle(['--resume']);
    $resumeOut = ob_get_clean();
    expect($resumeCode)->toBe(0);
    expect(chaos_kill_switch()->isActive())->toBeFalse();

    // 4. chaos:report in json mode
    $repCmd = new \NetFusion\Core\Console\Commands\ChaosReportCommand($app);
    ob_start();
    $repCode = $repCmd->handle(['--format=json']);
    $repOut = ob_get_clean();
    expect($repCode)->toBe(0);
    $repData = json_decode($repOut, true);
    expect(is_array($repData))->toBeTrue();
    expect($repData['total_experiments_run'])->toBeGreaterThanOrEqual(1);

    // 5. chaos:monkey with --force and json format
    $monkeyCmd = new \NetFusion\Core\Console\Commands\ChaosMonkeyCommand($app);
    ob_start();
    $monkeyCode = $monkeyCmd->handle(['--force', '--format=json']);
    $monkeyOut = ob_get_clean();
    expect($monkeyCode)->toBe(0);
    $monkeyData = json_decode($monkeyOut, true);
    expect(is_array($monkeyData))->toBeTrue();
    expect($monkeyData['status'])->toBe('SUCCESS');
});

// ==========================================
// 21. Injector Edge Cases & Fallbacks
// ==========================================
it('ExceptionInjector falls back to RuntimeException if invalid class provided', function () {
    $injector = new ExceptionInjector('fallback_exc', 'NonExistentFakeClass', 'Fallback error', 500, false);
    $res = $injector->inject();
    expect($res['exception_class'])->toBe(\RuntimeException::class);
});

it('DatabaseFaultInjector falls back to connection refused if unknown fault type', function () {
    $injector = new DatabaseFaultInjector('db_unknown', 'unknown_type', 0, false);
    $res = $injector->inject();
    expect($res['fault_type'])->toBe(DatabaseFaultInjector::TYPE_CONNECTION_REFUSED);
    expect($res['sql_state'])->toBe('08006');
});

// ==========================================
// 22. Blast Radius Percentage & Wildcard Routes
// ==========================================
it('BlastRadiusPolicy respects 0% affected traffic threshold and deep wildcard routes', function () {
    $policy = new BlastRadiusPolicy(['staging'], ['/api/*/users/*'], [], null, null, 0.0);
    $blocked = $policy->isPermitted(['environment' => 'staging', 'route' => '/api/v2/users/123/profile']);
    expect($blocked)->toBeFalse();
    expect($policy->getLastRejectionReason())->toContain('exceeded max allowed blast radius');

    $policy->setMaxAffectedPercent(100.0);
    $allowed = $policy->isPermitted(['environment' => 'staging', 'route' => '/api/v2/users/123/profile']);
    expect($allowed)->toBeTrue();
});

// ==========================================
// 23. Pre-Flight Steady State Failure Aborts Drill
// ==========================================
it('ChaosOrchestrator immediately halts drill if pre-flight steady state fails', function () {
    $orchestrator = new ChaosOrchestrator();
    $experiment = new ChaosExperiment('Flaky System Drill');
    $experiment->getPolicy()->setAllowedEnvironments(['staging']);

    $injected = false;
    $injector = new LatencyInjector('probe', 10, 0, 1.0, true);
    $experiment->addInjector($injector);

    // Feed bad pre-metrics
    $res = $orchestrator->run($experiment, [
        'environment' => 'staging',
        'pre_metrics' => ['error_rate' => 12.0, 'p99_latency_ms' => 2500.0, 'success_rate' => 88.0],
    ]);

    expect($res->getStatus())->toBe(ExperimentResult::STATUS_ABORTED);
    expect($res->isSteadyStatePre())->toBeFalse();
    expect(count($res->getInjectedFaults()))->toBe(0);
});

// ==========================================
// 24. Resiliency Score Penalties on Violations
// ==========================================
it('ResiliencyScorer heavily penalizes blast radius violations and aborts', function () {
    $scorer = new ResiliencyScorer();

    $resViolation = new ExperimentResult('exp_viol', 'Violation');
    $resViolation->setStatus(ExperimentResult::STATUS_BLAST_RADIUS_VIOLATION);
    $score1 = $scorer->calculateScore($resViolation);
    expect($score1)->toBe(10.0);
    expect($scorer->getGrade($score1))->toBe('F');

    $resAborted = new ExperimentResult('exp_aborted', 'Aborted');
    $resAborted->setStatus(ExperimentResult::STATUS_ABORTED);
    $score2 = $scorer->calculateScore($resAborted);
    expect($score2)->toBe(20.0);
    expect($scorer->getGrade($score2))->toBe('F');
});

// ==========================================
// 25. ChaosMonkey Disabled & Candidate Registry
// ==========================================
it('ChaosMonkey returns null when disabled or outside window', function () {
    $monkey = new ChaosMonkey(null, false);
    $res = $monkey->attack(['force_window' => true]);
    expect($res)->toBe(null);

    $monkey->setEnabled(true);
    $monkey->setWindow(1, 2, [1]); // 01:00 to 02:00 on Monday
    // Test without force_window when current time is outside
    $outsideRes = $monkey->attack(['force_window' => false]);
    // If not currently Monday 1-2am, returns null
    if (!$monkey->isWithinWindow()) {
        expect($outsideRes)->toBe(null);
    }
});

echo "\n--- Phase 72 Test Summary ---\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
