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

namespace NetFusion\Core\Search\Models;

class Suggestion
{
    /**
     * @param string $text Suggested keyword or phrase
     * @param float $score Confidence or frequency score
     * @param array<string, mixed> $payload Optional associated document or category data
     * @param string|null $highlighted Highlighted HTML representation
     */
    public function __construct(
        public string $text,
        public float $score = 1.0,
        public array $payload = [],
        public ?string $highlighted = null
    ) {
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getHighlighted(): ?string
    {
        return $this->highlighted;
    }
}
