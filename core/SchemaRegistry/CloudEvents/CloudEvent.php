<?php

declare(strict_types=1);

namespace NetFusion\Core\SchemaRegistry\CloudEvents;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use NetFusion\Core\SchemaRegistry\Exceptions\SchemaRegistryException;

class CloudEvent
{
    public const SPEC_VERSION = '1.0';

    /**
     * @param string $id
     * @param string $source
     * @param string $type
     * @param mixed $data
     * @param string|null $dataContentType
     * @param string|null $dataSchema
     * @param string|null $subject
     * @param string|null $time
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        private readonly string $id,
        private readonly string $source,
        private readonly string $type,
        private readonly mixed $data = null,
        private readonly ?string $dataContentType = 'application/json',
        private readonly ?string $dataSchema = null,
        private readonly ?string $subject = null,
        private readonly ?string $time = null,
        private readonly array $extensions = []
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('CloudEvent "id" must be a non-empty string');
        }
        if ($source === '') {
            throw new InvalidArgumentException('CloudEvent "source" must be a non-empty string');
        }
        if ($type === '') {
            throw new InvalidArgumentException('CloudEvent "type" must be a non-empty string');
        }
    }

    public static function create(
        string $type,
        string $source,
        mixed $data = null,
        ?string $subject = null,
        ?string $dataContentType = 'application/json',
        ?string $dataSchema = null,
        array $extensions = []
    ): self {
        $id = bin2hex(random_bytes(16));
        $time = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');

        return new self(
            id: $id,
            source: $source,
            type: $type,
            data: $data,
            dataContentType: $dataContentType,
            dataSchema: $dataSchema,
            subject: $subject,
            time: $time,
            extensions: $extensions
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getSpecVersion(): string
    {
        return self::SPEC_VERSION;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getDataContentType(): ?string
    {
        return $this->dataContentType;
    }

    public function getDataSchema(): ?string
    {
        return $this->dataSchema;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getTime(): ?string
    {
        return $this->time;
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function getExtension(string $key): mixed
    {
        return $this->extensions[$key] ?? null;
    }

    public function toArray(): array
    {
        $arr = [
            'specversion' => self::SPEC_VERSION,
            'id' => $this->id,
            'source' => $this->source,
            'type' => $this->type,
        ];

        if ($this->dataContentType !== null) {
            $arr['datacontenttype'] = $this->dataContentType;
        }
        if ($this->dataSchema !== null) {
            $arr['dataschema'] = $this->dataSchema;
        }
        if ($this->subject !== null) {
            $arr['subject'] = $this->subject;
        }
        if ($this->time !== null) {
            $arr['time'] = $this->time;
        }
        if ($this->data !== null) {
            $arr['data'] = $this->data;
        }

        foreach ($this->extensions as $key => $val) {
            $arr[$key] = $val;
        }

        return $arr;
    }

    public function toStructuredJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
    }

    public static function fromStructuredJson(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new SchemaRegistryException('Invalid JSON payload for CloudEvent');
        }

        return self::fromArray($data);
    }

    public static function fromArray(array $arr): self
    {
        $id = (string) ($arr['id'] ?? '');
        $source = (string) ($arr['source'] ?? '');
        $type = (string) ($arr['type'] ?? '');
        $payload = $arr['data'] ?? null;
        $contentType = isset($arr['datacontenttype']) ? (string) $arr['datacontenttype'] : null;
        $schema = isset($arr['dataschema']) ? (string) $arr['dataschema'] : null;
        $subject = isset($arr['subject']) ? (string) $arr['subject'] : null;
        $time = isset($arr['time']) ? (string) $arr['time'] : null;

        $standardKeys = ['id', 'source', 'type', 'specversion', 'data', 'datacontenttype', 'dataschema', 'subject', 'time'];
        $extensions = [];
        foreach ($arr as $key => $val) {
            if (!in_array($key, $standardKeys, true)) {
                $extensions[$key] = $val;
            }
        }

        return new self(
            id: $id,
            source: $source,
            type: $type,
            data: $payload,
            dataContentType: $contentType,
            dataSchema: $schema,
            subject: $subject,
            time: $time,
            extensions: $extensions
        );
    }

    /**
     * Map CloudEvent attributes to HTTP binary mode headers (ce- prefix).
     *
     * @return array<string, string>
     */
    public function toBinaryHeaders(): array
    {
        $headers = [
            'ce-specversion' => self::SPEC_VERSION,
            'ce-id' => $this->id,
            'ce-source' => $this->source,
            'ce-type' => $this->type,
        ];

        if ($this->dataContentType !== null) {
            $headers['content-type'] = $this->dataContentType;
        }
        if ($this->dataSchema !== null) {
            $headers['ce-dataschema'] = $this->dataSchema;
        }
        if ($this->subject !== null) {
            $headers['ce-subject'] = $this->subject;
        }
        if ($this->time !== null) {
            $headers['ce-time'] = $this->time;
        }

        foreach ($this->extensions as $key => $val) {
            $headers['ce-' . strtolower($key)] = (string) $val;
        }

        return $headers;
    }

    /**
     * Reconstruct CloudEvent from HTTP binary mode headers and request body.
     */
    public static function fromBinary(array $headers, mixed $data): self
    {
        $normalized = [];
        foreach ($headers as $k => $v) {
            $normalized[strtolower((string) $k)] = is_array($v) ? ($v[0] ?? '') : (string) $v;
        }

        $id = $normalized['ce-id'] ?? '';
        $source = $normalized['ce-source'] ?? '';
        $type = $normalized['ce-type'] ?? '';
        $schema = $normalized['ce-dataschema'] ?? null;
        $subject = $normalized['ce-subject'] ?? null;
        $time = $normalized['ce-time'] ?? null;
        $contentType = $normalized['content-type'] ?? ($normalized['ce-datacontenttype'] ?? 'application/json');

        $extensions = [];
        foreach ($normalized as $k => $v) {
            if (str_starts_with($k, 'ce-')) {
                $attr = substr($k, 3);
                if (!in_array($attr, ['specversion', 'id', 'source', 'type', 'dataschema', 'subject', 'time', 'datacontenttype'], true)) {
                    $extensions[$attr] = $v;
                }
            }
        }

        return new self(
            id: $id,
            source: $source,
            type: $type,
            data: $data,
            dataContentType: $contentType,
            dataSchema: $schema,
            subject: $subject,
            time: $time,
            extensions: $extensions
        );
    }
}
