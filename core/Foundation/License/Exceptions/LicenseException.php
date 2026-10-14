<?php
/**
 * NetFusion Framework - Enterprise License Fabric
 *
 * Exception thrown when license verification, activation, or validation fails.
 *
 * @package    NetFusion\Core\Foundation\License\Exceptions
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Foundation\License\Exceptions;

class LicenseException extends \RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct('[DotNetBD License CMS] ' . $message, $code, $previous);
    }
}
