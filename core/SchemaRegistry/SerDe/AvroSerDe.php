<?php

declare(strict_types=1);

namespace NetFusion\Core\SchemaRegistry\SerDe;

use NetFusion\Core\SchemaRegistry\Exceptions\SchemaRegistryException;

class AvroSerDe
{
    /**
     * Encode an associative array or value into Apache Avro binary representation.
     */
    public function encode(mixed $datum, mixed $schema): string
    {
        if (is_string($schema)) {
            return $this->encodePrimitive($datum, $schema);
        }

        if (is_array($schema)) {
            // Union type: e.g. ['null', 'string']
            if ($this->isIndexedArray($schema)) {
                return $this->encodeUnion($datum, $schema);
            }

            $type = $schema['type'] ?? 'record';

            if ($type === 'record') {
                return $this->encodeRecord($datum, $schema['fields'] ?? []);
            }

            if ($type === 'array') {
                return $this->encodeArray($datum, $schema['items'] ?? 'string');
            }

            if ($type === 'enum') {
                return $this->encodeEnum($datum, $schema['symbols'] ?? []);
            }

            if (is_string($type)) {
                return $this->encodePrimitive($datum, $type);
            }
        }

        throw new SchemaRegistryException('Unsupported Avro schema structure');
    }

    /**
     * Decode Apache Avro binary bytes into PHP values.
     */
    public function decode(string $binary, mixed $schema, int &$offset = 0): mixed
    {
        if (is_string($schema)) {
            return $this->decodePrimitive($binary, $schema, $offset);
        }

        if (is_array($schema)) {
            if ($this->isIndexedArray($schema)) {
                return $this->decodeUnion($binary, $schema, $offset);
            }

            $type = $schema['type'] ?? 'record';

            if ($type === 'record') {
                return $this->decodeRecord($binary, $schema['fields'] ?? [], $offset);
            }

            if ($type === 'array') {
                return $this->decodeArray($binary, $schema['items'] ?? 'string', $offset);
            }

            if ($type === 'enum') {
                return $this->decodeEnum($binary, $schema['symbols'] ?? [], $offset);
            }

            if (is_string($type)) {
                return $this->decodePrimitive($binary, $type, $offset);
            }
        }

        throw new SchemaRegistryException('Unsupported Avro schema structure');
    }

    private function encodePrimitive(mixed $datum, string $type): string
    {
        return match ($type) {
            'null' => '',
            'boolean' => chr($datum ? 1 : 0),
            'int', 'long' => $this->encodeZigzagVarint((int) $datum),
            'float' => pack('g', (float) $datum),
            'double' => pack('e', (float) $datum),
            'bytes', 'string' => $this->encodeString((string) $datum),
            default => throw new SchemaRegistryException("Unsupported Avro primitive type: {$type}"),
        };
    }

    private function decodePrimitive(string $binary, string $type, int &$offset): mixed
    {
        return match ($type) {
            'null' => null,
            'boolean' => ord($binary[$offset++]) !== 0,
            'int', 'long' => $this->decodeZigzagVarint($binary, $offset),
            'float' => $this->readFloat($binary, $offset),
            'double' => $this->readDouble($binary, $offset),
            'bytes', 'string' => $this->decodeString($binary, $offset),
            default => throw new SchemaRegistryException("Unsupported Avro primitive type: {$type}"),
        };
    }

    private function encodeRecord(array $datum, array $fields): string
    {
        $out = '';
        foreach ($fields as $field) {
            $name = $field['name'] ?? '';
            $fieldType = $field['type'] ?? 'string';
            $val = $datum[$name] ?? ($field['default'] ?? null);

            $out .= $this->encode($val, $fieldType);
        }
        return $out;
    }

    private function decodeRecord(string $binary, array $fields, int &$offset): array
    {
        $record = [];
        foreach ($fields as $field) {
            $name = $field['name'] ?? '';
            $fieldType = $field['type'] ?? 'string';
            $record[$name] = $this->decode($binary, $fieldType, $offset);
        }
        return $record;
    }

    private function encodeArray(array $datum, mixed $itemsSchema): string
    {
        $count = count($datum);
        if ($count === 0) {
            return $this->encodeZigzagVarint(0);
        }

        $out = $this->encodeZigzagVarint($count);
        foreach ($datum as $item) {
            $out .= $this->encode($item, $itemsSchema);
        }
        $out .= $this->encodeZigzagVarint(0); // Terminal 0 block

        return $out;
    }

    private function decodeArray(string $binary, mixed $itemsSchema, int &$offset): array
    {
        $items = [];
        while ($offset < strlen($binary)) {
            $blockCount = $this->decodeZigzagVarint($binary, $offset);
            if ($blockCount === 0) {
                break;
            }
            if ($blockCount < 0) {
                $blockCount = abs($blockCount);
                // In Avro spec, negative count means block byte length follows
                $this->decodeZigzagVarint($binary, $offset);
            }
            for ($i = 0; $i < $blockCount; $i++) {
                $items[] = $this->decode($binary, $itemsSchema, $offset);
            }
        }
        return $items;
    }

    private function encodeEnum(string $datum, array $symbols): string
    {
        $index = array_search($datum, $symbols, true);
        if ($index === false) {
            throw new SchemaRegistryException("Enum symbol '{$datum}' not found in symbols list");
        }
        return $this->encodeZigzagVarint((int) $index);
    }

    private function decodeEnum(string $binary, array $symbols, int &$offset): string
    {
        $index = $this->decodeZigzagVarint($binary, $offset);
        return $symbols[$index] ?? (string) $index;
    }

    private function encodeUnion(mixed $datum, array $unionBranches): string
    {
        $branchIndex = null;
        foreach ($unionBranches as $idx => $branchSchema) {
            if ($datum === null && $branchSchema === 'null') {
                $branchIndex = $idx;
                break;
            }
            if (is_string($datum) && ($branchSchema === 'string' || $branchSchema === 'bytes')) {
                $branchIndex = $idx;
                break;
            }
            if (is_int($datum) && ($branchSchema === 'int' || $branchSchema === 'long')) {
                $branchIndex = $idx;
                break;
            }
            if (is_float($datum) && ($branchSchema === 'float' || $branchSchema === 'double')) {
                $branchIndex = $idx;
                break;
            }
            if (is_bool($datum) && $branchSchema === 'boolean') {
                $branchIndex = $idx;
                break;
            }
            if (is_array($datum) && is_array($branchSchema)) {
                $branchIndex = $idx;
                break;
            }
        }

        if ($branchIndex === null) {
            $branchIndex = 0;
        }

        return $this->encodeZigzagVarint($branchIndex) . $this->encode($datum, $unionBranches[$branchIndex]);
    }

    private function decodeUnion(string $binary, array $unionBranches, int &$offset): mixed
    {
        $branchIndex = $this->decodeZigzagVarint($binary, $offset);
        $branchSchema = $unionBranches[$branchIndex] ?? 'string';
        return $this->decode($binary, $branchSchema, $offset);
    }

    private function encodeZigzagVarint(int $n): string
    {
        $zigzag = Varint::encodeZigZag64($n);
        return Varint::encode($zigzag);
    }

    private function decodeZigzagVarint(string $binary, int &$offset): int
    {
        $raw = Varint::decode($binary, $offset);
        return Varint::decodeZigZag64($raw);
    }

    private function encodeString(string $str): string
    {
        return $this->encodeZigzagVarint(strlen($str)) . $str;
    }

    private function decodeString(string $binary, int &$offset): string
    {
        $len = $this->decodeZigzagVarint($binary, $offset);
        $str = substr($binary, $offset, $len);
        $offset += $len;
        return $str;
    }

    private function readFloat(string $binary, int &$offset): float
    {
        $slice = substr($binary, $offset, 4);
        $offset += 4;
        $unpacked = unpack('gval', $slice);
        return $unpacked['val'] ?? 0.0;
    }

    private function readDouble(string $binary, int &$offset): float
    {
        $slice = substr($binary, $offset, 8);
        $offset += 8;
        $unpacked = unpack('eval', $slice);
        return $unpacked['val'] ?? 0.0;
    }

    private function isIndexedArray(array $arr): bool
    {
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
