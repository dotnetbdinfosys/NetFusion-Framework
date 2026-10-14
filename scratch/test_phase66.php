<?php

/**
 * Phase 66 Test Suite: Enterprise Distributed Task Scheduler, Quartz Cron Engine, DAG Dependency Execution & Fault-Tolerant Distributed Job Dispatcher
 */

declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../core/Support/helpers.php';

spl_autoload_register(function ($class) {
    $prefix = 'NetFusion\\Core\\';
    $baseDir = __DIR__ . '/../core/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use NetFusion\Core\Scheduling\Contracts\TaskInterface;
use NetFusion\Core\Scheduling\Contracts\CronParserInterface;
use NetFusion\Core\Scheduling\Contracts\ScheduleStoreInterface;
use NetFusion\Core\Scheduling\Contracts\DAGRunnerInterface;
use NetFusion\Core\Scheduling\Models\MisfirePolicy;
use NetFusion\Core\Scheduling\Models\RetryPolicy;
use NetFusion\Core\Scheduling\Models\TaskExecution;
use NetFusion\Core\Scheduling\Models\ScheduledTask;
use NetFusion\Core\Scheduling\Models\DAGNode;
use NetFusion\Core\Scheduling\Models\DAGGraph;
use NetFusion\Core\Scheduling\Cron\QuartzExpression;
use NetFusion\Core\Scheduling\DAG\TopologicalSorter;
use NetFusion\Core\Scheduling\DAG\DAGBuilder;
use NetFusion\Core\Scheduling\DAG\DAGExecutor;
use NetFusion\Core\Scheduling\Cluster\LeaderElection;
use NetFusion\Core\Scheduling\Cluster\MisfireHandler;
use NetFusion\Core\Scheduling\Dispatcher\TaskRunner;
use NetFusion\Core\Scheduling\Storage\DatabaseScheduleStore;
use NetFusion\Core\Scheduling\SchedulerManager;
use NetFusion\Core\Scheduling\Exceptions\CycleDetectedException;
use NetFusion\Core\Scheduling\Exceptions\CronParseException;
use NetFusion\Core\Database\Connection;

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, ?string $detail = null): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}" . PHP_EOL;
    } else {
        $failed++;
        echo "  [FAIL] {$name}" . ($detail ? " - {$detail}" : "") . PHP_EOL;
    }
}

echo "=== Starting Phase 66 Test Suite ===" . PHP_EOL;

// 1. Models & Retry Policy
echo PHP_EOL . "--- 1. Models & Retry Policy ---" . PHP_EOL;
$retry = new RetryPolicy(3, 2, true, 2.0);
assertTest("RetryPolicy maxAttempts", $retry->getMaxAttempts() === 3);
assertTest("RetryPolicy delay attempt 1", $retry->getDelayForAttempt(1) === 2);
assertTest("RetryPolicy delay attempt 2 exponential", $retry->getDelayForAttempt(2) === 4);
assertTest("RetryPolicy delay attempt 3 exponential", $retry->getDelayForAttempt(3) === 8);

$execSuccess = TaskExecution::success('t1', 'Task 1', 'done output', 0.12, 1);
assertTest("TaskExecution success status", $execSuccess->isSuccess() && $execSuccess->getStatus() === TaskExecution::STATUS_SUCCESS);
assertTest("TaskExecution output", $execSuccess->getOutput() === 'done output');

$execFail = TaskExecution::failure('t2', 'Task 2', 'connection error', 0.45, 3);
assertTest("TaskExecution failure status", $execFail->isFailure() && $execFail->getStatus() === TaskExecution::STATUS_FAILED);
assertTest("TaskExecution error message", $execFail->getError() === 'connection error');

// 2. Quartz & Extended Cron Expression Engine
echo PHP_EOL . "--- 2. Quartz & Extended Cron Engine ---" . PHP_EOL;
$cron = new QuartzExpression();

// Due checks
$now = new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC'));
assertTest("Cron isDue match standard 5-part", $cron->isDue('0 12 * * *', $now, 'UTC'));
assertTest("Cron isDue mismatch hour", !$cron->isDue('0 13 * * *', $now, 'UTC'));
assertTest("Cron isDue every minute", $cron->isDue('* * * * *', $now, 'UTC'));
assertTest("Cron isDue macro @daily", $cron->isDue('@daily', new \DateTimeImmutable('2026-09-25 00:00:00', new \DateTimeZone('UTC')), 'UTC'));
assertTest("Cron isDue step interval */5", $cron->isDue('*/5 * * * *', new \DateTimeImmutable('2026-09-25 12:15:00', new \DateTimeZone('UTC')), 'UTC'));
assertTest("Cron isDue step interval non-match", !$cron->isDue('*/5 * * * *', new \DateTimeImmutable('2026-09-25 12:16:00', new \DateTimeZone('UTC')), 'UTC'));

// 6-part Quartz with seconds
$secondTime = new \DateTimeImmutable('2026-09-25 12:00:30', new \DateTimeZone('UTC'));
assertTest("Quartz 6-part second match", $cron->isDue('30 0 12 * * *', $secondTime, 'UTC'));
assertTest("Quartz 6-part second mismatch", !$cron->isDue('15 0 12 * * *', $secondTime, 'UTC'));

// Day of week & month names
assertTest("Cron day names MON-FRI", $cron->isDue('0 12 * * MON-FRI', new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC')), 'UTC')); // 2026-09-25 is Friday
assertTest("Cron month names SEP", $cron->isDue('0 12 * SEP *', new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC')), 'UTC'));

// Next run calculations
$baseDate = new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC'));
$nextRun = $cron->getNextRunDate('0 13 * * *', $baseDate, 'UTC');
assertTest("Next run calculation (+1 hour)", $nextRun->format('Y-m-d H:i:s') === '2026-09-25 13:00:00');

$nextDates = $cron->getNextRunDates('0 0 * * *', 3, $baseDate, 'UTC');
assertTest("Next run dates count", count($nextDates) === 3);
assertTest("Next run date #1", $nextDates[0]->format('Y-m-d H:i:s') === '2026-09-26 00:00:00');
assertTest("Next run date #2", $nextDates[1]->format('Y-m-d H:i:s') === '2026-09-27 00:00:00');
assertTest("Next run date #3", $nextDates[2]->format('Y-m-d H:i:s') === '2026-09-28 00:00:00');

// 3. ScheduledTask
echo PHP_EOL . "--- 3. ScheduledTask ---" . PHP_EOL;
$task = new ScheduledTask('test_closure', function () {
    return "Hello Scheduled Task";
});
$task->dailyAt('14:30')->timezone('America/New_York')->retry(2, 0);

assertTest("ScheduledTask name", $task->getName() === 'test_closure');
assertTest("ScheduledTask expression", $task->getExpression() === '30 14 * * *');
assertTest("ScheduledTask timezone", $task->getTimezone() === 'America/New_York');
assertTest("ScheduledTask retry policy", $task->getRetryPolicy() !== null && $task->getRetryPolicy()->getMaxAttempts() === 2);

$execResult = $task->execute();
assertTest("ScheduledTask execution success", $execResult->isSuccess());
assertTest("ScheduledTask execution output capture", str_contains($execResult->getOutput(), 'Hello Scheduled Task'));

// ScheduledTask with failure & retry
$attemptCounter = 0;
$failingTask = new ScheduledTask('failing_task', function () use (&$attemptCounter) {
    $attemptCounter++;
    if ($attemptCounter < 2) {
        throw new \RuntimeException("Temporary error");
    }
    return "Recovered on attempt " . $attemptCounter;
});
$failingTask->retry(3, 0);
$failingResult = $failingTask->execute();
assertTest("ScheduledTask retry recovery", $failingResult->isSuccess() && $failingResult->getAttempts() === 2);
assertTest("ScheduledTask recovered output", str_contains($failingResult->getOutput(), 'Recovered on attempt 2'));

// ScheduledTask condition (when / skip)
$conditionalTask = new ScheduledTask('conditional', fn() => 'ran');
$conditionalTask->when(fn() => false);
assertTest("ScheduledTask when(false) is not due", !$conditionalTask->isDue());

$conditionalTask2 = new ScheduledTask('conditional2', fn() => 'ran');
$conditionalTask2->skip(fn() => true);
assertTest("ScheduledTask skip(true) is not due", !$conditionalTask2->isDue());

// 4. DAG Dependency Pipeline Engine
echo PHP_EOL . "--- 4. DAG Pipeline Engine ---" . PHP_EOL;
$builder = DAGBuilder::create('etl_pipeline');
$builder->task('extract', function ($ctx) {
    return ['records' => [1, 2, 3]];
})
->task('transform', function ($ctx) {
    $raw = $ctx['extract']['records'];
    return array_map(fn($n) => $n * 10, $raw);
}, ['extract'])
->task('load', function ($ctx) {
    $data = $ctx['transform'];
    return "Loaded " . count($data) . " records: " . implode(',', $data);
}, ['transform']);

$dag = $builder->build();
assertTest("DAGGraph node count", count($dag->getNodes()) === 3);

$sorter = new TopologicalSorter();
$sorted = $sorter->sort($dag);
assertTest("TopologicalSort order", $sorted === ['extract', 'transform', 'load']);

$stages = $sorter->getStages($dag);
assertTest("DAG execution stages count", count($stages) === 3);

// Cycle detection test
$cyclicDag = new DAGGraph('cyclic');
$cyclicDag->addNode(new DAGNode('a', 'A', fn() => 1, ['c']));
$cyclicDag->addNode(new DAGNode('b', 'B', fn() => 2, ['a']));
$cyclicDag->addNode(new DAGNode('c', 'C', fn() => 3, ['b']));

$cycleCaught = false;
try {
    $sorter->sort($cyclicDag);
} catch (CycleDetectedException $e) {
    $cycleCaught = true;
}
assertTest("Cycle detection in cyclic DAG", $cycleCaught);

// DAG Execution
$executor = new DAGExecutor($sorter);
$dagResult = $executor->run($dag);

assertTest("DAG execution overall status", $dagResult['status'] === 'SUCCESS');
assertTest("DAG extract node completed", $dagResult['nodes']['extract']['state'] === 'COMPLETED');
assertTest("DAG transform node output", $dagResult['context']['transform'] === [10, 20, 30]);
assertTest("DAG load node output", $dagResult['context']['load'] === "Loaded 3 records: 10,20,30");

// DAG with branch failure and downstream skip cascade
$failDagBuilder = DAGBuilder::create('cascade_test');
$failDagBuilder->task('step1', function () {
    throw new \Exception("Step 1 failed!");
})
->task('step2_child', fn($ctx) => "won't run", ['step1']);

$failDag = $failDagBuilder->build();
$failResult = $executor->run($failDag);
assertTest("DAG with failure overall status", $failResult['status'] === 'FAILED');
assertTest("DAG step1 failed state", $failResult['nodes']['step1']['state'] === 'FAILED');
assertTest("DAG step2_child skipped state", $failResult['nodes']['step2_child']['state'] === 'SKIPPED');

// 5. Cluster Leader Election & Misfire Handling
echo PHP_EOL . "--- 5. Cluster & Storage ---" . PHP_EOL;
$dbPath = sys_get_temp_dir() . '/test_nf_scheduling_' . bin2hex(random_bytes(4)) . '.sqlite';
$db = new Connection(['driver' => 'sqlite', 'database' => $dbPath]);

// Run migration
$migration = require __DIR__ . '/../database/migrations/2026_09_21_000046_create_scheduling_tables.php';
$migration->up($db);

$store = new DatabaseScheduleStore($db);

// Leader election
$leaderElectionNode1 = new LeaderElection($store, 'node_1');
$leaderElectionNode2 = new LeaderElection($store, 'node_2');

$node1Acquired = $leaderElectionNode1->tryAcquireLeader(30);
assertTest("Node 1 acquires leader lock", $node1Acquired && $leaderElectionNode1->isLeader());

$node2Acquired = $leaderElectionNode2->tryAcquireLeader(30);
assertTest("Node 2 fails to acquire leader lock while Node 1 holds it", !$node2Acquired && !$leaderElectionNode2->isLeader());

assertTest("Current leader node ID", $store->getLeaderNodeId() === 'node_1');

$renewed = $leaderElectionNode1->renew(30);
assertTest("Node 1 renews leader heartbeat", $renewed);

$released = $leaderElectionNode1->release();
assertTest("Node 1 releases leader lock", $released);

$node2AcquiredAfterRelease = $leaderElectionNode2->tryAcquireLeader(30);
assertTest("Node 2 acquires leader lock after Node 1 release", $node2AcquiredAfterRelease && $leaderElectionNode2->isLeader());

// Telemetry & Task persistence in DB
$store->registerTask($task);
$store->saveExecution($execSuccess);

$savedExecutions = $store->getExecutions('t1');
assertTest("Database execution telemetry retrieved", count($savedExecutions) >= 1 && $savedExecutions[0]['task_name'] === 'Task 1');

// Misfire handler
$misfire = new MisfireHandler();
$misfireTask = new ScheduledTask('misfire_job', fn() => 'fired');
$misfireTask->everyMinute()->misfirePolicy(MisfirePolicy::FIRE_NOW);

$lastRunPast = new \DateTimeImmutable('2026-09-25 10:00:00', new \DateTimeZone('UTC'));
$currentNow = new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC'));

$hasMisfire = $misfire->detectMisfire($misfireTask, $lastRunPast, $currentNow);
assertTest("Misfire detected for past overdue task", $hasMisfire);

$misfireExec = $misfire->handleMisfire($misfireTask, $lastRunPast, $currentNow);
assertTest("Misfire handled with FIRE_NOW policy", $misfireExec !== null && $misfireExec->isSuccess());

// 6. Master SchedulerManager Facade & Global Helpers
echo PHP_EOL . "--- 6. Master Facade & Global Helpers ---" . PHP_EOL;
$scheduler = new SchedulerManager($store);

$scheduler->call(fn() => "Closure Job Output", 'daily_report')->daily();
$scheduler->command('cache:clear', 'clear_cache_task')->hourly();
$scheduler->exec('echo "Hello NetFusion"', 'echo_task')->everyMinute();

assertTest("SchedulerManager tasks count", count($scheduler->getTasks()) === 3);

$scheduler->registerDAG('my_dag', $dag);
assertTest("SchedulerManager getDAG", $scheduler->getDAG('my_dag') !== null);

$dagRunReport = $scheduler->runDAG('my_dag');
assertTest("SchedulerManager runDAG execution", $dagRunReport['status'] === 'SUCCESS');

// Global helpers test
assertTest("helper: scheduler() returns SchedulerManager", scheduler() instanceof SchedulerManager);

$helperTask = schedule_task('* * * * *', fn() => "Helper Output", 'helper_task');
assertTest("helper: schedule_task() returns ScheduledTask", $helperTask instanceof ScheduledTask);

$helperDag = schedule_dag('helper_workflow');
assertTest("helper: schedule_dag() returns DAGBuilder", $helperDag instanceof DAGBuilder);

assertTest("helper: cron_match() true match", cron_match('0 12 * * *', new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC'))));
assertTest("helper: cron_match() false match", !cron_match('0 13 * * *', new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC'))));

// Clean up test DB
$migration->down($db);
@unlink($dbPath);

echo PHP_EOL . "=== Phase 66 Test Results ===" . PHP_EOL;
echo "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
