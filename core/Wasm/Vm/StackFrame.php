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

namespace NetFusion\Core\Wasm\Vm;

/**
 * Execution frame for a function call in the WebAssembly virtual machine.
 */
class StackFrame
{
    /**
     * @param int $funcIndex
     * @param array<int, mixed> $locals
     * @param int $returnArity
     * @param int $stackBase
     * @param array<array{type: string, targetIp: int, stackHeight: int, arity: int}> $blockStack
     */
    public function __construct(
        public readonly int $funcIndex,
        public array $locals = [],
        public readonly int $returnArity = 0,
        public readonly int $stackBase = 0,
        public array $blockStack = []
    ) {
    }

    public function getLocal(int $idx): mixed
    {
        return $this->locals[$idx] ?? 0;
    }

    public function setLocal(int $idx, mixed $val): void
    {
        $this->locals[$idx] = $val;
    }

    public function pushBlock(string $type, int $targetIp, int $stackHeight, int $arity = 0): void
    {
        $this->blockStack[] = [
            'type' => $type,
            'targetIp' => $targetIp,
            'stackHeight' => $stackHeight,
            'arity' => $arity,
        ];
    }

    public function popBlock(): ?array
    {
        return array_pop($this->blockStack);
    }

    public function getBlock(int $depth = 0): ?array
    {
        $count = count($this->blockStack);
        $index = $count - 1 - $depth;
        return $this->blockStack[$index] ?? null;
    }
}
