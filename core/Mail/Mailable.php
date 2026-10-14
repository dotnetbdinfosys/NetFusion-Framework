<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Mail/Mailable.php
| Module    : Core\Mail
| Namespace : NetFusion\Core\Mail
| Purpose   : Abstract base class for mailable email objects with fluent configuration.
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

use NetFusion\Core\View\ViewFactory;

/**
 * NetFusion Mailable
 *
 * Base class for all email definitions supporting views, attachments, and queueing.
 *
 * @package NetFusion\Core\Mail
 * @version 1.0.0
 */
abstract class Mailable
{
    /**
     * @var array<string, string|null> Recipient list.
     */
    public array $to = [];

    /**
     * @var array{0: string, 1: string|null}|null
     */
    public ?array $from = null;

    /**
     * @var array<string, string|null>
     */
    public array $cc = [];

    /**
     * @var array<string, string|null>
     */
    public array $bcc = [];

    /**
     * Subject line.
     */
    public string $subject = '';

    /**
     * View name to render as HTML body.
     */
    public ?string $viewName = null;

    /**
     * Data passed to view template.
     *
     * @var array<string, mixed>
     */
    public array $viewData = [];

    /**
     * Raw HTML content.
     */
    public ?string $rawHtml = null;

    /**
     * Plain text body.
     */
    public ?string $rawText = null;

    /**
     * Attachments.
     *
     * @var array<int, array{path?: string, data?: string, name?: string, mime?: string}>
     */
    public array $attachments = [];

    /**
     * Build the message dependencies and details.
     * Subclasses can override this method to configure the email.
     */
    public function build(): static
    {
        return $this;
    }

    /**
     * Set recipient(s).
     */
    public function to(string|array $address, ?string $name = null): static
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

    /**
     * Set sender.
     */
    public function from(string $address, ?string $name = null): static
    {
        $this->from = [$address, $name];
        return $this;
    }

    /**
     * Set subject.
     */
    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set HTML template view and data.
     */
    public function view(string $view, array $data = []): static
    {
        $this->viewName = $view;
        $this->viewData = array_merge($this->viewData, $data);
        return $this;
    }

    /**
     * Set raw HTML body.
     */
    public function html(string $html): static
    {
        $this->rawHtml = $html;
        return $this;
    }

    /**
     * Set raw text body.
     */
    public function text(string $text): static
    {
        $this->rawText = $text;
        return $this;
    }

    /**
     * Attach a file.
     */
    public function attach(string $path, ?string $name = null, ?string $mime = null): static
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name,
            'mime' => $mime,
        ];

        return $this;
    }

    /**
     * Convert this Mailable into a fully populated Message instance.
     */
    public function buildMessage(): Message
    {
        $this->build();

        $msg = new Message();

        if ($this->from) {
            $msg->from($this->from[0], $this->from[1]);
        } elseif (config('mail.from.address')) {
            $msg->from(
                (string) config('mail.from.address'),
                config('mail.from.name') ? (string) config('mail.from.name') : null
            );
        }

        $msg->to($this->to);
        $msg->cc($this->cc);
        $msg->bcc($this->bcc);
        $msg->subject($this->subject);

        // Render view if defined
        if ($this->viewName !== null) {
            if (app()->bound(ViewFactory::class)) {
                /** @var ViewFactory $viewFactory */
                $viewFactory = app()->make(ViewFactory::class);
                $renderedHtml = $viewFactory->make($this->viewName, $this->viewData)->render();
                $msg->html($renderedHtml);
            }
        } elseif ($this->rawHtml !== null) {
            $msg->html($this->rawHtml);
        }

        if ($this->rawText !== null) {
            $msg->text($this->rawText);
        }

        foreach ($this->attachments as $att) {
            if (!empty($att['path'])) {
                $msg->attach($att['path'], $att['name'] ?? null, $att['mime'] ?? null);
            } elseif (isset($att['data'], $att['name'])) {
                $msg->attachData($att['data'], $att['name'], $att['mime'] ?? null);
            }
        }

        return $msg;
    }

    /**
     * Send the mailable immediately.
     */
    public function send(?Mailer $mailer = null): bool
    {
        $mailer = $mailer ?: app('mailer');
        return $mailer->send($this);
    }

    /**
     * Push the mailable to the background queue.
     */
    public function queue(string $queue = 'default'): string
    {
        return queue(new SendQueuedMailableJob($this), $queue);
    }
}
