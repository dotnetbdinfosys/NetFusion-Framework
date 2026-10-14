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

namespace NetFusion\Core\ZKP\Circuit;

/**
 * Variable identifier in an arithmetic circuit.
 */
class Variable
{
    public const ONE = 'ONE'; // Constant 1 wire index 0

    private string $name;
    private bool $isPublic;

    public function __construct(string $name, bool $isPublic = false)
    {
        $this->name     = $name;
        $this->isPublic = $isPublic;
    }

    public static function constantOne(): self
    {
        return new self(self::ONE, true);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
