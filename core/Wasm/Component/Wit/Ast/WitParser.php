<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Wit/Ast/WitParser.php
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

namespace NetFusion\Core\Wasm\Component\Wit\Ast;

use NetFusion\Core\Wasm\Component\Enums\WitType;
use NetFusion\Core\Wasm\Component\Exceptions\WitParseException;

/**
 * Parser for WebAssembly Interface Type (WIT) specifications.
 */
class WitParser
{
    private array $tokens = [];
    private int $cursor = 0;
    private ?string $currentPackage = null;
    /** @var array<string, WitInterface> */
    private array $interfaces = [];
    /** @var array<string, WitWorld> */
    private array $worlds = [];

    public function parse(string $source): array
    {
        $lexer = new WitLexer($source);
        $this->tokens = $lexer->tokenize();
        $this->cursor = 0;
        $this->interfaces = [];
        $this->worlds = [];
        $this->currentPackage = null;

        while (!$this->isAtEnd()) {
            $token = $this->peek();
            if ($token['type'] === WitLexer::T_PACKAGE) {
                $this->parsePackage();
            } elseif ($token['type'] === WitLexer::T_INTERFACE) {
                $iface = $this->parseInterface();
                $this->interfaces[$iface->getName()] = $iface;
            } elseif ($token['type'] === WitLexer::T_WORLD) {
                $world = $this->parseWorld();
                $this->worlds[$world->getName()] = $world;
            } else {
                $this->advance(); // Skip or advance
            }
        }

        return [
            'package'    => $this->currentPackage,
            'interfaces' => $this->interfaces,
            'worlds'     => $this->worlds,
        ];
    }

    private function parsePackage(): void
    {
        $this->consume(WitLexer::T_PACKAGE, "Expected 'package'");
        $pkgName = '';
        while (!$this->isAtEnd() && $this->peek()['type'] !== ';') {
            $pkgName .= $this->advance()['value'];
        }
        $this->consume(';', "Expected ';' after package name");
        $this->currentPackage = $pkgName;
    }

    private function parseInterface(): WitInterface
    {
        $this->consume(WitLexer::T_INTERFACE, "Expected 'interface'");
        $name = $this->consume(WitLexer::T_IDENT, "Expected interface identifier")['value'];
        $this->consume('{', "Expected '{' to start interface body");

        $interface = new WitInterface($name, $this->currentPackage);

        while (!$this->isAtEnd() && $this->peek()['type'] !== '}') {
            $cur = $this->peek();
            if ($cur['type'] === WitLexer::T_TYPE) {
                $interface->addType($this->parseTypeAlias());
            } elseif ($cur['type'] === WitLexer::T_RECORD) {
                $interface->addType($this->parseRecord());
            } elseif ($cur['type'] === WitLexer::T_VARIANT) {
                $interface->addType($this->parseVariant());
            } elseif ($cur['type'] === WitLexer::T_ENUM) {
                $interface->addType($this->parseEnum());
            } elseif ($cur['type'] === WitLexer::T_FLAGS) {
                $interface->addType($this->parseFlags());
            } elseif ($cur['type'] === WitLexer::T_RESOURCE) {
                $interface->addType($this->parseResource());
            } elseif ($cur['type'] === WitLexer::T_IDENT || $cur['type'] === WitLexer::T_FUNC) {
                $interface->addFunction($this->parseFunction());
            } else {
                $this->advance();
            }
        }

        $this->consume('}', "Expected '}' to close interface body");
        return $interface;
    }

    private function parseWorld(): WitWorld
    {
        $this->consume(WitLexer::T_WORLD, "Expected 'world'");
        $name = $this->consume(WitLexer::T_IDENT, "Expected world identifier")['value'];
        $this->consume('{', "Expected '{' to start world body");

        $world = new WitWorld($name, $this->currentPackage);

        while (!$this->isAtEnd() && $this->peek()['type'] !== '}') {
            $cur = $this->peek();
            if ($cur['type'] === WitLexer::T_IMPORT) {
                $this->advance();
                $targetName = $this->advance()['value'];
                $this->consume(':', "Expected ':' after import name");
                $ifaceOrFn = $this->parseWorldTarget($targetName);
                $world->addImport($targetName, $ifaceOrFn);
            } elseif ($cur['type'] === WitLexer::T_EXPORT) {
                $this->advance();
                $targetName = $this->advance()['value'];
                if ($this->peek()['type'] === ';') {
                    $this->advance();
                    $world->addExport($targetName, $targetName);
                } else {
                    $this->consume(':', "Expected ':' after export name");
                    $ifaceOrFn = $this->parseWorldTarget($targetName);
                    $world->addExport($targetName, $ifaceOrFn);
                }
            } else {
                $this->advance();
            }
        }

        $this->consume('}', "Expected '}' to close world body");
        return $world;
    }

    private function parseWorldTarget(string $targetName = ''): WitInterface|WitFunction|string
    {
        $cur = $this->peek();
        if ($cur['type'] === WitLexer::T_INTERFACE) {
            return $this->parseInterface();
        }
        if ($cur['type'] === WitLexer::T_FUNC) {
            $this->advance(); // consume 'func'
            return $this->parseFunctionBody($targetName);
        }

        // Identifier or path
        $val = '';
        while (!$this->isAtEnd() && !in_array($this->peek()['type'], [';', '}'], true)) {
            $val .= $this->advance()['value'];
        }
        if ($this->peek()['type'] === ';') {
            $this->advance();
        }
        return $val;
    }

    private function parseTypeAlias(): WitTypeDefinition
    {
        $this->consume(WitLexer::T_TYPE, "Expected 'type'");
        $name = $this->consume(WitLexer::T_IDENT, "Expected type identifier")['value'];
        $this->consume('=', "Expected '=' in type definition");
        $targetType = $this->parseTypeExpr();
        $this->consume(';', "Expected ';' after type definition");
        return new WitTypeDefinition($name, $targetType);
    }

    private function parseRecord(): WitTypeDefinition
    {
        $this->consume(WitLexer::T_RECORD, "Expected 'record'");
        $name = $this->consume(WitLexer::T_IDENT, "Expected record identifier")['value'];
        $this->consume('{', "Expected '{' for record fields");

        $fields = [];
        while (!$this->isAtEnd() && $this->peek()['type'] !== '}') {
            $fieldName = $this->consume(WitLexer::T_IDENT, "Expected field identifier")['value'];
            $this->consume(':', "Expected ':' after field name");
            $fieldType = $this->parseTypeExpr();
            $fields[$fieldName] = $fieldType;
            if ($this->peek()['type'] === ',') {
                $this->advance();
            }
        }

        $this->consume('}', "Expected '}' after record fields");
        return new WitTypeDefinition($name, WitType::RECORD, ['fields' => $fields]);
    }

    private function parseVariant(): WitTypeDefinition
    {
        $this->consume(WitLexer::T_VARIANT, "Expected 'variant'");
        $name = $this->consume(WitLexer::T_IDENT, "Expected variant identifier")['value'];
        $this->consume('{', "Expected '{' for variant cases");

        $cases = [];
        while (!$this->isAtEnd() && $this->peek()['type'] !== '}') {
            $caseName = $this->consume(WitLexer::T_IDENT, "Expected variant case name")['value'];
            $payloadType = null;
            if ($this->peek()['type'] === '(') {
                $this->advance();
                $payloadType = $this->parseTypeExpr();
                $this->consume(')', "Expected ')' after variant case payload");
            }
            $cases[$caseName] = $payloadType;
            if ($this->peek()['type'] === ',') {
                $this->advance();
            }
        }

        $this->consume('}', "Expected '}' after variant cases");
        return new WitTypeDefinition($name, WitType::VARIANT, ['cases' => $cases]);
    }

    private function parseEnum(): WitTypeDefinition
    {
        $this->consume(WitLexer::T_ENUM, "Expected 'enum'");
        $name = $this->consume(WitLexer::T_IDENT, "Expected enum identifier")['value'];
        $this->consume('{', "Expected '{' for enum values");

        $cases = [];
        while (!$this->isAtEnd() && $this->peek()['type'] !== '}') {
            $cases[] = $this->consume(WitLexer::T_IDENT, "Expected enum member")['value'];
            if ($this->peek()['type'] === ',') {
                $this->advance();
            }
        }

        $this->consume('}', "Expected '}' after enum values");
        return new WitTypeDefinition($name, WitType::ENUM, ['members' => $cases]);
    }

    private function parseFlags(): WitTypeDefinition
    {
        $this->consume(WitLexer::T_FLAGS, "Expected 'flags'");
        $name = $this->consume(WitLexer::T_IDENT, "Expected flags identifier")['value'];
        $this->consume('{', "Expected '{' for flags");

        $flags = [];
        while (!$this->isAtEnd() && $this->peek()['type'] !== '}') {
            $flags[] = $this->consume(WitLexer::T_IDENT, "Expected flag name")['value'];
            if ($this->peek()['type'] === ',') {
                $this->advance();
            }
        }

        $this->consume('}', "Expected '}' after flags");
        return new WitTypeDefinition($name, WitType::FLAGS, ['flags' => $flags]);
    }

    private function parseResource(): WitTypeDefinition
    {
        $this->consume(WitLexer::T_RESOURCE, "Expected 'resource'");
        $name = $this->consume(WitLexer::T_IDENT, "Expected resource identifier")['value'];

        $methods = [];
        if ($this->peek()['type'] === '{') {
            $this->advance();
            while (!$this->isAtEnd() && $this->peek()['type'] !== '}') {
                $methods[] = $this->parseFunction();
            }
            $this->consume('}', "Expected '}' after resource methods");
        } else {
            $this->consume(';', "Expected ';' after resource declaration");
        }

        return new WitTypeDefinition($name, WitType::RESOURCE, ['methods' => $methods]);
    }

    private function parseFunction(): WitFunction
    {
        if ($this->peek()['type'] === WitLexer::T_FUNC) {
            $this->advance();
        }
        $name = $this->consume(WitLexer::T_IDENT, "Expected function name")['value'];
        $this->consume(':', "Expected ':' after function name");
        $this->consume('func', "Expected 'func'");
        return $this->parseFunctionBody($name);
    }

    private function parseFunctionBody(string $name): WitFunction
    {
        $this->consume('(', "Expected '(' for function params");

        $params = [];
        while (!$this->isAtEnd() && $this->peek()['type'] !== ')') {
            $paramName = $this->consume(WitLexer::T_IDENT, "Expected param name")['value'];
            $this->consume(':', "Expected ':' after param name");
            $paramType = $this->parseTypeExpr();
            $params[$paramName] = $paramType;
            if ($this->peek()['type'] === ',') {
                $this->advance();
            }
        }

        $this->consume(')', "Expected ')' after function params");

        $returnType = null;
        if ($this->peek()['type'] === WitLexer::T_ARROW) {
            $this->advance();
            $returnType = $this->parseTypeExpr();
        }

        if ($this->peek()['type'] === ';') {
            $this->advance();
        }

        return new WitFunction($name, $params, $returnType);
    }

    private function parseTypeExpr(): string
    {
        $cur = $this->advance();
        $base = $cur['value'];

        // Compound types like list<string> or result<u32, string> or option<u8>
        if (in_array(strtolower($base), ['list', 'option', 'result'], true) && $this->peek()['type'] === '<') {
            $this->advance(); // skip '<'
            $inner = '';
            while (!$this->isAtEnd() && $this->peek()['type'] !== '>') {
                $inner .= $this->advance()['value'];
            }
            $this->consume('>', "Expected '>' closing generic type");
            return "{$base}<{$inner}>";
        }

        return $base;
    }

    private function peek(): array
    {
        return $this->tokens[$this->cursor] ?? ['type' => WitLexer::T_EOF, 'value' => '', 'line' => -1];
    }

    private function advance(): array
    {
        $tok = $this->peek();
        if ($this->cursor < count($this->tokens)) {
            $this->cursor++;
        }
        return $tok;
    }

    private function consume(string $expectedType, string $errorMsg): array
    {
        $current = $this->peek();
        if ($current['type'] !== $expectedType && strtolower($current['value']) !== strtolower($expectedType)) {
            throw new WitParseException("{$errorMsg}. Found '{$current['value']}' at line {$current['line']}");
        }
        return $this->advance();
    }

    private function isAtEnd(): bool
    {
        return $this->peek()['type'] === WitLexer::T_EOF;
    }
}
