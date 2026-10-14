<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Notifications/Channels/MailChannel.php
| Module    : Notifications\Channels
| Namespace : NetFusion\Core\Notifications\Channels
| Purpose   : Mail notification delivery channel dispatching Mailable/Message.
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
use NetFusion\Core\Mail\Mailable;
use NetFusion\Core\Mail\Message;
use NetFusion\Core\Notifications\Contracts\NotificationChannelInterface;
use NetFusion\Core\Notifications\Notification;
use NetFusion\Core\Queue\Contracts\ShouldQueue;

/**
 * Class MailChannel
 *
 * Dispatches notifications over SMTP/Mail transports using NetFusion Mailer.
 *
 * @package NetFusion\Core\Notifications\Channels
 */
class MailChannel implements NotificationChannelInterface
{
    /**
     * @param Application|null $app
     */
    public function __construct(
        protected ?Application $app = null
    ) {
        $this->app = $app ?? (function_exists('app') ? app() : null);
    }

    /**
     * {@inheritdoc}
     */
    public function send(object $notifiable, Notification $notification): mixed
    {
        $recipient = $this->resolveRecipient($notifiable);
        if (! $recipient) {
            return null;
        }

        if (! method_exists($notification, 'toMail')) {
            return null;
        }

        $mail = $notification->toMail($notifiable);

        $mailer = $this->getMailer();
        if ($mailer === null) {
            return null;
        }

        if ($mail instanceof Mailable) {
            $mail->to($recipient);
            if ($notification instanceof ShouldQueue && method_exists($mail, 'queue')) {
                return $mail->queue();
            }
            return $mailer->send($mail);
        }

        if ($mail instanceof Message) {
            $mail->to($recipient);
            return $mailer->send($mail);
        }

        return null;
    }

    /**
     * Resolve destination email address for notifiable entity.
     *
     * @param object $notifiable
     * @return string|null
     */
    protected function resolveRecipient(object $notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('mail');
            if (is_string($route) && $route !== '') {
                return $route;
            }
        }

        return $notifiable->email ?? ($notifiable->attributes['email'] ?? null);
    }

    /**
     * Resolve mailer instance from container.
     *
     * @return mixed
     */
    protected function getMailer(): mixed
    {
        if ($this->app !== null && $this->app->has('mailer')) {
            return $this->app->make('mailer');
        }

        if (function_exists('app') && app()->has('mailer')) {
            return app('mailer');
        }

        return null;
    }
}
