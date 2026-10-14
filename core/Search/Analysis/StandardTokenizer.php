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

class StandardTokenizer implements TokenizerInterface
{
    /**
     * @param bool $lowercase Convert tokens to lowercase
     * @param int $minTokenLength Discard tokens shorter than this length
     * @param int $maxTokenLength Discard tokens longer than this length
     */
    public function __construct(
        protected bool $lowercase = true,
        protected int $minTokenLength = 1,
        protected int $maxTokenLength = 64
    ) {
    }

    public function tokenize(string $text): array
    {
        $withPositions = $this->tokenizeWithPositions($text);
        return array_map(fn($item) => $item['token'], $withPositions);
    }

    public function tokenizeWithPositions(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $results = [];
        $position = 0;

        // Split on standard Unicode word boundaries / whitespace / punctuation
        if (preg_match_all('/[\p{L}\p{N}_]+/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $rawToken = $match[0];
                $offset = (int)$match[1];
                $len = mb_strlen($rawToken, 'UTF-8');

                if ($len < $this->minTokenLength || $len > $this->maxTokenLength) {
                    continue;
                }

                $token = $this->lowercase ? mb_strtolower($rawToken, 'UTF-8') : $rawToken;

                $results[] = [
                    'token' => $token,
                    'position' => $position++,
                    'start_offset' => $offset,
                    'end_offset' => $offset + strlen($rawToken),
                ];
            }
        }

        return $results;
    }
}
