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

namespace NetFusion\Core\Media\Rtp;

/**
 * Adaptive Jitter Buffer & Playout Sequencer.
 * Handles sequence number reordering, deduplication, and missing packet NACK detection.
 */
class JitterBuffer
{
    /** @var array<int, RtpPacket> Indexed by sequence number */
    protected array $packets = [];
    protected ?int $lastPoppedSeq = null;
    protected int $maxBufferSize;

    public function __construct(int $maxBufferSize = 64, ?int $capacity = null)
    {
        $this->maxBufferSize = $capacity ?? $maxBufferSize;
    }

    /**
     * Push incoming RTP packet into buffer.
     *
     * @return bool True if accepted, false if duplicate or obsolete
     */
    public function push(RtpPacket $packet): bool
    {
        $seq = $packet->getSequenceNumber();

        // Check if duplicate
        if (isset($this->packets[$seq])) {
            return false;
        }

        // Check if already popped / obsolete (with 16-bit wrap-around awareness)
        if ($this->lastPoppedSeq !== null) {
            $diff = ($seq - $this->lastPoppedSeq) & 0xFFFF;
            if ($diff > 32768 && $diff !== 0) {
                // Obsolete / late packet
                return false;
            }
        }

        $this->packets[$seq] = $packet;

        // If buffer exceeds max capacity, drop oldest packet to avoid memory bloat
        if (count($this->packets) > $this->maxBufferSize) {
            $minSeq = min(array_keys($this->packets));
            unset($this->packets[$minSeq]);
        }

        return true;
    }

    /**
     * Pop next contiguous in-order packet.
     */
    public function pop(): ?RtpPacket
    {
        if (empty($this->packets)) {
            return null;
        }

        // If starting fresh, pick the lowest sequence number
        if ($this->lastPoppedSeq === null) {
            $keys = array_keys($this->packets);
            sort($keys);
            $nextSeq = $keys[0];
            $packet = $this->packets[$nextSeq];
            unset($this->packets[$nextSeq]);
            $this->lastPoppedSeq = $nextSeq;
            return $packet;
        }

        $expectedSeq = ($this->lastPoppedSeq + 1) & 0xFFFF;
        if (isset($this->packets[$expectedSeq])) {
            $packet = $this->packets[$expectedSeq];
            unset($this->packets[$expectedSeq]);
            $this->lastPoppedSeq = $expectedSeq;
            return $packet;
        }

        return null;
    }

    /**
     * Detect missing packet sequence numbers within the current window.
     *
     * @return array<int> Sequence numbers of lost packets requiring NACK
     */
    public function getMissingSequences(): array
    {
        if (count($this->packets) < 2) {
            return [];
        }

        $seqs = array_keys($this->packets);
        sort($seqs);

        $missing = [];
        $start = $this->lastPoppedSeq !== null ? (($this->lastPoppedSeq + 1) & 0xFFFF) : $seqs[0];
        $end = end($seqs);

        $curr = $start;
        while ($curr !== $end) {
            if (!isset($this->packets[$curr])) {
                $missing[] = $curr;
            }
            $curr = ($curr + 1) & 0xFFFF;
        }

        return $missing;
    }

    /**
     * Alias for getMissingSequences().
     */
    public function detectMissingSequences(): array
    {
        return $this->getMissingSequences();
    }

    public function size(): int
    {
        return count($this->packets);
    }

    public function flush(): void
    {
        $this->packets = [];
        $this->lastPoppedSeq = null;
    }
}
