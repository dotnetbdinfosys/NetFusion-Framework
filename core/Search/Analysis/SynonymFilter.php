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

class SynonymFilter
{
    /**
     * @var array<string, array<string>> Word => Synonyms
     */
    protected array $synonyms = [];

    /**
     * @param array<string, array<string>> $synonymMap
     */
    public function __construct(array $synonymMap = [])
    {
        foreach ($synonymMap as $word => $syns) {
            $this->addSynonyms($word, $syns);
        }
    }

    public function addSynonyms(string $word, array $synonyms, bool $bidirectional = true): static
    {
        $word = strtolower(trim($word));
        $cleanSyns = array_map(fn($s) => strtolower(trim($s)), $synonyms);

        $this->synonyms[$word] = array_values(array_unique(array_merge($this->synonyms[$word] ?? [], $cleanSyns)));

        if ($bidirectional) {
            foreach ($cleanSyns as $syn) {
                $all = array_values(array_unique(array_merge($this->synonyms[$syn] ?? [], [$word], array_diff($cleanSyns, [$syn]))));
                $this->synonyms[$syn] = $all;
            }
        }

        return $this;
    }

    /**
     * Expand query tokens by appending configured synonyms.
     *
     * @param array<string> $tokens
     * @return array<string>
     */
    public function expand(array $tokens): array
    {
        $expanded = [];
        foreach ($tokens as $token) {
            $expanded[] = $token;
            $lower = strtolower($token);
            if (isset($this->synonyms[$lower])) {
                foreach ($this->synonyms[$lower] as $syn) {
                    if (!in_array($syn, $expanded, true)) {
                        $expanded[] = $syn;
                    }
                }
            }
        }
        return array_values(array_unique($expanded));
    }

    public function getSynonyms(string $word): array
    {
        return $this->synonyms[strtolower(trim($word))] ?? [];
    }
}
