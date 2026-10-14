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

use NetFusion\Core\IMDG\ContinuousQuery\EntryEvent;

interface ContinuousQueryListenerInterface
{
    /**
     * Handle entry event.
     */
    public function onEvent(EntryEvent $event): void;
}
