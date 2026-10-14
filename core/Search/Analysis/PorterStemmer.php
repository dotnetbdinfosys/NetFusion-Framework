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

use NetFusion\Core\Search\Contracts\StemmerInterface;

class PorterStemmer implements StemmerInterface
{
    /**
     * @var array<string, string> Cache of previously computed stems
     */
    protected array $cache = [];

    public function stem(string $word): string
    {
        $word = strtolower(trim($word));
        if (strlen($word) <= 2) {
            return $word;
        }

        if (isset($this->cache[$word])) {
            return $this->cache[$word];
        }

        $stem = $this->step1($word);
        $stem = $this->step2($stem);
        $stem = $this->step3($stem);
        $stem = $this->step4($stem);
        $stem = $this->step5($stem);

        $this->cache[$word] = $stem;
        return $stem;
    }

    protected function step1(string $w): string
    {
        // 1a
        if (str_ends_with($w, 'sses')) {
            $w = substr($w, 0, -2);
        } elseif (str_ends_with($w, 'ies')) {
            $w = substr($w, 0, -2);
        } elseif (str_ends_with($w, 'ss')) {
            // keep
        } elseif (str_ends_with($w, 's')) {
            $w = substr($w, 0, -1);
        }

        // 1b
        $extra1b = false;
        if (str_ends_with($w, 'eed')) {
            $base = substr($w, 0, -3);
            if ($this->m($base) > 0) {
                $w = $base . 'ee';
            }
        } elseif (str_ends_with($w, 'ed')) {
            $base = substr($w, 0, -2);
            if ($this->containsVowel($base)) {
                $w = $base;
                $extra1b = true;
            }
        } elseif (str_ends_with($w, 'ing')) {
            $base = substr($w, 0, -3);
            if ($this->containsVowel($base)) {
                $w = $base;
                $extra1b = true;
            }
        }

        if ($extra1b) {
            if (str_ends_with($w, 'at') || str_ends_with($w, 'bl') || str_ends_with($w, 'iz')) {
                $w .= 'e';
            } elseif ($this->doubleConsonant($w) && !str_ends_with($w, 'l') && !str_ends_with($w, 's') && !str_ends_with($w, 'z')) {
                $w = substr($w, 0, -1);
            } elseif ($this->m($w) === 1 && $this->cvc($w)) {
                $w .= 'e';
            }
        }

        // 1c
        if (str_ends_with($w, 'y')) {
            $base = substr($w, 0, -1);
            if ($this->containsVowel($base)) {
                $w = $base . 'i';
            }
        }

        return $w;
    }

    protected function step2(string $w): string
    {
        $suffixes = [
            'ational' => 'ate', 'tional' => 'tion', 'enci' => 'ence', 'anci' => 'ance',
            'izer' => 'ize', 'bli' => 'ble', 'alli' => 'al', 'entli' => 'ent',
            'eli' => 'e', 'ousli' => 'ous', 'ization' => 'ize', 'ation' => 'ate',
            'ator' => 'ate', 'alism' => 'al', 'iveness' => 'ive', 'fulness' => 'ful',
            'ousness' => 'ous', 'aliti' => 'al', 'iviti' => 'ive', 'biliti' => 'ble',
            'logi' => 'log',
        ];

        foreach ($suffixes as $s => $rep) {
            if (str_ends_with($w, $s)) {
                $base = substr($w, 0, -strlen($s));
                if ($this->m($base) > 0) {
                    return $base . $rep;
                }
                break;
            }
        }

        return $w;
    }

    protected function step3(string $w): string
    {
        $suffixes = [
            'icate' => 'ic', 'ative' => '', 'alize' => 'al',
            'iciti' => 'ic', 'ical' => 'ic', 'ful' => '', 'ness' => ''
        ];

        foreach ($suffixes as $s => $rep) {
            if (str_ends_with($w, $s)) {
                $base = substr($w, 0, -strlen($s));
                if ($this->m($base) > 0) {
                    return $base . $rep;
                }
                break;
            }
        }

        return $w;
    }

    protected function step4(string $w): string
    {
        $suffixes = [
            'al', 'ance', 'ence', 'er', 'ic', 'able', 'ible', 'ant', 'ement',
            'ment', 'ent', 'ou', 'ism', 'ate', 'iti', 'ous', 'ive', 'ize'
        ];

        foreach ($suffixes as $s) {
            if (str_ends_with($w, $s)) {
                $base = substr($w, 0, -strlen($s));
                if ($this->m($base) > 1) {
                    return $base;
                }
                return $w;
            }
        }

        if (str_ends_with($w, 'ion')) {
            $base = substr($w, 0, -3);
            if ($this->m($base) > 1 && (str_ends_with($base, 's') || str_ends_with($base, 't'))) {
                return $base;
            }
        }

        return $w;
    }

    protected function step5(string $w): string
    {
        // 5a
        if (str_ends_with($w, 'e')) {
            $base = substr($w, 0, -1);
            $m = $this->m($base);
            if ($m > 1 || ($m === 1 && !$this->cvc($base))) {
                $w = $base;
            }
        }

        // 5b
        if ($this->m($w) > 1 && $this->doubleConsonant($w) && str_ends_with($w, 'l')) {
            $w = substr($w, 0, -1);
        }

        return $w;
    }

    protected function m(string $w): int
    {
        $w = preg_replace('/[^a-z]/', '', $w);
        if ($w === '' || $w === null) {
            return 0;
        }

        // Convert vowels to V and consonants to C
        $pattern = '';
        $len = strlen($w);
        for ($i = 0; $i < $len; $i++) {
            $ch = $w[$i];
            $isVowel = in_array($ch, ['a', 'e', 'i', 'o', 'u'], true);
            if (!$isVowel && $ch === 'y' && $i > 0 && !in_array($w[$i - 1], ['a', 'e', 'i', 'o', 'u'], true)) {
                $isVowel = true;
            }
            $pattern .= $isVowel ? 'V' : 'C';
        }

        // Compress consecutive V and C
        $compressed = preg_replace('/V+/', 'V', $pattern);
        $compressed = preg_replace('/C+/', 'C', (string)$compressed);

        // Count VC occurrences
        return substr_count((string)$compressed, 'VC');
    }

    protected function containsVowel(string $w): bool
    {
        $len = strlen($w);
        for ($i = 0; $i < $len; $i++) {
            $ch = $w[$i];
            if (in_array($ch, ['a', 'e', 'i', 'o', 'u'], true)) {
                return true;
            }
            if ($ch === 'y' && $i > 0 && !in_array($w[$i - 1], ['a', 'e', 'i', 'o', 'u'], true)) {
                return true;
            }
        }
        return false;
    }

    protected function doubleConsonant(string $w): bool
    {
        $len = strlen($w);
        if ($len < 2) {
            return false;
        }
        if ($w[$len - 1] === $w[$len - 2] && !in_array($w[$len - 1], ['a', 'e', 'i', 'o', 'u'], true)) {
            return true;
        }
        return false;
    }

    protected function cvc(string $w): bool
    {
        $len = strlen($w);
        if ($len < 3) {
            return false;
        }
        $c1 = $w[$len - 3];
        $v = $w[$len - 2];
        $c2 = $w[$len - 1];

        $isV = in_array($v, ['a', 'e', 'i', 'o', 'u'], true);
        $isC1 = !in_array($c1, ['a', 'e', 'i', 'o', 'u'], true);
        $isC2 = !in_array($c2, ['a', 'e', 'i', 'o', 'u', 'w', 'x', 'y'], true);

        return $isC1 && $isV && $isC2;
    }
}
