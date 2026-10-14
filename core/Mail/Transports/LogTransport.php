<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Mail/Transports/LogTransport.php
| Module    : Core\Mail\Transports
| Namespace : NetFusion\Core\Mail\Transports
| Purpose   : File logger transport recording outgoing emails to mail.log.
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

/**
 * NetFusion Log Mail Transport
 *
 * Writes formatted email messages to a log file for development and auditing.
 *
 * @package NetFusion\Core\Mail\Transports
 * @version 1.0.0
 */
class LogTransport implements TransportInterface
{
    /**
     * @param string $logPath Target log file path.
     */
    public function __construct(
        protected readonly string $logPath,
    ) {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function send(Message $message): bool
    {
        $now = date('Y-m-d H:i:s');
        $to = implode(', ', $message->getAllRecipientAddresses());
        $subject = $message->getSubject();
        $mime = $message->buildMime();

        $entry = <<<LOG
================================================================================
[{$now}] EMAIL DISPATCHED
To     : {$to}
Subject: {$subject}
--------------------------------------------------------------------------------
{$mime}
================================================================================


LOG;

        return @file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * Get path to log file.
     */
    public function getLogPath(): string
    {
        return $this->logPath;
    }
}
