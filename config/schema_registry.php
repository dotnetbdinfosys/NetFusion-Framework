<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Schema Compatibility Level
    |--------------------------------------------------------------------------
    |
    | Supported modes:
    |   BACKWARD, BACKWARD_TRANSITIVE, FORWARD, FORWARD_TRANSITIVE,
    |   FULL, FULL_TRANSITIVE, NONE
    |
    */
    'default_compatibility' => 'BACKWARD',

    /*
    |--------------------------------------------------------------------------
    | Confluent Wire Format Framing
    |--------------------------------------------------------------------------
    |
    | When enabled, messages will be framed with Magic Byte (0x00) and 4-byte
    | big-endian schema ID prefix for standard Kafka/Schema Registry tooling.
    |
    */
    'wire_format' => [
        'enabled' => true,
        'magic_byte' => 0x00,
    ],

    /*
    |--------------------------------------------------------------------------
    | CloudEvents Defaults
    |--------------------------------------------------------------------------
    |
    | Default source URI and content-type media types used when generating
    | CloudEvents v1.0 envelopes.
    |
    */
    'cloudevents' => [
        'default_source' => '/services/netfusion-app',
        'default_datacontenttype' => 'application/json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl_seconds' => 3600,
    ],
];
