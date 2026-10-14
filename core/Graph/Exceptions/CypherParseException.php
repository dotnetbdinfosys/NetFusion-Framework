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

namespace NetFusion\Core\Graph\Exceptions;

class CypherParseException extends GraphException
{
    public function __construct(string $message, int $position = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Cypher Syntax Error at position {$position}: {$message}", 0, $previous);
    }
}
