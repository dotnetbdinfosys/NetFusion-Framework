<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Workflow/WorkflowManager.php
| Module    : Workflow
| Namespace : NetFusion\Core\Workflow
| Purpose   : Central workflow registry for StateMachines and ApprovalPipelines.
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

namespace NetFusion\Core\Workflow;

use Closure;
use InvalidArgumentException;
use NetFusion\Core\Application;

class WorkflowManager
{
    /**
     * @var array<string, StateMachine>
     */
    protected array $workflows = [];

    /**
     * @var array<string, ApprovalPipeline>
     */
    protected array $pipelines = [];

    public function __construct(
        protected readonly ?Application $app = null
    ) {}

    /**
     * নতুন একটি স্টেট মেশিন ওয়ার্কফ্লো সংজ্ঞায়িত করুন।
     */
    public function define(string $name, Closure|StateMachine $definition, string $stateProperty = 'status'): StateMachine
    {
        if ($definition instanceof StateMachine) {
            $this->workflows[$name] = $definition;
            return $definition;
        }

        $sm = new StateMachine($name, $stateProperty);
        $definition($sm);
        $this->workflows[$name] = $sm;

        return $sm;
    }

    /**
     * নতুন একটি অনুমোদন পাইপলাইন সংজ্ঞায়িত করুন।
     */
    public function definePipeline(string $name, Closure|ApprovalPipeline $definition): ApprovalPipeline
    {
        if ($definition instanceof ApprovalPipeline) {
            $this->pipelines[$name] = $definition;
            return $definition;
        }

        $pipeline = new ApprovalPipeline($name);
        $definition($pipeline);
        $this->pipelines[$name] = $pipeline;

        return $pipeline;
    }

    public function get(string $name): StateMachine
    {
        if (!isset($this->workflows[$name])) {
            throw new InvalidArgumentException("Workflow [{$name}] is not defined.");
        }

        return $this->workflows[$name];
    }

    public function pipeline(string $name): ApprovalPipeline
    {
        if (!isset($this->pipelines[$name])) {
            throw new InvalidArgumentException("Approval pipeline [{$name}] is not defined.");
        }

        return $this->pipelines[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->workflows[$name]);
    }

    public function hasPipeline(string $name): bool
    {
        return isset($this->pipelines[$name]);
    }

    /**
     * @return array<string, StateMachine>
     */
    public function all(): array
    {
        return $this->workflows;
    }

    /**
     * @return array<string, ApprovalPipeline>
     */
    public function allPipelines(): array
    {
        return $this->pipelines;
    }
}