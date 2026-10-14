<?php

declare(strict_types=1);

namespace NetFusion\Core\SchemaRegistry;

use NetFusion\Core\SchemaRegistry\CloudEvents\CloudEvent;
use NetFusion\Core\SchemaRegistry\CloudEvents\EventMeshRouter;
use NetFusion\Core\SchemaRegistry\Compatibility\CompatibilityChecker;
use NetFusion\Core\SchemaRegistry\Contracts\SchemaInterface;
use NetFusion\Core\SchemaRegistry\Enums\CompatibilityMode;
use NetFusion\Core\SchemaRegistry\Enums\SchemaType;
use NetFusion\Core\SchemaRegistry\Exceptions\SchemaRegistryException;
use NetFusion\Core\SchemaRegistry\SerDe\AvroSerDe;
use NetFusion\Core\SchemaRegistry\SerDe\ProtobufSerDe;
use NetFusion\Core\SchemaRegistry\SerDe\WireFormat;

class SchemaManager
{
    private static ?self $instance = null;

    private SchemaRegistry $registry;
    private ProtobufSerDe $protobufSerDe;
    private AvroSerDe $avroSerDe;
    private EventMeshRouter $eventMesh;
    private CompatibilityChecker $checker;

    public function __construct(
        CompatibilityMode $defaultMode = CompatibilityMode::BACKWARD,
        ?SchemaRegistry $registry = null,
        ?ProtobufSerDe $protobuf = null,
        ?AvroSerDe $avro = null,
        ?EventMeshRouter $mesh = null
    ) {
        $this->checker = new CompatibilityChecker();
        $this->registry = $registry ?? new SchemaRegistry($defaultMode, $this->checker);
        $this->protobufSerDe = $protobuf ?? new ProtobufSerDe();
        $this->avroSerDe = $avro ?? new AvroSerDe();
        $this->eventMesh = $mesh ?? new EventMeshRouter();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function registry(): SchemaRegistry
    {
        return $this->registry;
    }

    public function protobuf(): ProtobufSerDe
    {
        return $this->protobufSerDe;
    }

    public function avro(): AvroSerDe
    {
        return $this->avroSerDe;
    }

    public function mesh(): EventMeshRouter
    {
        return $this->eventMesh;
    }

    public function checker(): CompatibilityChecker
    {
        return $this->checker;
    }

    /**
     * Convenience method to register a schema under a subject.
     */
    public function register(
        string $subject,
        array|string $definition,
        SchemaType $type = SchemaType::AVRO,
        ?CompatibilityMode $mode = null
    ): Schema {
        return $this->registry->register($subject, $definition, $type, $mode);
    }

    /**
     * Serializes data using the subject's latest schema, optionally wrapping with Confluent Wire Format.
     */
    public function encode(string $subject, mixed $data, bool $withWireHeader = true): string
    {
        $schema = $this->registry->getLatest($subject);
        if ($schema === null) {
            throw new SchemaRegistryException("No registered schema found for subject: '{$subject}'");
        }

        $payload = match ($schema->getType()) {
            SchemaType::AVRO => $this->avroSerDe->encode($data, $schema->getDefinition()),
            SchemaType::PROTOBUF => $this->protobufSerDe->encode((array) $data, $schema->getDefinition()),
            SchemaType::JSON_SCHEMA => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
        };

        if ($withWireHeader) {
            return WireFormat::encode($schema->getId(), $payload);
        }

        return $payload;
    }

    /**
     * Deserializes a binary payload using the embedded Confluent Wire Format schema ID.
     */
    public function decode(string $binary, ?SchemaInterface $fallbackSchema = null): mixed
    {
        $schema = null;
        $payload = $binary;

        if (WireFormat::hasWireHeader($binary)) {
            $decoded = WireFormat::decode($binary);
            $schemaId = $decoded['schema_id'];
            $payload = $decoded['payload'];

            $schema = $this->registry->getById($schemaId);
            if ($schema === null && $fallbackSchema === null) {
                throw new SchemaRegistryException("Schema with ID {$schemaId} not found in registry");
            }
        }

        $schema = $schema ?? $fallbackSchema;
        if ($schema === null) {
            throw new SchemaRegistryException('Cannot deserialize payload without a known schema or wire header');
        }

        return match ($schema->getType()) {
            SchemaType::AVRO => $this->avroSerDe->decode($payload, $schema->getDefinition()),
            SchemaType::PROTOBUF => $this->protobufSerDe->decode($payload, $schema->getDefinition()),
            SchemaType::JSON_SCHEMA => json_decode($payload, true),
        };
    }

    /**
     * Publish CloudEvent to the mesh.
     *
     * @return array<string, mixed>
     */
    public function publishEvent(CloudEvent $event): array
    {
        return $this->eventMesh->publish($event);
    }

    /**
     * Subscribe to CloudEvents on the mesh.
     */
    public function subscribeEvent(string $typePattern, callable $handler, ?string $sourcePattern = null): string
    {
        return $this->eventMesh->subscribe($typePattern, $handler, $sourcePattern);
    }
}
