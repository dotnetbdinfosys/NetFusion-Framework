<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Workflow/StateMachine.php
| Module    : Workflow
| Namespace : NetFusion\Core\Workflow
| Purpose   : Finite State Machine engine with validation, history and event hooks.
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

use InvalidArgumentException;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Events\Dispatcher;
use NetFusion\Core\Workflow\Contracts\StateInterface;
use NetFusion\Core\Workflow\Contracts\TransitionInterface;
use NetFusion\Core\Workflow\Contracts\WorkflowInterface;
use RuntimeException;

class StateMachine implements WorkflowInterface
{
    protected string $name;
    protected string $stateProperty = 'status';

    /**
     * @var array<string, StateInterface>
     */
    protected array $states = [];

    /**
     * @var array<string, TransitionInterface>
     */
    protected array $transitions = [];

    protected ?string $initialState = null;

    public function __construct(string $name, string $stateProperty = 'status')
    {
        $this->name = $name;
        $this->stateProperty = $stateProperty;
    }

    public static function make(string $name, string $stateProperty = 'status'): static
    {
        return new static($name, $stateProperty);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStateProperty(): string
    {
        return $this->stateProperty;
    }

    public function addState(StateInterface $state): static
    {
        $this->states[$state->getName()] = $state;
        if ($state->isInitial() || $this->initialState === null) {
            $this->initialState = $state->getName();
        }
        return $this;
    }

    public function addTransition(TransitionInterface $transition): static
    {
        $this->transitions[$transition->getName()] = $transition;
        return $this;
    }

    /**
     * @return array<string, StateInterface>
     */
    public function getStates(): array
    {
        return $this->states;
    }

    public function getState(string $name): ?StateInterface
    {
        return $this->states[$name] ?? null;
    }

    /**
     * @return array<string, TransitionInterface>
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    public function getTransition(string $name): ?TransitionInterface
    {
        return $this->transitions[$name] ?? null;
    }

    /**
     * সাবজেক্টের বর্তমান স্টেট রিটার্ন করে।
     */
    public function getCurrentState(object $subject): string
    {
        $prop = $this->stateProperty;

        if (method_exists($subject, 'getAttribute')) {
            $val = $subject->getAttribute($prop);
        } elseif (property_exists($subject, $prop)) {
            $val = $subject->{$prop};
        } else {
            $getter = 'get' . ucfirst($prop);
            $val = method_exists($subject, $getter) ? $subject->{$getter}() : null;
        }

        return (string)($val ?: $this->initialState);
    }

    /**
     * বর্তমান স্টেট থেকে ট্রানজিশন কার্যকর করা সম্ভব কিনা।
     */
    public function can(object $subject, string $transitionName, array $context = []): bool
    {
        $transition = $this->getTransition($transitionName);
        if ($transition === null) {
            return false;
        }

        $currentState = $this->getCurrentState($subject);

        // Check if transition applies from current state
        if (!in_array($currentState, $transition->getFromStates(), true) && !in_array('*', $transition->getFromStates(), true)) {
            return false;
        }

        return $transition->can($subject, $context);
    }

    /**
     * ট্রানজিশন প্রয়োগ করুন।
     */
    public function apply(object $subject, string $transitionName, array $context = []): bool
    {
        if (!$this->can($subject, $transitionName, $context)) {
            throw new RuntimeException(
                "Cannot apply transition [{$transitionName}] from state [{$this->getCurrentState($subject)}] on workflow [{$this->name}]."
            );
        }

        $transition = $this->getTransition($transitionName);
        $fromState = $this->getCurrentState($subject);
        $toState = $transition->getToState();
        $prop = $this->stateProperty;

        // Update subject state
        if (method_exists($subject, 'setAttribute')) {
            $subject->setAttribute($prop, $toState);
            if (method_exists($subject, 'save')) {
                $subject->save();
            }
        } elseif (property_exists($subject, $prop)) {
            $subject->{$prop} = $toState;
        } else {
            $setter = 'set' . ucfirst($prop);
            if (method_exists($subject, $setter)) {
                $subject->{$setter}($toState);
            }
        }

        // Log transition history to database
        $this->recordHistory($subject, $fromState, $toState, $transitionName, $context);

        // Execute transition action hooks
        $transition->execute($subject, $context);

        // Dispatch framework events
        if (function_exists('event')) {
            event('workflow.transition', [
                'workflow'   => $this->name,
                'subject'    => $subject,
                'from_state' => $fromState,
                'to_state'   => $toState,
                'transition' => $transitionName,
                'context'    => $context,
            ]);
        }

        return true;
    }

    /**
     * বর্তমান স্টেট থেকে প্রযোজ্য সমস্ত ট্রানজিশন তালিকা।
     *
     * @return array<string, TransitionInterface>
     */
    public function getAvailableTransitions(object $subject): array
    {
        $available = [];
        foreach ($this->transitions as $name => $transition) {
            if ($this->can($subject, $name)) {
                $available[$name] = $transition;
            }
        }
        return $available;
    }

    /**
     * ডাটাবেসে ট্রানজিশন অডিট লগ সংরক্ষণ করুন।
     */
    protected function recordHistory(object $subject, string $from, string $to, string $transition, array $context): void
    {
        if (!function_exists('app') || !app()->has(DatabaseManager::class)) {
            return;
        }

        try {
            /** @var DatabaseManager $db */
            $db = app(DatabaseManager::class);

            $entityType = (new \ReflectionClass($subject))->getShortName();
            $entityId = 0;
            if (method_exists($subject, 'getAttribute')) {
                $entityId = (int)$subject->getAttribute('id');
            } elseif (property_exists($subject, 'id')) {
                $entityId = (int)$subject->id;
            }

            $actorId = $context['actor_id'] ?? null;
            $actorType = $context['actor_type'] ?? null;
            $comments = $context['comments'] ?? ($context['comment'] ?? null);
            $now = date('Y-m-d H:i:s');

            $db->connection()->statement(
                "INSERT INTO workflow_histories (entity_type, entity_id, workflow_name, from_state, to_state, transition_name, actor_id, actor_type, comments, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$entityType, $entityId, $this->name, $from, $to, $transition, $actorId, $actorType, $comments, $now]
            );
        } catch (\Exception) {
            // Graceful ignore if database connection not active
        }
    }

    /**
     * সাবজেক্টের সমস্ত ট্রানজিশন অডিট হিস্ট্রি পান।
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(object $subject): array
    {
        if (!function_exists('app') || !app()->has(DatabaseManager::class)) {
            return [];
        }

        try {
            /** @var DatabaseManager $db */
            $db = app(DatabaseManager::class);
            $entityType = (new \ReflectionClass($subject))->getShortName();
            $entityId = method_exists($subject, 'getAttribute') ? (int)$subject->getAttribute('id') : (int)($subject->id ?? 0);

            return $db->connection()->select(
                "SELECT * FROM workflow_histories WHERE entity_type = ? AND entity_id = ? AND workflow_name = ? ORDER BY id ASC",
                [$entityType, $entityId, $this->name]
            );
        } catch (\Exception) {
            return [];
        }
    }
}