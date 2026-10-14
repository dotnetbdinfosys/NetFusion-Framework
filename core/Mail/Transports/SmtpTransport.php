<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Mail/Transports/SmtpTransport.php
| Module    : Core\Mail\Transports
| Namespace : NetFusion\Core\Mail\Transports
| Purpose   : Pure PHP raw socket SMTP transport supporting TLS/SSL & AUTH LOGIN.
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

namespace NetFusion\Core\Mail\Transports;

use NetFusion\Core\Mail\Contracts\TransportInterface;
use NetFusion\Core\Mail\Message;
use RuntimeException;

/**
 * NetFusion SMTP Mail Transport
 *
 * Direct RFC 5321 socket client implementing SMTP handshake, STARTTLS, and delivery.
 *
 * @package NetFusion\Core\Mail\Transports
 * @version 1.0.0
 */
class SmtpTransport implements TransportInterface
{
    /**
     * @param string      $host
     * @param int         $port
     * @param string|null $encryption 'tls', 'ssl', or null
     * @param string|null $username
     * @param string|null $password
     * @param int         $timeout Seconds
     */
    public function __construct(
        protected readonly string  $host = '127.0.0.1',
        protected readonly int     $port = 587,
        protected readonly ?string $encryption = 'tls',
        protected readonly ?string $username = null,
        protected readonly ?string $password = null,
        protected readonly int     $timeout = 30,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function send(Message $message): bool
    {
        $socket = $this->connect();

        try {
            $this->handshake($socket);
            $this->authenticate($socket);

            $sender = $message->getFrom()[0] ?? 'noreply@localhost';
            $this->sendCommand($socket, "MAIL FROM:<{$sender}>", 250);

            $recipients = $message->getAllRecipientAddresses();
            if (empty($recipients)) {
                throw new RuntimeException("Cannot send email: message has no recipients.");
            }

            foreach ($recipients as $recipient) {
                $this->sendCommand($socket, "RCPT TO:<{$recipient}>", [250, 251]);
            }

            $this->sendCommand($socket, 'DATA', 354);

            $payload = $message->buildMime();
            // Period escaping at start of line (dot-stuffing)
            $payload = preg_replace('/^\./m', '..', $payload);

            $this->write($socket, $payload . "\r\n.\r\n");
            $this->assertResponse($this->read($socket), 250);

            $this->sendCommand($socket, 'QUIT', 221);

            return true;
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    /**
     * Open socket stream to SMTP server.
     *
     * @return resource
     */
    protected function connect()
    {
        $protocol = strtolower((string)$this->encryption) === 'ssl' ? 'ssl://' : 'tcp://';
        $remoteSocket = "{$protocol}{$this->host}:{$this->port}";

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);

        $socket = @stream_socket_client(
            $remoteSocket,
            $errno,
            $errstr,
            (float) $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new RuntimeException("Could not connect to SMTP server [{$remoteSocket}]: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $this->timeout);

        $greeting = $this->read($socket);
        $this->assertResponse($greeting, 220);

        return $socket;
    }

    /**
     * Perform EHLO and optional STARTTLS upgrade.
     *
     * @param resource $socket
     */
    protected function handshake($socket): void
    {
        $hostname = gethostname() ?: '127.0.0.1';
        $this->sendCommand($socket, "EHLO {$hostname}", 250);

        if (strtolower((string)$this->encryption) === 'tls') {
            $this->sendCommand($socket, 'STARTTLS', 220);

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            $cryptoOk = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
            if (!$cryptoOk) {
                throw new RuntimeException("Failed to negotiate TLS encryption with SMTP server.");
            }

            $this->sendCommand($socket, "EHLO {$hostname}", 250);
        }
    }

    /**
     * Authenticate via AUTH LOGIN if credentials are provided.
     *
     * @param resource $socket
     */
    protected function authenticate($socket): void
    {
        if (empty($this->username) || empty($this->password)) {
            return;
        }

        $this->sendCommand($socket, 'AUTH LOGIN', 334);
        $this->sendCommand($socket, base64_encode($this->username), 334);
        $this->sendCommand($socket, base64_encode($this->password), 235);
    }

    /**
     * Send command and assert expected response code(s).
     *
     * @param resource  $socket
     * @param string    $command
     * @param int|int[] $expectedCode
     */
    protected function sendCommand($socket, string $command, int|array $expectedCode): string
    {
        $this->write($socket, $command . "\r\n");
        $response = $this->read($socket);
        $this->assertResponse($response, $expectedCode);

        return $response;
    }

    /**
     * Read full multiline response from socket.
     *
     * @param  resource $socket
     * @return string
     */
    protected function read($socket): string
    {
        $response = '';

        while (!feof($socket)) {
            $line = fgets($socket, 1024);
            if ($line === false) {
                break;
            }

            $response .= $line;

            // Check if end of multiline response (format: "XXX <message>\r\n")
            if (preg_match('/^\d{3}\s/', $line)) {
                break;
            }
        }

        return trim($response);
    }

    /**
     * Write raw data to socket.
     *
     * @param resource $socket
     * @param string   $data
     */
    protected function write($socket, string $data): void
    {
        $totalWritten = 0;
        $length = strlen($data);

        while ($totalWritten < $length) {
            $written = @fwrite($socket, substr($data, $totalWritten));
            if ($written === false || $written === 0) {
                throw new RuntimeException("Failed to write data to SMTP socket.");
            }
            $totalWritten += $written;
        }
    }

    /**
     * Verify that response matches expected numeric code(s).
     *
     * @param string    $response
     * @param int|int[] $expectedCode
     */
    protected function assertResponse(string $response, int|array $expectedCode): void
    {
        $code = (int) substr($response, 0, 3);
        $expected = is_array($expectedCode) ? $expectedCode : [$expectedCode];

        if (!in_array($code, $expected, true)) {
            throw new RuntimeException("SMTP Error [Expected " . implode('/', $expected) . ", got {$code}]: {$response}");
        }
    }
}
