<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Notifications/Notification.php
| Module    : Notifications
| Namespace : NetFusion\Core\Notifications
| Purpose   : Abstract base class for multi-channel domain notifications.
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

namespace NetFusion\Core\Notifications;

use NetFusion\Core\Mail\Mailable;
use NetFusion\Core\Mail\Message;

/**
 * Class Notification
 *
 * Base notification class representing multi-channel deliverable events
 * (Database, Mail, Webhook, SMS, Broadcast).
 *
 * @package NetFusion\Core\Notifications
 */
abstract class Notification
{
    /**
     * Unique notification ID.
     */
    public string $id;

    /**
     * The name of the queue the job should be sent to.
     */
    public ?string $queue = null;

    /**
     * The name of the connection the job should be sent to.
     */
    public ?string $connection = null;

    /**
     * Delay in seconds before processing queued delivery.
     */
    public int $delay = 0;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
    }

    /**
     * Specify notification delivery channels for the given recipient.
     *
     * @param object $notifiable
     * @return array<string> e.g. ['database', 'mail', 'webhook', 'sms', 'broadcast']
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param object $notifiable
     * @return Mailable|Message
     */
    public function toMail(object $notifiable): Mailable|Message
    {
        $msg = new Message();
        $msg->subject('Notification from NetFusion Framework');
        $msg->text('You have a new notification.');
        return $msg;
    }

    /**
     * Get the database representation of the notification.
     *
     * @param object $notifiable
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get the outgoing webhook payload representation.
     *
     * @param object $notifiable
     * @return array<string, mixed>
     */
    public function toWebhook(object $notifiable): array
    {
        return [
            'event' => 'notification.sent',
            'type'  => static::class,
            'data'  => $this->toArray($notifiable),
        ];
    }

    /**
     * Get the SMS text representation of the notification.
     *
     * @param object $notifiable
     * @return string|array<string, mixed>
     */
    public function toSms(object $notifiable): string|array
    {
        return 'You have a new alert from NetFusion Framework.';
    }

    /**
     * Get the real-time broadcast payload representation.
     *
     * @param object $notifiable
     * @return array<string, mixed>
     */
    public function toBroadcast(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get the fallback array representation of the notification.
     *
     * @param object $notifiable
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'id'   => $this->id,
            'type' => static::class,
        ];
    }

    /**
     * Set target queue name.
     *
     * @param string $queue
     * @return $this
     */
    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Set target queue connection.
     *
     * @param string $connection
     * @return $this
     */
    public function onConnection(string $connection): static
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Set delivery delay in seconds.
     *
     * @param int $seconds
     * @return $this
     */
    public function delay(int $seconds): static
    {
        $this->delay = $seconds;
        return $this;
    }
}
