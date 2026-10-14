<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Kubernetes Engine Configuration
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

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cluster Namespace
    |--------------------------------------------------------------------------
    */
    'default_namespace' => env('KUBE_DEFAULT_NAMESPACE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | CNI Subnet Settings
    |--------------------------------------------------------------------------
    */
    'cni' => [
        'pod_cidr'        => env('KUBE_POD_CIDR', '10.244.0.0/16'),
        'service_cidr'    => env('KUBE_SERVICE_CIDR', '10.96.0.0/12'),
        'subnet_prefix'   => '10.244',
        'service_prefix'  => '10.96.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pod Scheduler Settings
    |--------------------------------------------------------------------------
    */
    'scheduler' => [
        'strategy'           => env('KUBE_SCHEDULER_STRATEGY', 'LeastAllocated'), // 'LeastAllocated' or 'MostAllocated'
        'preemption_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Horizontal Pod Autoscaler (HPA) Defaults
    |--------------------------------------------------------------------------
    */
    'hpa' => [
        'default_target_utilization' => 80.0,
        'cooldown_seconds'           => 15,
        'min_replicas'               => 1,
        'max_replicas'               => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Worker Nodes
    |--------------------------------------------------------------------------
    */
    'default_nodes' => [
        [
            'name'        => 'node-worker-1',
            'internal_ip' => '192.168.1.101',
            'cpu'         => '4',
            'memory'      => '8Gi',
        ],
        [
            'name'        => 'node-worker-2',
            'internal_ip' => '192.168.1.102',
            'cpu'         => '4',
            'memory'      => '8Gi',
        ],
    ],
];
