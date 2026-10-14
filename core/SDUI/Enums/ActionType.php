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

enum ActionType: string
{
    case NAVIGATE       = 'navigate';
    case OPEN_MODAL     = 'open_modal';
    case CLOSE_MODAL    = 'close_modal';
    case API_CALL       = 'api_call';
    case DISPATCH_EVENT = 'dispatch_event';
    case DEEP_LINK      = 'deep_link';
    case SNACKBAR       = 'snackbar';
    case SET_STATE      = 'set_state';
    case COPY_CLIPBOARD = 'copy_clipboard';
    case REFRESH_SCREEN = 'refresh_screen';
}
