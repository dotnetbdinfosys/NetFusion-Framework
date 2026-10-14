<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\SDUI\Layout;

use JsonSerializable;

/**
 * Design system tokens and styling context for Server-Driven UI.
 */
class ThemeContext implements JsonSerializable
{
    /** @var array<string, string> */
    protected array $colors = [
        'primary'    => '#0066CC',
        'secondary'  => '#6C757D',
        'background' => '#F8F9FA',
        'surface'    => '#FFFFFF',
        'text'       => '#212529',
        'error'      => '#DC3545',
        'warning'    => '#FFC107',
        'success'    => '#28A745',
    ];

    /** @var array<string, mixed> */
    protected array $typography = [
        'fontFamily' => 'Inter, -apple-system, sans-serif',
        'fontSize'   => [
            'xs' => '12px',
            'sm' => '14px',
            'md' => '16px',
            'lg' => '18px',
            'xl' => '24px',
        ],
    ];

    /** @var array<string, int|string> */
    protected array $spacing = [
        'xs' => 4,
        'sm' => 8,
        'md' => 16,
        'lg' => 24,
        'xl' => 32,
    ];

    /** @var array<string, int|string> */
    protected array $borderRadius = [
        'sm'   => 4,
        'md'   => 8,
        'lg'   => 16,
        'full' => 9999,
    ];

    protected bool $isDarkMode = false;

    public function __construct(bool $isDarkMode = false, array $overrides = [])
    {
        $this->isDarkMode = $isDarkMode;
        if ($isDarkMode) {
            $this->colors['background'] = '#121212';
            $this->colors['surface'] = '#1E1E1E';
            $this->colors['text'] = '#E0E0E0';
        }

        if (isset($overrides['colors'])) {
            $this->colors = array_merge($this->colors, $overrides['colors']);
        }
        if (isset($overrides['typography'])) {
            $this->typography = array_merge($this->typography, $overrides['typography']);
        }
        if (isset($overrides['spacing'])) {
            $this->spacing = array_merge($this->spacing, $overrides['spacing']);
        }
        if (isset($overrides['borderRadius'])) {
            $this->borderRadius = array_merge($this->borderRadius, $overrides['borderRadius']);
        }
    }

    public function isDarkMode(): bool
    {
        return $this->isDarkMode;
    }

    public function getColor(string $key, string $default = '#000000'): string
    {
        return $this->colors[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'isDarkMode'   => $this->isDarkMode,
            'colors'       => $this->colors,
            'typography'   => $this->typography,
            'spacing'      => $this->spacing,
            'borderRadius' => $this->borderRadius,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
