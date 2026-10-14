<?php

declare(strict_types=1);

namespace NetFusion\Core\SchemaRegistry\Enums;

enum SchemaType: string
{
    case AVRO = 'AVRO';
    case PROTOBUF = 'PROTOBUF';
    case JSON_SCHEMA = 'JSON_SCHEMA';
}
