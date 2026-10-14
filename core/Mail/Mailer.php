<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Mail/Mailer.php
| Module    : Core\Mail
| Namespace : NetFusion\Core\Mail
| Purpose   : Central mail manager coordinating transports, mailables, and dispatching.
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

namespace NetFusion\Core\Mail;

use InvalidArgumentException;
use NetFusion\Core\Application;
use NetFusion\Core\Mail\Contracts\TransportInterface;
use NetFusion\Core\Mail\Transports\ArrayTransport;
use NetFusion\Core\Mail\Transports\LogTransport;
use NetFusion\Core\Mail\Transports\SmtpTransport;

/**
 * NetFusion Mailer
 *
 * Dispatches emails through pluggable transports and manages mail lifecycle.
 *
 * @package NetFusion\Core\Mail
 * @version 1.0.0
 */
class Mailer
{
    /**
     * Instantiated transport drivers.
     *
     * @var array<string, TransportInterface>
     */
    protected array $transports = [];

    /**
     * Default transport driver name.
     */
    protected string $defaultTransport;

    /**
     * @param Application $app
     */
    public function __construct(
        protected readonly Application $app,
    ) {
        $this->defaultTransport = (string) config('mail.default', env('MAIL_MAILER', 'log'));
    }

    /**
     * Begin composing a mail for the given recipient(s).
     *
     * @param  string|array $address
     * @param  string|null  $name
     * @return PendingMail
     */
    public function to(string|array $address, ?string $name = null): PendingMail
    {
        return (new PendingMail($this))->to($address, $name);
    }

    /**
     * Deliver a mailable or message.
     *
     * @param  Mailable|Message $message
     * @param  string|null      $transportName
     * @return bool
     */
    public function send(Mailable|Message $message, ?string $transportName = null): bool
    {
        $builtMessage = $message instanceof Mailable ? $message->buildMessage() : $message;

        return $this->transport($transportName)->send($builtMessage);
    }

    /**
     * Dispatch a mailable asynchronously onto background queue.
     *
     * @param  Mailable $mailable
     * @param  string   $queue
     * @return string   Job ID
     */
    public function queue(Mailable $mailable, string $queue = 'default'): string
    {
        return $mailable->queue($queue);
    }

    /**
     * Retrieve or resolve a transport driver instance.
     *
     * @param  string|null $name
     * @return TransportInterface
     */
    public function transport(?string $name = null): TransportInterface
    {
        $name = ($name === null || $name === 'default') ? $this->defaultTransport : $name;

        if (!isset($this->transports[$name])) {
            $this->transports[$name] = $this->resolveTransport($name);
        }

        return $this->transports[$name];
    }

    /**
     * Register a custom or mock transport.
     */
    public function setTransport(string $name, TransportInterface $transport): void
    {
        $this->transports[$name] = $transport;
    }

    /**
     * Resolve a transport driver based on configuration.
     */
    protected function resolveTransport(string $name): TransportInterface
    {
        $config = config("mail.mailers.{$name}", [
            'transport' => $name,
        ]);

        $transportType = $config['transport'] ?? $name;

        return match ($transportType) {
            'array' => new ArrayTransport(),
            'log' => new LogTransport(
                logPath: (string) ($config['path'] ?? $this->app->storagePath('logs/mail.log'))
            ),
            'smtp' => new SmtpTransport(
                host: (string) ($config['host'] ?? env('MAIL_HOST', '127.0.0.1')),
                port: (int) ($config['port'] ?? env('MAIL_PORT', 587)),
                encryption: $config['encryption'] ?? env('MAIL_ENCRYPTION', 'tls'),
                username: $config['username'] ?? env('MAIL_USERNAME'),
                password: $config['password'] ?? env('MAIL_PASSWORD'),
                timeout: (int) ($config['timeout'] ?? 30),
            ),
            default => throw new InvalidArgumentException("Unsupported mail transport: [{$transportType}]."),
        };
    }

    /**
     * Set default transport name.
     */
    public function setDefaultTransport(string $name): void
    {
        $this->defaultTransport = $name;
    }

    /**
     * Get default transport name.
     */
    public function getDefaultTransport(): string
    {
        return $this->defaultTransport;
    }
}
