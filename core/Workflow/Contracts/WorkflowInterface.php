<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Workflow/Contracts/WorkflowInterface.php
| Module    : Workflow
| Namespace : NetFusion\Core\Workflow\Contracts
| Purpose   : Main workflow contract for finite state machine management.
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

interface WorkflowInterface
{
    public function getName(): string;
    public function getCurrentState(object $subject): string;
    public function can(object $subject, string $transitionName, array $context = []): bool;
    public function apply(object $subject, string $transitionName, array $context = []): bool;
    public function getAvailableTransitions(object $subject): array;
}