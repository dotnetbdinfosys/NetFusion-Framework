<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Auth\WebAuthn\WebAuthnManager;
use NetFusion\Core\Console\Command;

class WebAuthnPruneCommand extends Command
{
    protected string $name = 'webauthn:prune';
    protected string $description = 'Prune expired WebAuthn authentication/registration challenges';

    public function handle(): int
    {
        /** @var WebAuthnManager $manager */
        $manager = $this->app->has('webauthn') ? $this->app->make('webauthn') : new WebAuthnManager();
        $pruned = $manager->getChallengeManager()->prune();

        $this->info("Successfully pruned {$pruned} expired WebAuthn challenge(s).");
        return 0;
    }
}
