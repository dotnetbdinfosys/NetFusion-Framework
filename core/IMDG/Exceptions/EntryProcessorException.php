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

namespace NetFusion\Core\IMDG\Exceptions;

use Throwable;

class EntryProcessorException extends GridException
{
    public function __construct(string $key, string $message, ?Throwable $previous = null)
    {
        parent::__construct("Failed to execute EntryProcessor on key [{$key}]: {$message}", 0, $previous);
    }
}
