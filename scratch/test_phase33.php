<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework - Phase 33 Test Suite
|--------------------------------------------------------------------------
|
| Automated tests for Enterprise Multi-Channel Notification Engine.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Broadcasting\BroadcastManager;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Database\Model;
use NetFusion\Core\Mail\Mailer;
use NetFusion\Core\Mail\Message;
use NetFusion\Core\Mail\Transports\ArrayTransport;
use NetFusion\Core\Notifications\Channels\BroadcastChannel;
use NetFusion\Core\Notifications\Channels\DatabaseChannel;
use NetFusion\Core\Notifications\Channels\MailChannel;
use NetFusion\Core\Notifications\Channels\SmsChannel;
use NetFusion\Core\Notifications\Channels\WebhookChannel;
use NetFusion\Core\Notifications\Contracts\NotificationChannelInterface;
use NetFusion\Core\Notifications\DatabaseNotification;
use NetFusion\Core\Notifications\Notifiable;
use NetFusion\Core\Notifications\Notification;
use NetFusion\Core\Notifications\NotificationManager;
use NetFusion\Core\Notifications\SendQueuedNotificationJob;
use NetFusion\Core\Queue\Contracts\ShouldQueue;
use NetFusion\Core\Queue\QueueManager;

$basePath = realpath(__DIR__ . '/..');
$app = new Application($basePath);

$passed = 0;
$failed = 0;

function assert_test(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS\033[0m: {$message}\n";
    } else {
        $failed++;
        echo "  \033[31m✖ FAIL\033[0m: {$message}\n";
    }
}

echo "\n=======================================================\n";
echo " NetFusion Phase 33: Multi-Channel Notification Engine \n";
echo "=======================================================\n\n";

// -----------------------------------------------------------------------------
// Setup SQLite Database & Notifications Migration
// -----------------------------------------------------------------------------
$testSqlite = __DIR__ . '/test_phase33.sqlite';
if (file_exists($testSqlite)) {
    @unlink($testSqlite);
}
touch($testSqlite);

$app->make(\NetFusion\Core\Config\Config::class)->set('database.default', 'sqlite');
$app->make(\NetFusion\Core\Config\Config::class)->set('database.connections.sqlite.database', $testSqlite);

/** @var DatabaseManager $db */
$db = $app->make(DatabaseManager::class);
$conn = $db->connection('sqlite');

$migration = require __DIR__ . '/../database/migrations/2026_09_17_000006_create_netfusion_notifications_table.php';
$migration->up($conn);

// Setup Array Mailer for email testing
$arrayTransport = new ArrayTransport();
/** @var Mailer $mailer */
$mailer = $app->make('mailer');
$mailer->setTransport('array', $arrayTransport);
$mailer->setTransport('log', $arrayTransport);
$mailer->setTransport('default', $arrayTransport);
$app->make(\NetFusion\Core\Config\Config::class)->set('mail.default', 'array');

// Setup Queue Manager for queued test
$queueManager = new QueueManager($app);
$app->singleton('queue', fn() => $queueManager);

// Setup Mock User Model with Notifiable
class TestUser extends Model
{
    use Notifiable;

    protected string $table = 'test_users';
    protected array $fillable = ['id', 'name', 'email', 'phone', 'webhook_url'];
}

$user = new TestUser([
    'id'          => 42,
    'name'        => 'Atiquzzaman Ashish',
    'email'       => 'ashish@dotnetbd.com',
    'phone'       => '+8801700000000',
    'webhook_url' => 'https://api.dotnetbd.com/webhook/alerts',
]);

// -----------------------------------------------------------------------------
// 1. Domain Notification Classes
// -----------------------------------------------------------------------------
class OrderShippedNotification extends Notification
{
    public function __construct(public string $orderNumber = 'ORD-2026-99')
    {
        parent::__construct();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'sms'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'order_number' => $this->orderNumber,
            'amount'       => 250.00,
            'carrier'      => 'FedEx',
        ];
    }

    public function toMail(object $notifiable): Message
    {
        $msg = new Message();
        $msg->subject("Order #{$this->orderNumber} Shipped");
        $msg->text("Hello {$notifiable->name}, your order #{$this->orderNumber} is on the way!");
        return $msg;
    }

    public function toSms(object $notifiable): string
    {
        return "Hi {$notifiable->name}, order #{$this->orderNumber} has shipped via FedEx.";
    }

    public function toWebhook(object $notifiable): array
    {
        return [
            'url'    => 'https://example.com/mock-webhook',
            'secret' => 'test-secret-key-123',
            'data'   => ['order' => $this->orderNumber],
        ];
    }
}

class QueuedAlertNotification extends Notification implements ShouldQueue
{
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['alert' => 'System Backup Completed'];
    }
}

// -----------------------------------------------------------------------------
// 2. DatabaseChannel & DatabaseNotification Tests
// -----------------------------------------------------------------------------
echo "[1] Testing DatabaseChannel & DatabaseNotification Model...\n";

$dbChannel = new DatabaseChannel($app);
$notif1 = new OrderShippedNotification('ORD-001');

$result = $dbChannel->send($user, $notif1);
assert_test(is_array($result) && $result['id'] === $notif1->id, 'DatabaseChannel inserted notification row');
assert_test($result['notifiable_id'] === '42', 'DatabaseChannel recorded correct notifiable_id');

// Retrieve via Notifiable Trait
$allNotifications = $user->notifications();
assert_test(count($allNotifications) === 1, 'Notifiable::notifications() retrieved 1 notification');

$storedNotif = $allNotifications[0];
assert_test($storedNotif instanceof DatabaseNotification, 'Returned item is DatabaseNotification model');
assert_test($storedNotif->id === $notif1->id, 'Notification ID matches');
assert_test($storedNotif->unread() === true, 'Notification is initially unread');
assert_test($storedNotif->read() === false, 'read() returns false for unread');

$dataPayload = $storedNotif->getData();
assert_test($dataPayload['order_number'] === 'ORD-001' && $dataPayload['carrier'] === 'FedEx', 'getData() accurately decodes JSON payload');

// Unread query
$unreadList = $user->unreadNotifications();
assert_test(count($unreadList) === 1, 'Notifiable::unreadNotifications() returns 1 unread notification');

// Mark single as read
$storedNotif->markAsRead();
assert_test($storedNotif->read() === true, 'DatabaseNotification::markAsRead() marks notification as read');
assert_test(count($user->unreadNotifications()) === 0, 'unreadNotifications() now returns 0');
assert_test(count($user->readNotifications()) === 1, 'readNotifications() now returns 1');

// Mark as unread
$storedNotif->markAsUnread();
assert_test($storedNotif->unread() === true, 'DatabaseNotification::markAsUnread() resets read status');

// Mark as read via Notifiable
$user->markAsRead();
assert_test(count($user->unreadNotifications()) === 0, 'Notifiable::markAsRead() marked all unread notifications as read');

// -----------------------------------------------------------------------------
// 3. MailChannel Tests
// -----------------------------------------------------------------------------
echo "\n[2] Testing MailChannel...\n";

$mailChannel = new MailChannel($app);
$notif2 = new OrderShippedNotification('ORD-002');
$mailChannel->send($user, $notif2);

$sentEmails = $arrayTransport->getMessages();
assert_test(count($sentEmails) >= 1, 'MailChannel dispatched email message');
$lastEmail = end($sentEmails);
assert_test(in_array('ashish@dotnetbd.com', $lastEmail->getToAddresses(), true), 'Email recipient matched user email');
assert_test(str_contains($lastEmail->getSubject(), 'Order #ORD-002 Shipped'), 'Email subject matches notification subject');

// -----------------------------------------------------------------------------
// 4. SmsChannel Tests
// -----------------------------------------------------------------------------
echo "\n[3] Testing SmsChannel...\n";

SmsChannel::flushSentMessages();
$smsChannel = new SmsChannel($app, 'array');
$notif3 = new OrderShippedNotification('ORD-003');
$smsResult = $smsChannel->send($user, $notif3);

assert_test(is_array($smsResult) && $smsResult['to'] === '+8801700000000', 'SmsChannel routed to user phone number');
assert_test(str_contains($smsResult['message'], 'ORD-003'), 'SmsChannel message content is populated');

$capturedSms = SmsChannel::getSentMessages();
assert_test(count($capturedSms) === 1, 'SmsChannel recorded message in mock store');

// -----------------------------------------------------------------------------
// 5. WebhookChannel Tests
// -----------------------------------------------------------------------------
echo "\n[4] Testing WebhookChannel...\n";

$webhookChannel = new WebhookChannel($app);
$notif4 = new OrderShippedNotification('ORD-004');
assert_test($webhookChannel instanceof NotificationChannelInterface, 'WebhookChannel implements NotificationChannelInterface');

// -----------------------------------------------------------------------------
// 6. BroadcastChannel Tests
// -----------------------------------------------------------------------------
echo "\n[5] Testing BroadcastChannel...\n";

$broadcastChannel = new BroadcastChannel($app);
$notif5 = new OrderShippedNotification('ORD-005');
$bResult = $broadcastChannel->send($user, $notif5);
assert_test(is_array($bResult) && $bResult['channel'] === 'private-notifications.42', 'BroadcastChannel published to private-notifications.42');
assert_test($bResult['event'] === 'notification.created', 'BroadcastChannel published notification.created event');

// -----------------------------------------------------------------------------
// 7. NotificationManager Multi-Channel Orchestrator Tests
// -----------------------------------------------------------------------------
echo "\n[6] Testing NotificationManager Multi-Channel Orchestration...\n";

$manager = new NotificationManager($app);

assert_test($manager->channel('database') instanceof DatabaseChannel, 'channel("database") resolves DatabaseChannel');
assert_test($manager->channel('mail') instanceof MailChannel, 'channel("mail") resolves MailChannel');
assert_test($manager->channel('sms') instanceof SmsChannel, 'channel("sms") resolves SmsChannel');
assert_test($manager->channel('webhook') instanceof WebhookChannel, 'channel("webhook") resolves WebhookChannel');
assert_test($manager->channel('broadcast') instanceof BroadcastChannel, 'channel("broadcast") resolves BroadcastChannel');

// Custom Channel Extension
$slackCalled = false;
$manager->extend('slack', function () use (&$slackCalled) {
    return new class($slackCalled) implements NotificationChannelInterface {
        public function __construct(private &$flag) {}
        public function send(object $notifiable, Notification $notification): mixed {
            $this->flag = true;
            return ['slack' => 'delivered'];
        }
    };
});

assert_test($manager->channel('slack') instanceof NotificationChannelInterface, 'Custom extended channel resolved');

// Dispatch across multiple channels at once
$multiNotif = new OrderShippedNotification('ORD-MULTI');
$manager->sendNow($user, $multiNotif);
assert_test(count($user->notifications()) >= 2, 'Multi-channel sendNow delivered to database');

// Test Queueing & SendQueuedNotificationJob
$queuedNotif = new QueuedAlertNotification();
$initialCount = count($user->notifications());
$queuedJob = new SendQueuedNotificationJob($user, $queuedNotif);
$queuedJob->handle();
assert_test(count($user->notifications()) === $initialCount + 1, 'SendQueuedNotificationJob executed and persisted to database');

$queuedNotif2 = new QueuedAlertNotification();
$initialCount2 = count($user->notifications());
$manager->send($user, $queuedNotif2);
assert_test(count($user->notifications()) === $initialCount2 + 1, 'Notification implementing ShouldQueue dispatched through queue');

// -----------------------------------------------------------------------------
// 8. Global Helpers and Container Resolution Tests
// -----------------------------------------------------------------------------
echo "\n[7] Testing Global Helpers and Container Bindings...\n";

assert_test($app->has('notification'), 'Application has "notification" binding');
assert_test($app->has('notifications'), 'Application has "notifications" binding');
assert_test($app->make('notification') instanceof NotificationManager, '"notification" resolves to NotificationManager');
assert_test(function_exists('notify'), 'Global notify() helper exists');
assert_test(function_exists('notification'), 'Global notification() helper exists');
assert_test(notification() instanceof NotificationManager, 'notification() returns NotificationManager');

// Send via notify helper
$helpNotif = new OrderShippedNotification('ORD-HELPER');
notify($user, $helpNotif);
assert_test(count($user->notifications()) >= 4, 'notify($user, $notif) successfully dispatched notification');

// -----------------------------------------------------------------------------
// 9. CLI Notification Commands Tests
// -----------------------------------------------------------------------------
echo "\n[8] Testing CLI Commands...\n";

$sendCmd = new \NetFusion\Core\Console\Commands\NotificationSendCommand($app);
assert_test($sendCmd->getName() === 'notification:send', 'NotificationSendCommand name is notification:send');

$listCmd = new \NetFusion\Core\Console\Commands\NotificationListCommand($app);
assert_test($listCmd->getName() === 'notification:list', 'NotificationListCommand name is notification:list');

// Execute notification:list for user 42
ob_start();
$listCmd->setInput(['TestUser', '42'], []);
$exitCode = $listCmd->handle();
$listOutput = ob_get_clean();

assert_test($exitCode === 0, 'NotificationListCommand exited with 0');
assert_test(str_contains($listOutput, 'Notifications for [TestUser #42]'), 'NotificationListCommand printed user header');
assert_test(str_contains($listOutput, 'Total Found:'), 'NotificationListCommand printed total count');

// -----------------------------------------------------------------------------
// Final Summary
// -----------------------------------------------------------------------------
echo "\n=======================================================\n";
echo " Phase 33 Test Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n\n";

// Clean up temporary sqlite file
if (file_exists($testSqlite)) {
    @unlink($testSqlite);
}

exit($failed > 0 ? 1 : 0);
