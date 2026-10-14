<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\SDUI\Components;

use NetFusion\Core\SDUI\Enums\ComponentType;
use NetFusion\Core\SDUI\Schema\BaseComponent;

class CustomComponent extends BaseComponent
{
    protected string $customName;

    public function __construct(string $customName, ?string $id = null, array $props = [])
    {
        $this->customName = $customName;
        parent::__construct($id, $props);
    }

    public function getType(): ComponentType
    {
        return ComponentType::CUSTOM;
    }

    public function getCustomName(): string
    {
        return $this->customName;
    }

    public static function make(string $customName, array $props = [], ?string $id = null): self
    {
        return new self($customName, $id, $props);
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['customName'] = $this->customName;
        return $data;
    }
}
