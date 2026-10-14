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

namespace NetFusion\Core\CEP\Contracts;

interface WindowFunctionInterface
{
    /**
     * Apply computation on accumulated events within a completed/fired window.
     *
     * @param string $key Partition / Stream key
     * @param WindowInterface $window The triggered window
     * @param array<EventInterface> $events Events collected in the window
     * @return mixed Computed window result or emitted event
     */
    public function apply(string $key, WindowInterface $window, array $events): mixed;
}
