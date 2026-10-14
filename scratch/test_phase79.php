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

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Wasm\Binary\BinaryReader;
use NetFusion\Core\Wasm\Binary\Leb128;
use NetFusion\Core\Wasm\Binary\ModuleParser;
use NetFusion\Core\Wasm\Module\WasmExport;
use NetFusion\Core\Wasm\Module\WasmFunction;
use NetFusion\Core\Wasm\Module\WasmImport;
use NetFusion\Core\Wasm\Module\WasmModule;
use NetFusion\Core\Wasm\Runtime\EdgeSandbox;
use NetFusion\Core\Wasm\Runtime\FuelMeter;
use NetFusion\Core\Wasm\Runtime\WasmInstance;
use NetFusion\Core\Wasm\Types\FunctionType;
use NetFusion\Core\Wasm\Types\SectionType;
use NetFusion\Core\Wasm\Types\ValueType;
use NetFusion\Core\Wasm\Vm\LinearMemory;
use NetFusion\Core\Wasm\Vm\Opcode;
use NetFusion\Core\Wasm\Vm\TrapException;
use NetFusion\Core\Wasm\Vm\VirtualMachine;
use NetFusion\Core\Wasm\Wasi\WasiSnapshotPreview1;
use NetFusion\Core\Wasm\WasmManager;

// Bootstrap application container
$app = new Application(dirname(__DIR__));

$passed = 0;
$failed = 0;

function it(string $description, callable $fn): void {
    global $passed, $failed;
    try {
        $fn();
        echo "  ✔ [P79] {$description}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  ✘ [P79] {$description}\n";
        echo "     Error: {$e->getMessage()}\n";
        echo "     At: {$e->getFile()}:{$e->getLine()}\n";
        $failed++;
    }
}

echo "=== NetFusion Phase 79: WebAssembly Runtime, VM & Edge Function Engine ===\n\n";

// ==========================================
// 1. LEB128 Variable-Length Integer SerDe
// ==========================================

it('Leb128 encodes and decodes unsigned and signed integers correctly', function () {
    // Unsigned
    $testUints = [0, 1, 42, 127, 128, 255, 624485, 1000000];
    foreach ($testUints as $u) {
        $encoded = Leb128::encodeUint($u);
        $offset = 0;
        $decoded = Leb128::decodeUint($encoded, $offset);
        assert($decoded === $u, "Failed ULEB128 for {$u}");
        assert($offset === strlen($encoded));
    }

    // Signed
    $testInts = [0, 1, -1, 42, -42, 63, -64, 127, -128, -624485, 1234567, -1234567];
    foreach ($testInts as $s) {
        $encoded = Leb128::encodeInt($s);
        $offset = 0;
        $decoded = Leb128::decodeInt($encoded, $offset);
        assert($decoded === $s, "Failed SLEB128 for {$s}");
        assert($offset === strlen($encoded));
    }
});

// ==========================================
// 2. BinaryReader & Wasm Types
// ==========================================

it('BinaryReader reads bytes, integers, floats, and strings sequentially', function () {
    $bytes = "\x01\x02\x03\x04" . Leb128::encodeUint(42) . pack('g', 3.14) . pack('e', 2.71828) . Leb128::encodeUint(5) . 'Hello';
    $reader = new BinaryReader($bytes);

    assert($reader->readByte() === 1);
    assert($reader->readBytes(3) === "\x02\x03\x04");
    assert($reader->readUint32() === 42);
    assert(abs($reader->readFloat32() - 3.14) < 0.001);
    assert(abs($reader->readFloat64() - 2.71828) < 0.00001);
    assert($reader->readString() === 'Hello');
    assert($reader->isEof() === true);
});

it('ValueType and FunctionType model WebAssembly type signatures', function () {
    $i32 = ValueType::I32;
    $f64 = ValueType::F64;
    assert($i32->typeName() === 'i32');
    assert($f64->typeName() === 'f64');
    assert(ValueType::fromByte(0x7F) === ValueType::I32);

    $sig = new FunctionType([ValueType::I32, ValueType::I32], [ValueType::I32]);
    assert($sig->getParamCount() === 2);
    assert($sig->getReturnCount() === 1);
    assert($sig->toString() === '(i32, i32) -> (i32)');
});

// ==========================================
// 3. Wasm Binary Module Parser
// ==========================================

/**
 * Helper to build a valid minimal Wasm binary module:
 * (module
 *   (func (export "add") (param i32 i32) (result i32)
 *     local.get 0
 *     local.get 1
 *     i32.add
 *   )
 * )
 */
function buildAddWasmBinary(): string {
    $magic = "\x00\x61\x73\x6D";
    $version = "\x01\x00\x00\x00";

    // 1. Type section (ID = 1): 1 type, func (i32, i32) -> i32
    $typePayload = "\x01\x60\x02\x7F\x7F\x01\x7F";
    $typeSec = "\x01" . Leb128::encodeUint(strlen($typePayload)) . $typePayload;

    // 2. Function section (ID = 3): 1 function using type index 0
    $funcPayload = "\x01\x00";
    $funcSec = "\x03" . Leb128::encodeUint(strlen($funcPayload)) . $funcPayload;

    // 3. Export section (ID = 7): 1 export "add", kind func (0), index 0
    $expPayload = "\x01" . Leb128::encodeUint(3) . "add\x00\x00";
    $expSec = "\x07" . Leb128::encodeUint(strlen($expPayload)) . $expPayload;

    // 4. Code section (ID = 10): 1 code body
    // local.get 0 (0x20 0x00), local.get 1 (0x20 0x01), i32.add (0x6A), end (0x0B)
    $instructions = "\x20\x00\x20\x01\x6A\x0B";
    $body = "\x00" . $instructions; // 0 local declarations
    $codePayload = "\x01" . Leb128::encodeUint(strlen($body)) . $body;
    $codeSec = "\x0A" . Leb128::encodeUint(strlen($codePayload)) . $codePayload;

    return $magic . $version . $typeSec . $funcSec . $expSec . $codeSec;
}

it('ModuleParser parses valid Wasm binary and rejects corrupted headers', function () {
    $parser = new ModuleParser();

    // Rejection on bad magic
    $caughtBadMagic = false;
    try {
        $parser->parse("NOT_A_WASM_FILE");
    } catch (\InvalidArgumentException $e) {
        $caughtBadMagic = true;
        assert(str_contains($e->getMessage(), 'Invalid WebAssembly magic header'));
    }
    assert($caughtBadMagic === true);

    // Parsing valid module
    $wasmBinary = buildAddWasmBinary();
    $module = $parser->parse($wasmBinary);

    assert($module instanceof WasmModule);
    assert($module->version === 1);
    assert(count($module->types) === 1);
    assert(count($module->functions) === 1);
    assert($module->hasExport('add') === true);

    $exp = $module->getExport('add');
    assert($exp !== null && $exp->isFunction() === true);
    assert($exp->index === 0);
});

// ==========================================
// 4. Virtual Machine Bytecode Interpreter
// ==========================================

it('VirtualMachine executes i32 arithmetic bytecode correctly', function () {
    $wasmBinary = buildAddWasmBinary();
    $parser = new ModuleParser();
    $module = $parser->parse($wasmBinary);

    $vm = new VirtualMachine($module);
    $result = $vm->invoke('add', [15, 27]);
    assert($result === 42, "Expected 15 + 27 = 42, got " . var_export($result, true));

    $result2 = $vm->invoke('add', [-100, 35]);
    assert($result2 === -65);
});

// ==========================================
// 5. Control Flow (If / Else & Loops)
// ==========================================

/**
 * Builds max(a, b) -> i32:
 * local.get 0
 * local.get 1
 * i32.gt_s
 * if (result i32)
 *   local.get 0
 * else
 *   local.get 1
 * end
 */
function buildMaxWasmBinary(): string {
    $magic = "\x00\x61\x73\x6D";
    $version = "\x01\x00\x00\x00";

    $typePayload = "\x01\x60\x02\x7F\x7F\x01\x7F";
    $typeSec = "\x01" . Leb128::encodeUint(strlen($typePayload)) . $typePayload;

    $funcPayload = "\x01\x00";
    $funcSec = "\x03" . Leb128::encodeUint(strlen($funcPayload)) . $funcPayload;

    $expPayload = "\x01" . Leb128::encodeUint(3) . "max\x00\x00";
    $expSec = "\x07" . Leb128::encodeUint(strlen($expPayload)) . $expPayload;

    // local.get 0 (0x20 0x00), local.get 1 (0x20 0x01), i32.gt_s (0x4A)
    // if i32 (0x04 0x7F)
    //   local.get 0 (0x20 0x00)
    // else (0x05)
    //   local.get 1 (0x20 0x01)
    // end (0x0B)
    // end function (0x0B)
    $instructions = "\x20\x00\x20\x01\x4A\x04\x7F\x20\x00\x05\x20\x01\x0B\x0B";
    $body = "\x00" . $instructions;
    $codePayload = "\x01" . Leb128::encodeUint(strlen($body)) . $body;
    $codeSec = "\x0A" . Leb128::encodeUint(strlen($codePayload)) . $codePayload;

    return $magic . $version . $typeSec . $funcSec . $expSec . $codeSec;
}

it('VirtualMachine evaluates conditional branching (if/else)', function () {
    $wasmBinary = buildMaxWasmBinary();
    $module = (new ModuleParser())->parse($wasmBinary);
    $vm = new VirtualMachine($module);

    assert($vm->invoke('max', [100, 20]) === 100);
    assert($vm->invoke('max', [5, 99]) === 99);
    assert($vm->invoke('max', [-5, -10]) === -5);
});

// ==========================================
// 6. Linear Memory Operations
// ==========================================

it('LinearMemory allocates pages, enforces bounds, and performs typed reads/writes', function () {
    $memory = new LinearMemory(initialPages: 1, maxPages: 4);
    assert($memory->getPageCount() === 1);
    assert($memory->getByteLength() === 65536);

    // Grow memory
    $oldPages = $memory->grow(1);
    assert($oldPages === 1);
    assert($memory->getPageCount() === 2);

    // Write and read i32
    $memory->writeI32(100, 987654);
    assert($memory->readI32(100) === 987654);

    // Write and read string
    $memory->writeBytes(200, "NetFusion Wasm\x00");
    assert($memory->readNullTerminatedString(200) === 'NetFusion Wasm');

    // Out of bounds trap
    $caughtTrap = false;
    try {
        $memory->readI32(1000000);
    } catch (TrapException $e) {
        $caughtTrap = true;
        assert(str_contains($e->getMessage(), 'out of bounds memory access'));
    }
    assert($caughtTrap === true);
});

// ==========================================
// 7. Execution Traps & Fuel Metering
// ==========================================

it('VirtualMachine traps on division by zero and fuel exhaustion', function () {
    // Build divide(a, b) -> i32 (local.get 0, local.get 1, i32.div_s, end)
    $instructions = "\x20\x00\x20\x01\x6D\x0B";
    $module = new WasmModule(
        types: [new FunctionType([ValueType::I32, ValueType::I32], [ValueType::I32])],
        functions: [new WasmFunction(typeIndex: 0, bytecode: $instructions)],
        exports: ['div' => new WasmExport('div', WasmExport::KIND_FUNCTION, 0)]
    );

    $vm = new VirtualMachine($module);
    assert($vm->invoke('div', [40, 4]) === 10);

    // Trap on divide by zero
    $divZeroTrapped = false;
    try {
        $vm->invoke('div', [40, 0]);
    } catch (TrapException $e) {
        $divZeroTrapped = true;
        assert(str_contains($e->getMessage(), 'integer divide by zero'));
    }
    assert($divZeroTrapped === true);

    // Trap on fuel exhaustion
    $fuelMeter = new FuelMeter(fuel: 2, enabled: true); // Only 2 instructions allowed
    $vmFuel = new VirtualMachine($module, null, [], $fuelMeter);
    $fuelTrapped = false;
    try {
        $vmFuel->invoke('div', [10, 2]); // Requires 3+ instructions (local.get, local.get, div_s)
    } catch (TrapException $e) {
        $fuelTrapped = true;
        assert(str_contains($e->getMessage(), 'fuel exhausted'));
    }
    assert($fuelTrapped === true);
});

// ==========================================
// 8. WASI Preview 1 & Host Function Interop
// ==========================================

it('WasiSnapshotPreview1 intercepts stdout and provides random entropy', function () {
    $memory = new LinearMemory(1);
    $wasi = new WasiSnapshotPreview1();
    $funcs = $wasi->getFunctions($memory);

    // 1. fd_write simulation
    // Write message into memory at offset 100: "Hello WASI\n"
    $msg = "Hello WASI\n";
    $memory->writeBytes(100, $msg);

    // Setup ciovec at offset 50: ptr=100, len=strlen($msg)
    $memory->writeI32(50, 100);
    $memory->writeI32(54, strlen($msg));

    // Call fd_write: fd=1 (stdout), iovsPtr=50, iovsLen=1, nwrittenPtr=60
    $ret = $funcs['fd_write'](1, 50, 1, 60);
    assert($ret === 0);
    assert($memory->readI32(60) === strlen($msg));
    assert($wasi->getStdout() === "Hello WASI\n");

    // 2. random_get simulation
    $retRandom = $funcs['random_get'](200, 16);
    assert($retRandom === 0);
    $randomBytes = $memory->readBytes(200, 16);
    assert(strlen($randomBytes) === 16);
    assert($randomBytes !== str_repeat("\x00", 16));
});

it('VirtualMachine calls imported PHP host functions seamlessly', function () {
    // (module (import "env" "triple" (func (param i32) (result i32))) ...)
    $module = new WasmModule(
        types: [new FunctionType([ValueType::I32], [ValueType::I32])],
        imports: [new WasmImport('env', 'triple', WasmImport::KIND_FUNCTION, 0)],
        // Function 1 calls import 0 (call 0, end)
        functions: [new WasmFunction(typeIndex: 0, bytecode: "\x20\x00\x10\x00\x0B")],
        exports: ['compute' => new WasmExport('compute', WasmExport::KIND_FUNCTION, 1)]
    );

    $hostFunctions = [
        'env.triple' => fn(int $x) => $x * 3,
    ];

    $vm = new VirtualMachine($module, null, $hostFunctions);
    $result = $vm->invoke('compute', [14]);
    assert($result === 42, "Expected 14 * 3 = 42, got " . var_export($result, true));
});

// ==========================================
// 9. EdgeSandbox & Serverless Execution
// ==========================================

it('EdgeSandbox executes Wasm module with bounded fuel and captures telemetry', function () {
    $wasmBinary = buildAddWasmBinary();
    $module = (new ModuleParser())->parse($wasmBinary);

    $sandbox = new EdgeSandbox(maxFuel: 10000, maxMemoryPages: 2);
    $telemetry = $sandbox->execute($module, 'add', [50, 75]);

    assert($telemetry['success'] === true);
    assert($telemetry['result'] === 125);
    assert($telemetry['fuelConsumed'] > 0);
    assert($telemetry['durationMs'] >= 0.0);
    assert($telemetry['error'] === null);
});

// ==========================================
// 10. WasmManager Facade & Global Helpers
// ==========================================

it('WasmManager facade and global helpers wasm(), wasm_run(), wasm_module() operate smoothly', function () {
    $mgr = wasm();
    assert($mgr instanceof WasmManager);

    $wasmBinary = buildAddWasmBinary();
    $module = wasm_module($wasmBinary);
    assert($module instanceof WasmModule);

    $res = wasm_run($module, 'add', [300, 20]);
    assert($res === 320);

    $inst = $mgr->instantiate($module);
    assert($inst instanceof WasmInstance);
    assert($inst->call('add', [11, 22]) === 33);
    assert($inst->getExports()['add'] === 'function');
});

// ==========================================
// 11. Console Commands
// ==========================================

it('Console commands wasm:run and wasm:inspect initialize cleanly', function () {
    global $app;

    $runCmd = new \NetFusion\Core\Console\Commands\WasmRunCommand($app);
    assert($runCmd->getName() === 'wasm:run');

    $inspectCmd = new \NetFusion\Core\Console\Commands\WasmInspectCommand($app);
    assert($inspectCmd->getName() === 'wasm:inspect');
});

// ==========================================
// 12. Database Migration
// ==========================================

it('Database migration up and down executes successfully for wasm schema', function () {
    $migration = require dirname(__DIR__) . '/database/migrations/2026_09_28_000062_create_wasm_tables.php';

    $conn = new \NetFusion\Core\Database\Connection(['driver' => 'sqlite', 'database' => ':memory:']);
    $migration->up($conn);

    $pdo = $conn->getPdo();
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='wasm_modules'");
    $hasTable = ($stmt->fetch() !== false);
    $stmt->closeCursor();
    unset($stmt);
    assert($hasTable === true);

    $migration->down($conn);
    $stmt2 = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='wasm_modules'");
    $dropped = ($stmt2->fetch() === false);
    $stmt2->closeCursor();
    unset($stmt2);
    assert($dropped === true);
});

echo "\n--------------------------------------------------\n";
echo "Total Tests : " . ($passed + $failed) . "\n";
echo "Passed      : {$passed}\n";
echo "Failed      : {$failed}\n";
echo "Pass Rate   : " . ($passed + $failed > 0 ? round(($passed / ($passed + $failed)) * 100, 2) : 0) . "%\n";
echo "--------------------------------------------------\n\n";

exit($failed > 0 ? 1 : 0);
