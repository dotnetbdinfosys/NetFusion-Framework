<?php

declare(strict_types=1);

namespace NetFusion\Core\SchemaRegistry;

use NetFusion\Core\SchemaRegistry\Compatibility\CompatibilityChecker;
use NetFusion\Core\SchemaRegistry\Contracts\SchemaInterface;
use NetFusion\Core\SchemaRegistry\Enums\CompatibilityMode;
use NetFusion\Core\SchemaRegistry\Enums\SchemaType;
use NetFusion\Core\SchemaRegistry\Exceptions\IncompatibleSchemaException;
use NetFusion\Core\SchemaRegistry\Exceptions\SchemaRegistryException;

class SchemaRegistry
{
    /** @var array<int, Schema> */
    private array $schemasById = [];

    /** @var array<string, array<int, Schema>> */
    private array $schemasBySubject = [];

    /** @var array<string, CompatibilityMode> */
    private array $subjectModes = [];

    private int $nextId = 1;
    private CompatibilityChecker $compatibilityChecker;

    public function __construct(
        private CompatibilityMode $defaultMode = CompatibilityMode::BACKWARD,
        ?CompatibilityChecker $checker = null
    ) {
        $this->compatibilityChecker = $checker ?? new CompatibilityChecker();
    }

    /**
     * Register a new schema under a subject. If an identical schema already exists, return it.
     */
    public function register(
        string $subject,
        array|string $definition,
        SchemaType $type = SchemaType::AVRO,
        ?CompatibilityMode $overrideMode = null
    ): Schema {
        $candidateSchema = new Schema(
            id: 0,
            subject: $subject,
            version: 0,
            type: $type,
            definition: $definition
        );

        $existingVersions = $this->schemasBySubject[$subject] ?? [];

        // Check if an identical schema fingerprint is already registered for this subject
        foreach ($existingVersions as $existing) {
            if ($existing->getFingerprint() === $candidateSchema->getFingerprint()) {
                return $existing;
            }
        }

        // Determine compatibility mode
        $mode = $overrideMode ?? $this->getCompatibilityMode($subject);

        // Check compatibility against previous versions
        if (!empty($existingVersions)) {
            $checkResult = $this->compatibilityChecker->check(
                $candidateSchema,
                array_values($existingVersions),
                $mode
            );

            if (!$checkResult['compatible']) {
                throw new IncompatibleSchemaException(
                    sprintf(
                        'Schema is incompatible with previous versions of subject "%s" under mode %s: %s',
                        $subject,
                        $mode->value,
                        implode('; ', $checkResult['violations'])
                    ),
                    $checkResult['violations']
                );
            }
        }

        $id = $this->nextId++;
        $version = count($existingVersions) + 1;

        $newSchema = new Schema(
            id: $id,
            subject: $subject,
            version: $version,
            type: $type,
            definition: $definition,
            fingerprint: $candidateSchema->getFingerprint()
        );

        $this->schemasById[$id] = $newSchema;
        $this->schemasBySubject[$subject][$version] = $newSchema;

        return $newSchema;
    }

    public function getById(int $id): ?Schema
    {
        return $this->schemasById[$id] ?? null;
    }

    public function getBySubjectAndVersion(string $subject, int $version): ?Schema
    {
        return $this->schemasBySubject[$subject][$version] ?? null;
    }

    public function getLatest(string $subject): ?Schema
    {
        if (empty($this->schemasBySubject[$subject])) {
            return null;
        }

        $versions = $this->schemasBySubject[$subject];
        $maxVersion = max(array_keys($versions));

        return $versions[$maxVersion];
    }

    /**
     * @return int[]
     */
    public function getAllVersions(string $subject): array
    {
        if (empty($this->schemasBySubject[$subject])) {
            return [];
        }

        return array_keys($this->schemasBySubject[$subject]);
    }

    public function setCompatibilityMode(string $subject, CompatibilityMode $mode): void
    {
        $this->subjectModes[$subject] = $mode;
    }

    public function getCompatibilityMode(string $subject): CompatibilityMode
    {
        return $this->subjectModes[$subject] ?? $this->defaultMode;
    }

    public function deleteSubject(string $subject): bool
    {
        if (!isset($this->schemasBySubject[$subject])) {
            return false;
        }

        foreach ($this->schemasBySubject[$subject] as $schema) {
            unset($this->schemasById[$schema->getId()]);
        }
        unset($this->schemasBySubject[$subject], $this->subjectModes[$subject]);

        return true;
    }

    /**
     * @return string[]
     */
    public function getSubjects(): array
    {
        return array_keys($this->schemasBySubject);
    }
}
