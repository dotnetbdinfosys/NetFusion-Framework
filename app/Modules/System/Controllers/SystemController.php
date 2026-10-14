<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/System/Controllers/SystemController.php
| Module    : Modules\System
| Namespace : NetFusion\Modules\System\Controllers
| Purpose   : System Module controller handling health status and module introspection.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/System/Controllers/SystemController.php
| Module    : Modules\System
| Namespace : NetFusion\Modules\System\Controllers
| Purpose   : System Module controller handling health status and module introspection.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/System/Controllers/SystemController.php
| Module    : Modules\System
| Namespace : NetFusion\Modules\System\Controllers
| Purpose   : System Module controller handling health status and module introspection.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Modules\System\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

/**
 * System Module Controller
 *
 * Demonstrates HMVC controller resolution, auto-wiring, and module introspection.
 */
class SystemController
{
    /**
     * Return operational status and telemetry.
     */
    public function status(Request $request): Response
    {
        return Response::json([
            'module'      => 'System Module',
            'status'      => 'healthy',
            'environment' => app()->environment(),
            'php'         => PHP_VERSION,
            'time'        => now()->format(\DateTimeInterface::ATOM),
            'config'      => [
                'inspect_enabled' => config('system.inspect_enabled'),
            ],
            'request_ip'  => $request->ip(),
        ]);
    }

    /**
     * List all loaded HMVC modules via the ModuleRepository.
     */
    public function modules(): Response
    {
        $allModules = [];

        foreach (modules()->all() as $slug => $mod) {
            $allModules[] = [
                'slug'        => $slug,
                'name'        => $mod->name(),
                'version'     => $mod->version(),
                'description' => $mod->description(),
                'enabled'     => $mod->isEnabled(),
                'priority'    => $mod->priority(),
                'namespace'   => $mod->namespace(),
                'path'        => $mod->path(),
            ];
        }

        return Response::json([
            'count'   => count($allModules),
            'modules' => $allModules,
        ]);
    }
}
