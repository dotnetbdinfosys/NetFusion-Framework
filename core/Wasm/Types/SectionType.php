<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Wasm\Types;

/**
 * Standard WebAssembly binary module section IDs (0 to 12).
 */
enum SectionType: int
{
    case CUSTOM = 0;
    case TYPE = 1;
    case IMPORT = 2;
    case FUNCTION = 3;
    case TABLE = 4;
    case MEMORY = 5;
    case GLOBAL = 6;
    case EXPORT = 7;
    case START = 8;
    case ELEMENT = 9;
    case CODE = 10;
    case DATA = 11;
    case DATA_COUNT = 12;

    public function name(): string
    {
        return match ($this) {
            self::CUSTOM => 'Custom',
            self::TYPE => 'Type',
            self::IMPORT => 'Import',
            self::FUNCTION => 'Function',
            self::TABLE => 'Table',
            self::MEMORY => 'Memory',
            self::GLOBAL => 'Global',
            self::EXPORT => 'Export',
            self::START => 'Start',
            self::ELEMENT => 'Element',
            self::CODE => 'Code',
            self::DATA => 'Data',
            self::DATA_COUNT => 'DataCount',
        };
    }
}
