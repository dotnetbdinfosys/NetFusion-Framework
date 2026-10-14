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
    /*
    |--------------------------------------------------------------------------
    | Default Store Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "array", "database"
    |
    */
    'store' => env('RULE_ENGINE_STORE', 'array'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Rule Firings Limit
    |--------------------------------------------------------------------------
    |
    | Prevents infinite execution loops during forward chaining.
    | Set to 0 for unlimited.
    |
    */
    'max_firings' => (int) env('RULE_MAX_FIRINGS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Enable automatic recording of rule firings and decision table evaluations.
    |
    */
    'audit_logging' => (bool) env('RULE_AUDIT_LOGGING', true),

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'rules' => 'nf_rules',
        'decision_tables' => 'nf_decision_tables',
        'audit_logs' => 'nf_rule_audit_logs',
        'metrics' => 'nf_rule_metrics',
    ],
];
