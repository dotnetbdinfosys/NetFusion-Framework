<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\SDUI\SDUIManager;

/**
 * CLI command to render an SDUI screen into JSON or hydration payload.
 * Usage: php netfusion sdui:render --screen=dashboard [--platform=ios] [--hydrate]
 */
class SduiRenderCommand extends Command
{
    protected string $name = 'sdui:render';
    protected string $description = 'Render a Server-Driven UI screen into client schema or hydration payload';

    public function handle(): int
    {
        /** @var SDUIManager $manager */
        $manager = function_exists('app') && app()->has('sdui') ? app('sdui') : new SDUIManager();

        $screenId = $this->option('screen');
        if ($screenId === null) {
            $this->error("Please specify --screen=<id>.");
            return 1;
        }

        $platform = $this->option('platform') ?? 'all';
        $context = ['platform' => $platform];

        if ($this->hasOption('hydrate') && $this->option('hydrate')) {
            $payload = $manager->hydrate($screenId, $context);
            $this->line($payload->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $rendered = $manager->render($screenId, $context);
            $this->line(json_encode($rendered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return 0;
    }
}
