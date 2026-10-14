<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Consul Configuration
 * |--------------------------------------------------------------------------
 * |
 * | Configuration settings for Consul Service Discovery, SWIM Gossip Mesh,
 * | Dynamic DNS Server, and Multi-Protocol Health Checking Engine.
 * |
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Node & Cluster Identification
    |--------------------------------------------------------------------------
    */
    'node_id' => env('CONSUL_NODE_ID', 'consul-node-1'),
    'node_name' => env('CONSUL_NODE_NAME', 'primary-node'),
    'datacenter' => env('CONSUL_DATACENTER', 'dc1'),
    'bind_addr' => env('CONSUL_BIND_ADDR', '127.0.0.1'),
    'gossip_port' => (int)env('CONSUL_GOSSIP_PORT', 8301),

    /*
    |--------------------------------------------------------------------------
    | SWIM Gossip Protocol Parameters
    |--------------------------------------------------------------------------
    */
    'ping_timeout' => (float)env('CONSUL_PING_TIMEOUT', 0.5),
    'indirect_ping_timeout' => (float)env('CONSUL_INDIRECT_PING_TIMEOUT', 1.0),
    'suspect_timeout' => (float)env('CONSUL_SUSPECT_TIMEOUT', 3.0),
    'indirect_peers' => (int)env('CONSUL_INDIRECT_PEERS', 3),
    'gossip_fanout' => (int)env('CONSUL_GOSSIP_FANOUT', 3),

    /*
    |--------------------------------------------------------------------------
    | DNS Server Parameters
    |--------------------------------------------------------------------------
    */
    'dns_domain' => env('CONSUL_DNS_DOMAIN', 'consul'),
    'dns_port' => (int)env('CONSUL_DNS_PORT', 8600),
    'dns_ttl' => (int)env('CONSUL_DNS_TTL', 0),

    /*
    |--------------------------------------------------------------------------
    | Health Probing & Eviction Defaults
    |--------------------------------------------------------------------------
    */
    'default_check_interval' => (int)env('CONSUL_CHECK_INTERVAL', 10),
    'default_check_timeout' => (int)env('CONSUL_CHECK_TIMEOUT', 5),
    'deregister_critical_after' => (int)env('CONSUL_DEREGISTER_CRITICAL_AFTER', 30),

    /*
    |--------------------------------------------------------------------------
    | Initial Bootstrap Peer Nodes
    |--------------------------------------------------------------------------
    */
    'peers' => [
        // '127.0.0.1:8301',
    ],
];
