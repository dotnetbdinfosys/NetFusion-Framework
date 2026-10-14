<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Workflow/Contracts/TransitionInterface.php
| Module    : Workflow
| Namespace : NetFusion\Core\Workflow\Contracts
| Purpose   : Standard contract for state transitions, guards and actions.
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

namespace NetFusion\Core\Workflow\Contracts;

interface TransitionInterface
{
    public function getName(): string;
    public function getFromStates(): array;
    public function getToState(): string;
    public function can(object $subject, array $context = []): bool;
    public function execute(object $subject, array $context = []): void;
}