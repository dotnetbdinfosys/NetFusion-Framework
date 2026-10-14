<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase28.php
| Module    : Tests
| Purpose   : Automated verification suite for Phase 28 (Real-Time Broadcasting & SSE).
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

use NetFusion\App\Http\Controllers\BroadcastStreamController;
use NetFusion\Core\Application;
use NetFusion\Core\Broadcasting\BroadcastManager;
use NetFusion\Core\Broadcasting\Channels\Channel;
use NetFusion\Core\Broadcasting\Channels\PresenceChannel;
use NetFusion\Core\Broadcasting\Channels\PrivateChannel;
use NetFusion\Core\Broadcasting\Contracts\ShouldBroadcast;
use NetFusion\Core\Broadcasting\Drivers\LogBroadcastDriver;
use NetFusion\Core\Broadcasting\Drivers\NullBroadcastDriver;
use NetFusion\Core\Broadcasting\Drivers\SseBroadcastDriver;
use NetFusion\Core\Events\Dispatcher;
use NetFusion\Core\Http\Request;

$basePath = realpath(__DIR__ . '/..');
$app = new Application($basePath);

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $label, string $details = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS\033[0m: {$label}\n";
    } else {
        $failed++;
        echo "  \033[31m✖ FAIL\033[0m: {$label}\n";
        if ($details !== '') {
            echo "         Detail: {$details}\n";
        }
    }
}

echo "\n============================================================\n";
echo "  NetFusion Framework — Phase 28 (Real-Time Broadcasting) Test Suite\n";
echo "============================================================\n\n";

// -------------------------------------------------------------
// 1. Channel Models & Types
// -------------------------------------------------------------
echo "\033[33m--- 1. Channel Model & Types ---\033[0m\n";
$publicChannel = new Channel('orders');
assertTest($publicChannel->getName() === 'orders', "Public channel returns assigned name");
assertTest(!$publicChannel->isPrivate() && !$publicChannel->isPresence(), "Public channel is neither private nor presence");
assertTest((string) $publicChannel === 'orders', "Public channel string casts to its name");

$privateChannel = new PrivateChannel('user.42');
assertTest($privateChannel->getName() === 'private-user.42', "PrivateChannel prefixes channel name with 'private-'");
assertTest($privateChannel->isPrivate() && !$privateChannel->isPresence(), "PrivateChannel identifies as private");

$presenceChannel = new PresenceChannel('room.dev');
assertTest($presenceChannel->getName() === 'presence-room.dev', "PresenceChannel prefixes channel name with 'presence-'");
assertTest($presenceChannel->isPrivate() && $presenceChannel->isPresence(), "PresenceChannel identifies as private and presence");


// -------------------------------------------------------------
// 2. Transport Drivers (Null, Log, SSE)
// -------------------------------------------------------------
echo "\n\033[33m--- 2. Broadcast Drivers ---\033[0m\n";

// NullDriver
$nullDriver = new NullBroadcastDriver();
$nullDriver->broadcast(['test'], 'NullEvent', ['foo' => 'bar']);
assertTest(true, "NullBroadcastDriver executes without errors");

// LogDriver
$logDriver = new LogBroadcastDriver();
$logDriver->broadcast(['public-chat', $privateChannel], 'ChatMessage', ['user' => 'Ashish', 'msg' => 'Hello']);
$logged = $logDriver->getEvents();
assertTest(count($logged) === 1, "LogBroadcastDriver records event in memory buffer");
assertTest($logged[0]['event'] === 'ChatMessage', "LogBroadcastDriver preserves event name");
assertTest(in_array('private-user.42', $logged[0]['channels'], true), "LogBroadcastDriver normalizes channel objects to string names");

// SseDriver
$tempSseDir = __DIR__ . '/temp_sse';
$sseDriver = new SseBroadcastDriver($tempSseDir);
$sseDriver->clear();

$sseDriver->broadcast(['notifications', 'dashboard'], 'MetricUpdated', ['kpi' => 'sales', 'val' => 5000]);
$eventsSince0 = $sseDriver->getEventsSince(['dashboard'], 0);
assertTest(count($eventsSince0) >= 1, "SseBroadcastDriver queues and retrieves events by channel");
assertTest($eventsSince0[0]['event'] === 'MetricUpdated', "SseBroadcastDriver retrieves exact event payload");

$firstEventId = $eventsSince0[0]['id'];
$eventsSinceCurrent = $sseDriver->getEventsSince(['dashboard'], $firstEventId);
assertTest(count($eventsSinceCurrent) === 0, "SseBroadcastDriver Last-Event-ID filter excludes older events");

// SSE wire-format validation
$sseFormatted = $sseDriver->formatSseMessage('OrderShipped', ['order_id' => 88], 1001, 2500);
assertTest(str_contains($sseFormatted, "id: 1001\n"), "SSE format includes event ID line");
assertTest(str_contains($sseFormatted, "retry: 2500\n"), "SSE format includes retry line");
assertTest(str_contains($sseFormatted, "event: OrderShipped\n"), "SSE format includes event name line");
assertTest(str_contains($sseFormatted, "data: {\"order_id\":88}\n\n"), "SSE format outputs JSON data with double trailing newline");

$heartbeat = $sseDriver->formatHeartbeat();
assertTest($heartbeat === ": ping\n\n", "SSE heartbeat outputs standard comment ping line");

$sseDriver->clear();
@rmdir($tempSseDir);


// -------------------------------------------------------------
// 3. BroadcastManager & Channel Authorization Gates
// -------------------------------------------------------------
echo "\n\033[33m--- 3. BroadcastManager & Channel Security Gates ---\033[0m\n";

/** @var BroadcastManager $manager */
$manager = $app->make(BroadcastManager::class);
assertTest($manager->driver('log') instanceof LogBroadcastDriver, "BroadcastManager resolves Log driver");
assertTest($manager->driver('sse') instanceof SseBroadcastDriver, "BroadcastManager resolves SSE driver");
assertTest($manager->driver('null') instanceof NullBroadcastDriver, "BroadcastManager resolves Null driver");

// Channel authorization rules
$manager->channel('private-user.{id}', function ($user, $id) {
    return $user !== null && (int) ($user['id'] ?? 0) === (int) $id;
});
$manager->channel('presence-room.{name}', function ($user, $name) {
    if (!$user) {
        return false;
    }
    return ['user_id' => $user['id'], 'user_name' => $user['name']];
});

// Test Public channel auth
$dummyReq = Request::create('GET', '/');
assertTest($manager->authenticate($dummyReq, 'public-news') === true, "Public channels are authenticated unconditionally");

// Test Unauthenticated request to private channel
assertTest($manager->authenticate($dummyReq, 'private-user.5') === false, "Unauthenticated request to private channel is rejected");

// Test Authenticated request matching user ID
$authReq = Request::create('GET', '/');
$authReq->setAttribute('user', ['id' => 5, 'name' => 'Ashish']);
assertTest($manager->authenticate($authReq, 'private-user.5') === true, "Authenticated user matching parameter pattern is approved");
assertTest($manager->authenticate($authReq, 'private-user.99') === false, "Authenticated user not matching parameter ID is rejected");

// Test Presence channel returning user data
$presenceAuth = $manager->authenticate($authReq, 'presence-room.developers');
assertTest(is_array($presenceAuth) && ($presenceAuth['user_id'] ?? 0) === 5, "Presence channel authentication returns user presence payload");


// -------------------------------------------------------------
// 4. ShouldBroadcast Interface & Event Auto-Broadcasting
// -------------------------------------------------------------
echo "\n\033[33m--- 4. ShouldBroadcast & Event Dispatcher Integration ---\033[0m\n";

class TestInvoiceCreatedEvent implements ShouldBroadcast
{
    public function __construct(
        public int $invoiceId,
        public float $amount
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('invoices'), new PrivateChannel('client.12')];
    }

    public function broadcastAs(): ?string
    {
        return 'invoice.created';
    }

    public function broadcastWith(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'amount'     => $this->amount,
            'currency'   => 'USD',
        ];
    }
}

// Swap broadcast driver with Log driver to inspect dispatched events
$app->instance('broadcast', $manager);
$logDriver->clear();
$manager->extend('log_test', fn() => $logDriver);
$app->make(NetFusion\Core\Config\Config::class)->set('broadcasting.default', 'log_test');

/** @var Dispatcher $eventDispatcher */
$eventDispatcher = $app->make(Dispatcher::class);
$eventDispatcher->dispatch(new TestInvoiceCreatedEvent(7001, 1250.50));

$dispatchedEvents = $logDriver->getEvents();
assertTest(count($dispatchedEvents) === 1, "Dispatcher automatically broadcasts ShouldBroadcast events upon dispatch");
assertTest($dispatchedEvents[0]['event'] === 'invoice.created', "Broadcasted event has custom broadcastAs name");
assertTest(in_array('invoices', $dispatchedEvents[0]['channels'], true) && in_array('private-client.12', $dispatchedEvents[0]['channels'], true), "Event was sent to all channels defined in broadcastOn");
assertTest(($dispatchedEvents[0]['payload']['invoice_id'] ?? 0) === 7001, "Broadcasted event contains payload from broadcastWith");


// -------------------------------------------------------------
// 5. Global Helper Function broadcast()
// -------------------------------------------------------------
echo "\n\033[33m--- 5. Global Helper broadcast() ---\033[0m\n";

assertTest(function_exists('broadcast'), "Global helper broadcast() is defined");
assertTest(broadcast() instanceof BroadcastManager, "broadcast() with no arguments returns BroadcastManager instance");

$logDriver->clear();
broadcast('system-alerts', 'MaintenanceWarning', ['time' => '10:00 PM']);
assertTest(count($logDriver->getEvents()) === 1, "broadcast(\$channel, \$event, \$payload) broadcasts to transport immediately");


// -------------------------------------------------------------
// 6. HTTP Controller & Route Endpoints
// -------------------------------------------------------------
echo "\n\033[33m--- 6. HTTP Controller & SSE Stream Endpoints ---\033[0m\n";

// Reset default driver to sse
$app->make(NetFusion\Core\Config\Config::class)->set('broadcasting.default', 'sse');
$controller = new BroadcastStreamController($manager);

// 6.1 Channel Auth Endpoint (unauthorized)
$authRequestBad = Request::create('POST', '/api/v1/broadcasting/auth', ['channel_name' => 'private-user.999']);
$authResponseBad = $controller->auth($authRequestBad);
assertTest($authResponseBad->getStatusCode() === 403, "BroadcastStreamController returns HTTP 403 for unauthorized channel");

// 6.2 Channel Auth Endpoint (authorized)
$authRequestGood = Request::create('POST', '/api/v1/broadcasting/auth', ['channel_name' => 'private-user.5']);
$authRequestGood->setAttribute('user', ['id' => 5, 'name' => 'Ashish']);
$authResponseGood = $controller->auth($authRequestGood);
assertTest($authResponseGood->getStatusCode() === 200, "BroadcastStreamController returns HTTP 200 for authorized private channel");
$goodJson = json_decode($authResponseGood->getContent(), true);
assertTest(($goodJson['auth'] ?? false) === true, "Auth response contains auth: true");

// 6.3 SSE Stream Endpoint
$streamReq = Request::create('GET', '/api/v1/broadcasting/stream?channel=public-news&once=1', [], ['X-Test-Mode' => '1']);
$streamRes = $controller->stream($streamReq);
assertTest($streamRes->getStatusCode() === 200, "SSE stream returns HTTP 200");
assertTest(str_contains($streamRes->getHeader('Content-Type') ?? '', 'text/event-stream'), "SSE stream response has Content-Type: text/event-stream");
assertTest(str_contains($streamRes->getContent(), ': ping'), "SSE stream outputs initial heartbeat");

// Clean up any remaining test SSE files
$manager->driver('sse')->clear();

echo "\n============================================================\n";
echo "  Total Passed: {$passed} | Total Failed: {$failed}\n";
echo "============================================================\n\n";

exit($failed > 0 ? 1 : 0);
