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

namespace NetFusion\Core\MFE\Enums;

/**
 * Rendering mode for Micro-Frontend fragments.
 */
enum RenderMode: string
{
    case SSR    = 'ssr';    // Server-Side Rendering (stitched on server)
    case CSR    = 'csr';    // Client-Side Rendering (container placeholder mounted by browser)
    case HYBRID = 'hybrid'; // SSR shell with CSR hydration bundle
}
