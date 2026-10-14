<?php

/**
 * NetFusion Phase 54 Test Suite:
 * Enterprise Distributed Actor Model, Virtual Actors & Reactive Message Mesh Engine
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Actors\ActorSystem;
use NetFusion\Core\Actors\Contracts\ActorRefInterface;
use NetFusion\Core\Actors\Core\AbstractActor;
use NetFusion\Core\Actors\Core\Envelope;
use NetFusion\Core\Actors\Core\DeadLetter;
use NetFusion\Core\Actors\Mailbox\PriorityMailbox;
use NetFusion\Core\Actors\Mailbox\UnboundedMailbox;
use NetFusion\Core\Actors\Persistence\AbstractPersistentActor;
use NetFusion\Core\Actors\Persistence\Store\ArrayActorStateStore;
use NetFusion\Core\Actors\Persistence\Store\DatabaseActorStateStore;
use NetFusion\Core\Actors\Supervision\OneForOneStrategy;
use NetFusion\Core\Actors\Supervision\SupervisorStrategy;

echo "\n=======================================================\n";
echo "   NetFusion Phase 54: Distributed Actor Model Engine  \n";
echo "=======================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "✔ [PASS] {$description}\n";
    } else {
        $failed++;
        echo "✖ [FAIL] {$description}\n";
    }
}

// -----------------------------------------------------------------------------
// Sample Test Actors
// -----------------------------------------------------------------------------

class CalculatorActor extends AbstractActor
{
    public int $count = 0;
    public bool $started = false;
    public bool $stopped = false;

    public function preStart(): void
    {
        $this->started = true;
    }

    public function postStop(): void
    {
        $this->stopped = true;
    }

    public function receive(mixed $message): mixed
    {
        if (is_array($message) && isset($message['op'])) {
            return match ($message['op']) {
                'add' => ($message['a'] ?? 0) + ($message['b'] ?? 0),
                'inc' => ++$this->count,
                'get' => $this->count,
                'fail' => throw new RuntimeException("Simulated actor failure"),
                default => 'unknown',
            };
        }

        if ($message === 'ping') {
            return 'pong';
        }

        return null;
    }
}

class StateMachineActor extends AbstractActor
{
    public string $state = 'idle';

    public function receive(mixed $message): mixed
    {
        if ($message === 'work') {
            $this->state = 'busy';
            $this->become(function (mixed $msg) {
                if ($msg === 'finish') {
                    $this->state = 'idle';
                    $this->unbecome();
                    return 'done';
                }
                return 'busy_handling_' . $msg;
            });
            return 'started';
        }

        return 'idle_handling_' . $message;
    }
}

class ParentSupervisorActor extends AbstractActor
{
    public ?ActorRefInterface $worker = null;

    public function preStart(): void
    {
        $this->worker = $this->getContext()->actorOf(CalculatorActor::class, 'worker');
    }

    public function supervisorStrategy(): SupervisorStrategy
    {
        return new OneForOneStrategy(maxRetries: 5, withinTimeRange: 60.0);
    }

    public function receive(mixed $message): mixed
    {
        if ($message === 'get_worker') {
            return $this->worker;
        }

        if (is_array($message) && isset($message['forward'])) {
            return $this->worker?->ask($message['forward']);
        }

        return null;
    }
}

class BankAccountActor extends AbstractPersistentActor
{
    public float $balance = 0.0;
    public int $transactionCount = 0;

    public function persistenceId(): string
    {
        return 'account_101';
    }

    public function receive(mixed $message): mixed
    {
        if (is_array($message) && isset($message['type'])) {
            if ($message['type'] === 'deposit') {
                $amount = (float)$message['amount'];
                $this->persist(['type' => 'deposited', 'amount' => $amount], function ($event) {
                    $this->balance += $event['amount'];
                    $this->transactionCount++;
                });
                return $this->balance;
            }

            if ($message['type'] === 'withdraw') {
                $amount = (float)$message['amount'];
                if ($this->balance >= $amount) {
                    $this->persist(['type' => 'withdrawn', 'amount' => $amount], function ($event) {
                        $this->balance -= $event['amount'];
                        $this->transactionCount++;
                    });
                    return $this->balance;
                }
                return false;
            }

            if ($message['type'] === 'snapshot') {
                $this->saveSnapshot(['balance' => $this->balance, 'transactions' => $this->transactionCount]);
                return true;
            }

            if ($message['type'] === 'balance') {
                return $this->balance;
            }
        }

        return null;
    }

    public function receiveRecover(mixed $data, bool $isSnapshot = false): void
    {
        if ($isSnapshot && is_array($data)) {
            $this->balance = (float)($data['balance'] ?? 0.0);
            $this->transactionCount = (int)($data['transactions'] ?? 0);
            return;
        }

        if (is_array($data)) {
            if ($data['type'] === 'deposited') {
                $this->balance += (float)$data['amount'];
                $this->transactionCount++;
            } elseif ($data['type'] === 'withdrawn') {
                $this->balance -= (float)$data['amount'];
                $this->transactionCount++;
            }
        }
    }
}

// -----------------------------------------------------------------------------
// 1. Actor System Initialization & Tell/Ask Messaging
// -----------------------------------------------------------------------------
echo "--- 1. Actor System & Tell/Ask Messaging ---\n";

$system = ActorSystem::create('cluster-node-1');
$calcRef = $system->actorOf(CalculatorActor::class, 'calc');

assertTest('ActorSystem: Spawns actor with hierarchical path',
    $calcRef->path() === 'netfusion://cluster-node-1/user/calc' &&
    $calcRef->name() === 'calc' &&
    $calcRef->isAlive()
);

// ask() request-response
$pong = $calcRef->ask('ping');
assertTest('LocalActorRef: ask() synchronously returns expected response', $pong === 'pong');

$sum = $calcRef->ask(['op' => 'add', 'a' => 45, 'b' => 55]);
assertTest('LocalActorRef: ask() computes arithmetic calculation accurately', $sum === 100);

// tell() asynchronous fire-and-forget
$calcRef->tell(['op' => 'inc']);
$calcRef->tell(['op' => 'inc']);
$system->runUntilIdle();

$count = $calcRef->ask(['op' => 'get']);
assertTest('LocalActorRef: tell() enqueues messages and updates actor state', $count === 2);

// -----------------------------------------------------------------------------
// 2. Dynamic Behavior Switching (become / unbecome)
// -----------------------------------------------------------------------------
echo "\n--- 2. Behavior Switching (become/unbecome) ---\n";

$fsmRef = $system->actorOf(StateMachineActor::class, 'fsm');

$initRes = $fsmRef->ask('check');
assertTest('AbstractActor: Initial receive behavior active', $initRes === 'idle_handling_check');

$fsmRef->ask('work');
$busyRes = $fsmRef->ask('query');
assertTest('AbstractActor: become() switches receive behavior to busy handler', $busyRes === 'busy_handling_query');

$doneRes = $fsmRef->ask('finish');
$afterRes = $fsmRef->ask('check');
assertTest('AbstractActor: unbecome() reverts receive behavior back to original',
    $doneRes === 'done' && $afterRes === 'idle_handling_check'
);

// -----------------------------------------------------------------------------
// 3. Hierarchical Child Spawning & Supervision (OneForOneStrategy)
// -----------------------------------------------------------------------------
echo "\n--- 3. Supervision & Fault Tolerance (OneForOneStrategy) ---\n";

$parentRef = $system->actorOf(ParentSupervisorActor::class, 'manager');
$workerRef = $parentRef->ask('get_worker');

assertTest('ActorContext: Spawns child actor under parent hierarchy',
    $workerRef instanceof ActorRefInterface &&
    $workerRef->path() === 'netfusion://cluster-node-1/user/manager/worker'
);

$parentRef->tell(['forward' => ['op' => 'inc']]);
$system->runUntilIdle();

// Trigger failure in worker
try {
    $parentRef->ask(['forward' => ['op' => 'fail']]);
} catch (Throwable) {
    // Expected simulation
}

// Check worker was restarted by supervisor and is still alive
assertTest('OneForOneStrategy: Recovers and restarts failing child actor',
    $workerRef->isAlive() &&
    $parentRef->isAlive()
);

$afterRestart = $parentRef->ask(['forward' => 'ping']);
assertTest('Supervisor: Child actor resumes processing messages after restart', $afterRestart === 'pong');

// -----------------------------------------------------------------------------
// 4. Priority Mailbox & Ordering
// -----------------------------------------------------------------------------
echo "\n--- 4. Mailbox & Dead Letters ---\n";

$pMailbox = new PriorityMailbox();
$pMailbox->enqueue(Envelope::create('low_msg', null, 0));
$pMailbox->enqueue(Envelope::create('urgent_msg', null, 10));
$pMailbox->enqueue(Envelope::create('med_msg', null, 5));

$first = $pMailbox->dequeue();
$second = $pMailbox->dequeue();
$third = $pMailbox->dequeue();

assertTest('PriorityMailbox: Dequeues messages strictly ordered by priority (10 -> 5 -> 0)',
    $first?->message === 'urgent_msg' &&
    $second?->message === 'med_msg' &&
    $third?->message === 'low_msg'
);

// Dead Letters
$system->tell('non_existent_actor', 'hello_ghost');
$deadLetters = $system->getDeadLetters()->all();

assertTest('DeadLetterQueue: Captures undeliverable messages sent to invalid actor paths',
    count($deadLetters) >= 1 &&
    $deadLetters[0]->message === 'hello_ghost'
);

// -----------------------------------------------------------------------------
// 5. Stateful Event Sourcing & Snapshot Persistence
// -----------------------------------------------------------------------------
echo "\n--- 5. Persistent Actors & Database Snapshots ---\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$migration = require __DIR__ . '/../database/migrations/2026_09_20_000034_create_actor_system_tables.php';
$migration->up($pdo);

$dbStateStore = new DatabaseActorStateStore($pdo);
$persistentSystem = new ActorSystem('persistent-node', $dbStateStore);

$bankRef = $persistentSystem->virtual(BankAccountActor::class, 'acc_101');

$bankRef->ask(['type' => 'deposit', 'amount' => 500.0]);
$bankRef->ask(['type' => 'deposit', 'amount' => 200.0]);
$bankRef->ask(['type' => 'withdraw', 'amount' => 150.0]);
$bankRef->ask(['type' => 'snapshot']);
$bankRef->ask(['type' => 'deposit', 'amount' => 50.0]);

$balanceBefore = $bankRef->ask(['type' => 'balance']);
assertTest('AbstractPersistentActor: Deposits and withdraws balance via event journaling ($600.0)',
    $balanceBefore === 600.0
);

// Simulate node restart: create fresh system and reactivate virtual actor
$restartedSystem = new ActorSystem('persistent-node-2', $dbStateStore);
$rehydratedBankRef = $restartedSystem->virtual(BankAccountActor::class, 'acc_101');
$rehydratedBalance = $rehydratedBankRef->ask(['type' => 'balance']);

assertTest('AbstractPersistentActor: Reconstitutes state from snapshot + replayed journal events ($600.0)',
    $rehydratedBalance === 600.0
);

// Down Migration test
$migration->down($pdo);
$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='netfusion_actor_snapshots'");
$dropped = $stmt && $stmt->fetchColumn() === false;
assertTest('Database Migration: Dropped actor system tables cleanly on down()', $dropped);

// -----------------------------------------------------------------------------
// 6. Global Helpers & DI Resolution
// -----------------------------------------------------------------------------
echo "\n--- 6. Global Helpers & DI Resolution ---\n";

$app = new Application(dirname(__DIR__));
$app->instance(ActorSystem::class, $system);
$app->instance('actors', $system);

assertTest('Global Helper: actor_system() returns ActorSystem instance', actor_system() instanceof ActorSystem);

$topRef = actor_of(CalculatorActor::class, 'global_calc');
assertTest('Global Helper: actor_of() registers actor in system', $topRef instanceof ActorRefInterface);

assertTest('Global Helper: actor_select() resolves actor by path', actor_select('global_calc') !== null);
assertTest('Global Helper: actor_ask() executes synchronous calculation', actor_ask('global_calc', ['op' => 'add', 'a' => 10, 'b' => 20]) === 30);

$tellSuccess = actor_tell('global_calc', ['op' => 'inc']);
assertTest('Global Helper: actor_tell() dispatches message', $tellSuccess === true);

echo "\n=======================================================\n";
echo "   Phase 54 Tests Completed: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit($failed > 0 ? 1 : 0);
