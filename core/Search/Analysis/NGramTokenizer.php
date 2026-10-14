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

namespace NetFusion\Core\Search\Analysis;

use NetFusion\Core\Search\Contracts\TokenizerInterface;

class NGramTokenizer implements TokenizerInterface
{
    /**
     * @param int $minGram Minimum n-gram size (e.g. 2 for bigram)
     * @param int $maxGram Maximum n-gram size (e.g. 3 for trigram)
     * @param bool $lowercase Lowercase incoming strings
     */
    public function __construct(
        protected int $minGram = 2,
        protected int $maxGram = 3,
        protected bool $lowercase = true
    ) {
    }

    public function tokenize(string $text): array
    {
        $withPositions = $this->tokenizeWithPositions($text);
        return array_map(fn($item) => $item['token'], $withPositions);
    }

    public function tokenizeWithPositions(string $text): array
    {
        $clean = $this->lowercase ? mb_strtolower(trim($text), 'UTF-8') : trim($text);
        $clean = preg_replace('/\s+/', ' ', $clean);

        if ($clean === null || $clean === '') {
            return [];
        }

        $length = mb_strlen($clean, 'UTF-8');
        $results = [];
        $pos = 0;

        for ($i = 0; $i < $length; $i++) {
            for ($n = $this->minGram; $n <= $this->maxGram; $n++) {
                if ($i + $n <= $length) {
                    $gram = mb_substr($clean, $i, $n, 'UTF-8');
                    $results[] = [
                        'token' => $gram,
                        'position' => $pos++,
                        'start_offset' => $i,
                        'end_offset' => $i + $n,
                    ];
                }
            }
        }

        return $results;
    }
}
