<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/CheckInstallation.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Middleware redirecting uninstalled apps to /install wizard and locking /install when completed.
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

namespace NetFusion\Core\Http\Middleware;

use Closure;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

class CheckInstallation
{
    protected string $lockFile;

    public function __construct(?string $lockFile = null)
    {
        $this->lockFile = $lockFile ?? (function_exists('storage_path') ? storage_path('installed') : 'storage/installed');
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = '/' . ltrim($request->path(), '/');
        $isInstalled = file_exists($this->lockFile);

        // If trying to access /install when already installed, redirect to dashboard or home
        if (str_starts_with($path, '/install') && $path !== '/install/completed') {
            if ($isInstalled) {
                return Response::redirect('/admin/dashboard');
            }
            return $next($request);
        }

        // If not installed and trying to access other routes, redirect to installer
        if (!$isInstalled && !str_starts_with($path, '/install') && $path !== '/health' && !str_starts_with($path, '/api/system/ping')) {
            return Response::redirect('/install');
        }

        return $next($request);
    }
}
