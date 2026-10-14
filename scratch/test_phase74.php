<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Application;
use NetFusion\Core\Console\Kernel;
use NetFusion\Core\SchemaRegistry\CloudEvents\CloudEvent;
use NetFusion\Core\SchemaRegistry\CloudEvents\EventMeshRouter;
use NetFusion\Core\SchemaRegistry\Compatibility\CompatibilityChecker;
use NetFusion\Core\SchemaRegistry\Enums\CompatibilityMode;
use NetFusion\Core\SchemaRegistry\Enums\SchemaType;
use NetFusion\Core\SchemaRegistry\Exceptions\IncompatibleSchemaException;
use NetFusion\Core\SchemaRegistry\Schema;
use NetFusion\Core\SchemaRegistry\SchemaManager;
use NetFusion\Core\SchemaRegistry\SchemaRegistry;
use NetFusion\Core\SchemaRegistry\SerDe\AvroSerDe;
use NetFusion\Core\SchemaRegistry\SerDe\ProtobufSerDe;
use NetFusion\Core\SchemaRegistry\SerDe\Varint;
use NetFusion\Core\SchemaRegistry\SerDe\WireFormat;

$app = new Application(realpath(__DIR__ . '/..'));
$kernel = new Kernel($app);

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertTest(string $description, bool $condition): void
{
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  ✔ {$description}\n";
    } else {
        $failedTests++;
        echo "  ✖ FAIL: {$description}\n";
    }
}

echo "\n--- Phase 74: Enterprise Schema Registry, SerDe & CloudEvents Fabric Suite ---\n\n";

// -------------------------------------------------------------
// 1. Schema & Fingerprint
// -------------------------------------------------------------
$avroDef1 = [
    'type' => 'record',
    'name' => 'User',
    'fields' => [
        ['name' => 'id', 'type' => 'long'],
        ['name' => 'username', 'type' => 'string'],
        ['name' => 'email', 'type' => 'string'],
    ],
];
$schema1 = new Schema(1, 'users-value', 1, SchemaType::AVRO, $avroDef1);
assertTest('Schema correctly stores metadata and calculates SHA-256 fingerprint',
    $schema1->getId() === 1 &&
    $schema1->getSubject() === 'users-value' &&
    $schema1->getVersion() === 1 &&
    $schema1->getType() === SchemaType::AVRO &&
    strlen($schema1->getFingerprint()) === 64
);

// -------------------------------------------------------------
// 2. SchemaRegistry Registration & Versioning
// -------------------------------------------------------------
$registry = new SchemaRegistry(CompatibilityMode::BACKWARD);
$reg1 = $registry->register('orders-value', $avroDef1, SchemaType::AVRO);
assertTest('SchemaRegistry registers initial schema with ID 1 and version 1',
    $reg1->getId() === 1 && $reg1->getVersion() === 1
);

// Register same definition returns existing schema instance
$regDuplicate = $registry->register('orders-value', $avroDef1, SchemaType::AVRO);
assertTest('SchemaRegistry deduplicates identical schema registrations',
    $regDuplicate->getId() === $reg1->getId() && $regDuplicate->getVersion() === 1
);

// -------------------------------------------------------------
// 3. CompatibilityChecker
// -------------------------------------------------------------
$checker = new CompatibilityChecker();

// Backward compatible: adding a field with a default
$avroDef2Compatible = [
    'type' => 'record',
    'name' => 'User',
    'fields' => [
        ['name' => 'id', 'type' => 'long'],
        ['name' => 'username', 'type' => 'string'],
        ['name' => 'email', 'type' => 'string'],
        ['name' => 'is_verified', 'type' => 'boolean', 'default' => false],
    ],
];
$schema2Candidate = new Schema(2, 'orders-value', 2, SchemaType::AVRO, $avroDef2Compatible);
$resCompat = $checker->check($schema2Candidate, [$reg1], CompatibilityMode::BACKWARD);
assertTest('CompatibilityChecker accepts BACKWARD compatible addition with default value',
    $resCompat['compatible'] === true && empty($resCompat['violations'])
);

// Backward incompatible: adding a field without a default
$avroDef2Incompatible = [
    'type' => 'record',
    'name' => 'User',
    'fields' => [
        ['name' => 'id', 'type' => 'long'],
        ['name' => 'username', 'type' => 'string'],
        ['name' => 'email', 'type' => 'string'],
        ['name' => 'required_phone', 'type' => 'string'], // no default
    ],
];
$schemaIncompatCandidate = new Schema(2, 'orders-value', 2, SchemaType::AVRO, $avroDef2Incompatible);
$resIncompat = $checker->check($schemaIncompatCandidate, [$reg1], CompatibilityMode::BACKWARD);
assertTest('CompatibilityChecker rejects BACKWARD incompatible addition without default',
    $resIncompat['compatible'] === false && count($resIncompat['violations']) > 0
);

// Registry throws IncompatibleSchemaException when registering incompatible schema
$threwIncompat = false;
try {
    $registry->register('orders-value', $avroDef2Incompatible, SchemaType::AVRO);
} catch (IncompatibleSchemaException $e) {
    $threwIncompat = true;
}
assertTest('SchemaRegistry throws IncompatibleSchemaException on breaking schema', $threwIncompat);

// Registering valid v2 succeeds
$reg2 = $registry->register('orders-value', $avroDef2Compatible, SchemaType::AVRO);
assertTest('SchemaRegistry registers backward-compatible v2 with ID 2 and version 2',
    $reg2->getId() === 2 && $reg2->getVersion() === 2 && $registry->getLatest('orders-value')->getVersion() === 2
);

// -------------------------------------------------------------
// 4. Confluent Wire Format (Magic Byte + 4-byte ID)
// -------------------------------------------------------------
$samplePayload = 'Hello Avro';
$wireBytes = WireFormat::encode(42, $samplePayload);
assertTest('WireFormat encodes 5-byte header (0x00 + 4-byte big-endian ID)',
    strlen($wireBytes) === 5 + strlen($samplePayload) &&
    $wireBytes[0] === "\x00" &&
    WireFormat::hasWireHeader($wireBytes)
);

$decodedWire = WireFormat::decode($wireBytes);
assertTest('WireFormat decodes schema ID and payload cleanly',
    $decodedWire['schema_id'] === 42 &&
    $decodedWire['payload'] === $samplePayload
);

// -------------------------------------------------------------
// 5. Varint & ZigZag Encoding
// -------------------------------------------------------------
$varint1 = Varint::encode(300);
$offset = 0;
$decodedVarint1 = Varint::decode($varint1, $offset);
assertTest('Varint encodes and decodes integer 300 across 2 bytes',
    strlen($varint1) === 2 && $decodedVarint1 === 300
);

$zzPositive = Varint::encodeZigZag32(42);
$zzNegative = Varint::encodeZigZag32(-42);
assertTest('ZigZag32 encodes positive and negative integers',
    Varint::decodeZigZag32($zzPositive) === 42 &&
    Varint::decodeZigZag32($zzNegative) === -42
);

$zz64Pos = Varint::encodeZigZag64(9876543210);
$zz64Neg = Varint::encodeZigZag64(-9876543210);
assertTest('ZigZag64 encodes large 64-bit numbers',
    Varint::decodeZigZag64($zz64Pos) === 9876543210 &&
    Varint::decodeZigZag64($zz64Neg) === -9876543210
);

// -------------------------------------------------------------
// 6. Protocol Buffers (Protobuf v3) SerDe
// -------------------------------------------------------------
$pbSchema = [
    'fields' => [
        1 => ['name' => 'user_id', 'type' => 'int64'],
        2 => ['name' => 'username', 'type' => 'string'],
        3 => ['name' => 'is_admin', 'type' => 'bool'],
        4 => ['name' => 'score', 'type' => 'double'],
        5 => ['name' => 'tags', 'type' => 'string', 'repeated' => true],
    ],
];
$pbSerDe = new ProtobufSerDe();
$originalPbData = [
    'user_id' => 1001,
    'username' => 'alice',
    'is_admin' => true,
    'score' => 99.5,
    'tags' => ['vip', 'staff'],
];
$encodedPb = $pbSerDe->encode($originalPbData, $pbSchema);
assertTest('ProtobufSerDe encodes protobuf message bytes', strlen($encodedPb) > 0);

$decodedPb = $pbSerDe->decode($encodedPb, $pbSchema);
assertTest('ProtobufSerDe decodes message with matching fields and types',
    $decodedPb['user_id'] === 1001 &&
    $decodedPb['username'] === 'alice' &&
    $decodedPb['is_admin'] === true &&
    abs($decodedPb['score'] - 99.5) < 0.001 &&
    $decodedPb['tags'] === ['vip', 'staff']
);

// -------------------------------------------------------------
// 7. Apache Avro SerDe
// -------------------------------------------------------------
$avroSerDe = new AvroSerDe();
$avroUserSchema = [
    'type' => 'record',
    'name' => 'Customer',
    'fields' => [
        ['name' => 'id', 'type' => 'long'],
        ['name' => 'name', 'type' => 'string'],
        ['name' => 'active', 'type' => 'boolean'],
        ['name' => 'rating', 'type' => 'double'],
        ['name' => 'status', 'type' => ['type' => 'enum', 'symbols' => ['NEW', 'ACTIVE', 'SUSPENDED']]],
        ['name' => 'interests', 'type' => ['type' => 'array', 'items' => 'string']],
    ],
];
$originalAvroData = [
    'id' => 500,
    'name' => 'Bob Marley',
    'active' => true,
    'rating' => 4.8,
    'status' => 'ACTIVE',
    'interests' => ['music', 'reggae'],
];
$encodedAvro = $avroSerDe->encode($originalAvroData, $avroUserSchema);
assertTest('AvroSerDe encodes binary record with enums and arrays', strlen($encodedAvro) > 0);

$decodedAvro = $avroSerDe->decode($encodedAvro, $avroUserSchema);
assertTest('AvroSerDe decodes binary record back to associative array',
    $decodedAvro['id'] === 500 &&
    $decodedAvro['name'] === 'Bob Marley' &&
    $decodedAvro['active'] === true &&
    abs($decodedAvro['rating'] - 4.8) < 0.001 &&
    $decodedAvro['status'] === 'ACTIVE' &&
    $decodedAvro['interests'] === ['music', 'reggae']
);

// -------------------------------------------------------------
// 8. CloudEvents 1.0 Universal Specification
// -------------------------------------------------------------
$ce = CloudEvent::create(
    type: 'com.netfusion.order.created',
    source: '/services/order-service',
    data: ['order_id' => 'ORD-101', 'amount' => 250.75],
    subject: 'order_101',
    extensions: ['tenant' => 'tenant_primary']
);

assertTest('CloudEvent creates valid 1.0 envelope with UUID and timestamp',
    $ce->getSpecVersion() === '1.0' &&
    $ce->getType() === 'com.netfusion.order.created' &&
    $ce->getSource() === '/services/order-service' &&
    $ce->getSubject() === 'order_101' &&
    $ce->getExtension('tenant') === 'tenant_primary' &&
    $ce->getTime() !== null
);

$structuredJson = $ce->toStructuredJson();
$reconstitutedCe = CloudEvent::fromStructuredJson($structuredJson);
assertTest('CloudEvent roundtrips cleanly via Structured JSON',
    $reconstitutedCe->getId() === $ce->getId() &&
    $reconstitutedCe->getType() === $ce->getType() &&
    $reconstitutedCe->getData()['order_id'] === 'ORD-101'
);

$binaryHeaders = $ce->toBinaryHeaders();
assertTest('CloudEvent formats HTTP binary headers with ce- prefix',
    isset($binaryHeaders['ce-id']) &&
    isset($binaryHeaders['ce-type']) &&
    $binaryHeaders['ce-type'] === 'com.netfusion.order.created' &&
    $binaryHeaders['ce-tenant'] === 'tenant_primary'
);

$fromBinaryCe = CloudEvent::fromBinary($binaryHeaders, ['order_id' => 'ORD-101']);
assertTest('CloudEvent reconstructs from binary headers and payload',
    $fromBinaryCe->getId() === $ce->getId() &&
    $fromBinaryCe->getExtension('tenant') === 'tenant_primary'
);

// -------------------------------------------------------------
// 9. EventMeshRouter
// -------------------------------------------------------------
$meshRouter = new EventMeshRouter();
$receivedEvent = null;
$subId = $meshRouter->subscribe('com.netfusion.order.*', function (CloudEvent $event) use (&$receivedEvent) {
    $receivedEvent = $event;
    return 'ACK';
});

assertTest('EventMeshRouter accepts wildcard pattern subscription', $meshRouter->getSubscriptionCount() === 1);

$dispatchResults = $meshRouter->publish($ce);
assertTest('EventMeshRouter dispatches CloudEvent to matching subscriber',
    isset($dispatchResults[$subId]) &&
    $dispatchResults[$subId] === 'ACK' &&
    $receivedEvent !== null &&
    $receivedEvent->getId() === $ce->getId()
);

// -------------------------------------------------------------
// 10. SchemaManager Central Facade & End-to-End Serialization
// -------------------------------------------------------------
$manager = new SchemaManager();
SchemaManager::setInstance($manager);

$manager->register('products-avro', [
    'type' => 'record',
    'name' => 'Product',
    'fields' => [
        ['name' => 'sku', 'type' => 'string'],
        ['name' => 'price', 'type' => 'double'],
        ['name' => 'in_stock', 'type' => 'boolean'],
    ],
], SchemaType::AVRO);

$productData = ['sku' => 'LAPTOP-PRO-16', 'price' => 1899.99, 'in_stock' => true];
$wireData = $manager->encode('products-avro', $productData);

assertTest('SchemaManager encodes payload with automatic Confluent WireFormat header',
    WireFormat::hasWireHeader($wireData)
);

$decodedProduct = $manager->decode($wireData);
assertTest('SchemaManager decodes payload automatically looking up schema by Wire ID',
    $decodedProduct['sku'] === 'LAPTOP-PRO-16' &&
    abs($decodedProduct['price'] - 1899.99) < 0.001 &&
    $decodedProduct['in_stock'] === true
);

// -------------------------------------------------------------
// 11. CLI Commands
// -------------------------------------------------------------
$regExit = $kernel->call('schema:register', [
    'inventory-item',
    json_encode([
        'type' => 'record',
        'name' => 'Item',
        'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'title', 'type' => 'string'],
        ],
    ]),
    '--type=AVRO',
]);
assertTest('CLI command schema:register registers new schema successfully', $regExit === 0);

$checkExit = $kernel->call('schema:check', [
    'inventory-item',
    json_encode([
        'type' => 'record',
        'name' => 'Item',
        'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'category', 'type' => 'string', 'default' => 'general'],
        ],
    ]),
    '--mode=BACKWARD',
]);
assertTest('CLI command schema:check confirms backward compatible candidate', $checkExit === 0);

$ceExit = $kernel->call('cloudevent:publish', [
    'inventory.updated',
    '/services/warehouse',
    '--data={"id":42,"qty":100}',
]);
assertTest('CLI command cloudevent:publish publishes message to event mesh', $ceExit === 0);

// -------------------------------------------------------------
// 12. Global Helpers
// -------------------------------------------------------------
assertTest('Global helper schema_manager() returns SchemaManager singleton', schema_manager() instanceof SchemaManager);
assertTest('Global helper schema_registry() returns SchemaRegistry repository', schema_registry() instanceof SchemaRegistry);
$helperCe = cloudevent('payment.processed', '/services/billing', ['amount' => 120.0]);
assertTest('Global helper cloudevent() instantiates valid CloudEvent',
    $helperCe instanceof CloudEvent && $helperCe->getType() === 'payment.processed'
);

$helperEncoded = schema_encode('inventory-item', ['id' => 99, 'title' => 'Keyboard']);
$helperDecoded = schema_decode($helperEncoded);
assertTest('Global helpers schema_encode() and schema_decode() roundtrip cleanly',
    is_array($helperDecoded) &&
    $helperDecoded['id'] === 99 &&
    $helperDecoded['title'] === 'Keyboard'
);

echo "\n--- Summary ---\n";
echo "Total: {$totalTests}\n";
echo "Passed: {$passedTests}\n";
echo "Failed: {$failedTests}\n";
echo "Rate: " . round(($passedTests / max(1, $totalTests)) * 100, 2) . "%\n";

exit($failedTests > 0 ? 1 : 0);
