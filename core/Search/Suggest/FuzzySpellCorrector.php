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

namespace NetFusion\Core\Search\Suggest;

class FuzzySpellCorrector
{
    /**
     * @var array<string, int> Word => Frequency
     */
    protected array $dictionary = [];

    /**
     * @param array<string, int> $initialDictionary
     */
    public function __construct(array $initialDictionary = [])
    {
        $this->dictionary = $initialDictionary;
    }

    public function addWord(string $word, int $count = 1): static
    {
        $w = strtolower(trim($word));
        if ($w !== '') {
            $this->dictionary[$w] = ($this->dictionary[$w] ?? 0) + $count;
        }
        return $this;
    }

    public function train(array $words): static
    {
        foreach ($words as $word) {
            $this->addWord((string)$word, 1);
        }
        return $this;
    }

    public function correct(string $query, int $maxDistance = 2): string
    {
        $res = $this->correctQuery($query, $maxDistance);
        return $res['corrected_query'];
    }

    public function trainFromText(string $corpus): static
    {
        if (preg_match_all('/[a-z0-9]+/i', strtolower($corpus), $matches)) {
            foreach ($matches[0] as $word) {
                $this->addWord($word, 1);
            }
        }
        return $this;
    }

    /**
     * Correct a potentially misspelled query into the most likely intended terms.
     *
     * @param string $query
     * @param int $maxDistance
     * @return array{corrected_query: string, has_correction: bool, suggestions: array<string>}
     */
    public function correctQuery(string $query, int $maxDistance = 2): array
    {
        $words = preg_split('/\s+/', trim($query));
        if (empty($words) || $words === ['']) {
            return ['corrected_query' => $query, 'has_correction' => false, 'suggestions' => []];
        }

        $correctedWords = [];
        $hasCorrection = false;

        foreach ($words as $word) {
            $lower = strtolower($word);
            if (isset($this->dictionary[$lower])) {
                $correctedWords[] = $word;
                continue;
            }

            // Find closest matches
            $candidates = [];
            foreach ($this->dictionary as $dictWord => $freq) {
                $dist = levenshtein($lower, $dictWord);
                if ($dist <= $maxDistance) {
                    $candidates[$dictWord] = [
                        'distance' => $dist,
                        'freq' => $freq,
                    ];
                }
            }

            if (!empty($candidates)) {
                uasort($candidates, function ($a, $b) {
                    if ($a['distance'] !== $b['distance']) {
                        return $a['distance'] <=> $b['distance'];
                    }
                    return $b['freq'] <=> $a['freq'];
                });

                $bestMatch = array_key_first($candidates);
                $correctedWords[] = $bestMatch;
                $hasCorrection = true;
            } else {
                $correctedWords[] = $word;
            }
        }

        $correctedString = implode(' ', $correctedWords);

        return [
            'corrected_query' => $correctedString,
            'has_correction' => $hasCorrection,
            'suggestions' => $hasCorrection ? [$correctedString] : [],
        ];
    }
}
