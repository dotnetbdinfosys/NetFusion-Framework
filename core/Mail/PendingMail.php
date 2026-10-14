<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Mail/PendingMail.php
| Module    : Core\Mail
| Namespace : NetFusion\Core\Mail
| Purpose   : Fluent builder for dispatching mailables to recipients.
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

/**
 * NetFusion Pending Mail
 *
 * Provides a fluent chainable API like mailer()->to('user@domain')->send(new InvoiceMail()).
 *
 * @package NetFusion\Core\Mail
 * @version 1.0.0
 */
class PendingMail
{
    /**
     * @var array<string, string|null>
     */
    protected array $to = [];

    /**
     * @var array<string, string|null>
     */
    protected array $cc = [];

    /**
     * @var array<string, string|null>
     */
    protected array $bcc = [];

    /**
     * @param Mailer $mailer
     */
    public function __construct(
        protected readonly Mailer $mailer,
    ) {}

    public function to(string|array $address, ?string $name = null): self
    {
        if (is_array($address)) {
            foreach ($address as $email => $n) {
                $this->to[is_int($email) ? $n : $email] = is_int($email) ? null : $n;
            }
        } else {
            $this->to[$address] = $name;
        }

        return $this;
    }

    public function cc(string|array $address, ?string $name = null): self
    {
        if (is_array($address)) {
            foreach ($address as $email => $n) {
                $this->cc[is_int($email) ? $n : $email] = is_int($email) ? null : $n;
            }
        } else {
            $this->cc[$address] = $name;
        }

        return $this;
    }

    public function bcc(string|array $address, ?string $name = null): self
    {
        if (is_array($address)) {
            foreach ($address as $email => $n) {
                $this->bcc[is_int($email) ? $n : $email] = is_int($email) ? null : $n;
            }
        } else {
            $this->bcc[$address] = $name;
        }

        return $this;
    }

    public function send(Mailable $mailable): bool
    {
        $this->fillRecipients($mailable);
        return $this->mailer->send($mailable);
    }

    public function queue(Mailable $mailable, string $queue = 'default'): string
    {
        $this->fillRecipients($mailable);
        return $mailable->queue($queue);
    }

    protected function fillRecipients(Mailable $mailable): void
    {
        if (!empty($this->to)) {
            $mailable->to($this->to);
        }
        if (!empty($this->cc)) {
            $mailable->cc = array_merge($mailable->cc, $this->cc);
        }
        if (!empty($this->bcc)) {
            $mailable->bcc = array_merge($mailable->bcc, $this->bcc);
        }
    }
}
