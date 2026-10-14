<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/LangMissingCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command detecting missing localization strings across translation dictionaries.
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

class LangMissingCommand extends Command
{
    protected string $name = 'lang:missing';
    protected string $description = 'Find missing translation keys in target locale compared to default locale (--locale=bn)';

    public function handle(): int
    {
        $targetLocale = (string) ($this->option('locale') ?? 'bn');
        $baseLocale   = 'en';

        $this->line("Comparing translation dictionaries ({$baseLocale} -> {$targetLocale})...");

        $langDir = function_exists('base_path') ? base_path('lang') : 'lang';

        $baseFile = $langDir . DIRECTORY_SEPARATOR . $baseLocale . DIRECTORY_SEPARATOR . 'messages.php';
        $targetFile = $langDir . DIRECTORY_SEPARATOR . $targetLocale . DIRECTORY_SEPARATOR . 'messages.php';

        if (!file_exists($baseFile) || !file_exists($targetFile)) {
            $this->warn("Could not find dictionary files for {$baseLocale} or {$targetLocale}.");
            return 1;
        }

        $baseKeys   = array_keys(require $baseFile);
        $targetKeys = array_keys(require $targetFile);

        $missing = array_diff($baseKeys, $targetKeys);

        if (empty($missing)) {
            $this->info("✔ All translation keys in 'messages' are fully localized for '{$targetLocale}'!");
            return 0;
        }

        $this->warn("Missing " . count($missing) . " key(s) in '{$targetLocale}':");
        foreach ($missing as $k) {
            $this->line("  - {$k}");
        }

        return 0;
    }
}
