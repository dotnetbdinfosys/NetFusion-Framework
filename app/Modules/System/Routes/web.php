<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/System/Routes/web.php
| Module    : Modules\System
| Namespace : None (Module Web Routes)
| Purpose   : Web routes for System HMVC module.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

use NetFusion\Core\Routing\Router;
use NetFusion\Modules\System\Controllers\SystemController;

/** @var Router $router */

$router->get('/system', [SystemController::class, 'status'])->name('system.index');
$router->get('/system/status', [SystemController::class, 'status'])->name('system.status');
$router->get('/system/modules', [SystemController::class, 'modules'])->name('system.modules');
$router->get('/system/modules/upload', [SystemController::class, 'uploadForm'])->name('system.modules.upload');
$router->post('/system/modules/upload', [SystemController::class, 'handleUpload'])->name('system.modules.upload.post');
$router->get('/system/modules/download-sample', [SystemController::class, 'downloadSample'])->name('system.modules.sample');
$router->get('/system/audit/deleted', [SystemController::class, 'auditDeleted'])->name('system.audit.deleted');
$router->post('/system/audit/restore', [SystemController::class, 'restoreDeleted'])->name('system.audit.restore');
$router->post('/system/audit/archive-cold', [SystemController::class, 'archiveColdAudit'])->name('system.audit.archive_cold');
$router->get('/system/audit/cold-archive', [SystemController::class, 'coldArchiveView'])->name('system.audit.cold_archive');
$router->post('/system/audit/purge', [SystemController::class, 'purgeAudit'])->name('system.audit.purge');

