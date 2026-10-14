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

namespace NetFusion\Core\IaC\Ast;

/**
 * High-level HCL and Terraform AST block types.
 */
enum BlockType: string
{
    case RESOURCE = 'resource';
    case DATA = 'data';
    case VARIABLE = 'variable';
    case OUTPUT = 'output';
    case PROVIDER = 'provider';
    case LOCALS = 'locals';
    case TERRAFORM = 'terraform';
    case MODULE = 'module';
}
