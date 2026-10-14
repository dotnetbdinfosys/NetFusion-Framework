<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Enums/CanonicalOption.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Wasm\Component\Enums;

/**
 * Canonical ABI Options for Lift and Lower Operations.
 */
enum CanonicalOption: string
{
    case UTF8        = 'utf8';
    case UTF16       = 'utf16';
    case COMPACT_UTF16 = 'compact-utf16';
    case MEMORY      = 'memory';
    case REALLOC     = 'realloc';
    case POST_RETURN = 'post-return';
    case STRING_ENCODING = 'string-encoding';
}
