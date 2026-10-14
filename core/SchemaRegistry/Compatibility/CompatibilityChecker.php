<?php

declare(strict_types=1);

namespace NetFusion\Core\SchemaRegistry\Compatibility;

use NetFusion\Core\SchemaRegistry\Contracts\SchemaInterface;
use NetFusion\Core\SchemaRegistry\Enums\CompatibilityMode;
use NetFusion\Core\SchemaRegistry\Enums\SchemaType;

class CompatibilityChecker
{
    /**
     * Check if a candidate schema is compatible with previous schemas according to the compatibility mode.
     *
     * @param SchemaInterface $newSchema
     * @param SchemaInterface[] $previousSchemas
     * @param CompatibilityMode $mode
     * @return array{compatible: bool, violations: string[]}
     */
    public function check(
        SchemaInterface $newSchema,
        array $previousSchemas,
        CompatibilityMode $mode = CompatibilityMode::BACKWARD
    ): array {
        if ($mode === CompatibilityMode::NONE || empty($previousSchemas)) {
            return ['compatible' => true, 'violations' => []];
        }

        $violations = [];

        // Determine which schemas to check against
        $isTransitive = in_array($mode, [
            CompatibilityMode::BACKWARD_TRANSITIVE,
            CompatibilityMode::FORWARD_TRANSITIVE,
            CompatibilityMode::FULL_TRANSITIVE,
        ], true);

        $schemasToCheck = $isTransitive ? $previousSchemas : [end($previousSchemas)];

        foreach ($schemasToCheck as $oldSchema) {
            if ($newSchema->getType() !== $oldSchema->getType()) {
                $violations[] = sprintf(
                    'Schema type mismatch: new is %s, previous is %s',
                    $newSchema->getType()->value,
                    $oldSchema->getType()->value
                );
                continue;
            }

            $checkBackward = in_array($mode, [
                CompatibilityMode::BACKWARD,
                CompatibilityMode::BACKWARD_TRANSITIVE,
                CompatibilityMode::FULL,
                CompatibilityMode::FULL_TRANSITIVE,
            ], true);

            $checkForward = in_array($mode, [
                CompatibilityMode::FORWARD,
                CompatibilityMode::FORWARD_TRANSITIVE,
                CompatibilityMode::FULL,
                CompatibilityMode::FULL_TRANSITIVE,
            ], true);

            if ($checkBackward) {
                $backwardViolations = $this->checkBackwardCompatibility($newSchema, $oldSchema);
                $violations = array_merge($violations, $backwardViolations);
            }

            if ($checkForward) {
                $forwardViolations = $this->checkForwardCompatibility($newSchema, $oldSchema);
                $violations = array_merge($violations, $forwardViolations);
            }
        }

        return [
            'compatible' => empty($violations),
            'violations' => array_values(array_unique($violations)),
        ];
    }

    /**
     * Backward: New schema can read data written with old schema.
     * Rule: Any newly added field must have a default value or be marked optional.
     *
     * @return string[]
     */
    private function checkBackwardCompatibility(SchemaInterface $newSchema, SchemaInterface $oldSchema): array
    {
        $violations = [];
        $newDef = $newSchema->getDefinition();
        $oldDef = $oldSchema->getDefinition();

        $oldFields = $this->extractFields($oldDef, $oldSchema->getType());
        $newFields = $this->extractFields($newDef, $newSchema->getType());

        // Check new fields: if added in new, must have default or be optional
        foreach ($newFields as $name => $field) {
            if (!isset($oldFields[$name])) {
                $hasDefault = array_key_exists('default', $field);
                $isOptional = ($field['optional'] ?? false) || ($field['type'] ?? '') === 'null'
                    || (is_array($field['type'] ?? null) && in_array('null', $field['type'], true));

                if (!$hasDefault && !$isOptional) {
                    $violations[] = sprintf(
                        'BACKWARD incompatibility: Newly added field "%s" must have a default value or be optional',
                        $name
                    );
                }
            } else {
                // Field exists in both: verify type compatibility
                if (!$this->isTypeCompatible($oldFields[$name]['type'] ?? null, $field['type'] ?? null)) {
                    $violations[] = sprintf(
                        'BACKWARD incompatibility: Field "%s" type changed incompatibly',
                        $name
                    );
                }
            }
        }

        return $violations;
    }

    /**
     * Forward: Old schema can read data written with new schema.
     * Rule: Any field deleted in new schema must have had a default value in old schema.
     *
     * @return string[]
     */
    private function checkForwardCompatibility(SchemaInterface $newSchema, SchemaInterface $oldSchema): array
    {
        $violations = [];
        $newDef = $newSchema->getDefinition();
        $oldDef = $oldSchema->getDefinition();

        $oldFields = $this->extractFields($oldDef, $oldSchema->getType());
        $newFields = $this->extractFields($newDef, $newSchema->getType());

        // Check deleted fields
        foreach ($oldFields as $name => $field) {
            if (!isset($newFields[$name])) {
                $hasDefault = array_key_exists('default', $field);
                $isOptional = ($field['optional'] ?? false) || ($field['type'] ?? '') === 'null'
                    || (is_array($field['type'] ?? null) && in_array('null', $field['type'], true));

                if (!$hasDefault && !$isOptional) {
                    $violations[] = sprintf(
                        'FORWARD incompatibility: Field "%s" was deleted but had no default value or optionality in old schema',
                        $name
                    );
                }
            }
        }

        return $violations;
    }

    /**
     * Normalize fields from Avro, JSON Schema, or Protobuf definition.
     *
     * @return array<string, array>
     */
    private function extractFields(array $definition, SchemaType $type): array
    {
        $fields = [];

        if ($type === SchemaType::AVRO && isset($definition['fields']) && is_array($definition['fields'])) {
            foreach ($definition['fields'] as $f) {
                if (isset($f['name'])) {
                    $fields[$f['name']] = $f;
                }
            }
        } elseif ($type === SchemaType::JSON_SCHEMA && isset($definition['properties']) && is_array($definition['properties'])) {
            $required = $definition['required'] ?? [];
            foreach ($definition['properties'] as $propName => $prop) {
                $prop['optional'] = !in_array($propName, $required, true);
                $fields[$propName] = $prop;
            }
        } elseif ($type === SchemaType::PROTOBUF && isset($definition['fields']) && is_array($definition['fields'])) {
            foreach ($definition['fields'] as $num => $f) {
                $name = $f['name'] ?? "field_{$num}";
                $fields[$name] = $f;
            }
        }

        return $fields;
    }

    private function isTypeCompatible(mixed $oldType, mixed $newType): bool
    {
        if ($oldType === $newType) {
            return true;
        }

        // Promotable Avro types: int -> long -> float -> double
        $promotions = [
            'int' => ['long', 'float', 'double'],
            'long' => ['float', 'double'],
            'float' => ['double'],
            'string' => ['bytes'],
            'bytes' => ['string'],
        ];

        if (is_string($oldType) && is_string($newType) && isset($promotions[$oldType])) {
            return in_array($newType, $promotions[$oldType], true);
        }

        return false;
    }
}
