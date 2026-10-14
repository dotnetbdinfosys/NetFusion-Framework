<?php

declare(strict_types=1);

namespace NetFusion\Core\SchemaRegistry\SerDe;

use NetFusion\Core\SchemaRegistry\Exceptions\SchemaRegistryException;

class ProtobufSerDe
{
    public const WIRE_VARINT = 0;
    public const WIRE_FIXED64 = 1;
    public const WIRE_LENGTH_DELIMITED = 2;
    public const WIRE_FIXED32 = 5;

    /**
     * Encode an associative array into Protobuf v3 binary format.
     */
    public function encode(array $data, array $schema): string
    {
        $fields = $schema['fields'] ?? [];
        $binary = '';

        foreach ($fields as $fieldNumber => $fieldDef) {
            $name = $fieldDef['name'] ?? "field_{$fieldNumber}";
            if (!array_key_exists($name, $data)) {
                continue;
            }

            $val = $data[$name];
            $type = $fieldDef['type'] ?? 'string';
            $isRepeated = $fieldDef['repeated'] ?? false;

            if ($isRepeated && is_array($val)) {
                foreach ($val as $item) {
                    $binary .= $this->encodeField((int) $fieldNumber, $type, $item);
                }
            } else {
                $binary .= $this->encodeField((int) $fieldNumber, $type, $val);
            }
        }

        return $binary;
    }

    /**
     * Decode Protobuf v3 binary format into an associative array.
     */
    public function decode(string $binary, array $schema): array
    {
        $fields = $schema['fields'] ?? [];
        $fieldMap = [];
        foreach ($fields as $num => $def) {
            $fieldMap[(int) $num] = $def;
        }

        $result = [];
        $offset = 0;
        $len = strlen($binary);

        while ($offset < $len) {
            $tag = Varint::decode($binary, $offset);
            $wireType = $tag & 0x07;
            $fieldNumber = $tag >> 3;

            $fieldDef = $fieldMap[$fieldNumber] ?? null;
            $fieldName = $fieldDef['name'] ?? "field_{$fieldNumber}";
            $fieldType = $fieldDef['type'] ?? $this->inferTypeFromWire($wireType);
            $isRepeated = $fieldDef['repeated'] ?? false;

            $value = $this->decodeField($binary, $offset, $wireType, $fieldType);

            if ($isRepeated) {
                if (!isset($result[$fieldName])) {
                    $result[$fieldName] = [];
                }
                $result[$fieldName][] = $value;
            } else {
                $result[$fieldName] = $value;
            }
        }

        return $result;
    }

    private function encodeField(int $fieldNumber, string $type, mixed $value): string
    {
        switch ($type) {
            case 'int32':
            case 'int64':
            case 'uint32':
            case 'uint64':
                $tag = ($fieldNumber << 3) | self::WIRE_VARINT;
                return Varint::encode($tag) . Varint::encode((int) $value);

            case 'sint32':
                $tag = ($fieldNumber << 3) | self::WIRE_VARINT;
                return Varint::encode($tag) . Varint::encode(Varint::encodeZigZag32((int) $value));

            case 'sint64':
                $tag = ($fieldNumber << 3) | self::WIRE_VARINT;
                return Varint::encode($tag) . Varint::encode(Varint::encodeZigZag64((int) $value));

            case 'bool':
                $tag = ($fieldNumber << 3) | self::WIRE_VARINT;
                return Varint::encode($tag) . chr($value ? 1 : 0);

            case 'float':
                $tag = ($fieldNumber << 3) | self::WIRE_FIXED32;
                return Varint::encode($tag) . pack('f', (float) $value);

            case 'double':
                $tag = ($fieldNumber << 3) | self::WIRE_FIXED64;
                return Varint::encode($tag) . pack('d', (float) $value);

            case 'string':
            case 'bytes':
            default:
                $tag = ($fieldNumber << 3) | self::WIRE_LENGTH_DELIMITED;
                $str = (string) $value;
                return Varint::encode($tag) . Varint::encode(strlen($str)) . $str;
        }
    }

    private function decodeField(string $binary, int &$offset, int $wireType, string $type): mixed
    {
        switch ($wireType) {
            case self::WIRE_VARINT:
                $raw = Varint::decode($binary, $offset);
                if ($type === 'bool') {
                    return $raw !== 0;
                }
                if ($type === 'sint32') {
                    return Varint::decodeZigZag32($raw);
                }
                if ($type === 'sint64') {
                    return Varint::decodeZigZag64($raw);
                }
                return $raw;

            case self::WIRE_FIXED32:
                $slice = substr($binary, $offset, 4);
                $offset += 4;
                if ($type === 'float') {
                    $unpacked = unpack('fval', $slice);
                    return $unpacked['val'] ?? 0.0;
                }
                $unpacked = unpack('Vval', $slice);
                return $unpacked['val'] ?? 0;

            case self::WIRE_FIXED64:
                $slice = substr($binary, $offset, 8);
                $offset += 8;
                if ($type === 'double') {
                    $unpacked = unpack('dval', $slice);
                    return $unpacked['val'] ?? 0.0;
                }
                $unpacked = unpack('Pval', $slice);
                return $unpacked['val'] ?? 0;

            case self::WIRE_LENGTH_DELIMITED:
                $length = Varint::decode($binary, $offset);
                $str = substr($binary, $offset, $length);
                $offset += $length;
                return $str;

            default:
                throw new SchemaRegistryException("Unsupported wire type: {$wireType}");
        }
    }

    private function inferTypeFromWire(int $wireType): string
    {
        return match ($wireType) {
            self::WIRE_VARINT => 'int64',
            self::WIRE_FIXED32 => 'float',
            self::WIRE_FIXED64 => 'double',
            self::WIRE_LENGTH_DELIMITED => 'string',
            default => 'unknown',
        };
    }
}
