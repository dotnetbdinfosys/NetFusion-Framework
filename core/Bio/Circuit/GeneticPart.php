<?php
/**
 * NetFusion Framework - Enterprise Synthetic Biology & BioCAD Fabric
 *
 * Modular BioBrick standard genetic component representation.
 *
 * @package    NetFusion\Core\Bio\Circuit
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Bio\Circuit;

use NetFusion\Core\Bio\Enums\GeneticPartType;
use NetFusion\Core\Bio\Sequence\DnaSequence;

class GeneticPart
{
    public function __construct(
        protected readonly string $partId,
        protected readonly GeneticPartType $type,
        protected readonly DnaSequence $sequence,
        protected readonly array $metadata = []
    ) {
    }

    public function getPartId(): string
    {
        return $this->partId;
    }

    public function getType(): GeneticPartType
    {
        return $this->type;
    }

    public function getSequence(): DnaSequence
    {
        return $this->sequence;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'part_id'  => $this->partId,
            'type'     => $this->type->value,
            'sequence' => $this->sequence->getSequence(),
            'length'   => $this->sequence->length(),
            'gc'       => $this->sequence->gcContent(),
            'metadata' => $this->metadata,
        ];
    }
}
