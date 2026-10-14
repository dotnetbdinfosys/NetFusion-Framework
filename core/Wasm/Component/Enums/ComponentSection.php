<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Enums/ComponentSection.php
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

namespace NetFusion\Core\Wasm\Component\Enums;

/**
 * Wasm Component Model Binary Sections (Component Model v0.2+).
 */
enum ComponentSection: int
{
    case CUSTOM           = 0;
    case CORE_MODULE      = 1;
    case CORE_INSTANCE    = 2;
    case CORE_TYPE        = 3;
    case COMPONENT        = 4;
    case INSTANCE         = 5;
    case ALIAS            = 6;
    case TYPE             = 7;
    case CANONICAL        = 8;
    case START            = 9;
    case IMPORT           = 10;
    case EXPORT           = 11;

    public function description(): string
    {
        return match ($this) {
            self::CUSTOM        => 'Custom metadata or WIT embedding section',
            self::CORE_MODULE   => 'Encapsulated WebAssembly core module binary',
            self::CORE_INSTANCE => 'Instantiation specification of a core module',
            self::CORE_TYPE     => 'Function and value types for core module interactions',
            self::COMPONENT     => 'Nested component definition',
            self::INSTANCE      => 'Component instance instantiation',
            self::ALIAS         => 'Alias export from nested module/component',
            self::TYPE          => 'Component-level WIT interface types and definitions',
            self::CANONICAL     => 'Canonical ABI lift/lower bindings',
            self::START         => 'Component start function index',
            self::IMPORT        => 'Imported WIT interfaces or values',
            self::EXPORT        => 'Exported WIT interfaces or values',
        };
    }
}
