<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Workflow/State.php
| Module    : Workflow
| Namespace : NetFusion\Core\Workflow
| Purpose   : State value object representation in finite state machine.
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

use NetFusion\Core\Workflow\Contracts\StateInterface;

class State implements StateInterface
{
    protected string $name;
    protected string $label;
    protected bool $isInitial = false;
    protected bool $isFinal = false;
    protected array $metadata = [];

    public function __construct(string $name, ?string $label = null, array $metadata = [])
    {
        $this->name = $name;
        $this->label = $label ?? ucfirst(str_replace(['_', '-'], ' ', $name));
        $this->metadata = $metadata;
    }

    public static function make(string $name, ?string $label = null, array $metadata = []): static
    {
        return new static($name, $label, $metadata);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function initial(bool $initial = true): static
    {
        $this->isInitial = $initial;
        return $this;
    }

    public function isInitial(): bool
    {
        return $this->isInitial;
    }

    public function final(bool $final = true): static
    {
        $this->isFinal = $final;
        return $this;
    }

    public function isFinal(): bool
    {
        return $this->isFinal;
    }

    public function meta(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->metadata = array_merge($this->metadata, $key);
        } else {
            $this->metadata[$key] = $value;
        }
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}