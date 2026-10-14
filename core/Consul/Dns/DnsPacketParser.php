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

namespace NetFusion\Core\Consul\Dns;

use NetFusion\Core\Consul\Enums\DnsRecordType;
use NetFusion\Core\Consul\Models\DnsMessage;

/**
 * RFC 1035 Binary Wire-Format DNS Packet Parser and Serializer.
 * Handles header bits/flags, domain label encoding/decoding, and QTYPE/RDATA packing.
 */
class DnsPacketParser
{
    /**
     * Parse binary DNS query payload into DnsMessage.
     */
    public function parse(string $packet): DnsMessage
    {
        if (strlen($packet) < 12) {
            throw new \InvalidArgumentException('DNS packet is too short (less than 12 bytes header).');
        }

        // Header: 12 bytes
        // ID: 2 bytes, Flags: 2 bytes, QDCOUNT: 2, ANCOUNT: 2, NSCOUNT: 2, ARCOUNT: 2
        $header = unpack('nid/nflags/nqdcount/nancount/nnscount/narcount', substr($packet, 0, 12));
        $id = $header['id'];
        $flags = $header['flags'];
        $qdcount = $header['qdcount'];

        $msg = new DnsMessage($id);
        $msg->setIsResponse((bool)(($flags >> 15) & 0x01));
        $msg->setOpcode(($flags >> 11) & 0x0F);
        $msg->setAuthoritative((bool)(($flags >> 10) & 0x01));
        $msg->setTruncated((bool)(($flags >> 9) & 0x01));
        $msg->setRecursionDesired((bool)(($flags >> 8) & 0x01));
        $msg->setRecursionAvailable((bool)(($flags >> 7) & 0x01));
        $msg->setResponseCode($flags & 0x0F);

        $offset = 12;

        // Parse Questions
        for ($i = 0; $i < $qdcount; $i++) {
            $domain = $this->decodeDomainName($packet, $offset);
            if ($offset + 4 > strlen($packet)) {
                break;
            }
            $qmeta = unpack('ntype/nclass', substr($packet, $offset, 4));
            $offset += 4;

            $recordType = DnsRecordType::tryFrom($qmeta['type']) ?? DnsRecordType::A;
            $msg->addQuestion($domain, $recordType, $qmeta['class']);
        }

        // Parse Answers
        $ancount = $header['ancount'];
        for ($i = 0; $i < $ancount; $i++) {
            if ($offset >= strlen($packet)) {
                break;
            }
            $domain = $this->decodeDomainName($packet, $offset);
            if ($offset + 10 > strlen($packet)) {
                break;
            }
            $rmeta = unpack('ntype/nclass/Nttl/nrdlength', substr($packet, $offset, 10));
            $offset += 10;

            $recordType = DnsRecordType::tryFrom($rmeta['type']) ?? DnsRecordType::A;
            $rdlength = $rmeta['rdlength'];
            $rdataRaw = substr($packet, $offset, $rdlength);
            $offset += $rdlength;

            $data = null;
            switch ($recordType) {
                case DnsRecordType::A:
                    if ($rdlength >= 4) {
                        $octets = unpack('C4', $rdataRaw);
                        $data = implode('.', $octets);
                    }
                    break;
                case DnsRecordType::TXT:
                    $txtLen = ord($rdataRaw[0] ?? "\0");
                    $data = substr($rdataRaw, 1, $txtLen);
                    break;
                case DnsRecordType::SRV:
                    if ($rdlength >= 6) {
                        $srvMeta = unpack('npriority/nweight/nport', substr($rdataRaw, 0, 6));
                        $srvOffset = 6;
                        $target = $this->decodeDomainName($rdataRaw, $srvOffset);
                        $data = [
                            'priority' => $srvMeta['priority'],
                            'weight' => $srvMeta['weight'],
                            'port' => $srvMeta['port'],
                            'target' => $target,
                        ];
                    }
                    break;
                default:
                    $data = $rdataRaw;
                    break;
            }

            if ($data !== null) {
                $msg->addAnswer($domain, $recordType, $rmeta['ttl'], $data, $rmeta['class']);
            }
        }

        return $msg;
    }

    /**
     * Serialize DnsMessage into RFC 1035 binary wire-format packet.
     */
    public function serialize(DnsMessage $msg): string
    {
        $flags = 0;
        if ($msg->isResponse()) {
            $flags |= (1 << 15);
        }
        $flags |= (($msg->getOpcode() & 0x0F) << 11);
        if ($msg->isAuthoritative()) {
            $flags |= (1 << 10);
        }
        if ($msg->isTruncated()) {
            $flags |= (1 << 9);
        }
        if ($msg->isRecursionDesired()) {
            $flags |= (1 << 8);
        }
        if ($msg->isRecursionAvailable()) {
            $flags |= (1 << 7);
        }
        $flags |= ($msg->getResponseCode() & 0x0F);

        $questions = $msg->getQuestions();
        $answers = $msg->getAnswers();

        // Build 12-byte header
        $header = pack('nnnnnn',
            $msg->getId(),
            $flags,
            count($questions),
            count($answers),
            0, // NSCOUNT
            0  // ARCOUNT
        );

        $body = '';

        // Questions section
        foreach ($questions as $q) {
            $body .= $this->encodeDomainName($q['name']);
            $body .= pack('nn', $q['type']->value, $q['class']);
        }

        // Answers section
        foreach ($answers as $ans) {
            $body .= $this->encodeDomainName($ans['name']);
            $type = $ans['type'];
            $class = $ans['class'];
            $ttl = $ans['ttl'];
            $data = $ans['data'];

            $rdata = '';
            switch ($type) {
                case DnsRecordType::A:
                    $ip = is_string($data) ? $data : '127.0.0.1';
                    $parts = array_map('intval', explode('.', $ip));
                    $rdata = pack('CCCC', $parts[0] ?? 127, $parts[1] ?? 0, $parts[2] ?? 0, $parts[3] ?? 1);
                    break;

                case DnsRecordType::TXT:
                    $txt = is_string($data) ? $data : (string)json_encode($data);
                    $rdata = chr(strlen($txt)) . $txt;
                    break;

                case DnsRecordType::CNAME:
                    $rdata = $this->encodeDomainName((string)$data);
                    break;

                case DnsRecordType::SRV:
                    // SRV: priority(2), weight(2), port(2), target(domain)
                    $priority = (int)($data['priority'] ?? 10);
                    $weight = (int)($data['weight'] ?? 1);
                    $port = (int)($data['port'] ?? 80);
                    $target = (string)($data['target'] ?? 'node.consul');
                    $rdata = pack('nnn', $priority, $weight, $port) . $this->encodeDomainName($target);
                    break;

                default:
                    $rdata = is_string($data) ? $data : '';
                    break;
            }

            $body .= pack('nnNn', $type->value, $class, $ttl, strlen($rdata));
            $body .= $rdata;
        }

        return $header . $body;
    }

    /**
     * Decode domain name starting at offset, moving offset to end of domain.
     */
    public function decodeDomainName(string $packet, int &$offset): string
    {
        $labels = [];
        $jumped = false;
        $originalOffset = $offset;
        $maxJumps = 10;
        $jumps = 0;

        while ($offset < strlen($packet)) {
            $len = ord($packet[$offset]);

            // Pointer (compression): top 2 bits set (0xC0)
            if (($len & 0xC0) === 0xC0) {
                if ($offset + 1 >= strlen($packet)) {
                    break;
                }
                $pointer = unpack('n', substr($packet, $offset, 2))[1] & 0x3FFF;
                if (!$jumped) {
                    $originalOffset = $offset + 2;
                    $jumped = true;
                }
                $offset = $pointer;
                $jumps++;
                if ($jumps > $maxJumps) {
                    break; // prevent infinite loops
                }
                continue;
            }

            $offset++;
            if ($len === 0) {
                break;
            }

            if ($offset + $len > strlen($packet)) {
                break;
            }

            $labels[] = substr($packet, $offset, $len);
            $offset += $len;
        }

        if ($jumped) {
            $offset = $originalOffset;
        }

        return implode('.', $labels);
    }

    /**
     * Encode domain name into RFC 1035 label wire-format.
     */
    public function encodeDomainName(string $domain): string
    {
        $domain = trim($domain, '.');
        if ($domain === '') {
            return "\0";
        }

        $parts = explode('.', $domain);
        $encoded = '';
        foreach ($parts as $part) {
            $encoded .= chr(strlen($part)) . $part;
        }
        $encoded .= "\0";

        return $encoded;
    }
}
