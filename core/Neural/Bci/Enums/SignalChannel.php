<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Brain-Computer Interface (BCI) & Neural Signal Decoding
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Neural\Bci\Enums;

enum SignalChannel: string
{
    case FP1 = 'Fp1';
    case FP2 = 'Fp2';
    case F3  = 'F3';
    case F4  = 'F4';
    case FZ  = 'Fz';
    case C3  = 'C3';
    case C4  = 'C4';
    case CZ  = 'Cz';
    case P3  = 'P3';
    case P4  = 'P4';
    case PZ  = 'Pz';
    case O1  = 'O1';
    case O2  = 'O2';

    public function lobe(): string
    {
        return match ($this) {
            self::FP1, self::FP2 => 'Pre-frontal',
            self::F3, self::F4, self::FZ => 'Frontal',
            self::C3, self::C4, self::CZ => 'Central (Sensorimotor)',
            self::P3, self::P4, self::PZ => 'Parietal',
            self::O1, self::O2 => 'Occipital (Visual)',
        };
    }
}
