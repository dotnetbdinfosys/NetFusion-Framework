<?php

declare(strict_types=1);

namespace NetFusion\Core\SchemaRegistry\Enums;

enum CompatibilityMode: string
{
    case BACKWARD = 'BACKWARD';
    case BACKWARD_TRANSITIVE = 'BACKWARD_TRANSITIVE';
    case FORWARD = 'FORWARD';
    case FORWARD_TRANSITIVE = 'FORWARD_TRANSITIVE';
    case FULL = 'FULL';
    case FULL_TRANSITIVE = 'FULL_TRANSITIVE';
    case NONE = 'NONE';
}
