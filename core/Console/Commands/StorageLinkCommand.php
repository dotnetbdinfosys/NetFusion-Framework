<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/StorageLinkCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Create symbolic links and directory junctions for public storage.
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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;

/**
 * NetFusion Storage Link Command
 *
 * Creates the symbolic links or directory junctions configured for the application.
 * Usage:
 *   php netfusion storage:link
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class StorageLinkCommand extends Command
{
    protected string $name = 'storage:link';
    protected string $description = 'Create the symbolic links configured for the application';

    public function handle(): int
    {
        $links = config('filesystems.links');

        if (empty($links) || !is_array($links)) {
            $links = [
                public_path('storage') => storage_path('app/public'),
            ];
        }

        foreach ($links as $link => $target) {
            $link   = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $link), DIRECTORY_SEPARATOR);
            $target = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $target), DIRECTORY_SEPARATOR);

            // Ensure the target directory exists
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }

            if (file_exists($link) || is_link($link)) {
                $this->warn("The [{$link}] link already exists.");
                continue;
            }

            $created = false;

            if (PHP_OS_FAMILY === 'Windows') {
                // Try PHP native symlink first
                try {
                    $created = @symlink($target, $link);
                } catch (\Throwable) {
                    $created = false;
                }

                // If symlink failed (e.g. without admin privilege on Windows), use Directory Junction
                if (!$created) {
                    $cmd = sprintf('cmd /c mklink /J %s %s 2>NUL', escapeshellarg($link), escapeshellarg($target));
                    exec($cmd, $output, $code);
                    $created = ($code === 0 && (file_exists($link) || is_dir($link)));
                }
            } else {
                $created = @symlink($target, $link);
            }

            if ($created) {
                $this->success("The [{$link}] link has been connected to [{$target}].");
            } else {
                $this->error("Failed to create link from [{$link}] to [{$target}].");
            }
        }

        return 0;
    }
}
