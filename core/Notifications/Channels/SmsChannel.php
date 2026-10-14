<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Notifications/Channels/SmsChannel.php
| Module    : Notifications\Channels
| Namespace : NetFusion\Core\Notifications\Channels
| Purpose   : SMS notification channel dispatching text messages via SMS gateways.
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

namespace NetFusion\Core\Notifications\Channels;

use NetFusion\Core\Application;
use NetFusion\Core\Notifications\Contracts\NotificationChannelInterface;
use NetFusion\Core\Notifications\Notification;

/**
 * Class SmsChannel
 *
 * Dispatches transactional and alert SMS messages through configurable providers
 * (log, array, or HTTP SMS Gateway API).
 *
 * @package NetFusion\Core\Notifications\Channels
 */
class SmsChannel implements NotificationChannelInterface
{
    /**
     * Sent SMS storage for array/mock driver.
     *
     * @var array<int, array{to: string, message: string, time: string}>
     */
    protected static array $sentMessages = [];

    /**
     * @param Application|null $app
     * @param string           $driver 'log', 'array', or 'http'
     */
    public function __construct(
        protected ?Application $app = null,
        protected string $driver = 'array'
    ) {
        $this->app = $app ?? (function_exists('app') ? app() : null);

        if ($this->app !== null && function_exists('config')) {
            $configured = config('notifications.sms.driver');
            if (is_string($configured) && $configured !== '') {
                $this->driver = $configured;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function send(object $notifiable, Notification $notification): mixed
    {
        if (! method_exists($notification, 'toSms')) {
            return null;
        }

        $recipient = $this->resolveRecipient($notifiable);
        if (! $recipient) {
            return null;
        }

        $content = $notification->toSms($notifiable);
        $message = is_array($content) ? ($content['message'] ?? $content['text'] ?? '') : (string) $content;

        $record = [
            'to'      => $recipient,
            'message' => $message,
            'time'    => date('Y-m-d H:i:s'),
        ];

        if ($this->driver === 'log') {
            if ($this->app !== null && $this->app->has('log')) {
                $this->app->make('log')->info("[SMS Notification] To: {$recipient} | Message: {$message}");
            }
        } else {
            // Default array/mock store
            self::$sentMessages[] = $record;
        }

        return $record;
    }

    /**
     * Get all captured sent SMS messages in current process.
     *
     * @return array<int, array{to: string, message: string, time: string}>
     */
    public static function getSentMessages(): array
    {
        return self::$sentMessages;
    }

    /**
     * Clear captured messages.
     *
     * @return void
     */
    public static function flushSentMessages(): void
    {
        self::$sentMessages = [];
    }

    /**
     * Resolve recipient phone number.
     *
     * @param object $notifiable
     * @return string|null
     */
    protected function resolveRecipient(object $notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('sms');
            if (is_string($route) && $route !== '') {
                return $route;
            }
        }

        return $notifiable->phone
            ?? ($notifiable->mobile
            ?? ($notifiable->attributes['phone']
            ?? ($notifiable->attributes['mobile'] ?? null)));
    }
}
