<?php

declare(strict_types=1);

namespace NetFusion\Core\SchemaRegistry\SerDe;

use NetFusion\Core\SchemaRegistry\Exceptions\SchemaRegistryException;

class WireFormat
{
    public const MAGIC_BYTE = "\x00";
    public const HEADER_LENGTH = 5; // 1 magic byte + 4 schema ID bytes

    /**
     * Prepends Confluent Wire Format header: Magic Byte (0x00) + 4-byte Schema ID (Big Endian)
     */
    public static function encode(int $schemaId, string $payload): string
    {
        return self::MAGIC_BYTE . pack('N', $schemaId) . $payload;
    }

    /**
     * Decodes Confluent Wire Format header from binary payload.
     *
     * @return array{schema_id: int, payload: string}
     */
    public static function decode(string $binary): array
    {
        if (strlen($binary) < self::HEADER_LENGTH) {
            throw new SchemaRegistryException(
                sprintf('Binary payload too short (%d bytes) for Confluent wire format header', strlen($binary))
            );
        }

        if ($binary[0] !== self::MAGIC_BYTE) {
            throw new SchemaRegistryException(
                sprintf('Invalid magic byte: expected 0x00, got 0x%02X', ord($binary[0]))
            );
        }

        $unpacked = unpack('Nid', substr($binary, 1, 4));
        $schemaId = $unpacked['id'] ?? 0;
        $payload = substr($binary, self::HEADER_LENGTH);

        return [
            'schema_id' => $schemaId,
            'payload' => $payload,
        ];
    }

    public static function hasWireHeader(string $binary): bool
    {
        return strlen($binary) >= self::HEADER_LENGTH && $binary[0] === self::MAGIC_BYTE;
    }
}
