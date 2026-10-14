<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Workflow/Contracts/StateInterface.php
| Module    : Workflow
| Namespace : NetFusion\Core\Workflow\Contracts
| Purpose   : Standard contract for workflow states.
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

interface StateInterface
{
    public function getName(): string;
    public function getLabel(): string;
    public function isInitial(): bool;
    public function isFinal(): bool;
    public function getMetadata(): array;
}