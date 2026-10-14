<?php

declare(strict_types=1);

namespace NetFusion\Core\SchemaRegistry;

use NetFusion\Core\SchemaRegistry\Contracts\SchemaInterface;
use NetFusion\Core\SchemaRegistry\Enums\SchemaType;

class Schema implements SchemaInterface
{
    private string $fingerprint;
    private string $definitionString;
    private array $definition;

    public function __construct(
        private readonly int $id,
        private readonly string $subject,
        private readonly int $version,
        private readonly SchemaType $type,
        array|string $definition,
        ?string $fingerprint = null
    ) {
        if (is_string($definition)) {
            $this->definitionString = $definition;
            $decoded = json_decode($definition, true);
            $this->definition = is_array($decoded) ? $decoded : ['raw' => $definition];
        } else {
            $this->definition = $definition;
            $this->definitionString = json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        $this->fingerprint = $fingerprint ?? hash('sha256', $this->canonicalize($this->definition));
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getType(): SchemaType
    {
        return $this->type;
    }

    public function getDefinition(): array
    {
        return $this->definition;
    }

    public function getDefinitionString(): string
    {
        return $this->definitionString;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'version' => $this->version,
            'schemaType' => $this->type->value,
            'schema' => $this->definitionString,
            'fingerprint' => $this->fingerprint,
        ];
    }

    private function canonicalize(mixed $data): string
    {
        if (is_array($data)) {
            ksort($data);
            foreach ($data as $key => $val) {
                $data[$key] = is_array($val) ? json_decode($this->canonicalize($val), true) : $val;
            }
            return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        return (string) $data;
    }
}
