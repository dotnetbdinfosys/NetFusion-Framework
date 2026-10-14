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

namespace NetFusion\Core\SDUI\Enums;

enum ComponentType: string
{
    case CONTAINER = 'container';
    case ROW       = 'row';
    case COLUMN    = 'column';
    case STACK     = 'stack';
    case GRID      = 'grid';
    case CARD      = 'card';
    case TEXT      = 'text';
    case IMAGE     = 'image';
    case BUTTON    = 'button';
    case INPUT     = 'input';
    case FORM      = 'form';
    case MODAL     = 'modal';
    case LIST      = 'list';
    case DIVIDER   = 'divider';
    case SPACER    = 'spacer';
    case BADGE     = 'badge';
    case CUSTOM    = 'custom';
}
