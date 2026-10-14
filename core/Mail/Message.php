<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Mail/Message.php
| Module    : Core\Mail
| Namespace : NetFusion\Core\Mail
| Purpose   : RFC 5322 and MIME compliant email message builder.
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
 * NetFusion Mail Message
 *
 * Encapsulates email recipients, subjects, MIME boundaries, headers, and attachments.
 *
 * @package NetFusion\Core\Mail
 * @version 1.0.0
 */
class Message
{
    /**
     * @var array<string, string|null> Recipient list indexed by email => name.
     */
    protected array $to = [];

    /**
     * @var array{0: string, 1: string|null}|null Sender address [email, name].
     */
    protected ?array $from = null;

    /**
     * @var array{0: string, 1: string|null}|null Reply-To address [email, name].
     */
    protected ?array $replyTo = null;

    /**
     * @var array<string, string|null> CC recipients.
     */
    protected array $cc = [];

    /**
     * @var array<string, string|null> BCC recipients.
     */
    protected array $bcc = [];

    /**
     * Email subject.
     */
    protected string $subject = '';

    /**
     * HTML body content.
     */
    protected ?string $htmlBody = null;

    /**
     * Plain text body content.
     */
    protected ?string $textBody = null;

    /**
     * Attachments list.
     *
     * @var array<int, array{name: string, data: string, mime: string}>
     */
    protected array $attachments = [];

    /**
     * Custom headers.
     *
     * @var array<string, string>
     */
    protected array $headers = [];

    /**
     * Set the recipient(s).
     */
    public function to(string|array $address, ?string $name = null): self
    {
        if (is_array($address)) {
            foreach ($address as $email => $n) {
                if (is_int($email)) {
                    $this->to[$n] = null;
                } else {
                    $this->to[$email] = $n;
                }
            }
        } else {
            $this->to[$address] = $name;
        }

        return $this;
    }

    /**
     * Set the sender.
     */
    public function from(string $address, ?string $name = null): self
    {
        $this->from = [$address, $name];
        return $this;
    }

    /**
     * Set the Reply-To address.
     */
    public function replyTo(string $address, ?string $name = null): self
    {
        $this->replyTo = [$address, $name];
        return $this;
    }

    /**
     * Set CC recipient(s).
     */
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

    /**
     * Set BCC recipient(s).
     */
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

    /**
     * Set the email subject line.
     */
    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set the HTML body content.
     */
    public function html(string $html): self
    {
        $this->htmlBody = $html;
        return $this;
    }

    /**
     * Set the plain text body content.
     */
    public function text(string $text): self
    {
        $this->textBody = $text;
        return $this;
    }

    /**
     * Attach a file from local filesystem.
     */
    public function attach(string $path, ?string $name = null, ?string $mime = null): self
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Attachment file not found: [{$path}].");
        }

        $data = file_get_contents($path);
        $fileName = $name ?: basename($path);
        $mimeType = $mime ?: (mime_content_type($path) ?: 'application/octet-stream');

        return $this->attachData($data ?: '', $fileName, $mimeType);
    }

    /**
     * Attach raw in-memory binary data.
     */
    public function attachData(string $data, string $name, ?string $mime = null): self
    {
        $this->attachments[] = [
            'name' => $name,
            'data' => $data,
            'mime' => $mime ?: 'application/octet-stream',
        ];

        return $this;
    }

    /**
     * Add a custom header.
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Get primary recipients list (emails only).
     *
     * @return array<string>
     */
    public function getToAddresses(): array
    {
        return array_keys($this->to);
    }

    /**
     * Get all target recipients including CC and BCC.
     *
     * @return array<string>
     */
    public function getAllRecipientAddresses(): array
    {
        return array_unique(array_merge(
            array_keys($this->to),
            array_keys($this->cc),
            array_keys($this->bcc)
        ));
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getFrom(): ?array
    {
        return $this->from;
    }

    public function getHtml(): ?string
    {
        return $this->htmlBody;
    }

    public function getText(): ?string
    {
        return $this->textBody;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * Format an address tuple into an RFC 2822 header value.
     */
    protected function formatAddress(string $email, ?string $name = null): string
    {
        if ($name) {
            return sprintf('"%s" <%s>', addcslashes($name, '"'), $email);
        }

        return "<{$email}>";
    }

    /**
     * Build the full MIME raw message text.
     */
    public function buildMime(): string
    {
        $boundaryMixed = '==NetFusion_Mixed_' . md5(uniqid((string)mt_rand(), true));
        $boundaryAlt   = '==NetFusion_Alt_' . md5(uniqid((string)mt_rand(), true));

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($this->subject) . '?=';

        if ($this->from) {
            $headers[] = 'From: ' . $this->formatAddress($this->from[0], $this->from[1]);
        }

        if (!empty($this->to)) {
            $toParts = [];
            foreach ($this->to as $email => $name) {
                $toParts[] = $this->formatAddress($email, $name);
            }
            $headers[] = 'To: ' . implode(', ', $toParts);
        }

        if (!empty($this->cc)) {
            $ccParts = [];
            foreach ($this->cc as $email => $name) {
                $ccParts[] = $this->formatAddress($email, $name);
            }
            $headers[] = 'Cc: ' . implode(', ', $ccParts);
        }

        if ($this->replyTo) {
            $headers[] = 'Reply-To: ' . $this->formatAddress($this->replyTo[0], $this->replyTo[1]);
        }

        foreach ($this->headers as $k => $v) {
            $headers[] = "{$k}: {$v}";
        }

        $hasAttachments = !empty($this->attachments);
        $body = '';

        if ($hasAttachments) {
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundaryMixed}\"";
            $body .= "--{$boundaryMixed}\r\n";
        }

        // Alternative part (HTML / Text)
        if ($this->htmlBody !== null && $this->textBody !== null) {
            if (!$hasAttachments) {
                $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"";
            } else {
                $body .= "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"\r\n\r\n";
            }

            $body .= "--{$boundaryAlt}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($this->textBody)) . "\r\n";

            $body .= "--{$boundaryAlt}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($this->htmlBody)) . "\r\n";

            $body .= "--{$boundaryAlt}--\r\n";
        } elseif ($this->htmlBody !== null) {
            if (!$hasAttachments) {
                $headers[] = "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64";
                $body .= chunk_split(base64_encode($this->htmlBody)) . "\r\n";
            } else {
                $body .= "Content-Type: text/html; charset=UTF-8\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split(base64_encode($this->htmlBody)) . "\r\n";
            }
        } else {
            $plain = $this->textBody ?? '';
            if (!$hasAttachments) {
                $headers[] = "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64";
                $body .= chunk_split(base64_encode($plain)) . "\r\n";
            } else {
                $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split(base64_encode($plain)) . "\r\n";
            }
        }

        // Attachments
        if ($hasAttachments) {
            foreach ($this->attachments as $attachment) {
                $body .= "\r\n--{$boundaryMixed}\r\n";
                $body .= "Content-Type: {$attachment['mime']}; name=\"{$attachment['name']}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$attachment['name']}\"\r\n\r\n";
                $body .= chunk_split(base64_encode($attachment['data'])) . "\r\n";
            }
            $body .= "--{$boundaryMixed}--\r\n";
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }
}
