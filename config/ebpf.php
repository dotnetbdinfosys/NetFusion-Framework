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

return [
    'driver'             => 'generic', // 'native', 'generic', 'simulated'
    'max_instructions'   => 4096,
    'stack_size_bytes'   => 512,
    'default_xdp_action' => 'XDP_PASS',
    'maps' => [
        'default_hash_entries' => 10000,
        'default_ringbuf_size' => 4096,
    ],
    'observability' => [
        'track_l4_tcp'      => true,
        'track_l7_http'     => true,
        'ringbuf_streaming' => true,
    ],
];
