<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Search\Indexing;

class PositionalPosting
{
    /**
     * @param string $documentId
     * @param string $field
     * @param int $frequency Term frequency (TF) within this document field
     * @param array<int> $positions Zero-indexed term token positions for phrase matching
     */
    public function __construct(
        public string $documentId,
        public string $field = 'body',
        public int $frequency = 1,
        public array $positions = []
    ) {
    }

    public function addPosition(int $position): static
    {
        $this->positions[] = $position;
        $this->frequency = count($this->positions);
        return $this;
    }
}
