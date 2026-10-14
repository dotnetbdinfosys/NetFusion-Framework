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

namespace NetFusion\Core\ServiceMesh\Contracts;

use NetFusion\Core\ServiceMesh\Proxy\MeshRequestContext;
use NetFusion\Core\ServiceMesh\Proxy\MeshResponse;

interface SidecarProxyInterface
{
    /**
     * Intercept and process an incoming request to this service.
     */
    public function handleInbound(MeshRequestContext $context): MeshResponse;

    /**
     * Intercept and forward an outgoing request to an upstream service.
     */
    public function handleOutbound(MeshRequestContext $context): MeshResponse;

    /**
     * Get the service name associated with this sidecar proxy.
     */
    public function getServiceName(): string;
}
