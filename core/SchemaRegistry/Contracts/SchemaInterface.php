<?php

declare(strict_types=1);

namespace NetFusion\Core\SchemaRegistry\Contracts;

use NetFusion\Core\SchemaRegistry\Enums\SchemaType;

interface SchemaInterface
{
    public function getId(): int;
    public function getSubject(): string;
    public function getVersion(): int;
    public function getType(): SchemaType;
    public function getDefinition(): array;
    public function getDefinitionString(): string;
    public function getFingerprint(): string;
    public function toArray(): array;
}
