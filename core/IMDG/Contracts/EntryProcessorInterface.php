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

namespace NetFusion\Core\IMDG\Contracts;

interface EntryProcessorInterface
{
    /**
     * Process an entry atomically in-place.
     *
     * @param string $key
     * @param mixed &$value Reference to the mutable entry value
     * @return mixed Result to return to caller
     */
    public function process(string $key, mixed &$value): mixed;
}
