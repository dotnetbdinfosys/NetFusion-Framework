<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Mail/SendQueuedMailableJob.php
| Module    : Core\Mail
| Namespace : NetFusion\Core\Mail
| Purpose   : Background queue job responsible for sending queued Mailables.
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

use NetFusion\Core\Queue\Job;

/**
 * NetFusion Send Queued Mailable Job
 *
 * Dispatches mailables asynchronously using the NetFusion Queue Engine.
 *
 * @package NetFusion\Core\Mail
 * @version 1.0.0
 */
class SendQueuedMailableJob extends Job
{
    public int $tries = 3;
    public int $backoff = 10;

    /**
     * @param Mailable $mailable The mailable instance to be delivered.
     */
    public function __construct(
        public readonly Mailable $mailable,
    ) {}

    /**
     * Execute the job on queue worker daemon.
     */
    public function handle(): void
    {
        $this->mailable->send();
    }
}
