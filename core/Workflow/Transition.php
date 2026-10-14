<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Workflow/Transition.php
| Module    : Workflow
| Namespace : NetFusion\Core\Workflow
| Purpose   : State transition representation with guards, conditions, and action hooks.
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

use NetFusion\Core\Workflow\Contracts\TransitionInterface;

class Transition implements TransitionInterface
{
    protected string $name;
    protected array $fromStates;
    protected string $toState;
    protected array $guards = [];
    protected array $actions = [];
    protected string $label;

    public function __construct(string $name, string|array $fromStates, string $toState, ?string $label = null)
    {
        $this->name = $name;
        $this->fromStates = (array)$fromStates;
        $this->toState = $toState;
        $this->label = $label ?? ucfirst(str_replace(['_', '-'], ' ', $name));
    }

    public static function make(string $name, string|array $fromStates, string $toState, ?string $label = null): static
    {
        return new static($name, $fromStates, $toState, $label);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getFromStates(): array
    {
        return $this->fromStates;
    }

    public function getToState(): string
    {
        return $this->toState;
    }

    /**
     * ট্রানজিশন গার্ড/কন্ডিশন যোগ করুন।
     *
     * @param callable(object $subject, array $context): bool $guard
     */
    public function guard(callable $guard): static
    {
        $this->guards[] = $guard;
        return $this;
    }

    /**
     * ট্রানজিশন সম্পন্ন হওয়ার পর কার্যকর হওয়ার অ্যাকশন হুক।
     *
     * @param callable(object $subject, array $context): void $action
     */
    public function action(callable $action): static
    {
        $this->actions[] = $action;
        return $this;
    }

    /**
     * বর্তমান ট্রানজিশনটি অনুমতিপ্রাপ্ত কিনা পরীক্ষা করুন।
     */
    public function can(object $subject, array $context = []): bool
    {
        foreach ($this->guards as $guard) {
            if (!$guard($subject, $context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * অ্যাকশন হুকসমূহ নির্বাহ করুন।
     */
    public function execute(object $subject, array $context = []): void
    {
        foreach ($this->actions as $action) {
            $action($subject, $context);
        }
    }
}