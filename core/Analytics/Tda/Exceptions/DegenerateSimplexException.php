<?php
/**
 * NetFusion Framework - Enterprise Topological Data Analysis (TDA) Engine
 *
 * Exception thrown when a simplex has duplicate vertices or invalid geometry.
 *
 * @package    NetFusion\Core\Analytics\Tda\Exceptions
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Analytics\Tda\Exceptions;

use RuntimeException;

class DegenerateSimplexException extends RuntimeException
{
}
