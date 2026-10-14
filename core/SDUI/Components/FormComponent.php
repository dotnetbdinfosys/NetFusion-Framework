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

use NetFusion\Core\SDUI\Actions\Action;
use NetFusion\Core\SDUI\Enums\ComponentType;
use NetFusion\Core\SDUI\Schema\BaseComponent;

class FormComponent extends BaseComponent
{
    public function getType(): ComponentType
    {
        return ComponentType::FORM;
    }

    public static function make(string $formId, string $actionEndpoint, string $method = 'POST', ?string $id = null): self
    {
        return new self($id, [
            'formId'         => $formId,
            'actionEndpoint' => $actionEndpoint,
            'method'         => strtoupper($method),
        ]);
    }

    public function withSubmitAction(Action $onSubmit): self
    {
        return $this->withAction('onSubmit', $onSubmit);
    }
}
