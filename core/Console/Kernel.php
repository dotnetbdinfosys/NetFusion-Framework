<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Kernel.php
| Module    : Core\Console
| Namespace : NetFusion\Core\Console
| Purpose   : Console Kernel responsible for parsing CLI argv input and dispatching to commands.
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

namespace NetFusion\Core\Console;

use NetFusion\Core\Application;

/**
 * NetFusion Console Kernel
 *
 * Dispatches terminal inputs to registered commands and manages CLI lifecycle.
 *
 * @package NetFusion\Core\Console
 * @version 1.0.0
 */
class Kernel
{
    /**
     * Registered console commands indexed by command name.
     *
     * @var array<string, class-string<Command>>
     */
    protected array $commands = [];

    /**
     * @param Application $app
     */
    public function __construct(
        protected readonly Application $app
    ) {
        $this->registerDefaultCommands();
    }

    /**
     * Register default built-in commands.
     */
    protected function registerDefaultCommands(): void
    {
        $this->registerCommand(Commands\ListCommand::class);
        $this->registerCommand(Commands\MakeModuleCommand::class);
        $this->registerCommand(Commands\MakeControllerCommand::class);
        $this->registerCommand(Commands\MakeModelCommand::class);
        $this->registerCommand(Commands\MakeMigrationCommand::class);
        $this->registerCommand(Commands\MigrateCommand::class);
        $this->registerCommand(Commands\SeedCommand::class);
        $this->registerCommand(Commands\QueueWorkCommand::class);
        $this->registerCommand(Commands\QueueFailedCommand::class);
        $this->registerCommand(Commands\QueueRetryCommand::class);
        $this->registerCommand(Commands\CacheClearCommand::class);
        $this->registerCommand(Commands\CacheForgetCommand::class);
        $this->registerCommand(Commands\MakeMailCommand::class);
        $this->registerCommand(Commands\MakeNotificationCommand::class);
        $this->registerCommand(Commands\ScheduleRunCommand::class);
        $this->registerCommand(Commands\ScheduleListCommand::class);
        $this->registerCommand(Commands\StorageLinkCommand::class);
        $this->registerCommand(Commands\WebhookRetryCommand::class);
        $this->registerCommand(Commands\ServerCheckCommand::class);
        $this->registerCommand(Commands\OptimizeCommand::class);
        $this->registerCommand(Commands\SecurityAuditCommand::class);
        $this->registerCommand(Commands\AuditPruneCommand::class);
        $this->registerCommand(Commands\BackupRunCommand::class);
        $this->registerCommand(Commands\BackupListCommand::class);
        $this->registerCommand(Commands\BackupCleanCommand::class);
        $this->registerCommand(Commands\LangMissingCommand::class);
        $this->registerCommand(Commands\WebhookReplayCommand::class);
        $this->registerCommand(Commands\TokenCreateCommand::class);
        $this->registerCommand(Commands\TokenListCommand::class);
        $this->registerCommand(Commands\TokenRevokeCommand::class);
        $this->registerCommand(Commands\ApmSummaryCommand::class);
        $this->registerCommand(Commands\FeatureListCommand::class);
        $this->registerCommand(Commands\FeatureEnableCommand::class);
        $this->registerCommand(Commands\FeatureDisableCommand::class);
        $this->registerCommand(Commands\SearchIndexCommand::class);
        $this->registerCommand(Commands\SearchFlushCommand::class);
        $this->registerCommand(Commands\SearchQueryCommand::class);
        $this->registerCommand(Commands\NotificationSendCommand::class);
        $this->registerCommand(Commands\NotificationListCommand::class);
        $this->registerCommand(Commands\CurrencyUpdateRatesCommand::class);
        $this->registerCommand(Commands\CurrencyConvertCommand::class);
        $this->registerCommand(Commands\FormFieldListCommand::class);
        $this->registerCommand(Commands\FormFieldAddCommand::class);
        $this->registerCommand(Commands\DocumentExportCommand::class);
        $this->registerCommand(Commands\WorkflowListCommand::class);
        $this->registerCommand(Commands\WorkflowTransitionCommand::class);
        $this->registerCommand(Commands\ApprovalPendingCommand::class);
        $this->registerCommand(Commands\EventReplayCommand::class);
        $this->registerCommand(Commands\EventStreamListCommand::class);
        $this->registerCommand(Commands\LockListCommand::class);
        $this->registerCommand(Commands\LockReleaseCommand::class);
        $this->registerCommand(Commands\LockCleanCommand::class);
        $this->registerCommand(Commands\TenantListCommand::class);
        $this->registerCommand(Commands\TenantCreateCommand::class);
        $this->registerCommand(Commands\TenantMigrateCommand::class);
        $this->registerCommand(Commands\SocketServeCommand::class);
        $this->registerCommand(Commands\SocketPushCommand::class);
        $this->registerCommand(Commands\GraphQLSchemaCommand::class);
        $this->registerCommand(Commands\GraphQLQueryCommand::class);
        $this->registerCommand(Commands\RpcServeCommand::class);
        $this->registerCommand(Commands\RpcCallCommand::class);
        $this->registerCommand(Commands\RpcRegistryCommand::class);
        $this->registerCommand(Commands\AiChatCommand::class);
        $this->registerCommand(Commands\AiAgentCommand::class);
        $this->registerCommand(Commands\AiEmbedCommand::class);
        $this->registerCommand(Commands\SagaListCommand::class);
        $this->registerCommand(Commands\SagaShowCommand::class);
        $this->registerCommand(Commands\SagaRetryCommand::class);
        $this->registerCommand(Commands\SagaCompensateCommand::class);
        $this->registerCommand(Commands\PolicyCheckCommand::class);
        $this->registerCommand(Commands\PolicyListCommand::class);
        $this->registerCommand(Commands\RebacTupleCommand::class);
        $this->registerCommand(Commands\TelemetryMetricsCommand::class);
        $this->registerCommand(Commands\TelemetryTraceCommand::class);
        $this->registerCommand(Commands\WebAuthnCredentialsCommand::class);
        $this->registerCommand(Commands\WebAuthnPruneCommand::class);
        $this->registerCommand(Commands\ReplicationSyncCommand::class);
        $this->registerCommand(Commands\ReplicationStatusCommand::class);
        $this->registerCommand(Commands\CrdtInspectCommand::class);
        $this->registerCommand(Commands\CollabSessionCommand::class);
        $this->registerCommand(Commands\CollabInspectCommand::class);
        $this->registerCommand(Commands\TimeSeriesQueryCommand::class);
        $this->registerCommand(Commands\TimeSeriesDownsampleCommand::class);
        $this->registerCommand(Commands\TimeSeriesAnomalyCommand::class);
        $this->registerCommand(Commands\VaultSetCommand::class);
        $this->registerCommand(Commands\VaultGetCommand::class);
        $this->registerCommand(Commands\VaultRotateCommand::class);
        $this->registerCommand(Commands\VaultLeaseCommand::class);
        $this->registerCommand(Commands\ExperimentListCommand::class);
        $this->registerCommand(Commands\ExperimentEvaluateCommand::class);
        $this->registerCommand(Commands\ExperimentReportCommand::class);
        $this->registerCommand(Commands\CanaryCommand::class);
        $this->registerCommand(Commands\ActorTreeCommand::class);
        $this->registerCommand(Commands\ActorSendCommand::class);
        $this->registerCommand(Commands\ActorDeadLettersCommand::class);
        $this->registerCommand(Commands\WorkflowStartCommand::class);
        $this->registerCommand(Commands\WorkflowListCommand::class);
        $this->registerCommand(Commands\WorkflowSignalCommand::class);
        $this->registerCommand(Commands\WorkflowHistoryCommand::class);
        $this->registerCommand(Commands\GridNodesCommand::class);
        $this->registerCommand(Commands\GridGetCommand::class);
        $this->registerCommand(Commands\GridPutCommand::class);
        $this->registerCommand(Commands\GridStatsCommand::class);
        $this->registerCommand(Commands\CEPRulesCommand::class);
        $this->registerCommand(Commands\CEPIngestCommand::class);
        $this->registerCommand(Commands\CEPStatsCommand::class);
        $this->registerCommand(Commands\CEPAlertsCommand::class);
        $this->registerCommand(Commands\MeshServicesCommand::class);
        $this->registerCommand(Commands\MeshRoutesCommand::class);
        $this->registerCommand(Commands\MeshCertsCommand::class);
        $this->registerCommand(Commands\MeshStatsCommand::class);
        $this->registerCommand(Commands\RuleListCommand::class);
        $this->registerCommand(Commands\RuleEvalCommand::class);
        $this->registerCommand(Commands\DecisionTablesCommand::class);
        $this->registerCommand(Commands\DecisionEvalCommand::class);
        $this->registerCommand(Commands\VectorCollectionsCommand::class);
        $this->registerCommand(Commands\VectorIndexCommand::class);
        $this->registerCommand(Commands\VectorSearchCommand::class);
        $this->registerCommand(Commands\RAGQueryCommand::class);
        $this->registerCommand(Commands\WAFStatsCommand::class);
        $this->registerCommand(Commands\WAFEventsCommand::class);
        $this->registerCommand(Commands\WAFBlockCommand::class);
        $this->registerCommand(Commands\WAFUnblockCommand::class);
        $this->registerCommand(Commands\DWStatusCommand::class);
        $this->registerCommand(Commands\DWPivotCommand::class);
        $this->registerCommand(Commands\PaymentStatusCommand::class);
        $this->registerCommand(Commands\PaymentReconcileCommand::class);
        $this->registerCommand(Commands\SearchIndexCommand::class);
        $this->registerCommand(Commands\SearchQueryCommand::class);
        $this->registerCommand(Commands\SearchSuggestCommand::class);
        $this->registerCommand(Commands\GraphQueryCommand::class);
        $this->registerCommand(Commands\GraphTraverseCommand::class);
        $this->registerCommand(Commands\GraphStatsCommand::class);
        $this->registerCommand(Commands\ScheduleDAGCommand::class);
        $this->registerCommand(Commands\GatewayRoutesCommand::class);
        $this->registerCommand(Commands\GatewayHealthCommand::class);
        $this->registerCommand(Commands\AccountingReportCommand::class);
        $this->registerCommand(Commands\MeshTopologyCommand::class);
        $this->registerCommand(Commands\MeshCertCommand::class);
        $this->registerCommand(Commands\PqcBenchmarkCommand::class);
        $this->registerCommand(Commands\PqcKeyCommand::class);
        $this->registerCommand(Commands\PqcVaultCommand::class);
        $this->registerCommand(Commands\DrStatusCommand::class);
        $this->registerCommand(Commands\DrFailoverCommand::class);
        $this->registerCommand(Commands\DrDrillCommand::class);
        $this->registerCommand(Commands\DrRestoreCommand::class);
        $this->registerCommand(Commands\ChaosRunCommand::class);
        $this->registerCommand(Commands\ChaosAbortCommand::class);
        $this->registerCommand(Commands\ChaosReportCommand::class);
        $this->registerCommand(Commands\ChaosMonkeyCommand::class);
        $this->registerCommand(Commands\OtelExportCommand::class);
        $this->registerCommand(Commands\OtelMetricsCommand::class);
        $this->registerCommand(Commands\OtelProfileCommand::class);
        $this->registerCommand(Commands\SchemaRegisterCommand::class);
        $this->registerCommand(Commands\SchemaCheckCommand::class);
        $this->registerCommand(Commands\CloudEventPublishCommand::class);
        $this->registerCommand(Commands\AgentRunCommand::class);
        $this->registerCommand(Commands\AgentToolsCommand::class);
        $this->registerCommand(Commands\AgentMemoryCommand::class);
        $this->registerCommand(Commands\CollabSessionCommand::class);
        $this->registerCommand(Commands\CollabPruneCommand::class);
        $this->registerCommand(Commands\RaftStatusCommand::class);
        $this->registerCommand(Commands\DbReplicateCommand::class);
        $this->registerCommand(Commands\DsqlQueryCommand::class);
        $this->registerCommand(Commands\IacPlanCommand::class);
        $this->registerCommand(Commands\IacApplyCommand::class);
        $this->registerCommand(Commands\IacDestroyCommand::class);
        $this->registerCommand(Commands\IacStateCommand::class);
        $this->registerCommand(Commands\WasmRunCommand::class);
        $this->registerCommand(Commands\WasmInspectCommand::class);
        $this->registerCommand(Commands\KubeApplyCommand::class);
        $this->registerCommand(Commands\KubeGetCommand::class);
        $this->registerCommand(Commands\KubeScaleCommand::class);
        $this->registerCommand(Commands\KubeLogsCommand::class);
        $this->registerCommand(Commands\EtcdPutCommand::class);
        $this->registerCommand(Commands\EtcdGetCommand::class);
        $this->registerCommand(Commands\EtcdWatchCommand::class);
        $this->registerCommand(Commands\EtcdLeaseCommand::class);
        $this->registerCommand(Commands\KafkaTopicCommand::class);
        $this->registerCommand(Commands\KafkaProduceCommand::class);
        $this->registerCommand(Commands\KafkaConsumeCommand::class);
        $this->registerCommand(Commands\KafkaGroupCommand::class);
        $this->registerCommand(Commands\ConsulServiceCommand::class);
        $this->registerCommand(Commands\ConsulMembersCommand::class);
        $this->registerCommand(Commands\ConsulDnsCommand::class);
        $this->registerCommand(Commands\ConsulCheckCommand::class);
        $this->registerCommand(Commands\DruidIngestCommand::class);
        $this->registerCommand(Commands\DruidQueryCommand::class);
        $this->registerCommand(Commands\DruidSegmentsCommand::class);
        $this->registerCommand(Commands\ZkCreateCommand::class);
        $this->registerCommand(Commands\ZkGetCommand::class);
        $this->registerCommand(Commands\ZkLsCommand::class);
        $this->registerCommand(Commands\MfeRemotesCommand::class);
        $this->registerCommand(Commands\MfeManifestCommand::class);
        $this->registerCommand(Commands\EdgeDeployCommand::class);
        $this->registerCommand(Commands\EdgeInvokeCommand::class);
        $this->registerCommand(Commands\EdgeStatsCommand::class);
        $this->registerCommand(Commands\MediaRoomsCommand::class);
        $this->registerCommand(Commands\MediaStatsCommand::class);
        $this->registerCommand(Commands\MediaTranscodeCommand::class);
        $this->registerCommand(Commands\GeoFenceCommand::class);
        $this->registerCommand(Commands\GeoH3Command::class);
        $this->registerCommand(Commands\GeoIndexCommand::class);
        $this->registerCommand(Commands\SduiScreensCommand::class);
        $this->registerCommand(Commands\SduiValidateCommand::class);
        $this->registerCommand(Commands\SduiRenderCommand::class);
        $this->registerCommand(Commands\GraphRagIngestCommand::class);
        $this->registerCommand(Commands\GraphRagQueryCommand::class);
        $this->registerCommand(Commands\GraphRagStatsCommand::class);
        $this->registerCommand(Commands\CrdtSyncCommand::class);
        $this->registerCommand(Commands\CrdtInspectDocCommand::class);
        $this->registerCommand(Commands\CrdtResolveCommand::class);
        $this->registerCommand(Commands\ZkpSetupCommand::class);
        $this->registerCommand(Commands\ZkpProveCommand::class);
        $this->registerCommand(Commands\ZkpVerifyCommand::class);
        $this->registerCommand(Commands\FormalVerifyCommand::class);
        $this->registerCommand(Commands\FormalSolveCommand::class);
        $this->registerCommand(Commands\FormalSymbolicCommand::class);
        $this->registerCommand(Commands\EbpfLoadCommand::class);
        $this->registerCommand(Commands\EbpfMapCommand::class);
        $this->registerCommand(Commands\XdpAttachCommand::class);
        $this->registerCommand(Commands\WitParseCommand::class);
        $this->registerCommand(Commands\WasmComposeCommand::class);
        $this->registerCommand(Commands\WasmComponentRunCommand::class);
        $this->registerCommand(Commands\DpQueryCommand::class);
        $this->registerCommand(Commands\DpBudgetCommand::class);
        $this->registerCommand(Commands\DpFederateCommand::class);
        $this->registerCommand(Commands\HeEncryptCommand::class);
        $this->registerCommand(Commands\HeComputeCommand::class);
        $this->registerCommand(Commands\SmpcShareCommand::class);
        $this->registerCommand(Commands\HealDiagnoseCommand::class);
        $this->registerCommand(Commands\HealRemediateCommand::class);
        $this->registerCommand(Commands\HealStatusCommand::class);
        $this->registerCommand(Commands\EnclaveAttestCommand::class);
        $this->registerCommand(Commands\EnclaveSealCommand::class);
        $this->registerCommand(Commands\EnclaveUnsealCommand::class);
        $this->registerCommand(Commands\QkdExchangeCommand::class);
        $this->registerCommand(Commands\QkdStatusCommand::class);
        $this->registerCommand(Commands\QkdBenchmarkCommand::class);
        $this->registerCommand(Commands\NeuromorphicTrainCommand::class);
        $this->registerCommand(Commands\NeuromorphicInferCommand::class);
        $this->registerCommand(Commands\NeuromorphicBenchmarkCommand::class);
        $this->registerCommand(Commands\SwarmStatusCommand::class);
        $this->registerCommand(Commands\SwarmAuctionCommand::class);
        $this->registerCommand(Commands\SwarmSimulateCommand::class);
        $this->registerCommand(Commands\DidResolveCommand::class);
        $this->registerCommand(Commands\VcIssueCommand::class);
        $this->registerCommand(Commands\VcVerifyCommand::class);
        $this->registerCommand(Commands\SpatialSceneCommand::class);
        $this->registerCommand(Commands\SpatialSplatCommand::class);
        $this->registerCommand(Commands\SpatialSimulateCommand::class);
        $this->registerCommand(Commands\MolecularEncodeCommand::class);
        $this->registerCommand(Commands\MolecularDecodeCommand::class);
        $this->registerCommand(Commands\MolecularHolographCommand::class);
        $this->registerCommand(Commands\BciStreamCommand::class);
        $this->registerCommand(Commands\BciFilterCommand::class);
        $this->registerCommand(Commands\BciDecodeCommand::class);
        $this->registerCommand(Commands\PhotonicMziCommand::class);
        $this->registerCommand(Commands\PhotonicGemmCommand::class);
        $this->registerCommand(Commands\PhotonicBenchmarkCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Ris\RisSteerCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Ris\RisChannelCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Ris\RisBenchmarkCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Relativity\RelativityChirpCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Relativity\RelativityMetricCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Relativity\RelativityDetectCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Tda\TdaHomologyCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Tda\TdaRipsCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Tda\TdaBarcodeCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Cognitive\CognitiveCycleCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Cognitive\CognitiveRecallCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Cognitive\CognitiveLearnCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Rollup\RollupBatchCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Rollup\RollupVerifyCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Rollup\RollupStateCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Lbm\LbmCavityCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Lbm\LbmFlowCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Lbm\LbmBenchmarkCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Bio\BioCadCrisprCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Bio\BioCadOptimizeCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Bio\BioCadCircuitCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Astro\AstroOrbitCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Astro\AstroNBodyCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Astro\AstroTransferCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Plasma\PlasmaTokamakCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Plasma\PlasmaFusionCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Plasma\PlasmaLawsonCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Superconductivity\SuperconductivityGlCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Superconductivity\SuperconductivityVortexCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Superconductivity\SuperconductivitySquidCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\QuantumThermodynamics\QuantumThermodynamicsLindbladCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\QuantumThermodynamics\QuantumThermodynamicsEngineCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\QuantumThermodynamics\QuantumThermodynamicsLandauerCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Release\ReleaseVerifyCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\Release\ReleaseInfoCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\License\LicenseActivateCommand::class);
        $this->registerCommand(\NetFusion\Core\Console\Commands\License\LicenseCheckCommand::class);
    }











    /**
     * Define the application's command schedule.
     *
     * @param \NetFusion\Core\Console\Scheduling\Schedule $schedule
     */
    public function schedule(\NetFusion\Core\Console\Scheduling\Schedule $schedule): void
    {
        // Default scheduled tasks
        $schedule->command('queue:work --once')->everyMinute()->withoutOverlapping();
    }


    /**
     * Register a command class into the Kernel.
     *
     * @param class-string<Command> $commandClass
     */
    public function registerCommand(string $commandClass): void
    {
        /** @var Command $instance */
        $instance = $this->app->make($commandClass);
        $this->commands[$instance->getName()] = $commandClass;
    }

    /**
     * Handle incoming terminal arguments.
     *
     * @param  string[] $argv
     * @return int Exit code
     */
    public function handle(array $argv): int
    {
        // Remove script name (index 0)
        array_shift($argv);

        $commandName = array_shift($argv) ?? 'list';

        // Parse remaining items into arguments and options
        [$arguments, $options] = $this->parseInput($argv);

        if (!isset($this->commands[$commandName])) {
            echo "\033[31m✖ Command [{$commandName}] not found. Run 'php netfusion list' to see available commands.\033[0m" . PHP_EOL;
            return 1;
        }

        $commandClass = $this->commands[$commandName];
        /** @var Command $command */
        $command = $this->app->make($commandClass);
        $command->setInput($arguments, $options);

        return $command->handle();
    }

    /**
     * Call a console command programmatically.
     *
     * @param string $commandName
     * @param array<string|int, mixed> $parameters
     * @return int
     */
    public function call(string $commandName, array $parameters = []): int
    {
        $argv = ['netfusion', $commandName];
        foreach ($parameters as $key => $val) {
            if (is_int($key)) {
                $argv[] = (string)$val;
            } elseif (is_bool($val)) {
                if ($val) {
                    $argv[] = "--{$key}";
                }
            } else {
                $argv[] = "--{$key}={$val}";
            }
        }
        return $this->handle($argv);
    }

    /**
     * Parse raw arguments into positional arguments and options.
     *
     * @param  string[] $rawArgs
     * @return array{0: string[], 1: array<string, mixed>}
     */
    protected function parseInput(array $rawArgs): array
    {
        $arguments = [];
        $options   = [];

        foreach ($rawArgs as $arg) {
            if (str_starts_with($arg, '--')) {
                $opt = substr($arg, 2);
                if (str_contains($opt, '=')) {
                    [$key, $value] = explode('=', $opt, 2);
                    $options[$key] = $value;
                } else {
                    $options[$opt] = true;
                }
            } elseif (str_starts_with($arg, '-')) {
                $options[substr($arg, 1)] = true;
            } else {
                $arguments[] = $arg;
            }
        }

        return [$arguments, $options];
    }

    /**
     * Return all registered commands.
     *
     * @return array<string, class-string<Command>>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }
}
