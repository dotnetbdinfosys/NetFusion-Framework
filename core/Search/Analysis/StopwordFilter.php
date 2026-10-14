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

class StopwordFilter
{
    /**
     * @var array<string, bool>
     */
    protected array $stopwords = [];

    /**
     * @param array<string>|null $customStopwords
     */
    public function __construct(?array $customStopwords = null)
    {
        $words = $customStopwords ?? [
            'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and', 'any', 'are', 'as', 'at',
            'be', 'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but', 'by',
            'could', 'did', 'do', 'does', 'doing', 'down', 'during',
            'each', 'few', 'for', 'from', 'further',
            'had', 'has', 'have', 'having', 'he', 'her', 'here', 'hers', 'herself', 'him', 'himself', 'his', 'how',
            'i', 'if', 'in', 'into', 'is', 'it', 'its', 'itself',
            'me', 'more', 'most', 'my', 'myself',
            'no', 'nor', 'not', 'of', 'off', 'on', 'once', 'only', 'or', 'other', 'ought', 'our', 'ours', 'ourselves', 'out', 'over', 'own',
            'same', 'she', 'should', 'so', 'some', 'such',
            'than', 'that', 'the', 'their', 'theirs', 'them', 'themselves', 'then', 'there', 'these', 'they', 'this', 'those', 'through', 'to', 'too',
            'under', 'until', 'up', 'very',
            'was', 'we', 'were', 'what', 'when', 'where', 'which', 'while', 'who', 'whom', 'why', 'with', 'would',
            'you', 'your', 'yours', 'yourself', 'yourselves'
        ];

        foreach ($words as $w) {
            $this->stopwords[strtolower(trim($w))] = true;
        }
    }

    public function isStopword(string $token): bool
    {
        return isset($this->stopwords[strtolower($token)]);
    }

    /**
     * @param array<string> $tokens
     * @return array<string>
     */
    public function filter(array $tokens): array
    {
        return array_values(array_filter($tokens, fn($t) => !$this->isStopword($t)));
    }

    public function addStopword(string $word): static
    {
        $this->stopwords[strtolower(trim($word))] = true;
        return $this;
    }
}
