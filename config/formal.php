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
    'default_logic'     => 'QF_UFLRA', // Quantifier-Free Uninterpreted Functions & Linear Real Arithmetic
    'sat_solver'        => 'cdcl',
    'max_conflict_cuts' => 10000,
    'timeout_ms'        => 5000,
    'theories'          => [
        'euf'        => true,
        'arithmetic' => true,
        'bitvector'  => true,
    ],
    'symbolic' => [
        'max_depth'            => 50,
        'detect_div_by_zero'   => true,
        'detect_overflow'      => true,
        'detect_null_pointers' => true,
    ],
];
