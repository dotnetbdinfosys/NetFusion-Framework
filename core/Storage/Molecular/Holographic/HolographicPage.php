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

namespace NetFusion\Core\Storage\Molecular\Holographic;

use JsonSerializable;
use NetFusion\Core\Storage\Molecular\Exceptions\MolecularStorageException;

class HolographicPage implements JsonSerializable
{
    /** @var int[] 0 or 1 bit array */
    protected array $bits = [];

    public function __construct(
        protected int $pageIndex,
        protected float $braggAngle, // in degrees, e.g. 15.0°
        protected int $width = 32,
        protected int $height = 32,
        protected float $wavelengthNm = 532.0,
        ?array $bits = null
    ) {
        $capacity = $this->width * $this->height;
        if ($bits !== null) {
            $this->bits = array_slice($bits, 0, $capacity);
        } else {
            $this->bits = array_fill(0, $capacity, 0);
        }
    }

    public function getPageIndex(): int
    {
        return $this->pageIndex;
    }

    public function getBraggAngle(): float
    {
        return $this->braggAngle;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getWavelengthNm(): float
    {
        return $this->wavelengthNm;
    }

    public function getBit(int $x, int $y): int
    {
        $idx = ($y * $this->width) + $x;
        return $this->bits[$idx] ?? 0;
    }

    public function setBit(int $x, int $y, int $val): void
    {
        $idx = ($y * $this->width) + $x;
        $this->bits[$idx] = $val ? 1 : 0;
    }

    /**
     * Pack binary byte payload into 2D SLM bit matrix
     */
    public static function fromBytes(string $payload, int $pageIndex, float $braggAngle, int $width = 32, int $height = 32): self
    {
        $capacity = $width * $height;
        $bits = [];
        $bytes = array_values(unpack('C*', $payload));

        foreach ($bytes as $byte) {
            for ($b = 7; $b >= 0; $b--) {
                $bits[] = ($byte >> $b) & 1;
                if (count($bits) >= $capacity) {
                    break 2;
                }
            }
        }

        while (count($bits) < $capacity) {
            $bits[] = 0;
        }

        return new self($pageIndex, $braggAngle, $width, $height, 532.0, $bits);
    }

    /**
     * Unpack 2D SLM bit matrix into raw binary byte payload
     */
    public function toBytes(int $byteLength): string
    {
        $bytes = [];
        $current = 0;
        $bitCount = 0;

        foreach ($this->bits as $bit) {
            $current = ($current << 1) | $bit;
            $bitCount++;
            if ($bitCount === 8) {
                $bytes[] = $current;
                $current = 0;
                $bitCount = 0;
                if (count($bytes) >= $byteLength) {
                    break;
                }
            }
        }

        return pack('C*', ...$bytes);
    }

    public function toArray(): array
    {
        return [
            'pageIndex' => $this->pageIndex,
            'braggAngle' => $this->braggAngle,
            'width' => $this->width,
            'height' => $this->height,
            'wavelengthNm' => $this->wavelengthNm,
            'totalBits' => count($this->bits),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
