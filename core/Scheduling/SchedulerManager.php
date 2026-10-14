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

namespace NetFusion\Core\Scheduling;

use NetFusion\Core\Scheduling\Contracts\CronParserInterface;
use NetFusion\Core\Scheduling\Contracts\DAGRunnerInterface;
use NetFusion\Core\Scheduling\Contracts\ScheduleStoreInterface;
use NetFusion\Core\Scheduling\Contracts\TaskInterface;
use NetFusion\Core\Scheduling\Models\ScheduledTask;
use NetFusion\Core\Scheduling\Models\DAGGraph;
use NetFusion\Core\Scheduling\Models\TaskExecution;
use NetFusion\Core\Scheduling\Cron\QuartzExpression;
use NetFusion\Core\Scheduling\DAG\DAGBuilder;
use NetFusion\Core\Scheduling\DAG\DAGExecutor;
use NetFusion\Core\Scheduling\Cluster\LeaderElection;
use NetFusion\Core\Scheduling\Cluster\MisfireHandler;
use NetFusion\Core\Scheduling\Dispatcher\TaskRunner;
use NetFusion\Core\Scheduling\Storage\DatabaseScheduleStore;

class SchedulerManager
{
    private ScheduleStoreInterface $store;
    private CronParserInterface $cronParser;
    private DAGRunnerInterface $dagExecutor;
    private LeaderElection $leaderElection;
    private MisfireHandler $misfireHandler;
    private TaskRunner $taskRunner;

    /** @var array<string, ScheduledTask> */
    private array $tasks = [];
    /** @var array<string, DAGGraph> */
    private array $dags = [];

    public function __construct(
        ?ScheduleStoreInterface $store = null,
        ?CronParserInterface $cronParser = null,
        ?DAGRunnerInterface $dagExecutor = null,
        ?LeaderElection $leaderElection = null,
        ?MisfireHandler $misfireHandler = null,
        ?TaskRunner $taskRunner = null
    ) {
        $this->store = $store ?? new DatabaseScheduleStore();
        $this->cronParser = $cronParser ?? new QuartzExpression();
        $this->dagExecutor = $dagExecutor ?? new DAGExecutor();
        $this->leaderElection = $leaderElection ?? new LeaderElection($this->store);
        $this->misfireHandler = $misfireHandler ?? new MisfireHandler();
        $this->taskRunner = $taskRunner ?? new TaskRunner($this->store);
    }

    /**
     * Schedule a closure callback
     */
    public function call(mixed $callback, ?string $name = null): ScheduledTask
    {
        $taskName = $name ?? 'closure_task_' . (count($this->tasks) + 1);
        $task = new ScheduledTask($taskName, $callback, 'closure');
        $this->tasks[$task->getId()] = $task;
        $this->store->registerTask($task);
        return $task;
    }

    /**
     * Schedule a console command
     */
    public function command(string $command, ?string $name = null): ScheduledTask
    {
        $taskName = $name ?? $command;
        $task = new ScheduledTask($taskName, $command, 'command');
        $this->tasks[$task->getId()] = $task;
        $this->store->registerTask($task);
        return $task;
    }

    /**
     * Schedule a shell command
     */
    public function exec(string $command, ?string $name = null): ScheduledTask
    {
        $taskName = $name ?? $command;
        $task = new ScheduledTask($taskName, $command, 'exec');
        $this->tasks[$task->getId()] = $task;
        $this->store->registerTask($task);
        return $task;
    }

    /**
     * Define a new DAG workflow
     */
    public function dag(string $name = 'workflow'): DAGBuilder
    {
        return new DAGBuilder($name);
    }

    /**
     * Register a DAG workflow
     */
    public function registerDAG(string $name, DAGGraph|DAGBuilder $dag): self
    {
        $graph = $dag instanceof DAGBuilder ? $dag->build() : $dag;
        $this->dags[$name] = $graph;
        return $this;
    }

    /**
     * Get registered DAG workflow
     */
    public function getDAG(string $name): ?DAGGraph
    {
        return $this->dags[$name] ?? null;
    }

    /**
     * Run a DAG workflow by name
     */
    public function runDAG(string $name, array $context = []): array
    {
        $dag = $this->getDAG($name);
        if ($dag === null) {
            throw new Exceptions\SchedulingException("DAG workflow '{$name}' not found.");
        }

        return $this->dagExecutor->run($dag, $context);
    }

    /**
     * Get all registered scheduled tasks
     * @return array<string, ScheduledTask>
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * Get all tasks that are due right now
     * @return array<ScheduledTask>
     */
    public function getDueTasks(?\DateTimeInterface $currentTime = null): array
    {
        $due = [];
        foreach ($this->tasks as $task) {
            if ($task->isDue($currentTime)) {
                $due[] = $task;
            }
        }
        return $due;
    }

    /**
     * Run all due tasks
     * @return array<TaskExecution>
     */
    public function runDueTasks(?\DateTimeInterface $currentTime = null): array
    {
        return $this->taskRunner->runDueTasks(array_values($this->tasks), $currentTime);
    }

    public function getStore(): ScheduleStoreInterface
    {
        return $this->store;
    }

    public function getCronParser(): CronParserInterface
    {
        return $this->cronParser;
    }

    public function getDAGExecutor(): DAGRunnerInterface
    {
        return $this->dagExecutor;
    }

    public function getLeaderElection(): LeaderElection
    {
        return $this->leaderElection;
    }

    public function getMisfireHandler(): MisfireHandler
    {
        return $this->misfireHandler;
    }

    public function getTaskRunner(): TaskRunner
    {
        return $this->taskRunner;
    }
}
