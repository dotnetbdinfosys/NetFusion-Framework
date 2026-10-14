<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Network\Ebpf\Enums;

/**
 * XDP (eXpress Data Path) packet processing decision action codes.
 */
enum XdpAction: int
{
    case XDP_ABORTED  = 0; // Error / drop with tracepoint
    case XDP_DROP     = 1; // Drop packet immediately at NIC driver layer
    case XDP_PASS     = 2; // Pass packet up to regular Linux network stack
    case XDP_TX       = 3; // Bounce packet back out of receiving interface
    case XDP_REDIRECT = 4; // Forward packet to CPU or other network interface
}
