<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Consul\Models;

use NetFusion\Core\Consul\Enums\DnsRecordType;

/**
 * DNS Question and Answer representations complying with RFC 1035.
 */
class DnsMessage
{
    protected int $id;
    protected bool $isResponse = false;
    protected int $opcode = 0;
    protected bool $authoritative = true;
    protected bool $truncated = false;
    protected bool $recursionDesired = false;
    protected bool $recursionAvailable = false;
    protected int $responseCode = 0; // 0 = NOERROR, 3 = NXDOMAIN

    /** @var array<array{name: string, type: DnsRecordType, class: int}> */
    protected array $questions = [];

    /** @var array<array{name: string, type: DnsRecordType, class: int, ttl: int, data: mixed}> */
    protected array $answers = [];

    public function __construct(int $id = 0)
    {
        $this->id = $id > 0 ? $id : random_int(1, 65535);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function isResponse(): bool
    {
        return $this->isResponse;
    }

    public function setIsResponse(bool $isResponse): self
    {
        $this->isResponse = $isResponse;
        return $this;
    }

    public function getOpcode(): int
    {
        return $this->opcode;
    }

    public function setOpcode(int $opcode): self
    {
        $this->opcode = $opcode;
        return $this;
    }

    public function isAuthoritative(): bool
    {
        return $this->authoritative;
    }

    public function setAuthoritative(bool $authoritative): self
    {
        $this->authoritative = $authoritative;
        return $this;
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    public function setTruncated(bool $truncated): self
    {
        $this->truncated = $truncated;
        return $this;
    }

    public function isRecursionDesired(): bool
    {
        return $this->recursionDesired;
    }

    public function setRecursionDesired(bool $recursionDesired): self
    {
        $this->recursionDesired = $recursionDesired;
        return $this;
    }

    public function isRecursionAvailable(): bool
    {
        return $this->recursionAvailable;
    }

    public function setRecursionAvailable(bool $recursionAvailable): self
    {
        $this->recursionAvailable = $recursionAvailable;
        return $this;
    }

    public function getResponseCode(): int
    {
        return $this->responseCode;
    }

    public function setResponseCode(int $responseCode): self
    {
        $this->responseCode = $responseCode;
        return $this;
    }

    /**
     * Add a question entry.
     */
    public function addQuestion(string $name, DnsRecordType $type, int $class = 1): self
    {
        $this->questions[] = [
            'name' => rtrim(strtolower($name), '.'),
            'type' => $type,
            'class' => $class,
        ];
        return $this;
    }

    /**
     * @return array<array{name: string, type: DnsRecordType, class: int}>
     */
    public function getQuestions(): array
    {
        return $this->questions;
    }

    /**
     * Add an answer record.
     *
     * @param mixed $data e.g. IP string for A record, array for SRV ['priority' => 10, 'weight' => 1, 'port' => 8080, 'target' => 'node1.node.consul']
     */
    public function addAnswer(string $name, DnsRecordType $type, int $ttl, mixed $data, int $class = 1): self
    {
        $this->answers[] = [
            'name' => rtrim(strtolower($name), '.'),
            'type' => $type,
            'class' => $class,
            'ttl' => $ttl,
            'data' => $data,
        ];
        return $this;
    }

    /**
     * @return array<array{name: string, type: DnsRecordType, class: int, ttl: int, data: mixed}>
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'isResponse' => $this->isResponse,
            'opcode' => $this->opcode,
            'authoritative' => $this->authoritative,
            'responseCode' => $this->responseCode,
            'questions' => array_map(function ($q) {
                return [
                    'name' => $q['name'],
                    'type' => $q['type']->name,
                    'class' => $q['class'],
                ];
            }, $this->questions),
            'answers' => array_map(function ($a) {
                return [
                    'name' => $a['name'],
                    'type' => $a['type']->name,
                    'class' => $a['class'],
                    'ttl' => $a['ttl'],
                    'data' => $a['data'],
                ];
            }, $this->answers),
        ];
    }
}
