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

class ImageComponent extends BaseComponent
{
    public function getType(): ComponentType
    {
        return ComponentType::IMAGE;
    }

    public static function make(string $src, string $alt = '', ?string $id = null): self
    {
        return new self($id, [
            'src' => $src,
            'alt' => $alt,
            'fit' => 'cover',
        ]);
    }

    public function withDimensions(int|string $width, int|string $height): self
    {
        $this->withStyle('width', $width);
        $this->withStyle('height', $height);
        return $this;
    }

    public function withFit(string $fit): self
    {
        return $this->withProp('fit', $fit);
    }
}
