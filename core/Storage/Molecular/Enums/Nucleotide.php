<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Holographic Storage & DNA Molecular Data Encoding
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Storage\Molecular\Enums;

enum Nucleotide: string
{
    case ADENINE = 'A';
    case CYTOSINE = 'C';
    case GUANINE = 'G';
    case THYMINE = 'T';

    public static function fromIndex(int $index): self
    {
        return match ($index % 4) {
            0 => self::ADENINE,
            1 => self::CYTOSINE,
            2 => self::GUANINE,
            3 => self::THYMINE,
        };
    }

    public function toIndex(): int
    {
        return match ($this) {
            self::ADENINE => 0,
            self::CYTOSINE => 1,
            self::GUANINE => 2,
            self::THYMINE => 3,
        };
    }

    public function complement(): self
    {
        return match ($this) {
            self::ADENINE => self::THYMINE,
            self::THYMINE => self::ADENINE,
            self::CYTOSINE => self::GUANINE,
            self::GUANINE => self::CYTOSINE,
        };
    }
}
