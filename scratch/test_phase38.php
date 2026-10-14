<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Phase 38 Test Suite
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase38.php
| Module    : EventSourcing
| Purpose   : Comprehensive tests for Event Sourcing, Aggregate Roots,
|             CQRS Command & Query Buses, and Projections.
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

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Config\Config;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\EventSourcing\AggregateRepository;
use NetFusion\Core\EventSourcing\AggregateRoot;
use NetFusion\Core\EventSourcing\CommandBus;
use NetFusion\Core\EventSourcing\Contracts\CommandInterface;
use NetFusion\Core\EventSourcing\Contracts\QueryInterface;
use NetFusion\Core\EventSourcing\DomainEvent;
use NetFusion\Core\EventSourcing\EventSourcingManager;
use NetFusion\Core\EventSourcing\EventStore;
use NetFusion\Core\EventSourcing\Exceptions\ConcurrencyException;
use NetFusion\Core\EventSourcing\Projection;
use NetFusion\Core\EventSourcing\ProjectionManager;
use NetFusion\Core\EventSourcing\QueryBus;

$basePath = realpath(__DIR__ . '/..');
$app      = new Application($basePath);

$sqlitePath = __DIR__ . '/test_phase38.sqlite';
if (file_exists($sqlitePath)) {
    @unlink($sqlitePath);
}
touch($sqlitePath);

$app->make(Config::class)->set('database.default', 'sqlite');
$app->make(Config::class)->set('database.connections.sqlite.database', $sqlitePath);

/** @var DatabaseManager $db */
$db   = $app->make(DatabaseManager::class);
$conn = $db->connection('sqlite');

// Run migrations
$migration = require __DIR__ . '/../database/migrations/2026_09_18_000020_create_event_sourcing_tables.php';
$migration->up($conn);

$passed = 0;
$failed = 0;
$total  = 0;

function t(bool $cond, string $name): void
{
    global $passed, $failed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo "  \033[32m✔\033[0m {$name}\n";
    } else {
        $failed++;
        echo "  \033[31m✖\033[0m {$name}\n";
    }
}

echo "\n=================================================================\n";
echo "  NetFusion Phase 38 — Event Sourcing & CQRS Engine Tests\n";
echo "=================================================================\n\n";

// -------------------------------------------------------------
// Test Concrete Events & Aggregate Root
// -------------------------------------------------------------

class AccountOpenedEvent extends DomainEvent {}
class MoneyDepositedEvent extends DomainEvent {}
class MoneyWithdrawnEvent extends DomainEvent {}

class BankAccountAggregate extends AggregateRoot
{
    private string $accountHolder = '';
    private float $balance = 0.0;
    private bool $isOpen = false;

    public static function open(string $accountId, string $holder, float $initialDeposit): self
    {
        $account = new self();
        $account->id = $accountId;
        $account->recordThat(new AccountOpenedEvent([
            'holder' => $holder,
            'initial_deposit' => $initialDeposit,
        ]));
        return $account;
    }

    public function deposit(float $amount): void
    {
        if (!$this->isOpen) {
            throw new \RuntimeException("Cannot deposit to closed account.");
        }
        $this->recordThat(new MoneyDepositedEvent(['amount' => $amount]));
    }

    public function withdraw(float $amount): void
    {
        if ($this->balance < $amount) {
            throw new \RuntimeException("Insufficient funds.");
        }
        $this->recordThat(new MoneyWithdrawnEvent(['amount' => $amount]));
    }

    protected function applyAccountOpenedEvent(AccountOpenedEvent $event): void
    {
        $this->accountHolder = (string)$event->get('holder');
        $this->balance = (float)$event->get('initial_deposit', 0.0);
        $this->isOpen = true;
    }

    protected function applyMoneyDepositedEvent(MoneyDepositedEvent $event): void
    {
        $this->balance += (float)$event->get('amount', 0.0);
    }

    protected function applyMoneyWithdrawnEvent(MoneyWithdrawnEvent $event): void
    {
        $this->balance -= (float)$event->get('amount', 0.0);
    }

    public function getBalance(): float
    {
        return $this->balance;
    }

    public function getHolder(): string
    {
        return $this->accountHolder;
    }
}

// Concrete Projection
class AccountSummaryProjection extends Projection
{
    public array $accounts = [];

    public function onAccountOpenedEvent(AccountOpenedEvent $event): void
    {
        $this->accounts[$event->getStreamId()] = [
            'holder' => (string)$event->get('holder'),
            'balance' => (float)$event->get('initial_deposit', 0.0),
            'tx_count' => 1,
        ];
    }

    public function onMoneyDepositedEvent(MoneyDepositedEvent $event): void
    {
        $streamId = $event->getStreamId();
        if (isset($this->accounts[$streamId])) {
            $this->accounts[$streamId]['balance'] += (float)$event->get('amount', 0.0);
            $this->accounts[$streamId]['tx_count']++;
        }
    }

    public function onMoneyWithdrawnEvent(MoneyWithdrawnEvent $event): void
    {
        $streamId = $event->getStreamId();
        if (isset($this->accounts[$streamId])) {
            $this->accounts[$streamId]['balance'] -= (float)$event->get('amount', 0.0);
            $this->accounts[$streamId]['tx_count']++;
        }
    }

    public function reset(): void
    {
        $this->accounts = [];
    }
}

// CQRS Commands & Queries
class OpenAccountCommand implements CommandInterface
{
    public function __construct(public string $id, public string $holder, public float $initialDeposit) {}
}

class DepositCommand implements CommandInterface
{
    public function __construct(public string $id, public float $amount) {}
}

class GetAccountBalanceQuery implements QueryInterface
{
    public function __construct(public string $id) {}
}

// =============================================================================
// Section 1: Domain Event Immutability & Structure
// =============================================================================
echo "\033[36m[Section 1: Domain Event Immutability & Structure]\033[0m\n";

$event = new AccountOpenedEvent(['holder' => 'Ashish', 'initial_deposit' => 1000.0], ['ip' => '127.0.0.1']);
t(strlen($event->getEventId()) >= 32, 'DomainEvent generates valid UUID eventId');
t($event->get('holder') === 'Ashish', 'DomainEvent get() retrieves payload field');
t($event->getMetadata()['ip'] === '127.0.0.1', 'DomainEvent getMetadata() returns metadata');
t($event->getVersion() === 0, 'Initial event version is 0');

$eventV5 = $event->withVersion(5)->withStreamId('acc-101');
t($eventV5->getVersion() === 5, 'withVersion() returns clone with updated version');
t($eventV5->getStreamId() === 'acc-101', 'withStreamId() returns clone with updated streamId');
t($event->getVersion() === 0, 'Original event remains immutable');

// =============================================================================
// Section 2: Aggregate Root Lifecycle & State Mutators
// =============================================================================
echo "\033[36m[Section 2: Aggregate Root Lifecycle & State Mutators]\033[0m\n";

$account = BankAccountAggregate::open('acc-001', 'Rahim', 500.0);
t($account->getId() === 'acc-001', 'Aggregate ID correctly initialized');
t($account->getBalance() === 500.0, 'Initial balance correctly applied from event');
t($account->getVersion() === 1, 'Aggregate version is 1 after open');
t(count($account->getUncommittedEvents()) === 1, 'Aggregate has 1 uncommitted event');

$account->deposit(250.0);
t($account->getBalance() === 750.0, 'Balance updated to 750.0 after deposit');
t($account->getVersion() === 2, 'Aggregate version incremented to 2');
t(count($account->getUncommittedEvents()) === 2, 'Aggregate has 2 uncommitted events');

$account->withdraw(100.0);
t($account->getBalance() === 650.0, 'Balance updated to 650.0 after withdrawal');
t($account->getVersion() === 3, 'Aggregate version incremented to 3');
t(count($account->getUncommittedEvents()) === 3, 'Aggregate has 3 uncommitted events');

// =============================================================================
// Section 3: Event Store & Repository Persistence
// =============================================================================
echo "\033[36m[Section 3: Event Store & Repository Persistence]\033[0m\n";

$projManager = new ProjectionManager();
$summaryProj = new AccountSummaryProjection();
$projManager->register($summaryProj);

$store = new EventStore($db, $projManager);
$repo = new AggregateRepository($store);

$acc2 = BankAccountAggregate::open('acc-002', 'Karim', 1000.0);
$acc2->deposit(500.0);
$repo->save($acc2);

t(count($acc2->getUncommittedEvents()) === 0, 'Uncommitted events cleared after repo save');

$stream = $store->loadStream('acc-002');
t($stream->getVersion() === 2, 'Loaded stream version is 2');
t(count($stream->getEvents()) === 2, 'Loaded stream contains 2 events');
t(isset($summaryProj->accounts['acc-002']), 'Projection automatically projected events on save');
t($summaryProj->accounts['acc-002']['balance'] === 1500.0, 'Projection computed correct balance 1500.0');

// =============================================================================
// Section 4: Aggregate Reconstitution from History
// =============================================================================
echo "\033[36m[Section 4: Aggregate Reconstitution from History]\033[0m\n";

/** @var BankAccountAggregate $reconstituted */
$reconstituted = $repo->load(BankAccountAggregate::class, 'acc-002');
t($reconstituted->getId() === 'acc-002', 'Reconstituted aggregate has ID acc-002');
t($reconstituted->getHolder() === 'Karim', 'Reconstituted aggregate has holder Karim');
t($reconstituted->getBalance() === 1500.0, 'Reconstituted aggregate has balance 1500.0');
t($reconstituted->getVersion() === 2, 'Reconstituted aggregate version is 2');
t(empty($reconstituted->getUncommittedEvents()), 'Reconstituted aggregate has 0 uncommitted events');

$reconstituted->withdraw(300.0);
$repo->save($reconstituted);

$streamV3 = $store->loadStream('acc-002');
t($streamV3->getVersion() === 3, 'Stream updated to version 3 after new transaction');
t(count($streamV3->getEvents()) === 3, 'Stream now has 3 events');

// =============================================================================
// Section 5: Optimistic Concurrency Control (OCC)
// =============================================================================
echo "\033[36m[Section 5: Optimistic Concurrency Control (OCC)]\033[0m\n";

$concurrencyThrew = false;
try {
    $conflictEvent = new MoneyDepositedEvent(['amount' => 50.0]);
    $store->append('acc-002', BankAccountAggregate::class, [$conflictEvent], 1);
} catch (ConcurrencyException $e) {
    $concurrencyThrew = true;
}
t($concurrencyThrew, 'ConcurrencyException thrown when expected version does not match stream');

// =============================================================================
// Section 6: Projection Replay Engine
// =============================================================================
echo "\033[36m[Section 6: Projection Replay Engine]\033[0m\n";

$freshProj = new AccountSummaryProjection();
t(empty($freshProj->accounts), 'Fresh projection starts empty');

$replayed = $store->replayStream('acc-002', [$freshProj]);
t($replayed === 3, 'Replay stream replayed 3 events');
t($freshProj->accounts['acc-002']['balance'] === 1200.0, 'Projection after replay matches 1200.0 balance');
t($freshProj->accounts['acc-002']['tx_count'] === 3, 'Projection after replay has 3 transactions');

$allReplayed = $store->replayAll([$freshProj]);
t($allReplayed >= 3, 'Replay all projected all stored events');

// =============================================================================
// Section 7: CQRS Command & Query Buses
// =============================================================================
echo "\033[36m[Section 7: CQRS Command & Query Buses]\033[0m\n";

$bus = new CommandBus();
$pipeLog = [];
$bus->pipe(function ($cmd, $next) use (&$pipeLog) {
    $pipeLog[] = 'before:' . (new ReflectionClass($cmd))->getShortName();
    $res = $next($cmd);
    $pipeLog[] = 'after:' . (new ReflectionClass($cmd))->getShortName();
    return $res;
});

$bus->register(OpenAccountCommand::class, function (OpenAccountCommand $cmd) use ($repo) {
    $acc = BankAccountAggregate::open($cmd->id, $cmd->holder, $cmd->initialDeposit);
    $repo->save($acc);
    return $acc->getId();
});

$createdId = $bus->dispatch(new OpenAccountCommand('acc-003', 'Jamal', 2000.0));
t($createdId === 'acc-003', 'CommandBus executed handler and returned acc-003');
t($pipeLog === ['before:OpenAccountCommand', 'after:OpenAccountCommand'], 'CommandBus middleware pipeline executed');

$qBus = new QueryBus();
$qBus->register(GetAccountBalanceQuery::class, function (GetAccountBalanceQuery $q) use ($repo) {
    $acc = $repo->load(BankAccountAggregate::class, $q->id);
    return $acc->getBalance();
});

$bal = $qBus->ask(new GetAccountBalanceQuery('acc-003'));
t($bal === 2000.0, 'QueryBus executed query and returned balance 2000.0');

// =============================================================================
// Section 8: Global Helpers & Facade
// =============================================================================
echo "\033[36m[Section 8: Global Helpers & Facade]\033[0m\n";

$esManager = eventsource();
t($esManager instanceof EventSourcingManager, 'eventsource() returns EventSourcingManager');
t(command_bus() instanceof CommandBus, 'command_bus() returns CommandBus');
t(query_bus() instanceof QueryBus, 'query_bus() returns QueryBus');

echo "\n=================================================================\n";
echo "  Results: {$passed}/{$total} Passed (" . round(($passed / $total) * 100) . "%)\n";
echo "=================================================================\n\n";

// Cleanup sqlite file
if (file_exists($sqlitePath)) {
    @unlink($sqlitePath);
}

if ($failed > 0) {
    exit(1);
}