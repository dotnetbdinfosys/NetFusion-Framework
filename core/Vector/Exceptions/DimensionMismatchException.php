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

namespace NetFusion\Core\Vector\Exceptions;

class DimensionMismatchException extends VectorException
{
    public function __construct(int $expected, int $actual)
    {
        parent::__construct("Vector dimension mismatch: expected {$expected}, got {$actual}");
    }
}
