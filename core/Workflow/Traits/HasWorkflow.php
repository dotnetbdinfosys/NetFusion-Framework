<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Workflow/Traits/HasWorkflow.php
| Module    : Workflow
| Namespace : NetFusion\Core\Workflow\Traits
| Purpose   : Trait allowing ActiveRecord models to interact with StateMachine and ApprovalPipelines.
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

namespace NetFusion\Core\Workflow\Traits;

use NetFusion\Core\Workflow\ApprovalPipeline;
use NetFusion\Core\Workflow\StateMachine;
use NetFusion\Core\Workflow\WorkflowManager;

trait HasWorkflow
{
    public function getWorkflowName(): string
    {
        if (property_exists($this, 'workflowName') && !empty($this->workflowName)) {
            return $this->workflowName;
        }

        return strtolower((new \ReflectionClass($this))->getShortName());
    }

    public function getApprovalPipelineName(): string
    {
        if (property_exists($this, 'approvalPipelineName') && !empty($this->approvalPipelineName)) {
            return $this->approvalPipelineName;
        }

        return strtolower((new \ReflectionClass($this))->getShortName()) . '_approval';
    }

    public function workflow(?string $name = null): StateMachine
    {
        /** @var WorkflowManager $mgr */
        $mgr = app('workflow');
        return $mgr->get($name ?? $this->getWorkflowName());
    }

    public function canTransition(string $transition, array $context = []): bool
    {
        return $this->workflow()->can($this, $transition, $context);
    }

    public function applyTransition(string $transition, array $context = []): bool
    {
        return $this->workflow()->apply($this, $transition, $context);
    }

    public function getAvailableTransitions(): array
    {
        return $this->workflow()->getAvailableTransitions($this);
    }

    public function getWorkflowHistory(): array
    {
        return $this->workflow()->getHistory($this);
    }

    public function approvalPipeline(?string $name = null): ApprovalPipeline
    {
        /** @var WorkflowManager $mgr */
        $mgr = app('workflow');
        return $mgr->pipeline($name ?? $this->getApprovalPipelineName());
    }

    public function submitForApproval(?string $name = null): bool
    {
        return $this->approvalPipeline($name)->submit($this);
    }

    public function approve(int $approverId, ?string $comment = null, ?string $name = null): bool
    {
        return $this->approvalPipeline($name)->approve($this, $approverId, $comment);
    }

    public function reject(int $approverId, ?string $comment = null, ?string $name = null): bool
    {
        return $this->approvalPipeline($name)->reject($this, $approverId, $comment);
    }

    public function getApprovalStatus(?string $name = null): string
    {
        return $this->approvalPipeline($name)->getStatus($this);
    }
}