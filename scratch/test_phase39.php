<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Phase 39 Test Suite
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase39.php
| Module    : Lock
| Purpose   : Comprehensive tests for Distributed Locks, Drivers,
|             Spinlocks, Closure Wrappers, and CLI Commands.
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
use NetFusion\Core\Lock\Contracts\LockDriverInterface;
use NetFusion\Core\Lock\Contracts\LockInterface;
use NetFusion\Core\Lock\Drivers\ArrayLockDriver;
use NetFusion\Core\Lock\Drivers\DatabaseLockDriver;
use NetFusion\Core\Lock\Drivers\FileAtomicLockDriver;
use NetFusion\Core\Lock\Exceptions\LockTimeoutException;
use NetFusion\Core\Lock\Lock;
use NetFusion\Core\Lock\LockManager;

$basePath = realpath(__DIR__ . '/..');
$app      = new Application($basePath);

$sqlitePath = __DIR__ . '/test_phase39.sqlite';
if (file_exists($sqlitePath)) {
    @unlink($sqlitePath);
}
touch($sqlitePath);

$fileLockDir = __DIR__ . '/test_locks';
if (is_dir($fileLockDir)) {
    array_map('unlink', glob("$fileLockDir/*.*"));
    @rmdir($fileLockDir);
}
@mkdir($fileLockDir, 0755, true);

$app->make(Config::class)->set('database.default', 'sqlite');
$app->make(Config::class)->set('database.connections.sqlite.database', $sqlitePath);
$app->make(Config::class)->set('lock.storage_path', $fileLockDir);

/** @var DatabaseManager $db */
$db   = $app->make(DatabaseManager::class);
$conn = $db->connection('sqlite');

// Run migrations
$migration = require __DIR__ . '/../database/migrations/2026_09_18_000021_create_locks_table.php';
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
echo "  NetFusion Phase 39 — Distributed Lock Engine Tests\n";
echo "=================================================================\n\n";

// =============================================================================
// Section 1: In-Memory ArrayLockDriver
// =============================================================================
echo "\033[36m[Section 1: In-Memory ArrayLockDriver]\033[0m\n";

$arrayDriver = new ArrayLockDriver();
t($arrayDriver->acquire('job-1', 'worker-A', 10) === true, 'ArrayLockDriver acquires fresh lock');
t($arrayDriver->isLocked('job-1') === true, 'ArrayLockDriver verifies resource is locked');
t($arrayDriver->getCurrentOwner('job-1') === 'worker-A', 'ArrayLockDriver returns correct owner');
t($arrayDriver->acquire('job-1', 'worker-B', 10) === false, 'ArrayLockDriver rejects acquisition by different owner');
t($arrayDriver->acquire('job-1', 'worker-A', 10) === true, 'ArrayLockDriver re-acquires/renews lock for same owner');
t($arrayDriver->release('job-1', 'worker-B') === false, 'ArrayLockDriver prevents release by non-owner');
t($arrayDriver->release('job-1', 'worker-A') === true, 'ArrayLockDriver releases lock when owner matches');
t($arrayDriver->isLocked('job-1') === false, 'ArrayLockDriver confirms lock is released');

// Expiration test
$arrayDriver->acquire('expired-job', 'worker-A', -1); // Expired immediately
t($arrayDriver->isLocked('expired-job') === false, 'ArrayLockDriver considers expired lock unlocked');
t($arrayDriver->acquire('expired-job', 'worker-B', 10) === true, 'ArrayLockDriver allows acquisition over expired lock');

// =============================================================================
// Section 2: DatabaseLockDriver (Atomic SQLite / MySQL Engine)
// =============================================================================
echo "\033[36m[Section 2: DatabaseLockDriver]\033[0m\n";

$dbDriver = new DatabaseLockDriver($db);
t($dbDriver->acquire('order-sync', 'worker-1', 60) === true, 'DatabaseLockDriver inserts atomic lock record');
t($dbDriver->isLocked('order-sync') === true, 'DatabaseLockDriver checks active lock in database');
t($dbDriver->getCurrentOwner('order-sync') === 'worker-1', 'DatabaseLockDriver returns owner from DB');
t($dbDriver->acquire('order-sync', 'worker-2', 60) === false, 'DatabaseLockDriver denies concurrent worker acquisition');
t($dbDriver->release('order-sync', 'worker-2') === false, 'DatabaseLockDriver rejects release by unauthorized worker');
t($dbDriver->release('order-sync', 'worker-1') === true, 'DatabaseLockDriver successfully releases lock for owner');
t($dbDriver->isLocked('order-sync') === false, 'DatabaseLockDriver confirms lock record cleared');

// Force release & Clean expired
$dbDriver->acquire('stuck-lock', 'dead-worker', 100);
$dbDriver->forceRelease('stuck-lock');
t($dbDriver->isLocked('stuck-lock') === false, 'DatabaseLockDriver forceRelease removes lock without owner check');

$dbDriver->acquire('old-1', 'worker-1', -10);
$dbDriver->acquire('old-2', 'worker-2', -5);
$dbDriver->acquire('live-1', 'worker-3', 100);
$pruned = $dbDriver->cleanExpired();
t($pruned >= 2, 'DatabaseLockDriver cleanExpired prunes expired records');
t($dbDriver->isLocked('live-1') === true, 'DatabaseLockDriver preserves active locks during cleanup');

// =============================================================================
// Section 3: FileAtomicLockDriver
// =============================================================================
echo "\033[36m[Section 3: FileAtomicLockDriver]\033[0m\n";

$fileDriver = new FileAtomicLockDriver($fileLockDir);
t($fileDriver->acquire('invoice-batch', 'proc-1', 60) === true, 'FileAtomicLockDriver acquires file-based atomic lock');
t($fileDriver->isLocked('invoice-batch') === true, 'FileAtomicLockDriver reports file is locked');
t($fileDriver->getCurrentOwner('invoice-batch') === 'proc-1', 'FileAtomicLockDriver returns proc-1 owner');
t($fileDriver->acquire('invoice-batch', 'proc-2', 60) === false, 'FileAtomicLockDriver denies proc-2');
t($fileDriver->release('invoice-batch', 'proc-1') === true, 'FileAtomicLockDriver releases lock file');
t($fileDriver->isLocked('invoice-batch') === false, 'FileAtomicLockDriver reports file unlocked');

// =============================================================================
// Section 4: Lock Value Object & High-Concurrency Helpers
// =============================================================================
echo "\033[36m[Section 4: Lock Value Object & Closure Wrapper]\033[0m\n";

$lock = new Lock($dbDriver, 'payment-process', 30, 'thread-A');
t($lock->getName() === 'payment-process', 'Lock::getName() returns resource name');
t($lock->getOwner() === 'thread-A', 'Lock::getOwner() returns owner identifier');

$executed = false;
$result = $lock->get(function () use (&$executed, $lock) {
    $executed = true;
    t($lock->isAcquiredByCurrentProcess() === true, 'Lock is active inside get() callback');
    return 'PAYMENT_SUCCESS';
});

t($executed === true, 'Lock::get() executed the given closure');
t($result === 'PAYMENT_SUCCESS', 'Lock::get() returned closure result');
t($lock->isAcquired() === false, 'Lock::get() automatically released lock in finally block');

// Test closure exception safety
$exceptionThrown = false;
try {
    $lock->get(function () {
        throw new \RuntimeException("Payment gateway failure");
    });
} catch (\RuntimeException $e) {
    $exceptionThrown = true;
}
t($exceptionThrown === true, 'Exception propagated out of get()');
t($lock->isAcquired() === false, 'Lock automatically released even when callback throws Exception');

// =============================================================================
// Section 5: Spinlock & Blocking Lock
// =============================================================================
echo "\033[36m[Section 5: Spinlock & Blocking Lock]\033[0m\n";

$blockingLock = new Lock($dbDriver, 'high-load-queue', 10, 'owner-block');
$timedOut = false;
try {
    // Acquire first so next block() will timeout
    $blockingLock->acquire();

    $competingLock = new Lock($dbDriver, 'high-load-queue', 10, 'competing-worker');
    $competingLock->block(1); // Wait max 1 second
} catch (LockTimeoutException $e) {
    $timedOut = true;
}
t($timedOut === true, 'Lock::block() throws LockTimeoutException on timeout');
$blockingLock->release();

// Test successful block acquisition after quick release
$blockingLock2 = new Lock($arrayDriver, 'quick-lock', 10, 'first');
$blockingLock2->acquire();

$competing2 = new Lock($arrayDriver, 'quick-lock', 10, 'second');
// Simulate early release
$arrayDriver->release('quick-lock', 'first');
$blockResult = $competing2->block(2, function () {
    return 'BLOCKED_SUCCESS';
});
t($blockResult === 'BLOCKED_SUCCESS', 'Lock::block() acquired lock and returned callback result');

// =============================================================================
// Section 6: LockManager Facade & Global Helpers
// =============================================================================
echo "\033[36m[Section 6: LockManager Facade & Global Helpers]\033[0m\n";

/** @var LockManager $manager */
$manager = $app->make(LockManager::class);
t($manager->driver('database') instanceof DatabaseLockDriver, 'LockManager resolves DatabaseLockDriver');
t($manager->driver('file') instanceof FileAtomicLockDriver, 'LockManager resolves FileAtomicLockDriver');
t($manager->driver('array') instanceof ArrayLockDriver, 'LockManager resolves ArrayLockDriver');

$namedLock = $manager->create('cron-sync', 30);
t($namedLock instanceof LockInterface, 'LockManager::create() returns LockInterface instance');

$res = $manager->get('daily-backup', function () {
    return 'BACKUP_DONE';
});
t($res === 'BACKUP_DONE', 'LockManager::get() executes and returns callback result');

t(function_exists('lock'), 'Global lock() helper function exists');
t(lock() instanceof LockManager, 'lock() without arguments returns LockManager');
t(lock('cron-job', 60) instanceof LockInterface, 'lock("cron-job", 60) returns Lock instance');

echo "\n=================================================================\n";
echo "  Results: {$passed}/{$total} Passed (" . round(($passed / $total) * 100) . "%)\n";
echo "=================================================================\n\n";

// Cleanup sqlite file & lock dir
if (file_exists($sqlitePath)) {
    @unlink($sqlitePath);
}
if (is_dir($fileLockDir)) {
    array_map('unlink', glob("$fileLockDir/*.*"));
    @rmdir($fileLockDir);
}

if ($failed > 0) {
    exit(1);
}