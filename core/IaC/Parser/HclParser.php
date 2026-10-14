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

namespace NetFusion\Core\IaC\Parser;

use NetFusion\Core\IaC\Ast\AstBlock;
use NetFusion\Core\IaC\Ast\BlockType;

/**
 * Pure PHP Parser transforming HCL tokens into an Abstract Syntax Tree (AST).
 */
class HclParser
{
    /**
     * @var array<HclToken>
     */
    private array $tokens = [];
    private int $pos = 0;
    private int $count = 0;

    /**
     * @var array<string, mixed> User-supplied input variables for interpolation
     */
    private array $variables = [];

    /**
     * Parse HCL source text into an array of AstBlock objects.
     *
     * @param string $source HCL configuration string
     * @param array<string, mixed> $variables Input variables
     * @return array<AstBlock>
     */
    public function parse(string $source, array $variables = []): array
    {
        $lexer = new HclLexer($source);
        $this->tokens = $lexer->tokenize();
        $this->pos = 0;
        $this->count = count($this->tokens);
        $this->variables = $variables;

        $blocks = [];
        while (!$this->isAtEnd()) {
            if ($this->check(HclToken::T_EOF)) {
                break;
            }

            $block = $this->parseTopLevelBlock();
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    private function parseTopLevelBlock(): ?AstBlock
    {
        $token = $this->peek();
        if ($token->type !== HclToken::T_IDENTIFIER) {
            $this->advance();
            return null;
        }

        $line = $token->line;
        $keyword = strtolower((string) $token->value);
        $this->advance();

        $blockType = BlockType::tryFrom($keyword) ?? BlockType::RESOURCE;

        $labels = [];
        while (!$this->isAtEnd() && !$this->check(HclToken::T_LBRACE)) {
            $labelToken = $this->peek();
            if ($labelToken->type === HclToken::T_STRING || $labelToken->type === HclToken::T_IDENTIFIER) {
                $labels[] = (string) $labelToken->value;
                $this->advance();
            } else {
                break;
            }
        }

        // Determine type and name from labels
        $type = '';
        $name = '';
        if ($blockType === BlockType::RESOURCE || $blockType === BlockType::DATA) {
            $type = $labels[0] ?? 'generic';
            $name = $labels[1] ?? 'default';
        } else {
            $type = $keyword;
            $name = $labels[0] ?? 'default';
        }

        $attributes = [];
        $nestedBlocks = [];

        if ($this->match(HclToken::T_LBRACE)) {
            $body = $this->parseBlockBody();
            $attributes = $body['attributes'];
            $nestedBlocks = $body['nested_blocks'];
        }

        return new AstBlock(
            blockType: $blockType,
            type: $type,
            name: $name,
            attributes: $attributes,
            nestedBlocks: $nestedBlocks,
            labels: $labels,
            line: $line
        );
    }

    /**
     * @return array{attributes: array<string, mixed>, nested_blocks: array<AstBlock>}
     */
    private function parseBlockBody(): array
    {
        $attributes = [];
        $nestedBlocks = [];

        while (!$this->isAtEnd() && !$this->check(HclToken::T_RBRACE)) {
            $token = $this->peek();
            if ($token->type === HclToken::T_IDENTIFIER || $token->type === HclToken::T_STRING) {
                $key = (string) $token->value;
                $this->advance();

                // Case 1: Sub-block (e.g. `ingress { ... }` or `ingress "label" { ... }`)
                if ($this->check(HclToken::T_LBRACE) || ($this->peek()->type === HclToken::T_STRING && $this->peekNext()?->type === HclToken::T_LBRACE)) {
                    $subLabels = [];
                    while (!$this->check(HclToken::T_LBRACE) && !$this->isAtEnd()) {
                        $subLabels[] = (string) $this->advance()->value;
                    }

                    if ($this->match(HclToken::T_LBRACE)) {
                        $subBody = $this->parseBlockBody();
                        $nestedBlocks[] = new AstBlock(
                            blockType: BlockType::RESOURCE,
                            type: $key,
                            name: $subLabels[0] ?? $key,
                            attributes: $subBody['attributes'],
                            nestedBlocks: $subBody['nested_blocks'],
                            labels: $subLabels,
                            line: $token->line
                        );
                    }
                    continue;
                }

                // Case 2: Attribute assignment (key = value)
                if ($this->match(HclToken::T_EQUALS)) {
                    $val = $this->parseExpression();
                    $attributes[$key] = $val;
                }
            } else {
                $this->advance();
            }
        }

        $this->match(HclToken::T_RBRACE);
        return ['attributes' => $attributes, 'nested_blocks' => $nestedBlocks];
    }

    private function parseExpression(): mixed
    {
        $token = $this->peek();

        // 1. Strings with variable interpolation
        if ($token->type === HclToken::T_STRING) {
            $this->advance();
            return $this->interpolateString((string) $token->value);
        }

        // 2. Numbers & Booleans
        if ($token->type === HclToken::T_NUMBER || $token->type === HclToken::T_BOOLEAN) {
            $this->advance();
            return $token->value;
        }

        // 3. Lists ([ ... ])
        if ($this->match(HclToken::T_LBRACKET)) {
            $list = [];
            while (!$this->isAtEnd() && !$this->check(HclToken::T_RBRACKET)) {
                $list[] = $this->parseExpression();
                $this->match(HclToken::T_COMMA);
            }
            $this->match(HclToken::T_RBRACKET);
            return $list;
        }

        // 4. Maps ({ key = val, ... })
        if ($this->match(HclToken::T_LBRACE)) {
            $map = [];
            while (!$this->isAtEnd() && !$this->check(HclToken::T_RBRACE)) {
                $keyToken = $this->advance();
                $mapKey = (string) $keyToken->value;
                if ($this->match(HclToken::T_EQUALS) || $this->match(HclToken::T_COMMA)) {
                    $map[$mapKey] = $this->parseExpression();
                }
                $this->match(HclToken::T_COMMA);
            }
            $this->match(HclToken::T_RBRACE);
            return $map;
        }

        // 5. Identifiers / References (var.env, aws_instance.web.id)
        if ($token->type === HclToken::T_IDENTIFIER) {
            $this->advance();
            $val = (string) $token->value;
            return $this->resolveVariableReference($val);
        }

        $this->advance();
        return null;
    }

    /**
     * Interpolate `${var.name}` or bare string tokens with input variables.
     */
    private function interpolateString(string $str): string
    {
        return preg_replace_callback('/\$\{\s*var\.([a-zA-Z0-9_]+)\s*\}/', function ($matches) {
            $varName = $matches[1];
            return isset($this->variables[$varName]) ? (string) $this->variables[$varName] : $matches[0];
        }, $str);
    }

    /**
     * Resolve variable references e.g. var.region
     */
    private function resolveVariableReference(string $ref): mixed
    {
        if (str_starts_with($ref, 'var.')) {
            $varName = substr($ref, 4);
            if (array_key_exists($varName, $this->variables)) {
                return $this->variables[$varName];
            }
        }

        return $ref;
    }

    private function peek(): HclToken
    {
        return $this->tokens[$this->pos] ?? new HclToken(HclToken::T_EOF, null);
    }

    private function peekNext(): ?HclToken
    {
        return $this->tokens[$this->pos + 1] ?? null;
    }

    private function advance(): HclToken
    {
        if (!$this->isAtEnd()) {
            $this->pos++;
        }
        return $this->tokens[$this->pos - 1];
    }

    private function match(string $type): bool
    {
        if ($this->check($type)) {
            $this->advance();
            return true;
        }
        return false;
    }

    private function check(string $type): bool
    {
        if ($this->isAtEnd()) {
            return false;
        }
        return $this->peek()->type === $type;
    }

    private function isAtEnd(): bool
    {
        return $this->pos >= $this->count || $this->peek()->type === HclToken::T_EOF;
    }
}
