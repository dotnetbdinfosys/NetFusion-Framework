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
 * eBPF Shared Memory Map Types.
 */
enum MapType: string
{
    case HASH        = 'BPF_MAP_TYPE_HASH';
    case ARRAY       = 'BPF_MAP_TYPE_ARRAY';
    case LRU_HASH    = 'BPF_MAP_TYPE_LRU_HASH';
    case RING_BUFFER = 'BPF_MAP_TYPE_RINGBUF';
    case PER_CPU     = 'BPF_MAP_TYPE_PERCPU_ARRAY';
}
