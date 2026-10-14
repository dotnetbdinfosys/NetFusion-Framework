<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Tools;

enum ParameterType: string
{
    case STRING = 'string';
    case NUMBER = 'number';
    case INTEGER = 'integer';
    case BOOLEAN = 'boolean';
    case ARRAY = 'array';
    case OBJECT = 'object';
}
