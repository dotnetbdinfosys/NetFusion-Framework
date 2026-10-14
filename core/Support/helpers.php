<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Support/helpers.php
| Module    : Core\Support
| Namespace : None (Global Helper Functions)
| Purpose   : Expressive global helper functions: app(), config(), db(), csrf_token(), modules(), etc.
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
| File      : core/Support/helpers.php
| Module    : Core\Support
| Namespace : None (Global Helper Functions)
| Purpose   : Expressive global helper functions: app(), config(), db(), csrf_token(), modules(), etc.
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
| File      : core/Support/helpers.php
| Module    : Core\Support
| Namespace : None (Global Helper Functions)
| Purpose   : Expressive global helper functions: app(), config(), db(), csrf_token(), modules(), etc.
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

use NetFusion\Core\Application;
use NetFusion\Core\Container\Container;
use NetFusion\Core\Config\Env;

/**
 * NetFusion Global Helper Functions
 *
 * These functions are loaded via Composer's "files" autoloader and
 * provide a clean, expressive global API for common framework operations.
 *
 * All helpers are wrapped in function_exists() checks to prevent
 * conflicts with user-defined or third-party functions.
 *
 * @package NetFusion\Core\Support
 * @version 1.0.0
 */

if (!function_exists('app')) {
    /**
     * Get the global Application (DI Container) instance, or resolve
     * an abstract from it.
     *
     * @param  string|null          $abstract  Optional: abstract type or alias to resolve.
     * @param  array<string, mixed> $overrides Optional constructor parameter overrides.
     * @return ($abstract is null ? Application : mixed)
     */
    function app(string $abstract = null, array $overrides = []): mixed
    {
        $container = Container::getInstance();

        if ($abstract === null) {
            return $container;
        }

        return $container->make($abstract, $overrides);
    }
}

if (!function_exists('env')) {
    /**
     * Retrieve an environment variable value, with an optional default.
     *
     * @param  string                     $key     The env variable name.
     * @param  bool|float|int|string|null $default Default value if not found.
     * @return bool|float|int|string|null
     */
    function env(string $key, bool|float|int|string|null $default = null): bool|float|int|string|null
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    /**
     * Retrieve a configuration value using dot-notation, or set one at runtime.
     *
     * @param  string|array<string,mixed>|null $key     Dot-notation key or array of [key => value] pairs.
     * @param  mixed                           $default Default value if the key is not found.
     * @return mixed   The config value, the Config object (if null), or void (if setting).
     *
     * @example
     *   config('app.name')                   // Get a value
     *   config(['app.debug' => true])         // Set multiple values
     *   config()                             // Get the Config instance
     */
    function config(string|array|null $key = null, mixed $default = null): mixed
    {
        /** @var \NetFusion\Core\Config\Config $config */
        $config = app('config');

        if ($key === null) {
            return $config;
        }

        if (is_array($key)) {
            foreach ($key as $configKey => $configValue) {
                $config->set($configKey, $configValue);
            }
            return null;
        }

        return $config->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the absolute path to the application base directory.
     *
     * @param  string $path Optional sub-path to append.
     * @return string
     */
    function base_path(string $path = ''): string
    {
        /** @var Application $app */
        $app = app();
        return $app->basePath($path);
    }
}

if (!function_exists('app_path')) {
    /**
     * Get the absolute path to the app/ directory.
     *
     * @param  string $path Optional sub-path to append.
     * @return string
     */
    function app_path(string $path = ''): string
    {
        /** @var Application $app */
        $app = app();
        return $app->appPath($path);
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the absolute path to the config/ directory.
     *
     * @param  string $path Optional sub-path to append.
     * @return string
     */
    function config_path(string $path = ''): string
    {
        /** @var Application $app */
        $app = app();
        return $app->configPath($path);
    }
}

if (!function_exists('modules_path')) {
    /**
     * Get the absolute path to the app/Modules/ directory.
     *
     * @param  string $path Optional sub-path to append.
     * @return string
     */
    function modules_path(string $path = ''): string
    {
        /** @var Application $app */
        $app = app();
        return $app->modulesPath($path);
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the absolute path to the public/ directory.
     *
     * @param  string $path Optional sub-path to append.
     * @return string
     */
    function public_path(string $path = ''): string
    {
        /** @var Application $app */
        $app = app();
        return $app->publicPath($path);
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the absolute path to the storage/ directory.
     *
     * @param  string $path Optional sub-path to append.
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        /** @var Application $app */
        $app = app();
        return $app->storagePath($path);
    }
}

if (!function_exists('resource_path')) {
    /**
     * Get the absolute path to the resources/ directory.
     *
     * @param  string $path Optional sub-path to append.
     * @return string
     */
    function resource_path(string $path = ''): string
    {
        $resources = base_path('resources');
        return $path ? $resources . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $resources;
    }
}

if (!function_exists('dd')) {
    /**
     * Dump the given values and die.
     * Development debugging helper. Should never be used in production.
     *
     * @param  mixed ...$vars Values to dump.
     * @return never
     */
    function dd(mixed ...$vars): never
    {
        // Output pre-formatted dump with HTML in web context
        $isCli = PHP_SAPI === 'cli';

        if (!$isCli) {
            echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;'
               . 'font-size:13px;line-height:1.6;overflow:auto;margin:10px;">';
        }

        foreach ($vars as $var) {
            var_dump($var);
        }

        if (!$isCli) {
            echo '</pre>';
        }

        exit(1);
    }
}

if (!function_exists('dump')) {
    /**
     * Dump the given values without stopping execution.
     *
     * @param mixed ...$vars Values to dump.
     */
    function dump(mixed ...$vars): void
    {
        $isCli = PHP_SAPI === 'cli';

        if (!$isCli) {
            echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;'
               . 'font-size:13px;line-height:1.6;overflow:auto;margin:10px;">';
        }

        foreach ($vars as $var) {
            var_dump($var);
        }

        if (!$isCli) {
            echo '</pre>';
        }
    }
}

if (!function_exists('abort')) {
    /**
     * Throw an HTTP exception with the given status code and message.
     *
     * @param  int    $code    HTTP status code (e.g., 404, 403, 500).
     * @param  string $message Optional error message.
     * @return never
     *
     * @throws \RuntimeException
     */
    function abort(int $code, string $message = ''): never
    {
        // In Phase 2 this will be replaced with proper HTTP Exception handling.
        http_response_code($code);

        $defaultMessages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
        ];

        $phrase = $message ?: ($defaultMessages[$code] ?? 'Error');

        throw new \RuntimeException("[HTTP {$code}] {$phrase}", $code);
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     * If the value is a Closure, it will be invoked; otherwise returned as-is.
     *
     * @param  mixed  $value   A raw value or a Closure.
     * @param  mixed  ...$args Arguments to pass if the value is a Closure.
     * @return mixed
     */
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('now')) {
    /**
     * Get the current datetime as a DateTimeImmutable instance.
     *
     * @return \DateTimeImmutable
     */
    function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now');
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML entities in a string for safe output.
     * This is the primary XSS prevention helper for the View Engine.
     *
     * @param  string|null $value  The raw string to escape.
     * @param  bool        $double Whether to double-encode existing entities.
     * @return string              The HTML-safe string.
     */
    function e(?string $value, bool $double = true): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $double);
    }
}

if (!function_exists('class_basename')) {
    /**
     * Get the class "basename" of the given object/class.
     * Equivalent to basename() but for namespaced PHP class names.
     *
     * @param  object|string $class The object instance or fully-qualified class name.
     * @return string               The short class name without namespace prefix.
     */
    function class_basename(object|string $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('modules')) {
    /**
     * Get the ModuleRepository instance or list of all active modules.
     *
     * @return \NetFusion\Core\Modules\ModuleRepository
     */
    function modules(): \NetFusion\Core\Modules\ModuleRepository
    {
        return app(\NetFusion\Core\Modules\ModuleRepository::class);
    }
}

if (!function_exists('module')) {
    /**
     * Get a specific module by its slug or check its status.
     *
     * @param  string|null $slug Module slug (e.g. 'System', 'SaaS').
     * @return \NetFusion\Core\Modules\Module|null
     */
    function module(?string $slug = null): ?\NetFusion\Core\Modules\Module
    {
        if ($slug === null) {
            return null;
        }
        return modules()->get($slug);
    }
}

if (!function_exists('db')) {
    /**
     * Get the DatabaseManager or a Query Builder for a specific table.
     *
     * @param  string|null $table
     * @param  string|null $connection
     * @return \NetFusion\Core\Database\DatabaseManager|\NetFusion\Core\Database\Query\Builder
     *
     * @example
     *   db('users')->where('id', 1)->first();
     *   db()->connection('pgsql');
     */
    function db(?string $table = null, ?string $connection = null): mixed
    {
        /** @var \NetFusion\Core\Database\DatabaseManager $manager */
        $manager = app(\NetFusion\Core\Database\DatabaseManager::class);

        if ($table !== null) {
            return $manager->table($table, $connection);
        }

        return $manager;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the current CSRF token string.
     */
    function csrf_token(): string
    {
        return app(\NetFusion\Core\Security\Csrf::class)->token();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate an HTML hidden input containing the CSRF token.
     */
    function csrf_field(): string
    {
        return app(\NetFusion\Core\Security\Csrf::class)->field();
    }
}

if (!function_exists('tenant')) {
    /**
     * Get the active Tenant entity or a specific attribute.
     *
     * Usage:
     *   tenant()                 → TenantInterface|null
     *   tenant('name')           → string|null
     *
     * @param  string|null $key
     * @return \NetFusion\Core\Tenancy\Contracts\TenantInterface|mixed|null
     */
    function tenant(?string $key = null): mixed
    {
        /** @var \NetFusion\Core\Tenancy\TenantManager $manager */
        $manager = app(\NetFusion\Core\Tenancy\TenantManager::class);
        $tenant = $manager->getTenant();

        if ($tenant === null) {
            return null;
        }

        if ($key !== null) {
            return match ($key) {
                'id', 'key'     => $tenant->getTenantKey(),
                'name'          => $tenant->getName(),
                'subdomain'     => $tenant->getSubdomain(),
                'domain'        => $tenant->getDomain(),
                'database'      => $tenant->getDatabaseName(),
                default         => $tenant->getMetadata()[$key] ?? null,
            };
        }

        return $tenant;
    }
}

if (!function_exists('view')) {
    /**
     * Get the ViewFactory or render a View template.
     *
     * @param  string|null          $view
     * @param  array<string, mixed> $data
     * @return \NetFusion\Core\View\ViewFactory|\NetFusion\Core\View\View
     */
    function view(?string $view = null, array $data = []): mixed
    {
        /** @var \NetFusion\Core\View\ViewFactory $factory */
        $factory = app(\NetFusion\Core\View\ViewFactory::class);

        if ($view !== null) {
            return $factory->make($view, $data);
        }

        return $factory;
    }
}

if (!function_exists('event')) {
    /**
     * Dispatch an event with the Event Dispatcher.
     *
     * @param  string|object $event
     * @param  mixed         $payload
     * @return array<int, mixed>
     */
    function event(string|object $event, mixed $payload = []): array
    {
        return app(\NetFusion\Core\Events\Dispatcher::class)->dispatch($event, $payload);
    }
}

if (!function_exists('dispatch')) {
    /**
     * Alias for event() — dispatch an event.
     *
     * @param  string|object $event
     * @param  mixed         $payload
     * @return array<int, mixed>
     */
    function dispatch(string|object $event, mixed $payload = []): array
    {
        return event($event, $payload);
    }
}


if (!function_exists('session')) {
    /**
     * Get the Session instance or retrieve/store session data.
     *
     * @param  string|array|null $key
     * @param  mixed             $default
     * @return \NetFusion\Core\Session\Session|mixed
     */
    function session(string|array|null $key = null, mixed $default = null): mixed
    {
        /** @var \NetFusion\Core\Session\Session $session */
        $session = app(\NetFusion\Core\Session\Session::class);

        if ($key === null) {
            return $session;
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $session->set($k, $v);
            }
            return null;
        }

        return $session->get($key, $default);
    }
}

if (!function_exists('auth')) {
    /**
     * Get the AuthManager or a specific Guard instance.
     *
     * @param  string|null $guard
     * @return \NetFusion\Core\Auth\AuthManager|\NetFusion\Core\Auth\GuardInterface
     */
    function auth(?string $guard = null): mixed
    {
        /** @var \NetFusion\Core\Auth\AuthManager $manager */
        $manager = app(\NetFusion\Core\Auth\AuthManager::class);

        if ($guard !== null) {
            return $manager->guard($guard);
        }

        return $manager;
    }
}

if (!function_exists('queue')) {
    /**
     * Get the QueueManager instance or push a job to the queue.
     *
     * @param  \NetFusion\Core\Queue\Contracts\JobInterface|string|null $job
     * @param  string                                                   $queue
     * @return \NetFusion\Core\Queue\QueueManager|string
     */
    function queue(\NetFusion\Core\Queue\Contracts\JobInterface|string|null $job = null, string $queue = 'default'): mixed
    {
        /** @var \NetFusion\Core\Queue\QueueManager $manager */
        $manager = app('queue');

        if ($job !== null) {
            return $manager->push($job, $queue);
        }

        return $manager;
    }
}

if (!function_exists('cache')) {
    /**
     * Get or set cache values, or retrieve the CacheManager instance.
     *
     * Usage:
     *   cache()                           -> \NetFusion\Core\Cache\CacheManager
     *   cache('key')                      -> mixed
     *   cache('key', 'default')           -> mixed
     *   cache(['key' => 'val'], $ttl)     -> bool
     *
     * @param  array<string, mixed>|string|null $key
     * @param  mixed                            $default
     * @return mixed
     */
    function cache(array|string|null $key = null, mixed $default = null): mixed
    {
        /** @var \NetFusion\Core\Cache\CacheManager $manager */
        $manager = app('cache');

        if ($key === null) {
            return $manager;
        }

        if (is_array($key)) {
            return $manager->setMultiple($key, $default);
        }

        return $manager->get($key, $default);
    }
}

if (!function_exists('mailer')) {
    /**
     * Get the Mailer instance or begin composing an email.
     *
     * Usage:
     *   mailer() -> \NetFusion\Core\Mail\Mailer
     *   mailer()->to('user@example.com')->send(new InvoiceMail())
     *
     * @return \NetFusion\Core\Mail\Mailer
     */
    function mailer(): \NetFusion\Core\Mail\Mailer
    {
        return app('mailer');
    }
}

if (!function_exists('storage')) {
    /**
     * Get the StorageManager instance or a specific filesystem disk.
     *
     * Usage:
     *   storage() -> \NetFusion\Core\Filesystem\StorageManager
     *   storage('public') -> \NetFusion\Core\Filesystem\Contracts\FilesystemInterface
     *   storage()->put('file.txt', 'data')
     *
     * @param  string|null $disk
     * @return \NetFusion\Core\Filesystem\StorageManager|\NetFusion\Core\Filesystem\Contracts\FilesystemInterface
     */
    function storage(?string $disk = null): mixed
    {
        /** @var \NetFusion\Core\Filesystem\StorageManager $manager */
        $manager = app('storage');

        if ($disk !== null) {
            return $manager->disk($disk);
        }

        return $manager;
    }
}

if (!function_exists('webhook')) {
    /**
     * Get the WebhookManager instance or dispatch a webhook event.
     *
     * Usage:
     *   webhook() -> \NetFusion\Core\Webhooks\WebhookManager
     *   webhook('payroll.processed', ['employee_id' => 1, 'amount' => 5000])
     *
     * @param  string|null          $event
     * @param  array<string, mixed> $data
     * @param  bool                 $sync
     * @return \NetFusion\Core\Webhooks\WebhookManager|array<int, mixed>
     */
    function webhook(?string $event = null, array $data = [], bool $sync = false): mixed
    {
        /** @var \NetFusion\Core\Webhooks\WebhookManager $manager */
        $manager = app('webhook');

        if ($event !== null) {
            return $manager->dispatch($event, $data, $sync);
        }

        return $manager;
    }
}

if (!function_exists('audit')) {
    /**
     * Get the AuditManager instance or record an audit entry.
     *
     * Usage:
     *   audit() -> \NetFusion\Core\Audit\AuditManager
     *   audit('user.login', 'App\Models\User', 1, null, ['status' => 'success'])
     *
     * @param  string|null               $event
     * @param  string|null               $auditableType
     * @param  int|string|null           $auditableId
     * @param  array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null $newValues
     * @param  int|null                  $userId
     * @param  string|null               $tags
     * @return \NetFusion\Core\Audit\AuditManager|bool
     */
    function audit(
        ?string $event = null,
        ?string $auditableType = null,
        int|string|null $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?string $tags = null
    ): mixed {
        /** @var \NetFusion\Core\Audit\AuditManager $manager */
        $manager = app('audit');

        if ($event !== null && $auditableType !== null && $auditableId !== null) {
            return $manager->record($event, $auditableType, $auditableId, $oldValues, $newValues, $userId, $tags);
        }

        return $manager;
    }
}

if (!function_exists('backup')) {
    /**
     * Get the BackupManager instance.
     *
     * Usage:
     *   backup() -> \NetFusion\Core\Backup\BackupManager
     *   backup()->createZipBackup(['db', 'storage'])
     *
     * @return \NetFusion\Core\Backup\BackupManager
     */
    function backup(): \NetFusion\Core\Backup\BackupManager
    {
        return app('backup');
    }
}

if (!function_exists('__')) {
    /**
     * Translate the given message key.
     *
     * Usage:
     *   __('messages.welcome')
     *   __('Welcome :name', ['name' => 'Ashish'])
     *
     * @param  string|null $key
     * @param  array<string, mixed> $replace
     * @param  string|null $locale
     * @return string|\NetFusion\Core\Translation\Translator
     */
    function __(string|null $key = null, array $replace = [], ?string $locale = null): string|\NetFusion\Core\Translation\Translator
    {
        /** @var \NetFusion\Core\Translation\Translator $translator */
        $translator = app('translator');

        if ($key === null) {
            return $translator;
        }

        return $translator->get($key, $replace, $locale);
    }
}

if (!function_exists('trans')) {
    /**
     * Translate the given message key (alias for __()).
     */
    function trans(string|null $key = null, array $replace = [], ?string $locale = null): string|\NetFusion\Core\Translation\Translator
    {
        return __($key, $replace, $locale);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * Translates the given message based on a count.
     */
    function trans_choice(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        return app('translator')->choice($key, $number, $replace, $locale);
    }
}

if (!function_exists('app_locale')) {
    /**
     * Get the current application locale.
     */
    function app_locale(): string
    {
        return app('translator')->getLocale();
    }
}

if (!function_exists('set_locale')) {
    /**
     * Set the current application locale.
     */
    function set_locale(string $locale): void
    {
        app('translator')->setLocale($locale);
    }
}

if (!function_exists('webhook_gateway')) {
    /**
     * Get the WebhookGateway instance.
     *
     * @return \NetFusion\Core\Webhooks\Incoming\WebhookGateway
     */
    function webhook_gateway(): \NetFusion\Core\Webhooks\Incoming\WebhookGateway
    {
        return app('webhook.gateway');
    }
}

if (!function_exists('broadcast')) {
    /**
     * Get the BroadcastManager instance or broadcast an event.
     *
     * Usage:
     *   broadcast() -> \NetFusion\Core\Broadcasting\BroadcastManager
     *   broadcast(new OrderCreated($order))
     *   broadcast('news', 'alert', ['msg' => 'Server restart'])
     *
     * @param  mixed        $eventOrChannels
     * @param  string|null  $event
     * @param  array<string, mixed> $payload
     * @return \NetFusion\Core\Broadcasting\BroadcastManager|mixed
     */
    function broadcast(mixed $eventOrChannels = null, ?string $event = null, array $payload = []): mixed
    {
        /** @var \NetFusion\Core\Broadcasting\BroadcastManager $manager */
        $manager = app('broadcast');

        if ($eventOrChannels === null) {
            return $manager;
        }

        if ($eventOrChannels instanceof \NetFusion\Core\Broadcasting\Contracts\ShouldBroadcast) {
            $manager->broadcastEvent($eventOrChannels);
            return null;
        }

        if ($event !== null) {
            $manager->broadcast($eventOrChannels, $event, $payload);
            return null;
        }

        return $manager;
    }
}

if (!function_exists('trace')) {
    /**
     * Get Tracer instance or measure a named operation within a span.
     *
     * Usage:
     *   trace() -> \NetFusion\Core\Tracing\Tracer
     *   trace('expensive.op', fn() => doSomething())
     *
     * @param string|null          $operation
     * @param callable|null        $callback
     * @param array<string, mixed> $attributes
     * @return \NetFusion\Core\Tracing\Tracer|mixed
     */
    function trace(?string $operation = null, ?callable $callback = null, array $attributes = []): mixed
    {
        /** @var \NetFusion\Core\Tracing\Tracer $tracer */
        $tracer = app('tracer');

        if ($operation === null) {
            return $tracer;
        }

        if ($callback !== null) {
            return $tracer->measure($operation, $callback, $attributes);
        }

        return $tracer->startSpan($operation, $attributes);
    }
}

if (!function_exists('apm')) {
    /**
     * Get the Application Performance Monitoring (APM) manager instance.
     *
     * @return \NetFusion\Core\Tracing\ApmManager
     */
    function apm(): \NetFusion\Core\Tracing\ApmManager
    {
        return app('apm');
    }
}

if (!function_exists('feature')) {
    /**
     * Determine if a feature flag is active or resolve the FeatureManager.
     *
     * Usage:
     *   feature() -> \NetFusion\Core\Features\FeatureManager
     *   feature('new-dashboard') -> bool
     *   feature('beta-checkout', $user) -> bool
     *
     * @param string|null $feature
     * @param mixed       $scope
     * @param bool        $default
     * @return \NetFusion\Core\Features\FeatureManager|bool
     */
    function feature(?string $feature = null, mixed $scope = null, bool $default = false): mixed
    {
        /** @var \NetFusion\Core\Features\FeatureManager $manager */
        $manager = app('features');

        if ($feature === null) {
            return $manager;
        }

        return $manager->active($feature, $scope) ?: $default;
    }
}

if (!function_exists('feature_variant')) {
    /**
     * Determine the assigned variant for an A/B experiment.
     *
     * Usage:
     *   feature_variant('checkout_v2', $user, ['control' => 50, 'treatment' => 50])
     *
     * @param string             $experiment
     * @param mixed              $scope
     * @param array<string, int> $variants
     * @return string
     */
    function feature_variant(
        string $experiment,
        mixed $scope = null,
        array $variants = ['control' => 50, 'treatment' => 50]
    ): string {
        return app('features')->variant($experiment, $scope, $variants);
    }
}

if (!function_exists('search')) {
    /**
     * Get the SearchEngineManager/SearchManager instance, index or perform a search query.
     *
     * Usage:
     *   search()                                    → SearchEngineManager|SearchManager
     *   search('products')                          → IndexInterface
     *   search('articles', 'php 8.2')               → SearchResult
     *
     * @param  string|null          $index
     * @param  string|null          $query
     * @param  array<string, mixed> $options
     * @return mixed
     */
    function search(?string $index = null, ?string $query = null, array $options = []): mixed
    {
        /** @var \NetFusion\Core\Search\SearchEngineManager|\NetFusion\Core\Search\SearchManager $manager */
        $manager = app()->has('search.engine') ? app('search.engine') : (app()->has('search') ? app('search') : app(\NetFusion\Core\Search\SearchEngineManager::class));

        if ($index === null) {
            return $manager;
        }

        if ($manager instanceof \NetFusion\Core\Search\SearchEngineManager) {
            if ($query === null) {
                return $manager->hasIndex($index) ? $manager->getIndex($index) : $manager->createIndex($index);
            }
            $searchQuery = new \NetFusion\Core\Search\Models\SearchQuery(
                queryString: $query,
                vector: $options['vector'] ?? null,
                filters: $options['filters'] ?? [],
                facets: $options['facets'] ?? [],
                boostFields: $options['boost'] ?? [],
                limit: $options['limit'] ?? 20,
                offset: $options['offset'] ?? 0,
                sortField: $options['sort_field'] ?? null,
                sortOrder: $options['sort_order'] ?? 'desc',
                fuzzy: $options['fuzzy'] ?? false,
                hybridWeight: $options['hybrid_weight'] ?? 0.5
            );
            return $manager->search($index, $searchQuery);
        }

        if ($query === null) {
            return $manager;
        }

        return $manager->search($index, $query, $options);
    }
}

if (!function_exists('notify')) {
    /**
     * Send a notification to the given notifiable entity or entities.
     *
     * Usage:
     *   notify($user, new InvoicePaidNotification($invoice));
     *
     * @param object|array<object>|null                          $notifiables
     * @param \NetFusion\Core\Notifications\Notification|null   $notification
     * @return \NetFusion\Core\Notifications\NotificationManager|void
     */
    function notify(object|array|null $notifiables = null, ?\NetFusion\Core\Notifications\Notification $notification = null): mixed
    {
        /** @var \NetFusion\Core\Notifications\NotificationManager $manager */
        $manager = app(\NetFusion\Core\Notifications\NotificationManager::class);

        if ($notifiables === null && $notification === null) {
            return $manager;
        }

        if ($notifiables !== null && $notification !== null) {
            $manager->send($notifiables, $notification);
            return null;
        }

        return $manager;
    }
}

if (!function_exists('notification')) {
    /**
     * Get the NotificationManager instance.
     *
     * @return \NetFusion\Core\Notifications\NotificationManager
     */
    function notification(): \NetFusion\Core\Notifications\NotificationManager
    {
        return app(\NetFusion\Core\Notifications\NotificationManager::class);
    }
}

if (!function_exists('money')) {
    /**
     * Create a Money value object from a decimal string or minor units.
     *
     * Usage:
     *   money('100.50', 'USD')         → Money object ($100.50)
     *   money(10050, 'USD')            → Money object (from minor units: 100 cents = $100.50 — NO, 10050 cents = $100.50)
     *   money('100.50', 'BDT')->format() → '৳100.50'
     *
     * @param  string|int $amount   Decimal string or integer minor units
     * @param  string     $currency ISO 4217 currency code
     * @return \NetFusion\Core\Currency\Money
     */
    function money(string|int $amount, string $currency): \NetFusion\Core\Currency\Money
    {
        if (is_int($amount)) {
            return \NetFusion\Core\Currency\Money::ofMinor($amount, $currency);
        }

        return \NetFusion\Core\Currency\Money::of($amount, $currency);
    }
}

if (!function_exists('currency')) {
    /**
     * Get the CurrencyManager instance or perform a quick conversion.
     *
     * Usage:
     *   currency()                              → CurrencyManager
     *   currency('USD', 'BDT')                 → string rate (e.g. '110.00')
     *   currency()->convert(money('100', 'USD'), 'BDT') → Money
     *
     * @param  string|null $from Source currency code
     * @param  string|null $to   Target currency code
     * @return \NetFusion\Core\Currency\CurrencyManager|string
     */
    function currency(?string $from = null, ?string $to = null): mixed
    {
        /** @var \NetFusion\Core\Currency\CurrencyManager $manager */
        $manager = app('currency');

        if ($from !== null && $to !== null) {
            return $manager->getRate($from, $to);
        }

        return $manager;
    }
}

if (!function_exists('form')) {
    /**
     * Get a FormBuilder instance or construct a Form from an array/JSON schema.
     *
     * Usage:
     *   form()                                        → FormBuilder
     *   form('/submit', 'POST')                       → FormBuilder with action/method
     *   form(['fields' => ['email' => ['type'=>'text']]]) → Form
     *   form('{"fields": {"email": {"type":"text"}}}')   → Form
     *
     * @param  string|array|null $schemaOrAction
     * @param  string            $method
     * @return \NetFusion\Core\Forms\FormBuilder|\NetFusion\Core\Forms\Form
     */
    function form(string|array|null $schemaOrAction = null, string $method = 'POST'): mixed
    {
        if (is_array($schemaOrAction)) {
            return \NetFusion\Core\Forms\FormSchema::fromArray($schemaOrAction);
        }

        if (is_string($schemaOrAction) && (str_starts_with(trim($schemaOrAction), '{') || str_starts_with(trim($schemaOrAction), '['))) {
            return \NetFusion\Core\Forms\FormSchema::fromJson($schemaOrAction);
        }

        return new \NetFusion\Core\Forms\FormBuilder($schemaOrAction ?? '', $method);
    }
}

if (!function_exists('document')) {
    /**
     * Get the DocumentManager instance.
     *
     * @return \NetFusion\Core\Documents\DocumentManager
     */
    function document(): \NetFusion\Core\Documents\DocumentManager
    {
        return app('document');
    }
}

if (!function_exists('pdf')) {
    /**
     * Create a PDF Document or HTML-to-PDF Adapter.
     *
     * Usage:
     *   pdf()                             → PdfDocument
     *   pdf('<h1>Invoice</h1>')            → HtmlToPdfAdapter
     *   pdf()->text(40, 50, 'Hello')->output()
     *
     * @param  string|null $html
     * @param  string      $paperSize
     * @param  string      $orientation
     * @return \NetFusion\Core\Documents\Pdf\PdfDocument|\NetFusion\Core\Documents\Pdf\HtmlToPdfAdapter
     */
    function pdf(?string $html = null, string $paperSize = 'A4', string $orientation = 'portrait'): mixed
    {
        /** @var \NetFusion\Core\Documents\DocumentManager $manager */
        $manager = app('document');

        if ($html !== null) {
            return $manager->htmlToPdf($html, $paperSize, $orientation);
        }

        return $manager->pdf($paperSize, $orientation);
    }
}

if (!function_exists('csv')) {
    /**
     * Create a CSV Exporter instance.
     *
     * Usage:
     *   csv($users, ['id' => 'ID', 'name' => 'Name'])->download('users.csv')
     *
     * @param  iterable $data
     * @param  array    $headers
     * @return \NetFusion\Core\Documents\Exporters\CsvExporter
     */
    function csv(iterable $data = [], array $headers = []): \NetFusion\Core\Documents\Exporters\CsvExporter
    {
        return app('document')->csv($data, $headers);
    }
}

if (!function_exists('excel')) {
    /**
     * Create an Excel XML Exporter instance.
     *
     * Usage:
     *   excel($orders, ['id' => 'Order #', 'total' => 'Total'])->download('orders.xls')
     *
     * @param  iterable $data
     * @param  array    $headers
     * @param  string   $sheetTitle
     * @return \NetFusion\Core\Documents\Exporters\ExcelXmlExporter
     */
    function excel(iterable $data = [], array $headers = [], string $sheetTitle = 'Sheet1'): \NetFusion\Core\Documents\Exporters\ExcelXmlExporter
    {
        return app('document')->excel($data, $headers, $sheetTitle);
    }
}

if (!function_exists('workflow')) {
    /**
     * Get the WorkflowManager instance or a specific StateMachine by name.
     *
     * Usage:
     *   workflow()                                  → WorkflowManager
     *   workflow('order')                           → StateMachine
     *   workflow('invoice')->can($inv, 'pay')       → bool
     *
     * @param  string|null $name
     * @return \NetFusion\Core\Workflow\WorkflowManager|\NetFusion\Core\Workflow\StateMachine
     */
    function workflow(?string $name = null): mixed
    {
        /** @var \NetFusion\Core\Workflow\WorkflowManager $manager */
        $manager = app('workflow');

        if ($name !== null) {
            return $manager->get($name);
        }

        return $manager;
    }
}

if (!function_exists('eventsource')) {
    /**
     * Get the EventSourcingManager instance.
     *
     * @return \NetFusion\Core\EventSourcing\EventSourcingManager
     */
    function eventsource(): \NetFusion\Core\EventSourcing\EventSourcingManager
    {
        return app('eventsource');
    }
}

if (!function_exists('command_bus')) {
    /**
     * Get the CommandBus instance or dispatch a command.
     *
     * @param  \NetFusion\Core\EventSourcing\Contracts\CommandInterface|null $command
     * @return \NetFusion\Core\EventSourcing\CommandBus|mixed
     */
    function command_bus(?\NetFusion\Core\EventSourcing\Contracts\CommandInterface $command = null): mixed
    {
        /** @var \NetFusion\Core\EventSourcing\CommandBus $bus */
        $bus = app('command.bus');

        if ($command !== null) {
            return $bus->dispatch($command);
        }

        return $bus;
    }
}

if (!function_exists('query_bus')) {
    /**
     * Get the QueryBus instance or ask a query.
     *
     * @param  \NetFusion\Core\EventSourcing\Contracts\QueryInterface|null $query
     * @return \NetFusion\Core\EventSourcing\QueryBus|mixed
     */
    function query_bus(?\NetFusion\Core\EventSourcing\Contracts\QueryInterface $query = null): mixed
    {
        /** @var \NetFusion\Core\EventSourcing\QueryBus $bus */
        $bus = app('query.bus');

        if ($query !== null) {
            return $bus->ask($query);
        }

        return $bus;
    }
}

if (!function_exists('lock')) {
    /**
     * Get the LockManager instance or create a new named Lock.
     *
     * Usage:
     *   lock()                                                → LockManager
     *   lock('process-payouts', 60)                           → LockInterface
     *   lock('process-payouts', 60)->get(fn() => doWork())    → mixed
     *
     * @param  string|null $name
     * @param  int         $seconds
     * @param  string|null $owner
     * @return \NetFusion\Core\Lock\LockManager|\NetFusion\Core\Lock\Contracts\LockInterface
     */
    function lock(?string $name = null, int $seconds = 60, ?string $owner = null): mixed
    {
        /** @var \NetFusion\Core\Lock\LockManager $manager */
        $manager = app('lock');

        if ($name !== null) {
            return $manager->create($name, $seconds, $owner);
        }

        return $manager;
    }
}

if (!function_exists('tenant_id')) {
    /**
     * Get the current active Tenant ID string.
     *
     * @return string|null
     */
    function tenant_id(): ?string
    {
        /** @var \NetFusion\Core\Tenancy\TenantManager $manager */
        $manager = app('tenant');
        return $manager->getTenantId();
    }
}

if (!function_exists('tenancy')) {
    /**
     * Get the TenantManager instance.
     *
     * @return \NetFusion\Core\Tenancy\TenantManager
     */
    function tenancy(): \NetFusion\Core\Tenancy\TenantManager
    {
        return app('tenant');
    }
}

if (!function_exists('socket')) {
    /**
     * Get the SocketManager instance or push message to channel.
     *
     * Usage:
     *   socket()                                       → SocketManager
     *   socket('orders', 'OrderShipped', ['id' => 1])  → bool
     *
     * @param  string|null $channel
     * @param  string|null $event
     * @param  array       $data
     * @return \NetFusion\Core\Sockets\SocketManager|bool
     */
    function socket(?string $channel = null, ?string $event = null, array $data = []): mixed
    {
        /** @var \NetFusion\Core\Sockets\SocketManager $manager */
        $manager = app('socket');

        if ($channel !== null && $event !== null) {
            return $manager->push($channel, $event, $data);
        }

        return $manager;
    }
}

if (!function_exists('websocket')) {
    /**
     * Get the SocketManager instance.
     *
     * @return \NetFusion\Core\Sockets\SocketManager
     */
    function websocket(): \NetFusion\Core\Sockets\SocketManager
    {
        return app('socket');
    }
}

if (!function_exists('graphql')) {
    /**
     * Get the GraphQLManager instance or execute a query.
     *
     * Usage:
     *   graphql()                                           → GraphQLManager
     *   graphql('{ ping }')                                 → ExecutionResult
     *   graphql('query GetUser($id: ID!) { user(id: $id) { name } }', ['id' => 1]) → ExecutionResult
     *
     * @param  string|null          $query
     * @param  array<string, mixed> $variables
     * @param  mixed                $context
     * @return \NetFusion\Core\GraphQL\GraphQLManager|\NetFusion\Core\GraphQL\Execution\ExecutionResult
     */
    function graphql(?string $query = null, array $variables = [], mixed $context = null): mixed
    {
        /** @var \NetFusion\Core\GraphQL\GraphQLManager $manager */
        $manager = app('graphql');

        if ($query !== null) {
            return $manager->execute($query, $variables, $context);
        }

        return $manager;
    }
}

if (!function_exists('rpc')) {
    /**
     * Get the RpcManager instance or get a fluent service proxy.
     *
     * Usage:
     *   rpc()                                       → RpcManager
     *   rpc('BillingService')                       → RpcProxy
     *   rpc('BillingService')->charge(101, 50.00)   → mixed
     *
     * @param  string|null $service
     * @param  array       $options
     * @return \NetFusion\Core\RPC\RpcManager|\NetFusion\Core\RPC\Client\RpcProxy
     */
    function rpc(?string $service = null, array $options = []): mixed
    {
        /** @var \NetFusion\Core\RPC\RpcManager $manager */
        $manager = app('rpc');

        if ($service !== null) {
            return $manager->service($service, $options);
        }

        return $manager;
    }
}

if (!function_exists('circuit_breaker')) {
    /**
     * Execute an action protected by Circuit Breaker.
     *
     * Usage:
     *   circuit_breaker('payment_gateway', fn() => $api->charge(), fn($e) => $fallbackResponse)
     *   circuit_breaker('payment_gateway') → CircuitBreaker
     *
     * @param  string        $name
     * @param  callable|null $action
     * @param  callable|null $fallback
     * @return mixed|\NetFusion\Core\RPC\CircuitBreaker\CircuitBreaker
     */
    function circuit_breaker(string $name, ?callable $action = null, ?callable $fallback = null): mixed
    {
        /** @var \NetFusion\Core\RPC\RpcManager $manager */
        $manager = app('rpc');
        $cb = $manager->circuitBreaker($name);

        if ($action !== null) {
            return $cb->execute($action, $fallback);
        }

        return $cb;
    }
}

if (!function_exists('ai')) {
    /**
     * Get the AiManager instance or execute a chat prompt.
     *
     * Usage:
     *   ai()                                      → \NetFusion\Core\AI\AiManager
     *   ai('Explain quantum computing in 1 line') → \NetFusion\Core\AI\ChatResponse
     *   ai(['role' => 'user', 'content' => 'Hi']) → \NetFusion\Core\AI\ChatResponse
     *
     * @param  string|array|null $promptOrMessages
     * @param  array<string, mixed> $options
     * @param  string|null $driver
     * @return \NetFusion\Core\AI\AiManager|\NetFusion\Core\AI\ChatResponse
     */
    function ai(string|array|null $promptOrMessages = null, array $options = [], ?string $driver = null): mixed
    {
        /** @var \NetFusion\Core\AI\AiManager $manager */
        $manager = app('ai');

        if ($promptOrMessages !== null) {
            return $manager->chat($promptOrMessages, $options, $driver);
        }

        return $manager;
    }
}

if (!function_exists('ai_agent')) {
    /**
     * Create or run an autonomous AI Agent.
     *
     * Usage:
     *   ai_agent('Calculate 50 * 20')            → \NetFusion\Core\AI\Agents\AgentResult
     *   ai_agent()                              → \NetFusion\Core\AI\Agents\Agent
     *
     * @param  string|null $prompt
     * @param  string      $systemPrompt
     * @param  array       $tools
     * @param  string|null $driver
     * @return \NetFusion\Core\AI\Agents\Agent|\NetFusion\Core\AI\Agents\AgentResult
     */
    function ai_agent(
        ?string $prompt = null,
        string $systemPrompt = 'You are an intelligent AI assistant equipped with tools.',
        array $tools = [],
        ?string $driver = null
    ): mixed {
        /** @var \NetFusion\Core\AI\AiManager $manager */
        $manager = app('ai');
        $agent = $manager->agent($systemPrompt, $tools, $driver);

        if ($prompt !== null) {
            return $agent->run($prompt);
        }

        return $agent;
    }
}

if (!function_exists('ai_vector')) {
    /**
     * Get vector store instance or search vector documents.
     *
     * Usage:
     *   ai_vector()                             → \NetFusion\Core\AI\Contracts\VectorStoreInterface
     *   ai_vector()->search($vector, limit: 5)
     *
     * @param  string|null $store
     * @return \NetFusion\Core\AI\Contracts\VectorStoreInterface
     */
    function ai_vector(?string $store = null): \NetFusion\Core\AI\Contracts\VectorStoreInterface
    {
        /** @var \NetFusion\Core\AI\AiManager $manager */
        $manager = app('ai');
        return $manager->vectorStore($store);
    }
}

if (!function_exists('saga')) {
    /**
     * Get the SagaManager instance or define a new Saga.
     *
     * Usage:
     *   saga()                                                    → \NetFusion\Core\Saga\SagaManager
     *   saga('order_checkout', fn($b) => $b->step(...))           → \NetFusion\Core\Saga\SagaManager
     *
     * @param  string|null $name
     * @param  callable|null $definition
     * @return \NetFusion\Core\Saga\SagaManager|\NetFusion\Core\Saga\Contracts\SagaInterface
     */
    function saga(?string $name = null, ?callable $definition = null): mixed
    {
        /** @var \NetFusion\Core\Saga\SagaManager $manager */
        $manager = app('saga');

        if ($name !== null && $definition !== null) {
            $manager->define($name, $definition);
            return $manager;
        }

        if ($name !== null) {
            return $manager->get($name);
        }

        return $manager;
    }
}

if (!function_exists('saga_start')) {
    /**
     * Start and execute a distributed Saga transaction by name.
     *
     * Usage:
     *   saga_start('order_checkout', ['order_id' => 101, 'amount' => 500]) → \NetFusion\Core\Saga\SagaInstance
     *
     * @param  string $name
     * @param  array<string, mixed> $payload
     * @param  string|null $store
     * @return \NetFusion\Core\Saga\SagaInstance
     */
    function saga_start(string $name, array $payload = [], ?string $store = null): \NetFusion\Core\Saga\SagaInstance
    {
        /** @var \NetFusion\Core\Saga\SagaManager $manager */
        $manager = app('saga');
        return $manager->run($name, $payload, $store);
    }
}

if (!function_exists('can_access')) {
    /**
     * Check if a subject has permission to perform an action on a resource under given environment.
     *
     * Usage:
     *   can_access($user, 'edit', $document, ['ip' => '127.0.0.1']) → bool
     *
     * @param  mixed $subject
     * @param  string $action
     * @param  mixed $resource
     * @param  array<string, mixed> $environment
     * @return bool
     */
    function can_access(mixed $subject, string $action, mixed $resource = null, array $environment = []): bool
    {
        /** @var \NetFusion\Core\Auth\Policy\AccessManager $manager */
        $manager = app(\NetFusion\Core\Auth\Policy\AccessManager::class);
        return $manager->can($subject, $action, $resource, $environment);
    }
}

if (!function_exists('policy')) {
    /**
     * Get the PolicyEngine instance or define a new policy.
     *
     * Usage:
     *   policy()                                               → \NetFusion\Core\Auth\Policy\Contracts\PolicyEngineInterface
     *   policy('document_policy', fn($p) => $p->allow(...))     → \NetFusion\Core\Auth\Policy\Contracts\PolicyEngineInterface
     *
     * @param  string|null $name
     * @param  callable|null $definition
     * @return \NetFusion\Core\Auth\Policy\Contracts\PolicyEngineInterface
     */
    function policy(?string $name = null, ?callable $definition = null): \NetFusion\Core\Auth\Policy\Contracts\PolicyEngineInterface
    {
        /** @var \NetFusion\Core\Auth\Policy\AccessManager $manager */
        $manager = app(\NetFusion\Core\Auth\Policy\AccessManager::class);

        if ($name !== null && $definition !== null) {
            $manager->define($name, $definition);
        }

        return $manager->engine();
    }
}

if (!function_exists('rebac')) {
    /**
     * Get the ReBAC Manager instance or check a relation.
     *
     * Usage:
     *   rebac()                                                 → \NetFusion\Core\Auth\Policy\Rebac\RebacManager
     *   rebac('doc:101', 'viewer', 'user:42')                  → bool
     *
     * @param  string|null $entity
     * @param  string|null $relation
     * @param  string|null $subject
     * @return \NetFusion\Core\Auth\Policy\Rebac\RebacManager|bool
     */
    function rebac(?string $entity = null, ?string $relation = null, ?string $subject = null): mixed
    {
        /** @var \NetFusion\Core\Auth\Policy\AccessManager $manager */
        $manager = app(\NetFusion\Core\Auth\Policy\AccessManager::class);
        $rebacManager = $manager->rebac();

        if ($entity !== null && $relation !== null && $subject !== null) {
            return $rebacManager->check($entity, $relation, $subject);
        }

        return $rebacManager;
    }
}

if (!function_exists('telemetry')) {
    /**
     * Get the TelemetryManager instance or current metric registry.
     *
     * Usage:
     *   telemetry()                                             → \NetFusion\Core\Telemetry\TelemetryManager
     *
     * @return \NetFusion\Core\Telemetry\TelemetryManager
     */
    function telemetry(): \NetFusion\Core\Telemetry\TelemetryManager
    {
        /** @var \NetFusion\Core\Telemetry\TelemetryManager $manager */
        $manager = app(\NetFusion\Core\Telemetry\TelemetryManager::class);
        return $manager;
    }
}

if (!function_exists('metric_counter')) {
    /**
     * Get or create a Counter metric instance.
     *
     * @param  string $name
     * @param  string $description
     * @param  string $unit
     * @return \NetFusion\Core\Telemetry\Contracts\CounterInterface
     */
    function metric_counter(string $name, string $description = '', string $unit = '1'): \NetFusion\Core\Telemetry\Contracts\CounterInterface
    {
        return telemetry()->counter($name, $description, $unit);
    }
}

if (!function_exists('metric_gauge')) {
    /**
     * Get or create a Gauge metric instance.
     *
     * @param  string $name
     * @param  string $description
     * @param  string $unit
     * @return \NetFusion\Core\Telemetry\Contracts\GaugeInterface
     */
    function metric_gauge(string $name, string $description = '', string $unit = '1'): \NetFusion\Core\Telemetry\Contracts\GaugeInterface
    {
        return telemetry()->gauge($name, $description, $unit);
    }
}

if (!function_exists('metric_histogram')) {
    /**
     * Get or create a Histogram metric instance.
     *
     * @param  string $name
     * @param  string $description
     * @param  string $unit
     * @param  float[] $buckets
     * @return \NetFusion\Core\Telemetry\Contracts\HistogramInterface
     */
    function metric_histogram(string $name, string $description = '', string $unit = 's', array $buckets = []): \NetFusion\Core\Telemetry\Contracts\HistogramInterface
    {
        return telemetry()->histogram($name, $description, $unit, $buckets);
    }
}

if (!function_exists('metric_measure')) {
    /**
     * Measure the execution latency of a callable using a Histogram and Counter.
     *
     * @template T
     * @param  string $name
     * @param  callable(): T $action
     * @param  array<string, string|int|float|bool> $labels
     * @param  string $description
     * @return T
     */
    function metric_measure(string $name, callable $action, array $labels = [], string $description = ''): mixed
    {
        return telemetry()->measure($name, $action, $labels, $description);
    }
}

if (!function_exists('trace_context')) {
    /**
     * Get or set the active W3C TraceContext.
     *
     * @param  \NetFusion\Core\Tracing\TraceContext|null $context
     * @return \NetFusion\Core\Tracing\TraceContext|null
     */
    function trace_context(?\NetFusion\Core\Tracing\TraceContext $context = null): ?\NetFusion\Core\Tracing\TraceContext
    {
        $manager = telemetry();
        if ($context !== null) {
            $manager->setTraceContext($context);
        }

        return $manager->getTraceContext();
    }
}

if (!function_exists('baggage')) {
    /**
     * Get or set values on the active W3C Baggage context.
     *
     * Usage:
     *   baggage()                           → \NetFusion\Core\Telemetry\Context\Baggage
     *   baggage('userId')                   → string|null
     *   baggage('userId', 'alice')          → \NetFusion\Core\Telemetry\Context\Baggage
     *
     * @param  string|null $key
     * @param  string|null $value
     * @return \NetFusion\Core\Telemetry\Context\Baggage|string|null
     */
    function baggage(?string $key = null, ?string $value = null): mixed
    {
        $baggage = telemetry()->getBaggage();

        if ($key !== null && $value !== null) {
            $baggage->set($key, $value);
            return $baggage;
        }

        if ($key !== null) {
            return $baggage->get($key);
        }

        return $baggage;
    }
}

if (!function_exists('webauthn')) {
    /**
     * Get the WebAuthnManager instance.
     *
     * @return \NetFusion\Core\Auth\WebAuthn\WebAuthnManager
     */
    function webauthn(): \NetFusion\Core\Auth\WebAuthn\WebAuthnManager
    {
        return app('webauthn');
    }
}

if (!function_exists('passkey')) {
    /**
     * Get the WebAuthnManager instance or perform passkey operation.
     *
     * @return \NetFusion\Core\Auth\WebAuthn\WebAuthnManager
     */
    function passkey(): \NetFusion\Core\Auth\WebAuthn\WebAuthnManager
    {
        return app('passkey');
    }
}

if (!function_exists('replication')) {
    /**
     * Get the ReplicationManager instance.
     *
     * @return \NetFusion\Core\Data\Replication\ReplicationManager
     */
    function replication(): \NetFusion\Core\Data\Replication\ReplicationManager
    {
        return app('replication');
    }
}

if (!function_exists('crdt')) {
    /**
     * Get or register a CRDT entity instance.
     *
     * @param  string|null                                      $name
     * @param  \NetFusion\Core\Data\Crdt\Contracts\CrdtInterface|null $instance
     * @return \NetFusion\Core\Data\Crdt\Contracts\CrdtInterface|\NetFusion\Core\Data\Replication\ReplicationManager
     */
    function crdt(?string $name = null, ?\NetFusion\Core\Data\Crdt\Contracts\CrdtInterface $instance = null): mixed
    {
        $manager = replication();
        if ($name !== null) {
            return $manager->crdt($name, $instance);
        }
        return $manager;
    }
}

if (!function_exists('vector_clock')) {
    /**
     * Create a new VectorClock instance.
     *
     * @param  array<string, int> $initial
     * @return \NetFusion\Core\Data\Crdt\VectorClock
     */
    function vector_clock(array $initial = []): \NetFusion\Core\Data\Crdt\VectorClock
    {
        return new \NetFusion\Core\Data\Crdt\VectorClock($initial);
    }
}

if (!function_exists('collab')) {
    /**
     * Get the CollabManager instance or a DocumentSession.
     *
     * @param  string|null $docId
     * @return \NetFusion\Core\Collaboration\CollabManager|\NetFusion\Core\Collaboration\Document\DocumentSession
     */
    function collab(?string $docId = null): mixed
    {
        /** @var \NetFusion\Core\Collaboration\CollabManager $manager */
        $manager = app('collab');
        if ($docId !== null) {
            return $manager->session($docId);
        }
        return $manager;
    }
}

if (!function_exists('ot_operation')) {
    /**
     * Create a new TextOperation instance.
     *
     * @param  array<int, int|string> $ops
     * @return \NetFusion\Core\Collaboration\Ot\TextOperation
     */
    function ot_operation(array $ops = []): \NetFusion\Core\Collaboration\Ot\TextOperation
    {
        return new \NetFusion\Core\Collaboration\Ot\TextOperation($ops);
    }
}

if (!function_exists('webrtc_signal')) {
    /**
     * Create a WebRTC SignalingMessage instance.
     *
     * @param  string      $type
     * @param  string      $roomId
     * @param  string      $senderId
     * @param  string|null $targetId
     * @param  array|null  $sdp
     * @param  array|null  $candidate
     * @param  array       $payload
     * @return \NetFusion\Core\Collaboration\WebRtc\SignalingMessage
     */
    function webrtc_signal(
        string $type,
        string $roomId,
        string $senderId,
        ?string $targetId = null,
        ?array $sdp = null,
        ?array $candidate = null,
        array $payload = []
    ): \NetFusion\Core\Collaboration\WebRtc\SignalingMessage {
        return \NetFusion\Core\Collaboration\WebRtc\SignalingMessage::create(
            type: $type,
            roomId: $roomId,
            senderId: $senderId,
            targetId: $targetId,
            sdp: $sdp,
            candidate: $candidate,
            payload: $payload
        );
    }
}

if (!function_exists('tsdb')) {
    /**
     * Get the TimeSeriesManager instance or execute a query.
     *
     * @param  string|null $metric
     * @param  float|null  $from
     * @param  float|null  $to
     * @param  array       $tags
     * @return \NetFusion\Core\Data\TimeSeries\TimeSeriesManager|\NetFusion\Core\Data\TimeSeries\TimeSeries
     */
    function tsdb(?string $metric = null, ?float $from = null, ?float $to = null, array $tags = []): mixed
    {
        /** @var \NetFusion\Core\Data\TimeSeries\TimeSeriesManager $manager */
        $manager = app('tsdb');
        if ($metric !== null) {
            $fromTs = $from ?? (microtime(true) - 3600);
            $toTs = $to ?? microtime(true);
            return $manager->query($metric, $fromTs, $toTs, $tags);
        }
        return $manager;
    }
}

if (!function_exists('tsdb_write')) {
    /**
     * Write a data point into the TSDB engine.
     *
     * @param  string     $metric
     * @param  float      $value
     * @param  array      $tags
     * @param  float|null $timestamp
     * @return bool
     */
    function tsdb_write(string $metric, float $value, array $tags = [], ?float $timestamp = null): bool
    {
        return tsdb()->write($metric, $value, $tags, $timestamp);
    }
}

if (!function_exists('anomaly_detect')) {
    /**
     * Run anomaly detection against a metric time-series or series instance.
     *
     * @param  string|\NetFusion\Core\Data\TimeSeries\TimeSeries $metricOrSeries
     * @param  string|null                                        $method
     * @param  array                                              $options
     * @return \NetFusion\Core\Data\TimeSeries\Anomaly\AnomalyResult
     */
    function anomaly_detect(
        string|\NetFusion\Core\Data\TimeSeries\TimeSeries $metricOrSeries,
        ?string $method = null,
        array $options = []
    ): \NetFusion\Core\Data\TimeSeries\Anomaly\AnomalyResult {
        $manager = tsdb();
        $series = is_string($metricOrSeries)
            ? $manager->query($metricOrSeries, microtime(true) - 86400, microtime(true))
            : $metricOrSeries;

        $detector = $manager->detector();
        return match ($method) {
            'iqr' => $detector->iqr($series, $options),
            'ewma' => $detector->ewma($series, $options),
            'ensemble' => $detector->ensemble($series, $options),
            default => $detector->zscore($series, $options),
        };
    }
}

if (!function_exists('vault')) {
    /**
     * Get the VaultManager instance.
     *
     * @return \NetFusion\Core\Security\Vault\VaultManager
     */
    function vault(): \NetFusion\Core\Security\Vault\VaultManager
    {
        return app('vault');
    }
}

if (!function_exists('secret')) {
    /**
     * Retrieve and decrypt a secret value from Vault.
     *
     * @param  string     $path
     * @param  mixed      $default
     * @param  int|null   $version
     * @return mixed
     */
    function secret(string $path, mixed $default = null, ?int $version = null): mixed
    {
        $val = vault()->get($path, $version);
        return $val !== null ? $val : $default;
    }
}

if (!function_exists('experiment')) {
    /**
     * Get the ExperimentManager instance or retrieve experiment by key.
     *
     * @param  string|null $key
     * @return \NetFusion\Core\Experimentation\ExperimentManager|\NetFusion\Core\Experimentation\Experiment|null
     */
    function experiment(?string $key = null): mixed
    {
        /** @var \NetFusion\Core\Experimentation\ExperimentManager $manager */
        $manager = app('experiment');

        if ($key !== null) {
            return $manager->getExperiment($key);
        }

        return $manager;
    }
}

if (!function_exists('ab_test')) {
    /**
     * Evaluate A/B experiment variant for the given context.
     *
     * @param  string $experimentKey
     * @param  mixed  $context
     * @param  string $default
     * @return string Variant name (e.g. 'control', 'treatment_a')
     */
    function ab_test(string $experimentKey, mixed $context = null, string $default = 'control'): string
    {
        /** @var \NetFusion\Core\Experimentation\ExperimentManager $manager */
        $manager = app('experiment');
        return $manager->variant($experimentKey, $context, $default);
    }
}

if (!function_exists('canary_check')) {
    /**
     * Evaluate canary rollout flag for context.
     *
     * @param  string $flagKey
     * @param  mixed  $context
     * @return bool
     */
    function canary_check(string $flagKey, mixed $context = null): bool
    {
        /** @var \NetFusion\Core\Experimentation\ExperimentManager $manager */
        $manager = app('experiment');
        return $manager->canary($flagKey, $context);
    }
}

if (!function_exists('track_conversion')) {
    /**
     * Track a conversion goal event.
     *
     * @param  string      $goal
     * @param  float       $value
     * @param  mixed       $context
     * @param  string|null $experimentKey
     * @param  array       $metadata
     * @return void
     */
    function track_conversion(
        string $goal,
        float $value = 1.0,
        mixed $context = null,
        ?string $experimentKey = null,
        array $metadata = []
    ): void {
        /** @var \NetFusion\Core\Experimentation\ExperimentManager $manager */
        $manager = app('experiment');
        $manager->trackGoal($goal, $value, $context, $experimentKey, $metadata);
    }
}

if (!function_exists('actor_system')) {
    /**
     * Get the ActorSystem instance.
     *
     * @return \NetFusion\Core\Actors\ActorSystem
     */
    function actor_system(): \NetFusion\Core\Actors\ActorSystem
    {
        return app('actors');
    }
}

if (!function_exists('actor_of')) {
    /**
     * Spawn an actor in the system.
     *
     * @param  string $actorClass
     * @param  string $name
     * @param  array  $args
     * @return \NetFusion\Core\Actors\Contracts\ActorRefInterface
     */
    function actor_of(string $actorClass, string $name = '', array $args = []): \NetFusion\Core\Actors\Contracts\ActorRefInterface
    {
        return actor_system()->actorOf($actorClass, $name, $args);
    }
}

if (!function_exists('actor_select')) {
    /**
     * Look up an actor reference by path.
     *
     * @param  string $path
     * @return \NetFusion\Core\Actors\Contracts\ActorRefInterface|null
     */
    function actor_select(string $path): ?\NetFusion\Core\Actors\Contracts\ActorRefInterface
    {
        return actor_system()->actorSelection($path);
    }
}

if (!function_exists('actor_tell')) {
    /**
     * Send a message to an actor by path (fire-and-forget).
     *
     * @param  string                                                 $path
     * @param  mixed                                                  $message
     * @param  \NetFusion\Core\Actors\Contracts\ActorRefInterface|null $sender
     * @return bool
     */
    function actor_tell(string $path, mixed $message, ?\NetFusion\Core\Actors\Contracts\ActorRefInterface $sender = null): bool
    {
        return actor_system()->tell($path, $message, $sender);
    }
}

if (!function_exists('actor_ask')) {
    /**
     * Send a message to an actor and wait for response.
     *
     * @param  string $path
     * @param  mixed  $message
     * @param  float  $timeout
     * @return mixed
     */
    function actor_ask(string $path, mixed $message, float $timeout = 5.0): mixed
    {
        return actor_system()->ask($path, $message, $timeout);
    }
}

if (!function_exists('workflow_engine')) {
    /**
     * Get the WorkflowEngine instance.
     *
     * @return \NetFusion\Core\Workflow\WorkflowEngine
     */
    function workflow_engine(): \NetFusion\Core\Workflow\WorkflowEngine
    {
        if (function_exists('app') && app()->has('workflow_engine')) {
            return app('workflow_engine');
        }

        static $engine = null;
        if ($engine === null) {
            $engine = new \NetFusion\Core\Workflow\WorkflowEngine();
        }
        return $engine;
    }
}

if (!function_exists('workflow_start')) {
    /**
     * Start a new workflow process instance.
     *
     * @param  string $definitionId
     * @param  array<string, mixed> $variables
     * @param  string|null $businessKey
     * @return \NetFusion\Core\Workflow\Execution\ProcessInstance
     */
    function workflow_start(string $definitionId, array $variables = [], ?string $businessKey = null): \NetFusion\Core\Workflow\Execution\ProcessInstance
    {
        return workflow_engine()->start($definitionId, $variables, $businessKey);
    }
}

if (!function_exists('workflow_signal')) {
    /**
     * Send an external signal to resume a waiting workflow activity.
     *
     * @param  string $instanceId
     * @param  string $activityId
     * @param  array<string, mixed> $payload
     * @return \NetFusion\Core\Workflow\Execution\ProcessInstance
     */
    function workflow_signal(string $instanceId, string $activityId, array $payload = []): \NetFusion\Core\Workflow\Execution\ProcessInstance
    {
        return workflow_engine()->signal($instanceId, $activityId, $payload);
    }
}

if (!function_exists('workflow_task_complete')) {
    /**
     * Complete a human user task in a workflow.
     *
     * @param  string $instanceId
     * @param  string $taskId
     * @param  array<string, mixed> $data
     * @param  string|null $completedBy
     * @return \NetFusion\Core\Workflow\Execution\ProcessInstance
     */
    function workflow_task_complete(string $instanceId, string $taskId, array $data = [], ?string $completedBy = null): \NetFusion\Core\Workflow\Execution\ProcessInstance
    {
        return workflow_engine()->completeUserTask($instanceId, $taskId, $data, $completedBy);
    }
}

if (!function_exists('imdg')) {
    /**
     * Get the IMDGManager instance or cluster.
     *
     * @param  string|null $cluster
     * @return \NetFusion\Core\IMDG\IMDGManager|\NetFusion\Core\IMDG\GridCluster
     */
    function imdg(?string $cluster = null): mixed
    {
        /** @var \NetFusion\Core\IMDG\IMDGManager $manager */
        $manager = null;
        if (function_exists('app') && app()->has('imdg')) {
            $manager = app('imdg');
        } else {
            static $inst = null;
            if ($inst === null) {
                $inst = new \NetFusion\Core\IMDG\IMDGManager();
            }
            $manager = $inst;
        }

        if ($cluster !== null) {
            return $manager->cluster($cluster);
        }

        return $manager;
    }
}

if (!function_exists('grid_map')) {
    /**
     * Get a distributed map by name from the IMDG.
     *
     * @param  string $name
     * @param  string|null $cluster
     * @return \NetFusion\Core\IMDG\Contracts\DistributedMapInterface
     */
    function grid_map(string $name, ?string $cluster = null): \NetFusion\Core\IMDG\Contracts\DistributedMapInterface
    {
        return imdg()->map($name, $cluster);
    }
}

if (!function_exists('grid_get')) {
    /**
     * Get a value from an IMDG distributed map.
     *
     * @param  string $map
     * @param  string $key
     * @param  mixed  $default
     * @param  string|null $cluster
     * @return mixed
     */
    function grid_get(string $map, string $key, mixed $default = null, ?string $cluster = null): mixed
    {
        return imdg()->get($map, $key, $default, $cluster);
    }
}

if (!function_exists('grid_put')) {
    /**
     * Put a value into an IMDG distributed map.
     *
     * @param  string $map
     * @param  string $key
     * @param  mixed  $value
     * @param  int    $ttlSeconds
     * @param  string|null $cluster
     * @return mixed Previous value
     */
    function grid_put(string $map, string $key, mixed $value, int $ttlSeconds = 0, ?string $cluster = null): mixed
    {
        return imdg()->put($map, $key, $value, $ttlSeconds, $cluster);
    }
}

if (!function_exists('cep')) {
    /**
     * Get the master Complex Event Processing (CEP) manager instance.
     *
     * Usage:
     *   cep()                       → CEPManager
     *   cep('iot_temperature')      → DataStream
     *
     * @param  string|null $stream
     * @return \NetFusion\Core\CEP\CEPManager|\NetFusion\Core\CEP\Stream\DataStream
     */
    function cep(?string $stream = null): mixed
    {
        /** @var \NetFusion\Core\CEP\CEPManager $manager */
        $manager = function_exists('app') && app()->has('cep')
            ? app('cep')
            : (function_exists('app') ? app()->singleton('cep', fn() => new \NetFusion\Core\CEP\CEPManager()) : new \NetFusion\Core\CEP\CEPManager());

        if ($stream !== null) {
            return $manager->stream($stream);
        }

        return $manager;
    }
}

if (!function_exists('cep_event')) {
    /**
     * Create a new CEP event instance.
     *
     * @param  string $type
     * @param  array  $payload
     * @param  float|null $timestamp
     * @return \NetFusion\Core\CEP\Event
     */
    function cep_event(string $type, array $payload = [], ?float $timestamp = null): \NetFusion\Core\CEP\Event
    {
        return new \NetFusion\Core\CEP\Event($type, $payload, $timestamp);
    }
}

if (!function_exists('cep_ingest')) {
    /**
     * Ingest an event or payload into the CEP engine.
     *
     * @param  \NetFusion\Core\CEP\Contracts\EventInterface|array $event
     * @param  string|null $type
     * @return array
     */
    function cep_ingest(\NetFusion\Core\CEP\Contracts\EventInterface|array $event, ?string $type = null): array
    {
        return cep()->ingest($event, $type);
    }
}

if (!function_exists('cep_rule')) {
    /**
     * Create and build a new CEP rule.
     *
     * @param  string $name
     * @return \NetFusion\Core\CEP\RuleBuilder
     */
    function cep_rule(string $name): \NetFusion\Core\CEP\RuleBuilder
    {
        return cep()->rule($name);
    }
}

if (!function_exists('mesh')) {
    /**
     * Get the master ServiceMeshManager instance or dispatch a mesh call.
     *
     * Usage:
     *   mesh()                                                       → ServiceMeshManager
     *   mesh('billing-service', '/charges', 'POST', [], $payload)   → MeshResponse
     *
     * @param  string|null $service
     * @param  string      $path
     * @param  string      $method
     * @param  array       $headers
     * @param  mixed       $body
     * @return \NetFusion\Core\ServiceMesh\ServiceMeshManager|\NetFusion\Core\ServiceMesh\Proxy\MeshResponse
     */
    function mesh(
        ?string $service = null,
        string $path = '/',
        string $method = 'GET',
        array $headers = [],
        mixed $body = null
    ): mixed {
        /** @var \NetFusion\Core\ServiceMesh\ServiceMeshManager $manager */
        $manager = function_exists('app') && app()->has('mesh')
            ? app('mesh')
            : (function_exists('app') ? app()->singleton('mesh', fn() => new \NetFusion\Core\ServiceMesh\ServiceMeshManager()) : new \NetFusion\Core\ServiceMesh\ServiceMeshManager());

        if ($service !== null) {
            return $manager->call($service, $path, $method, $headers, $body);
        }

        return $manager;
    }
}

if (!function_exists('mesh_proxy')) {
    /**
     * Get or create a sidecar proxy for a local service.
     *
     * @param  string|null $serviceName
     * @return \NetFusion\Core\ServiceMesh\Proxy\SidecarProxy
     */
    function mesh_proxy(?string $serviceName = null): \NetFusion\Core\ServiceMesh\Proxy\SidecarProxy
    {
        return mesh()->proxy($serviceName);
    }
}

if (!function_exists('mesh_route')) {
    /**
     * Create a new TrafficRoute definition.
     *
     * @param  string $serviceName
     * @param  string $name
     * @return \NetFusion\Core\ServiceMesh\Routing\TrafficRoute
     */
    function mesh_route(string $serviceName, string $name = ''): \NetFusion\Core\ServiceMesh\Routing\TrafficRoute
    {
        return \NetFusion\Core\ServiceMesh\Routing\TrafficRoute::to($serviceName, $name);
    }
}

if (!function_exists('spiffe_id')) {
    /**
     * Create or parse a SPIFFE service identity.
     *
     * @param  string $identity
     * @param  string $namespace
     * @param  string $trustDomain
     * @return \NetFusion\Core\ServiceMesh\Security\SpiffeId
     */
    function spiffe_id(string $identity, string $namespace = 'default', string $trustDomain = 'cluster.local'): \NetFusion\Core\ServiceMesh\Security\SpiffeId
    {
        return \NetFusion\Core\ServiceMesh\Security\SpiffeId::of($identity, $namespace, $trustDomain);
    }
}

if (!function_exists('rule_engine')) {
    /**
     * Get the RuleEngineManager singleton instance.
     *
     * @return \NetFusion\Core\RuleEngine\RuleEngineManager
     */
    function rule_engine(): \NetFusion\Core\RuleEngine\RuleEngineManager
    {
        return app('rule_engine');
    }
}

if (!function_exists('fact')) {
    /**
     * Create a new Fact instance or assert into working memory.
     *
     * @param  string      $type
     * @param  array       $attributes
     * @param  string|null $id
     * @return \NetFusion\Core\RuleEngine\Fact\Fact
     */
    function fact(string $type, array $attributes = [], ?string $id = null): \NetFusion\Core\RuleEngine\Fact\Fact
    {
        return \NetFusion\Core\RuleEngine\Fact\Fact::of($type, $attributes, $id);
    }
}

if (!function_exists('rule')) {
    /**
     * Create a new fluent RuleBuilder definition.
     *
     * @param  string $name
     * @return \NetFusion\Core\RuleEngine\DSL\RuleBuilder
     */
    function rule(string $name): \NetFusion\Core\RuleEngine\DSL\RuleBuilder
    {
        return \NetFusion\Core\RuleEngine\DSL\RuleBuilder::name($name);
    }
}

if (!function_exists('decision_table')) {
    /**
     * Create a new fluent DecisionTableBuilder definition.
     *
     * @param  string $name
     * @return \NetFusion\Core\RuleEngine\DSL\DecisionTableBuilder
     */
    function decision_table(string $name): \NetFusion\Core\RuleEngine\DSL\DecisionTableBuilder
    {
        return \NetFusion\Core\RuleEngine\DSL\DecisionTableBuilder::name($name);
    }
}

if (!function_exists('feel')) {
    /**
     * Evaluate a FEEL expression with an optional context.
     *
     * @param  string $expression
     * @param  array  $context
     * @return mixed
     */
    function feel(string $expression, array $context = []): mixed
    {
        /** @var \NetFusion\Core\RuleEngine\RuleEngineManager $manager */
        $manager = app('rule_engine');
        return $manager->getFeelEvaluator()->evaluate($expression, $context);
    }
}

if (!function_exists('vector')) {
    /**
     * Get the VectorEngineManager instance or search a collection.
     *
     * @param  string|null $collectionName
     * @param  string|\NetFusion\Core\Vector\Contracts\VectorInterface|null $query
     * @param  int $k
     * @param  array $options
     * @return \NetFusion\Core\Vector\VectorEngineManager|array
     */
    function vector(?string $collectionName = null, string|\NetFusion\Core\Vector\Contracts\VectorInterface|null $query = null, int $k = 10, array $options = []): mixed
    {
        /** @var \NetFusion\Core\Vector\VectorEngineManager $manager */
        $manager = app('vector');

        if ($collectionName !== null && $query !== null) {
            return $manager->search($collectionName, $query, $k, $options);
        }

        if ($collectionName !== null) {
            return $manager->collection($collectionName);
        }

        return $manager;
    }
}

if (!function_exists('vector_collection')) {
    /**
     * Get or create a VectorCollection instance.
     *
     * @param  string $name
     * @param  int|null $dimension
     * @param  \NetFusion\Core\Vector\Math\DistanceMetric|null $metric
     * @return \NetFusion\Core\Vector\VectorCollection
     */
    function vector_collection(string $name, ?int $dimension = null, ?\NetFusion\Core\Vector\Math\DistanceMetric $metric = null): \NetFusion\Core\Vector\VectorCollection
    {
        /** @var \NetFusion\Core\Vector\VectorEngineManager $manager */
        $manager = app('vector');
        return $manager->collection($name, $dimension, $metric);
    }
}

if (!function_exists('embed')) {
    /**
     * Generate dense vector embedding for text.
     *
     * @param  string $text
     * @param  string|null $driver
     * @return \NetFusion\Core\Vector\Contracts\VectorInterface
     */
    function embed(string $text, ?string $driver = null): \NetFusion\Core\Vector\Contracts\VectorInterface
    {
        /** @var \NetFusion\Core\Vector\VectorEngineManager $manager */
        $manager = app('vector');
        return $manager->embed($text, $driver);
    }
}

if (!function_exists('rag')) {
    /**
     * Query knowledge base via RAG pipeline.
     *
     * @param  string|null $query
     * @param  string|null $collectionName
     * @param  array $options
     * @return \NetFusion\Core\Vector\RAG\RAGPipeline|\NetFusion\Core\Vector\RAG\RAGResponse
     */
    function rag(?string $query = null, ?string $collectionName = null, array $options = []): mixed
    {
        /** @var \NetFusion\Core\Vector\VectorEngineManager $manager */
        $manager = app('vector');
        $pipeline = $manager->rag();

        if ($query !== null && $collectionName !== null) {
            return $pipeline->query($query, $collectionName, $options);
        }

        return $pipeline;
    }
}

if (!function_exists('waf')) {
    /**
     * Get the WAFManager instance or inspect a request context.
     *
     * @return \NetFusion\Core\WAF\WAFManager
     */
    function waf(): \NetFusion\Core\WAF\WAFManager
    {
        /** @var \NetFusion\Core\WAF\WAFManager $manager */
        return app('waf');
    }
}

if (!function_exists('waf_check')) {
    /**
     * Inspect a quick request payload through WAF.
     *
     * @param  string $ip
     * @param  string $method
     * @param  string $uri
     * @param  array  $payload
     * @return \NetFusion\Core\WAF\IP\ThreatContext
     */
    function waf_check(string $ip, string $method, string $uri, array $payload = []): \NetFusion\Core\WAF\IP\ThreatContext
    {
        /** @var \NetFusion\Core\WAF\WAFManager $manager */
        $manager = app('waf');
        return $manager->inspect(
            ip: $ip,
            method: $method,
            uri: $uri,
            query: $payload,
            body: $payload
        );
    }
}

if (!function_exists('waf_block_ip')) {
    /**
     * Block an IP address via WAF.
     *
     * @param  string $ip
     * @param  string $reason
     * @param  int    $ttl
     * @return void
     */
    function waf_block_ip(string $ip, string $reason = 'Manual Block', int $ttl = 86400): void
    {
        /** @var \NetFusion\Core\WAF\WAFManager $manager */
        $manager = app('waf');
        $manager->blockIp($ip, $reason, $ttl);
    }
}

if (!function_exists('waf_is_blocked')) {
    /**
     * Check if an IP address is blocked by WAF.
     *
     * @param  string $ip
     * @return bool
     */
    function waf_is_blocked(string $ip): bool
    {
        /** @var \NetFusion\Core\WAF\WAFManager $manager */
        $manager = app('waf');
        return $manager->isBlocked($ip);
    }
}

if (!function_exists('datawarehouse')) {
    /**
     * Get the DataWarehouseManager instance.
     *
     * @return \NetFusion\Core\DataWarehouse\DataWarehouseManager
     */
    function datawarehouse(): \NetFusion\Core\DataWarehouse\DataWarehouseManager
    {
        return app('datawarehouse');
    }
}

if (!function_exists('olap_cube')) {
    /**
     * Get a registered OLAP Cube instance.
     *
     * @param  string $name
     * @return \NetFusion\Core\DataWarehouse\Cube\OLAPCube|null
     */
    function olap_cube(string $name): ?\NetFusion\Core\DataWarehouse\Cube\OLAPCube
    {
        return datawarehouse()->getCube($name);
    }
}

if (!function_exists('bi_pivot')) {
    /**
     * Create a BI Pivot Table query on an OLAP Cube.
     *
     * @param  string $cubeName
     * @param  array  $rows
     * @param  array  $columns
     * @param  array  $measures
     * @return \NetFusion\Core\DataWarehouse\BI\PivotTable
     */
    function bi_pivot(string $cubeName, array $rows = [], array $columns = [], array $measures = []): \NetFusion\Core\DataWarehouse\BI\PivotTable
    {
        return datawarehouse()->pivot($cubeName, $rows, $columns, $measures);
    }
}

if (!function_exists('payment')) {
    /**
     * Get the PaymentOrchestrator instance.
     *
     * @return \NetFusion\Core\Payment\PaymentOrchestrator
     */
    function payment(): \NetFusion\Core\Payment\PaymentOrchestrator
    {
        return app('payment');
    }
}

if (!function_exists('payment_charge')) {
    /**
     * Execute a quick payment charge.
     *
     * @param  float       $amount
     * @param  string      $currency
     * @param  string|null $token
     * @param  array       $metadata
     * @return \NetFusion\Core\Payment\Models\PaymentResult
     */
    function payment_charge(float $amount, string $currency = 'USD', ?string $token = null, array $metadata = []): \NetFusion\Core\Payment\Models\PaymentResult
    {
        $intent = \NetFusion\Core\Payment\Models\PaymentIntent::create($amount, $currency);
        if ($token !== null) {
            $intent->withPaymentMethodToken($token);
        }
        if (!empty($metadata)) {
            $intent->withMetadata($metadata);
        }
        return payment()->charge($intent);
    }
}

if (!function_exists('payment_tokenize')) {
    /**
     * Tokenize raw card/wallet data via PCI-DSS Vault.
     *
     * @param  \NetFusion\Core\Payment\Models\PaymentMethod $method
     * @param  string $customerId
     * @return string
     */
    function payment_tokenize(\NetFusion\Core\Payment\Models\PaymentMethod $method, string $customerId = ''): string
    {
        return payment()->tokenize($method, $customerId);
    }
}

if (!function_exists('payment_split')) {
    /**
     * Create a SplitRule for marketplace settlements.
     *
     * @param  string $vendorId
     * @param  float  $percentage
     * @param  float  $fixedFee
     * @param  int    $delayDays
     * @return \NetFusion\Core\Payment\Models\SplitRule
     */
    function payment_split(string $vendorId, float $percentage, float $fixedFee = 0.0, int $delayDays = 0): \NetFusion\Core\Payment\Models\SplitRule
    {
        return new \NetFusion\Core\Payment\Models\SplitRule($vendorId, $percentage, $fixedFee, $delayDays);
    }
}


if (!function_exists('search_query')) {
    /**
     * Execute a search query against a specific search index.
     *
     * @param  string $index
     * @param  string $queryString
     * @param  array  $options
     * @return \NetFusion\Core\Search\Models\SearchResult
     */
    function search_query(string $index, string $queryString, array $options = []): \NetFusion\Core\Search\Models\SearchResult
    {
        $query = new \NetFusion\Core\Search\Models\SearchQuery(
            queryString: $queryString,
            vector: $options['vector'] ?? null,
            filters: $options['filters'] ?? [],
            facets: $options['facets'] ?? [],
            boostFields: $options['boost'] ?? [],
            limit: $options['limit'] ?? 20,
            offset: $options['offset'] ?? 0,
            sortField: $options['sort_field'] ?? null,
            sortOrder: $options['sort_order'] ?? 'desc',
            fuzzy: $options['fuzzy'] ?? false,
            hybridWeight: $options['hybrid_weight'] ?? 0.5
        );
        return search()->search($index, $query);
    }
}

if (!function_exists('search_index')) {
    /**
     * Index documents into a search index.
     *
     * @param  string $index
     * @param  array<\NetFusion\Core\Search\Models\Document>|array<array<string, mixed>> $documents
     * @return \NetFusion\Core\Search\Contracts\IndexInterface
     */
    function search_index(string $index, array $documents): \NetFusion\Core\Search\Contracts\IndexInterface
    {
        $idx = search($index);
        foreach ($documents as $doc) {
            if ($doc instanceof \NetFusion\Core\Search\Models\Document) {
                search()->indexDocument($index, $doc);
            } elseif (is_array($doc) && isset($doc['id'])) {
                $docObj = new \NetFusion\Core\Search\Models\Document(
                    id: (string)$doc['id'],
                    fields: $doc['fields'] ?? $doc,
                    vector: $doc['vector'] ?? null,
                    boost: (float)($doc['boost'] ?? 1.0)
                );
                search()->indexDocument($index, $docObj);
            }
        }
        return $idx;
    }
}

if (!function_exists('search_suggest')) {
    /**
     * Get real-time auto-complete prefix suggestions from index.
     *
     * @param  string $index
     * @param  string $prefix
     * @param  int    $limit
     * @return array<\NetFusion\Core\Search\Models\Suggestion>
     */
    function search_suggest(string $index, string $prefix, int $limit = 5): array
    {
        return search()->suggest($index, $prefix, $limit);
    }
}

if (!function_exists('graph')) {
    /**
     * Get the GraphManager instance or resolve a specific named graph store.
     *
     * Usage:
     *   graph()                     → GraphManager
     *   graph('knowledge')          → GraphStoreInterface
     *
     * @param  string|null $name
     * @return \NetFusion\Core\Graph\GraphManager|\NetFusion\Core\Graph\Contracts\GraphStoreInterface
     */
    function graph(?string $name = null): mixed
    {
        /** @var \NetFusion\Core\Graph\GraphManager $manager */
        $manager = app()->has('graph') ? app('graph') : app(\NetFusion\Core\Graph\GraphManager::class);
        if ($name !== null) {
            return $manager->graph($name);
        }
        return $manager;
    }
}

if (!function_exists('graph_query')) {
    /**
     * Execute a Cypher query against a graph.
     *
     * Usage:
     *   graph_query('MATCH (u:User) RETURN u.name', [], 'default') → QueryResult
     *
     * @param  string      $cypher
     * @param  array       $parameters
     * @param  string|null $graphName
     * @return \NetFusion\Core\Graph\Models\QueryResult
     */
    function graph_query(string $cypher, array $parameters = [], ?string $graphName = null): \NetFusion\Core\Graph\Models\QueryResult
    {
        return graph()->query($cypher, $parameters, $graphName);
    }
}

if (!function_exists('cypher')) {
    /**
     * Alias for graph_query.
     *
     * @param  string      $cypher
     * @param  array       $parameters
     * @param  string|null $graphName
     * @return \NetFusion\Core\Graph\Models\QueryResult
     */
    function cypher(string $cypher, array $parameters = [], ?string $graphName = null): \NetFusion\Core\Graph\Models\QueryResult
    {
        return graph()->query($cypher, $parameters, $graphName);
    }
}

if (!function_exists('shortest_path')) {
    /**
     * Find the shortest path between two nodes in a graph.
     *
     * @param  string      $startId
     * @param  string      $targetId
     * @param  string|null $relType
     * @param  string|null $graphName
     * @return \NetFusion\Core\Graph\Models\Path|null
     */
    function shortest_path(string $startId, string $targetId, ?string $relType = null, ?string $graphName = null): ?\NetFusion\Core\Graph\Models\Path
    {
        return graph()->shortestPath($startId, $targetId, $relType, $graphName);
    }
}

if (!function_exists('pagerank')) {
    /**
     * Compute PageRank scores across graph nodes.
     *
     * @param  string|null $graphName
     * @param  float       $damping
     * @param  int         $maxIter
     * @return array<string, float>
     */
    function pagerank(?string $graphName = null, float $damping = 0.85, int $maxIter = 100): array
    {
        return graph()->pageRank($graphName, $damping, $maxIter);
    }
}

if (!function_exists('scheduler')) {
    /**
     * Get the SchedulerManager instance.
     *
     * @return \NetFusion\Core\Scheduling\SchedulerManager
     */
    function scheduler(): \NetFusion\Core\Scheduling\SchedulerManager
    {
        if (function_exists('app')) {
            try {
                $app = app();
                if ($app->has('scheduler')) {
                    return $app->make('scheduler');
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        static $instance = null;
        if ($instance === null) {
            $instance = new \NetFusion\Core\Scheduling\SchedulerManager();
        }
        return $instance;
    }
}

if (!function_exists('schedule_task')) {
    /**
     * Fluent shortcut to register a scheduled task.
     *
     * @param  string $expression Cron expression
     * @param  mixed  $callback   Callable, command, or shell command
     * @param  string|null $name  Task name
     * @return \NetFusion\Core\Scheduling\Models\ScheduledTask
     */
    function schedule_task(string $expression, mixed $callback, ?string $name = null): \NetFusion\Core\Scheduling\Models\ScheduledTask
    {
        return scheduler()->call($callback, $name)->cron($expression);
    }
}

if (!function_exists('schedule_dag')) {
    /**
     * Create a new DAG workflow builder.
     *
     * @param  string $name DAG pipeline name
     * @return \NetFusion\Core\Scheduling\DAG\DAGBuilder
     */
    function schedule_dag(string $name = 'workflow'): \NetFusion\Core\Scheduling\DAG\DAGBuilder
    {
        return scheduler()->dag($name);
    }
}

if (!function_exists('cron_match')) {
    /**
     * Check if a cron expression matches a given datetime.
     *
     * @param  string $expression
     * @param  \DateTimeInterface|null $dateTime
     * @param  string $timezone
     * @return bool
     */
    function cron_match(string $expression, ?\DateTimeInterface $dateTime = null, string $timezone = 'UTC'): bool
    {
        return scheduler()->getCronParser()->isDue($expression, $dateTime, $timezone);
    }
}

if (!function_exists('gateway')) {
    /**
     * Returns the GatewayManager instance from the container.
     * Falls back to a new in-memory GatewayManager when the container
     * is not available (e.g. during unit tests).
     *
     * @return \NetFusion\Core\Gateway\GatewayManager
     */
    function gateway(): \NetFusion\Core\Gateway\GatewayManager
    {
        static $instance = null;

        try {
            $resolved = app(\NetFusion\Core\Gateway\GatewayManager::class);
            if ($resolved instanceof \NetFusion\Core\Gateway\GatewayManager) {
                return $resolved;
            }
        } catch (\Throwable) {
            // Container not bootstrapped — use singleton fallback
        }

        if ($instance === null) {
            $instance = \NetFusion\Core\Gateway\GatewayManager::create();
        }

        return $instance;
    }
}

if (!function_exists('proxy_pass')) {
    /**
     * Forwards an incoming GatewayRequest through the gateway and returns
     * the resulting GatewayResponse. Optionally sends the response.
     *
     * @param  \NetFusion\Core\Gateway\Models\GatewayRequest $request
     * @param  bool $send  If true, calls $response->send() and exits
     * @return \NetFusion\Core\Gateway\Models\GatewayResponse
     */
    function proxy_pass(
        \NetFusion\Core\Gateway\Models\GatewayRequest $request,
        bool $send = false
    ): \NetFusion\Core\Gateway\Models\GatewayResponse {
        $response = gateway()->handle($request);
        if ($send) {
            $response->send();
            exit(0);
        }
        return $response;
    }
}

if (!function_exists('api_gateway_route')) {
    /**
     * Registers a new API gateway route from a path prefix and one or more
     * upstream URLs using the global GatewayManager.
     *
     * Example:
     *   api_gateway_route('/api/v1', ['http://backend1:8080', 'http://backend2:8080'], 'api_v1');
     *
     * @param  string   $pathPrefix   Inbound path prefix to match
     * @param  string[] $upstreamUrls Backend URL(s)
     * @param  string   $id           Route identifier
     * @param  string   $hostname     Hostname constraint (default '*' = any)
     * @return \NetFusion\Core\Gateway\Models\Route
     */
    function api_gateway_route(
        string $pathPrefix,
        array $upstreamUrls,
        string $id = '',
        string $hostname = '*'
    ): \NetFusion\Core\Gateway\Models\Route {
        if ($id === '') {
            $id = 'route_' . ltrim(str_replace('/', '_', $pathPrefix), '_');
        }

        $pool = new \NetFusion\Core\Gateway\Models\UpstreamPool($id . '_pool');
        foreach ($upstreamUrls as $url) {
            $pool->add(\NetFusion\Core\Gateway\Models\Upstream::fromUrl($url));
        }

        return gateway()->route($id, $pathPrefix, $pool, $hostname);
    }
}

// =============================================================================
// Phase 68A: Enterprise Double-Entry Accounting Engine Helpers
// =============================================================================

if (!function_exists('accounting')) {
    /**
     * Resolve the AccountingManager singleton.
     * Seeds the default Chart of Accounts on first call.
     *
     * @return \NetFusion\Core\Accounting\AccountingManager
     */
    function accounting(): \NetFusion\Core\Accounting\AccountingManager
    {
        static $instance = null;
        if ($instance === null) {
            try {
                $instance = app(\NetFusion\Core\Accounting\AccountingManager::class);
            } catch (\Throwable) {
                $instance = \NetFusion\Core\Accounting\AccountingManager::create();
                $instance->getCoa()->seedDefaults();
            }
        }
        return $instance;
    }
}

if (!function_exists('journal_entry')) {
    /**
     * Create a new JournalEntry builder.
     *
     * @param  string $date        ISO date (Y-m-d)
     * @param  string $description Human-readable description
     * @param  string $reference   Optional reference number
     * @return \NetFusion\Core\Accounting\Models\JournalEntry
     */
    function journal_entry(
        string $date,
        string $description,
        string $reference = ''
    ): \NetFusion\Core\Accounting\Models\JournalEntry {
        return \NetFusion\Core\Accounting\Models\JournalEntry::create($date, $description, $reference);
    }
}

if (!function_exists('ledger_balance')) {
    /**
     * Get the current balance of an account by its code.
     *
     * @param  string $accountCode Account code (e.g., "1001")
     * @return \NetFusion\Core\Accounting\Models\Money
     */
    function ledger_balance(string $accountCode): \NetFusion\Core\Accounting\Models\Money
    {
        return accounting()->balance($accountCode);
    }
}

// =============================================================================
// Phase 68B: Enterprise Push Notification Gateway Helpers
// =============================================================================

if (!function_exists('push_gateway')) {
    /**
     * Resolve the Push NotificationManager singleton.
     *
     * @return \NetFusion\Core\Notification\NotificationManager
     */
    function push_gateway(): \NetFusion\Core\Notification\NotificationManager
    {
        static $instance = null;
        if ($instance === null) {
            try {
                $instance = app(\NetFusion\Core\Notification\NotificationManager::class);
            } catch (\Throwable) {
                $instance = \NetFusion\Core\Notification\NotificationManager::create();
                // Auto-register the database channel as default
                $instance->addChannel(new \NetFusion\Core\Notification\Channels\DatabaseChannel());
            }
        }
        return $instance;
    }
}

if (!function_exists('push_notify')) {
    /**
     * Alias for push_gateway()
     *
     * @return \NetFusion\Core\Notification\NotificationManager
     */
    function push_notify(): \NetFusion\Core\Notification\NotificationManager
    {
        return push_gateway();
    }
}

if (!function_exists('push_to_topic')) {
    /**
     * Broadcast a push notification to all subscribers of a topic.
     *
     * @param  string $topic   Topic name
     * @param  \NetFusion\Core\Notification\Notification $notification
     * @return array<string, array> Results keyed by recipient
     */
    function push_to_topic(
        string $topic,
        \NetFusion\Core\Notification\Notification $notification
    ): array {
        return push_gateway()->broadcastToTopic($topic, $notification);
    }
}

if (!function_exists('notification_log')) {
    /**
     * Retrieve the global NotificationLog instance.
     *
     * @return \NetFusion\Core\Notification\NotificationLog
     */
    function notification_log(): \NetFusion\Core\Notification\NotificationLog
    {
        return push_gateway()->getLog();
    }
}

// =============================================================================
// Phase 68C: APM Engine Helpers
// =============================================================================

if (!function_exists('apm_engine')) {
    /**
     * Resolve the APM Engine ApmManager singleton.
     *
     * @return \NetFusion\Core\Apm\ApmManager
     */
    function apm_engine(): \NetFusion\Core\Apm\ApmManager
    {
        static $instance = null;
        if ($instance === null) {
            try {
                $instance = app(\NetFusion\Core\Apm\ApmManager::class);
            } catch (\Throwable) {
                $instance = \NetFusion\Core\Apm\ApmManager::create();
            }
        }
        return $instance;
    }
}

if (!function_exists('apm_monitor')) {
    /**
     * Alias for apm_engine()
     *
     * @return \NetFusion\Core\Apm\ApmManager
     */
    function apm_monitor(): \NetFusion\Core\Apm\ApmManager
    {
        return apm_engine();
    }
}

if (!function_exists('trace_span')) {
    /**
     * Execute a callable inside a named tracing span.
     * Returns the result of the callable.
     *
     * @template T
     * @param  string   $name     Operation name
     * @param  callable $callback fn(Span): T
     * @param  array    $tags     Additional span tags
     * @return T
     */
    function trace_span(string $name, callable $callback, array $tags = []): mixed
    {
        return apm_engine()->tracer()->trace($name, $callback, $tags);
    }
}

if (!function_exists('log_event')) {
    /**
     * Log a structured INFO event via the APM logger.
     *
     * @param string $message Log message (supports {placeholder} interpolation)
     * @param array  $context Contextual data
     */
    function log_event(string $message, array $context = []): void
    {
        apm_engine()->logger()->info($message, $context);
    }
}

if (!function_exists('apm_profile')) {
    /**
     * Create and start a new Profiler session.
     *
     * @param  string $name Profiler name / label
     * @return \NetFusion\Core\Apm\Profiling\Profiler
     */
    function apm_profile(string $name = 'request'): \NetFusion\Core\Apm\Profiling\Profiler
    {
        return apm_engine()->profile($name);
    }
}

// =============================================================================
// Phase 68D: Multi-Region Replication Helpers
// =============================================================================

if (!function_exists('crdt_replication')) {
    /**
     * Resolve the CRDT Multi-Region ReplicationManager singleton.
     *
     * @return \NetFusion\Core\Replication\ReplicationManager
     */
    function crdt_replication(): \NetFusion\Core\Replication\ReplicationManager
    {
        static $instance = null;
        if ($instance === null) {
            try {
                $instance = app(\NetFusion\Core\Replication\ReplicationManager::class);
            } catch (\Throwable) {
                $instance = \NetFusion\Core\Replication\ReplicationManager::create();
            }
        }
        return $instance;
    }
}

if (!function_exists('replication_engine')) {
    /**
     * Alias for crdt_replication()
     *
     * @return \NetFusion\Core\Replication\ReplicationManager
     */
    function replication_engine(): \NetFusion\Core\Replication\ReplicationManager
    {
        return crdt_replication();
    }
}

if (!function_exists('crdt_counter')) {
    /**
     * Get or increment a named PNCounter CRDT.
     * Passing $by > 0 increments, $by < 0 decrements. $by = 0 returns current value.
     *
     * @param  string $name Named counter key
     * @param  int    $by   Increment/decrement amount (0 = read-only)
     * @return int    Current counter value
     */
    function crdt_counter(string $name, int $by = 0): int
    {
        $mgr = crdt_replication();
        if ($by > 0) return $mgr->incrementPNCounter($name, $by);
        if ($by < 0) return $mgr->decrementPNCounter($name, abs($by));
        return $mgr->pnCounter($name)->value();
    }
}

if (!function_exists('crdt_set')) {
    /**
     * Interact with a named ORSet CRDT.
     *
     * @param  string $name    Set key
     * @param  mixed  $element Element to add/remove/check
     * @param  string $action  'add' | 'remove' | 'contains' | 'values'
     * @return mixed
     */
    function crdt_set(string $name, mixed $element = null, string $action = 'values'): mixed
    {
        $mgr = crdt_replication();
        return match ($action) {
            'add'      => ($mgr->addToSet($name, $element) ?? true),
            'remove'   => ($mgr->removeFromSet($name, $element) ?? true),
            'contains' => $mgr->orSet($name)->contains($element),
            default    => $mgr->orSet($name)->values(),
        };
    }
}

// =============================================================================
// Phase 68E: BPMN 2.0 Extension Helpers
// =============================================================================

if (!function_exists('dmn_evaluate')) {
    /**
     * Evaluate a registered DMN decision table by name.
     *
     * @param  string $tableName Name of registered decision table
     * @param  array  $context   Input context
     * @return array|null        Decision output(s) or null
     */
    function dmn_evaluate(string $tableName, array $context): array|null
    {
        static $evaluator = null;
        if ($evaluator === null) {
            try {
                $evaluator = app(\NetFusion\Core\Workflow\DMN\DmnEvaluator::class);
            } catch (\Throwable) {
                $evaluator = new \NetFusion\Core\Workflow\DMN\DmnEvaluator();
            }
        }
        return $evaluator->decide($tableName, $context);
    }
}

if (!function_exists('compensation_boundary')) {
    /**
     * Create a new CompensationCoordinator pre-populated with a handler.
     *
     * @param  string   $activityId     Activity to compensate
     * @param  \Closure $compensate     fn(array $output, array $context): array
     * @param  array    $activityOutput Output data from the completed activity
     * @return \NetFusion\Core\Workflow\Compensation\CompensationCoordinator
     */
    function compensation_boundary(
        string   $activityId,
        \Closure $compensate,
        array    $activityOutput = []
    ): \NetFusion\Core\Workflow\Compensation\CompensationCoordinator {
        $coordinator = new \NetFusion\Core\Workflow\Compensation\CompensationCoordinator();
        $coordinator->register(
            $activityId,
            new \NetFusion\Core\Workflow\Compensation\CompensationHandler(
                $activityId,
                $compensate,
                $activityOutput
            )
        );
        return $coordinator;
    }
}

// =============================================================================
// Phase 69: Enterprise Zero-Trust Service Mesh & SPIFFE/mTLS Helpers
// =============================================================================

if (!function_exists('service_mesh')) {
    /**
     * Resolve the ServiceMeshManager singleton.
     *
     * @return \NetFusion\Core\ServiceMesh\Mesh\ServiceMeshManager
     */
    function service_mesh(): \NetFusion\Core\ServiceMesh\Mesh\ServiceMeshManager
    {
        static $instance = null;
        if ($instance === null) {
            try {
                $instance = app(\NetFusion\Core\ServiceMesh\Mesh\ServiceMeshManager::class);
            } catch (\Throwable) {
                $instance = \NetFusion\Core\ServiceMesh\Mesh\ServiceMeshManager::create();
            }
        }
        return $instance;
    }
}

if (!function_exists('mesh_spiffe_id')) {
    /**
     * Create or parse a SpiffeId object.
     *
     * @param  string      $uriOrDomain Full URI or trust domain
     * @param  string|null $path        Optional path if first param is trust domain
     * @return \NetFusion\Core\ServiceMesh\Spiffe\SpiffeId
     */
    function mesh_spiffe_id(string $uriOrDomain, ?string $path = null): \NetFusion\Core\ServiceMesh\Spiffe\SpiffeId
    {
        if ($path !== null) {
            return \NetFusion\Core\ServiceMesh\Spiffe\SpiffeId::of($uriOrDomain, $path);
        }
        return new \NetFusion\Core\ServiceMesh\Spiffe\SpiffeId($uriOrDomain);
    }
}

if (!function_exists('mesh_policy')) {
    /**
     * Define or retrieve a zero-trust MeshPolicy for a target service.
     *
     * @param  string $serviceName Target microservice name
     * @return \NetFusion\Core\ServiceMesh\Policy\MeshPolicy
     */
    function mesh_policy(string $serviceName): \NetFusion\Core\ServiceMesh\Policy\MeshPolicy
    {
        return service_mesh()->definePolicy($serviceName);
    }
}

if (!function_exists('mtls_context')) {
    /**
     * Create an mTLS stream SSL context.
     *
     * @param  \NetFusion\Core\ServiceMesh\Tls\CertificatePair $cert
     * @param  string                                          $caPem
     * @param  string                                          $mode 'client' | 'server'
     * @return resource
     */
    function mtls_context(
        \NetFusion\Core\ServiceMesh\Tls\CertificatePair $cert,
        string $caPem,
        string $mode = 'client'
    ) {
        $contextObj = $mode === 'server'
            ? \NetFusion\Core\ServiceMesh\Tls\MtlsContext::forServer($cert, $caPem)
            : \NetFusion\Core\ServiceMesh\Tls\MtlsContext::forClient($cert, $caPem);

        return $contextObj->toStreamContext();
    }
}

// --------------------------------------------------------------------------
// Phase 70: Enterprise Post-Quantum Cryptography (PQC) Global Helpers
// --------------------------------------------------------------------------

if (!function_exists('pqc')) {
    /**
     * Get the Post-Quantum Cryptography Manager singleton instance.
     *
     * @return \NetFusion\Core\Security\Pqc\PqcManager
     */
    function pqc(): \NetFusion\Core\Security\Pqc\PqcManager
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new \NetFusion\Core\Security\Pqc\PqcManager();
        }
        return $instance;
    }
}

if (!function_exists('pqc_kem')) {
    /**
     * Get an ML-KEM Key Encapsulation Engine instance.
     *
     * @param string|null $parameterSet 'ml-kem-512', 'ml-kem-768', 'ml-kem-1024'
     * @return \NetFusion\Core\Security\Pqc\Kem\MlKemEngine
     */
    function pqc_kem(?string $parameterSet = null): \NetFusion\Core\Security\Pqc\Kem\MlKemEngine
    {
        return pqc()->kem($parameterSet);
    }
}

if (!function_exists('pqc_dsa')) {
    /**
     * Get an ML-DSA Digital Signature Engine instance.
     *
     * @param string|null $parameterSet 'ml-dsa-44', 'ml-dsa-65', 'ml-dsa-87'
     * @return \NetFusion\Core\Security\Pqc\Dsa\MlDsaEngine
     */
    function pqc_dsa(?string $parameterSet = null): \NetFusion\Core\Security\Pqc\Dsa\MlDsaEngine
    {
        return pqc()->dsa($parameterSet);
    }
}

if (!function_exists('pqc_hybrid')) {
    /**
     * Get the Hybrid Post-Quantum Key Exchange instance (X25519 + ML-KEM-768).
     *
     * @return \NetFusion\Core\Security\Pqc\Hybrid\HybridKeyExchange
     */
    function pqc_hybrid(): \NetFusion\Core\Security\Pqc\Hybrid\HybridKeyExchange
    {
        return pqc()->hybrid();
    }
}

if (!function_exists('pqc_vault')) {
    /**
     * Get the Quantum-Resistant Envelope Encryption Vault.
     *
     * @return \NetFusion\Core\Security\Pqc\Vault\PqcEnvelopeVault
     */
    function pqc_vault(): \NetFusion\Core\Security\Pqc\Vault\PqcEnvelopeVault
    {
        return pqc()->vault();
    }
}

if (!function_exists('pqc_encrypt')) {
    /**
     * Encrypt sensitive data using the Quantum-Resistant Envelope Vault.
     *
     * @param string $data
     * @param string|null $keyId
     * @param string $aad
     * @return string
     */
    function pqc_encrypt(string $data, ?string $keyId = null, string $aad = ''): string
    {
        return pqc_vault()->encrypt($data, $keyId, $aad);
    }
}

if (!function_exists('pqc_decrypt')) {
    /**
     * Decrypt data from a Quantum-Resistant envelope.
     *
     * @param string $envelopeJson
     * @param string $aad
     * @return string
     */
    function pqc_decrypt(string $envelopeJson, string $aad = ''): string
    {
        return pqc_vault()->decrypt($envelopeJson, null, $aad);
    }
}

// --------------------------------------------------------------------------
// Phase 71: Enterprise Autonomous Disaster Recovery (DR) Global Helpers
// --------------------------------------------------------------------------

if (!function_exists('dr')) {
    /**
     * Get the Disaster Recovery FailoverManager singleton instance.
     *
     * @return \NetFusion\Core\DisasterRecovery\FailoverManager
     */
    function dr(): \NetFusion\Core\DisasterRecovery\FailoverManager
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new \NetFusion\Core\DisasterRecovery\FailoverManager();
        }
        return $instance;
    }
}

if (!function_exists('disaster_recovery')) {
    /**
     * Alias for dr() helper.
     *
     * @return \NetFusion\Core\DisasterRecovery\FailoverManager
     */
    function disaster_recovery(): \NetFusion\Core\DisasterRecovery\FailoverManager
    {
        return dr();
    }
}

if (!function_exists('dr_cdp')) {
    /**
     * Get the Continuous Data Protection (CDP) engine instance.
     *
     * @return \NetFusion\Core\DisasterRecovery\Cdp\CdpEngine
     */
    function dr_cdp(): \NetFusion\Core\DisasterRecovery\Cdp\CdpEngine
    {
        return dr()->getCdp();
    }
}

if (!function_exists('dr_quorum')) {
    /**
     * Get the DR Quorum Consensus Manager instance.
     *
     * @return \NetFusion\Core\DisasterRecovery\Cluster\QuorumManager
     */
    function dr_quorum(): \NetFusion\Core\DisasterRecovery\Cluster\QuorumManager
    {
        return dr()->getQuorum();
    }
}

if (!function_exists('dr_router')) {
    /**
     * Get the Multi-Cloud Traffic Router instance.
     *
     * @return \NetFusion\Core\DisasterRecovery\Routing\TrafficRouter
     */
    function dr_router(): \NetFusion\Core\DisasterRecovery\Routing\TrafficRouter
    {
        return dr()->getRouter();
    }
}

if (!function_exists('dr_failover')) {
    /**
     * Execute cross-region failover to a target standby node.
     *
     * @param string $targetNodeId
     * @param bool $force
     * @return array
     */
    function dr_failover(string $targetNodeId, bool $force = false): array
    {
        return dr()->promoteStandby($targetNodeId, $force);
    }
}

if (!function_exists('dr_drill')) {
    /**
     * Run a non-disruptive DR drill simulation against a target region.
     *
     * @param string $targetRegion
     * @return \NetFusion\Core\DisasterRecovery\Drill\DrillExecution
     */
    function dr_drill(string $targetRegion): \NetFusion\Core\DisasterRecovery\Drill\DrillExecution
    {
        return dr()->runDrill($targetRegion);
    }
}

if (!function_exists('chaos')) {
    /**
     * Access the Chaos Engineering Manager facade.
     *
     * @param array|null $config
     * @return \NetFusion\Core\Chaos\ChaosManager
     */
    function chaos(?array $config = null): \NetFusion\Core\Chaos\ChaosManager
    {
        return \NetFusion\Core\Chaos\ChaosManager::getInstance($config ?? []);
    }
}

if (!function_exists('chaos_experiment')) {
    /**
     * Create a new Chaos Experiment definition.
     *
     * @param string $name
     * @param string $description
     * @return \NetFusion\Core\Chaos\Experiment\ChaosExperiment
     */
    function chaos_experiment(string $name, string $description = ''): \NetFusion\Core\Chaos\Experiment\ChaosExperiment
    {
        return new \NetFusion\Core\Chaos\Experiment\ChaosExperiment($name, $description);
    }
}

if (!function_exists('inject_fault')) {
    /**
     * Manually invoke a registered chaos fault injector.
     *
     * @param string $injectorName
     * @param array $context
     * @return mixed
     */
    function inject_fault(string $injectorName, array $context = []): mixed
    {
        return chaos()->inject($injectorName, $context);
    }
}

if (!function_exists('chaos_kill_switch')) {
    /**
     * Access the global Chaos Emergency Kill Switch.
     *
     * @return \NetFusion\Core\Chaos\Guardrails\EmergencyKillSwitch
     */
    function chaos_kill_switch(): \NetFusion\Core\Chaos\Guardrails\EmergencyKillSwitch
    {
        return \NetFusion\Core\Chaos\Guardrails\EmergencyKillSwitch::getInstance();
    }
}

if (!function_exists('otel')) {
    /**
     * Access the OpenTelemetry Observability Manager facade.
     *
     * @param array|null $config
     * @return \NetFusion\Core\Observability\ObservabilityManager
     */
    function otel(?array $config = null): \NetFusion\Core\Observability\ObservabilityManager
    {
        return \NetFusion\Core\Observability\ObservabilityManager::getInstance($config ?? []);
    }
}

if (!function_exists('otel_tracer')) {
    /**
     * Access the active OpenTelemetry Tracer.
     *
     * @return \NetFusion\Core\Observability\Tracing\Tracer
     */
    function otel_tracer(): \NetFusion\Core\Observability\Tracing\Tracer
    {
        return otel()->getTracer();
    }
}

if (!function_exists('otel_meter')) {
    /**
     * Access the active OpenTelemetry Meter.
     *
     * @return \NetFusion\Core\Observability\Metrics\Meter
     */
    function otel_meter(): \NetFusion\Core\Observability\Metrics\Meter
    {
        return otel()->getMeter();
    }
}

if (!function_exists('otel_span')) {
    /**
     * Execute a callable within a traced OpenTelemetry span.
     *
     * @param string $name
     * @param callable $callable
     * @param array $options
     * @return mixed
     */
    function otel_span(string $name, callable $callable, array $options = []): mixed
    {
        return otel()->trace($name, $callable, $options);
    }
}

if (!function_exists('otel_counter')) {
    /**
     * Create or retrieve a monotonic Counter instrument.
     *
     * @param string $name
     * @param string $description
     * @param string $unit
     * @return \NetFusion\Core\Observability\Metrics\Instruments\Counter
     */
    function otel_counter(string $name, string $description = '', string $unit = '1'): \NetFusion\Core\Observability\Metrics\Instruments\Counter
    {
        return otel()->counter($name, $description, $unit);
    }
}

if (!function_exists('otel_gauge')) {
    /**
     * Create or retrieve a Gauge instrument.
     *
     * @param string $name
     * @param string $description
     * @param string $unit
     * @return \NetFusion\Core\Observability\Metrics\Instruments\Gauge
     */
    function otel_gauge(string $name, string $description = '', string $unit = '1'): \NetFusion\Core\Observability\Metrics\Instruments\Gauge
    {
        return otel()->gauge($name, $description, $unit);
    }
}

if (!function_exists('otel_histogram')) {
    /**
     * Create or retrieve a Histogram instrument.
     *
     * @param string $name
     * @param string $description
     * @param string $unit
     * @param array $boundaries
     * @return \NetFusion\Core\Observability\Metrics\Instruments\Histogram
     */
    function otel_histogram(string $name, string $description = '', string $unit = 'ms', array $boundaries = []): \NetFusion\Core\Observability\Metrics\Instruments\Histogram
    {
        return otel()->histogram($name, $description, $unit, $boundaries);
    }
}

if (!function_exists('schema_manager')) {
    /**
     * Get the SchemaManager singleton instance.
     *
     * @return \NetFusion\Core\SchemaRegistry\SchemaManager
     */
    function schema_manager(): \NetFusion\Core\SchemaRegistry\SchemaManager
    {
        return \NetFusion\Core\SchemaRegistry\SchemaManager::getInstance();
    }
}

if (!function_exists('schema_registry')) {
    /**
     * Get the SchemaRegistry repository instance.
     *
     * @return \NetFusion\Core\SchemaRegistry\SchemaRegistry
     */
    function schema_registry(): \NetFusion\Core\SchemaRegistry\SchemaRegistry
    {
        return schema_manager()->registry();
    }
}

if (!function_exists('cloudevent')) {
    /**
     * Create or instantiate a CloudEvent v1.0 message.
     *
     * @param string $type
     * @param string $source
     * @param mixed $data
     * @param string|null $subject
     * @param string|null $dataContentType
     * @param string|null $dataSchema
     * @param array $extensions
     * @return \NetFusion\Core\SchemaRegistry\CloudEvents\CloudEvent
     */
    function cloudevent(
        string $type,
        string $source,
        mixed $data = null,
        ?string $subject = null,
        ?string $dataContentType = 'application/json',
        ?string $dataSchema = null,
        array $extensions = []
    ): \NetFusion\Core\SchemaRegistry\CloudEvents\CloudEvent {
        return \NetFusion\Core\SchemaRegistry\CloudEvents\CloudEvent::create(
            type: $type,
            source: $source,
            data: $data,
            subject: $subject,
            dataContentType: $dataContentType,
            dataSchema: $dataSchema,
            extensions: $extensions
        );
    }
}

if (!function_exists('schema_encode')) {
    /**
     * Serialize payload using the latest registered schema for the subject.
     *
     * @param string $subject
     * @param mixed $data
     * @param bool $withWireHeader
     * @return string
     */
    function schema_encode(string $subject, mixed $data, bool $withWireHeader = true): string
    {
        return schema_manager()->encode($subject, $data, $withWireHeader);
    }
}

if (!function_exists('schema_decode')) {
    /**
     * Deserialize payload using embedded schema ID or fallback schema.
     *
     * @param string $binary
     * @param \NetFusion\Core\SchemaRegistry\Contracts\SchemaInterface|null $fallback
     * @return mixed
     */
    function schema_decode(string $binary, ?\NetFusion\Core\SchemaRegistry\Contracts\SchemaInterface $fallback = null): mixed
    {
        return schema_manager()->decode($binary, $fallback);
    }
}

if (!function_exists('agent_manager')) {
    /**
     * Get or create the master AgentManager singleton instance.
     *
     * @param array $config
     * @return \NetFusion\Core\AI\Agent\AgentManager
     */
    function agent_manager(array $config = []): \NetFusion\Core\AI\Agent\AgentManager
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new \NetFusion\Core\AI\Agent\AgentManager($config);
        }
        return $instance;
    }
}

if (!function_exists('agent')) {
    /**
     * Create or retrieve an autonomous ReAct AI Agent instance.
     *
     * @param (callable(string): string)|null $llmCallable
     * @param string|null $name
     * @return \NetFusion\Core\AI\Agent\Planning\ReActAgent|\NetFusion\Core\AI\Agent\AgentManager
     */
    function agent(?callable $llmCallable = null, ?string $name = null): mixed
    {
        $manager = agent_manager();
        if ($llmCallable !== null || $name !== null) {
            return $manager->createAgent($llmCallable, $name);
        }
        return $manager;
    }
}

if (!function_exists('agent_tool')) {
    /**
     * Register a callable tool into the global agent tool registry.
     *
     * @param string $name
     * @param string $description
     * @param callable $handler
     * @param array $parameters
     * @return \NetFusion\Core\AI\Agent\Tools\CallableTool
     */
    function agent_tool(string $name, string $description, callable $handler, array $parameters = []): \NetFusion\Core\AI\Agent\Tools\CallableTool
    {
        $tool = new \NetFusion\Core\AI\Agent\Tools\CallableTool($name, $description, $handler, $parameters);
        agent_manager()->registerTool($tool);
        return $tool;
    }
}

if (!function_exists('agent_memory')) {
    /**
     * Create or retrieve an AgenticMemoryFabric for an agent.
     *
     * @param string $agentName
     * @return \NetFusion\Core\AI\Agent\Memory\AgenticMemoryFabric
     */
    function agent_memory(string $agentName = 'default'): \NetFusion\Core\AI\Agent\Memory\AgenticMemoryFabric
    {
        return agent_manager()->createMemory($agentName);
    }
}

if (!function_exists('collaboration')) {
    /**
     * Get the master CollaborationManager singleton instance.
     *
     * @param array $config
     * @return \NetFusion\Core\Collaboration\CollaborationManager
     */
    function collaboration(array $config = []): \NetFusion\Core\Collaboration\CollaborationManager
    {
        return \NetFusion\Core\Collaboration\CollaborationManager::getInstance($config);
    }
}

if (!function_exists('collab_doc')) {
    /**
     * Get or create a collaborative document.
     *
     * @param string $docId
     * @param string $peerId
     * @param string $initialText
     * @return \NetFusion\Core\Collaboration\Session\CollaborativeDocument
     */
    function collab_doc(string $docId, string $peerId = 'local-peer', string $initialText = ''): \NetFusion\Core\Collaboration\Session\CollaborativeDocument
    {
        return collaboration()->getOrCreateDocument($docId, $peerId, $initialText);
    }
}

if (!function_exists('collab_room')) {
    /**
     * Get or create a WebRTC signaling room.
     *
     * @param string $roomId
     * @return \NetFusion\Core\Collaboration\WebRTC\SignalingRoom
     */
    function collab_room(string $roomId = 'general'): \NetFusion\Core\Collaboration\WebRTC\SignalingRoom
    {
        return collaboration()->getOrCreateRoom($roomId);
    }
}

if (!function_exists('distributed_db')) {
    /**
     * Get the DistributedDbManager master facade instance.
     *
     * @return \NetFusion\Core\DistributedDb\DistributedDbManager
     */
    function distributed_db(): \NetFusion\Core\DistributedDb\DistributedDbManager
    {
        return app(\NetFusion\Core\DistributedDb\DistributedDbManager::class);
    }
}

if (!function_exists('raft_cluster')) {
    /**
     * Get or initialize a named Raft consensus cluster.
     *
     * @param  string|null $name
     * @return \NetFusion\Core\DistributedDb\Raft\RaftCluster
     */
    function raft_cluster(?string $name = 'default'): \NetFusion\Core\DistributedDb\Raft\RaftCluster
    {
        return distributed_db()->raft($name ?? 'default');
    }
}

if (!function_exists('dsql')) {
    /**
     * Plan and execute a distributed SQL query across partitioned shards.
     *
     * @param  string $query
     * @param  array<mixed> $params
     * @return array<mixed>
     */
    function dsql(string $query, array $params = []): array
    {
        return distributed_db()->query($query, $params);
    }
}

if (!function_exists('dist_lock')) {
    /**
     * Execute a callback protected by a distributed lease lock.
     *
     * @template T
     * @param  string $resource
     * @param  callable(\NetFusion\Core\DistributedDb\TwoPhaseCommit\DistributedLock): T $callback
     * @param  int $ttlMs
     * @param  string|null $owner
     * @return T
     */
    function dist_lock(string $resource, callable $callback, int $ttlMs = 5000, ?string $owner = null): mixed
    {
        $owner ??= 'proc_' . getmypid();
        return distributed_db()->lock()->withLock($resource, $owner, $callback, $ttlMs);
    }
}

if (!function_exists('iac')) {
    /**
     * Get the IacManager master facade instance.
     *
     * @return \NetFusion\Core\IaC\IacManager
     */
    function iac(): \NetFusion\Core\IaC\IacManager
    {
        if (function_exists('app')) {
            try {
                if (app()->bound(\NetFusion\Core\IaC\IacManager::class)) {
                    return app(\NetFusion\Core\IaC\IacManager::class);
                }
            } catch (\Throwable) {}
        }

        return \NetFusion\Core\IaC\IacManager::getInstance();
    }
}

if (!function_exists('iac_plan')) {
    /**
     * Generate an infrastructure mutation execution plan diff against state.
     *
     * @param  string $hcl
     * @param  array<string, mixed> $vars
     * @return \NetFusion\Core\IaC\Plan\ExecutionPlan
     */
    function iac_plan(string $hcl, array $vars = []): \NetFusion\Core\IaC\Plan\ExecutionPlan
    {
        return iac()->plan($hcl, $vars);
    }
}

if (!function_exists('iac_apply')) {
    /**
     * Apply planned infrastructure mutations or HCL configuration directly.
     *
     * @param  \NetFusion\Core\IaC\Plan\ExecutionPlan|string $plan
     * @param  array<string, mixed> $vars
     * @return array<string, mixed>
     */
    function iac_apply(\NetFusion\Core\IaC\Plan\ExecutionPlan|string $plan, array $vars = []): array
    {
        return iac()->apply($plan, $vars);
    }
}

if (!function_exists('iac_state')) {
    /**
     * Inspect tracked state or retrieve a specific resource from state.
     *
     * @param  string|null $address
     * @return mixed
     */
    function iac_state(?string $address = null): mixed
    {
        $state = iac()->getState();
        if ($address !== null) {
            return $state->getResource($address);
        }
        return $state;
    }
}

if (!function_exists('wasm')) {
    /**
     * Get the WasmManager master facade instance.
     *
     * @param  array<string, mixed> $config
     * @return \NetFusion\Core\Wasm\WasmManager
     */
    function wasm(array $config = []): \NetFusion\Core\Wasm\WasmManager
    {
        if (function_exists('app')) {
            try {
                if (app()->bound(\NetFusion\Core\Wasm\WasmManager::class)) {
                    return app(\NetFusion\Core\Wasm\WasmManager::class);
                }
            } catch (\Throwable) {}
        }

        return \NetFusion\Core\Wasm\WasmManager::getInstance($config);
    }
}

if (!function_exists('wasm_run')) {
    /**
     * Execute a function in a WebAssembly module or binary file path.
     *
     * @param  \NetFusion\Core\Wasm\Module\WasmModule|string $moduleOrPath
     * @param  string $functionName
     * @param  array<mixed> $args
     * @return mixed
     */
    function wasm_run(\NetFusion\Core\Wasm\Module\WasmModule|string $moduleOrPath, string $functionName, array $args = []): mixed
    {
        return wasm()->run($moduleOrPath, $functionName, $args);
    }
}

if (!function_exists('wasm_module')) {
    /**
     * Load or parse a WebAssembly module.
     *
     * @param  string $filePathOrBinary
     * @return \NetFusion\Core\Wasm\Module\WasmModule
     */
    function wasm_module(string $filePathOrBinary): \NetFusion\Core\Wasm\Module\WasmModule
    {
        if (str_starts_with($filePathOrBinary, "\x00\x61\x73\x6D")) {
            return wasm()->parse($filePathOrBinary);
        }
        return wasm()->loadFile($filePathOrBinary);
    }
}

if (!function_exists('kube')) {
    /**
     * Get the master KubeManager cluster facade instance.
     *
     * @return \NetFusion\Core\Kube\KubeManager
     */
    function kube(): \NetFusion\Core\Kube\KubeManager
    {
        if (function_exists('app') && app()->bound(\NetFusion\Core\Kube\KubeManager::class)) {
            return app(\NetFusion\Core\Kube\KubeManager::class);
        }
        return \NetFusion\Core\Kube\KubeManager::getInstance();
    }
}

if (!function_exists('kube_apply')) {
    /**
     * Apply a declarative Kubernetes YAML or JSON manifest string.
     *
     * @param  string $manifestContent
     * @return object[] Array of created or updated Kubernetes resource objects
     */
    function kube_apply(string $manifestContent): array
    {
        return kube()->apply($manifestContent);
    }
}

if (!function_exists('kube_pods')) {
    /**
     * Get active cluster pods in a namespace.
     *
     * @param  string|null $namespace
     * @return \NetFusion\Core\Kube\Models\Pod[]
     */
    function kube_pods(?string $namespace = null): array
    {
        return kube()->getPods($namespace);
    }
}

if (!function_exists('kube_scale')) {
    /**
     * Scale a named deployment to the desired replica count.
     *
     * @param  string $deploymentName
     * @param  int $replicas
     * @param  string $namespace
     * @return \NetFusion\Core\Kube\Models\Deployment
     */
    function kube_scale(string $deploymentName, int $replicas, string $namespace = 'default'): \NetFusion\Core\Kube\Models\Deployment
    {
        return kube()->scaleDeployment($deploymentName, $replicas, $namespace);
    }
}

if (!function_exists('etcd')) {
    /**
     * Get the master etcd v3 MVCC store manager singleton instance.
     *
     * @return \NetFusion\Core\Etcd\EtcdManager
     */
    function etcd(): \NetFusion\Core\Etcd\EtcdManager
    {
        return app(\NetFusion\Core\Etcd\EtcdManager::class);
    }
}

if (!function_exists('etcd_get')) {
    /**
     * Retrieve a key revision from the etcd MVCC store.
     *
     * @param  string $key
     * @param  int $revision
     * @return \NetFusion\Core\Etcd\Models\KeyRevision|null
     */
    function etcd_get(string $key, int $revision = 0): ?\NetFusion\Core\Etcd\Models\KeyRevision
    {
        return etcd()->get($key, $revision);
    }
}

if (!function_exists('etcd_put')) {
    /**
     * Store a key-value pair in the etcd MVCC store.
     *
     * @param  string $key
     * @param  string $value
     * @param  int $leaseId
     * @return \NetFusion\Core\Etcd\Models\KeyRevision
     */
    function etcd_put(string $key, string $value, int $leaseId = 0): \NetFusion\Core\Etcd\Models\KeyRevision
    {
        return etcd()->put($key, $value, $leaseId);
    }
}

if (!function_exists('etcd_watch')) {
    /**
     * Watch for mutation events on a key or prefix.
     *
     * @param  string $key
     * @param  callable $callback
     * @param  bool $isPrefix
     * @param  int $startRevision
     * @param  bool $prevKv
     * @return \NetFusion\Core\Etcd\Watch\Watcher
     */
    function etcd_watch(string $key, callable $callback, bool $isPrefix = false, int $startRevision = 0, bool $prevKv = false): \NetFusion\Core\Etcd\Watch\Watcher
    {
        return etcd()->watch($key, $callback, $isPrefix, $startRevision, $prevKv);
    }
}

if (!function_exists('kafka')) {
    /**
     * Get the master Kafka streaming manager singleton instance.
     *
     * @return \NetFusion\Core\Kafka\KafkaManager
     */
    function kafka(): \NetFusion\Core\Kafka\KafkaManager
    {
        return app(\NetFusion\Core\Kafka\KafkaManager::class);
    }
}

if (!function_exists('kafka_produce')) {
    /**
     * Produce and publish a record to a Kafka topic.
     *
     * @param  string $topic
     * @param  string $value
     * @param  string|null $key
     * @param  int|null $partition
     * @param  array<string, string> $headers
     * @return \NetFusion\Core\Kafka\Models\Record
     */
    function kafka_produce(
        string $topic,
        string $value,
        ?string $key = null,
        ?int $partition = null,
        array $headers = []
    ): \NetFusion\Core\Kafka\Models\Record {
        return kafka()->produce($topic, $value, $key, $partition, $headers);
    }
}

if (!function_exists('kafka_consume')) {
    /**
     * Consume messages from a topic using a consumer group.
     *
     * @param  string $topic
     * @param  string|null $groupId
     * @param  int $maxRecords
     * @return \NetFusion\Core\Kafka\Models\Record[]
     */
    function kafka_consume(string $topic, ?string $groupId = null, int $maxRecords = 100): array
    {
        $consumer = kafka()->consumer($groupId);
        $consumer->subscribe([$topic]);
        $records = $consumer->poll(timeoutMs: 100, maxRecords: $maxRecords);
        $consumer->close();
        return $records;
    }
}

if (!function_exists('kafka_topic')) {
    /**
     * Create or describe a Kafka topic.
     *
     * @param  string $name
     * @param  int $partitions
     * @param  int $replicationFactor
     * @param  array<string, mixed> $config
     * @return \NetFusion\Core\Kafka\Models\TopicMetadata
     */
    function kafka_topic(
        string $name,
        int $partitions = 1,
        int $replicationFactor = 1,
        array $config = []
    ): \NetFusion\Core\Kafka\Models\TopicMetadata {
        $meta = kafka()->describeTopic($name);
        if ($meta !== null) {
            return $meta;
        }
        return kafka()->createTopic($name, $partitions, $replicationFactor, $config);
    }
}

if (!function_exists('consul')) {
    /**
     * Get the ConsulManager instance.
     *
     * @return \NetFusion\Core\Consul\ConsulManager
     */
    function consul(): \NetFusion\Core\Consul\ConsulManager
    {
        return app(\NetFusion\Core\Consul\ConsulManager::class);
    }
}

if (!function_exists('consul_register')) {
    /**
     * Register a service instance in Consul catalog.
     *
     * @param string $name
     * @param string $address
     * @param int $port
     * @param array<string> $tags
     * @param array<string, string> $meta
     * @return \NetFusion\Core\Consul\Models\ServiceInstance
     */
    function consul_register(
        string $name,
        string $address,
        int $port,
        array $tags = [],
        array $meta = []
    ): \NetFusion\Core\Consul\Models\ServiceInstance {
        return consul()->registerService(name: $name, address: $address, port: $port, tags: $tags, meta: $meta);
    }
}

if (!function_exists('consul_discover')) {
    /**
     * Discover healthy instances of a service from Consul catalog.
     *
     * @param string $serviceName
     * @param array<string> $tags
     * @return array<\NetFusion\Core\Consul\Models\ServiceInstance>
     */
    function consul_discover(string $serviceName, array $tags = []): array
    {
        return consul()->discover($serviceName, $tags, true);
    }
}

if (!function_exists('consul_dns')) {
    /**
     * Perform DNS resolution for a Consul query domain.
     *
     * @param string $query
     * @param string $type
     * @return array<array{name: string, type: \NetFusion\Core\Consul\Enums\DnsRecordType, ttl: int, data: mixed}>
     */
    function consul_dns(string $query, string $type = 'ANY'): array
    {
        return consul()->dnsLookup($query, $type);
    }
}

if (!function_exists('analytics')) {
    /**
     * Get the AnalyticsManager instance.
     *
     * @return \NetFusion\Core\Analytics\AnalyticsManager
     */
    function analytics(): \NetFusion\Core\Analytics\AnalyticsManager
    {
        return app(\NetFusion\Core\Analytics\AnalyticsManager::class);
    }
}

if (!function_exists('analytics_ingest')) {
    /**
     * Ingest an analytical event into a data source.
     *
     * @param string $dataSource
     * @param array<string, mixed> $dimensions
     * @param array<string, float|int> $metrics
     * @param int|null $timestamp
     */
    function analytics_ingest(
        string $dataSource,
        array $dimensions = [],
        array $metrics = [],
        ?int $timestamp = null
    ): void {
        analytics()->ingest($dataSource, $timestamp ?? time(), $dimensions, $metrics);
    }
}

if (!function_exists('analytics_query')) {
    /**
     * Initialize an analytical query builder for a data source.
     *
     * @param string $dataSource
     * @return \NetFusion\Core\Analytics\Query\AnalyticsQuery
     */
    function analytics_query(string $dataSource): \NetFusion\Core\Analytics\Query\AnalyticsQuery
    {
        return analytics()->query($dataSource);
    }
}

if (!function_exists('zookeeper')) {
    /**
     * Get the ZooKeeperManager instance.
     *
     * @return \NetFusion\Core\ZooKeeper\ZooKeeperManager
     */
    function zookeeper(): \NetFusion\Core\ZooKeeper\ZooKeeperManager
    {
        return app(\NetFusion\Core\ZooKeeper\ZooKeeperManager::class);
    }
}

if (!function_exists('zk_create')) {
    /**
     * Create a ZNode in ZooKeeper hierarchy.
     *
     * @param string $path
     * @param string|null $data
     * @param \NetFusion\Core\ZooKeeper\Enums\CreateMode $mode
     * @return string Created path
     */
    function zk_create(
        string $path,
        ?string $data = null,
        \NetFusion\Core\ZooKeeper\Enums\CreateMode $mode = \NetFusion\Core\ZooKeeper\Enums\CreateMode::PERSISTENT
    ): string {
        return zookeeper()->create($path, $data, $mode);
    }
}

if (!function_exists('zk_get')) {
    /**
     * Get data of a ZNode.
     *
     * @param string $path
     * @param \NetFusion\Core\ZooKeeper\Models\Stat|null $stat
     * @return string|null
     */
    function zk_get(string $path, ?\NetFusion\Core\ZooKeeper\Models\Stat &$stat = null): ?string
    {
        return zookeeper()->getData($path, null, $stat);
    }
}

if (!function_exists('zk_watch')) {
    /**
     * Register a reactive one-time watcher on a ZNode.
     *
     * @param string $path
     * @param callable $callback
     * @return string|null Current data
     */
    function zk_watch(string $path, callable $callback): ?string
    {
        return zookeeper()->getData($path, $callback);
    }
}

if (!function_exists('mfe')) {
    /**
     * Get the MfeManager instance.
     *
     * @return \NetFusion\Core\MFE\MfeManager
     */
    function mfe(): \NetFusion\Core\MFE\MfeManager
    {
        return app(\NetFusion\Core\MFE\MfeManager::class);
    }
}

if (!function_exists('mfe_fragment')) {
    /**
     * Render a Micro-Frontend component fragment.
     *
     * @param string $remote
     * @param string $component
     * @param array<string, mixed> $props
     * @param \NetFusion\Core\MFE\Enums\RenderMode $renderMode
     * @param string|null $fallbackHtml
     * @return string
     */
    function mfe_fragment(
        string $remote,
        string $component,
        array $props = [],
        \NetFusion\Core\MFE\Enums\RenderMode $renderMode = \NetFusion\Core\MFE\Enums\RenderMode::SSR,
        ?string $fallbackHtml = null
    ): string {
        return mfe()->fragment($remote, $component, $props, $renderMode, $fallbackHtml);
    }
}

if (!function_exists('mfe_dispatch')) {
    /**
     * Dispatch an event across the Cross-MFE Bus.
     *
     * @param string $event
     * @param mixed $payload
     * @param string $origin
     * @return int
     */
    function mfe_dispatch(string $event, mixed $payload = null, string $origin = 'host'): int
    {
        return mfe()->dispatch($event, $payload, $origin);
    }
}

if (!function_exists('edge_worker')) {
    /**
     * Resolve the EdgeWorkerManager facade singleton or register/get an edge worker.
     *
     * @param string|null $workerName
     * @return \NetFusion\Core\Edge\EdgeWorkerManager|\NetFusion\Core\Edge\Worker\WorkerScript|null
     */
    function edge_worker(?string $workerName = null): mixed
    {
        /** @var \NetFusion\Core\Edge\EdgeWorkerManager $manager */
        $manager = function_exists('app') && app()->has('edge')
            ? app('edge')
            : new \NetFusion\Core\Edge\EdgeWorkerManager();

        if ($workerName !== null) {
            return $manager->getWorker($workerName);
        }

        return $manager;
    }
}

if (!function_exists('edge_invoke')) {
    /**
     * Directly invoke an edge worker by name.
     *
     * @param string $workerName
     * @param \NetFusion\Core\Edge\Http\EdgeRequest|string $request
     * @param array<string, string> $envOverrides
     * @return \NetFusion\Core\Edge\Http\EdgeResponse
     */
    function edge_invoke(string $workerName, mixed $request, array $envOverrides = []): \NetFusion\Core\Edge\Http\EdgeResponse
    {
        return edge_worker()->invoke($workerName, $request, $envOverrides);
    }
}

if (!function_exists('edge_isolate')) {
    /**
     * Execute a task inside an isolated V8 sandbox with strict bounds.
     *
     * @template T
     * @param callable(): T $task
     * @param int $memoryMb
     * @param int $timeoutMs
     * @return T
     */
    function edge_isolate(callable $task, int $memoryMb = 128, int $timeoutMs = 50): mixed
    {
        $isolate = new \NetFusion\Core\Edge\Isolate\Isolate($memoryMb, $timeoutMs);
        return $isolate->execute($task);
    }
}

if (!function_exists('media_server')) {
    /**
     * Resolve the MediaServerManager singleton or access room.
     *
     * @return \NetFusion\Core\Media\MediaServerManager
     */
    function media_server(): \NetFusion\Core\Media\MediaServerManager
    {
        return function_exists('app') && app()->has('media')
            ? app('media')
            : new \NetFusion\Core\Media\MediaServerManager();
    }
}

if (!function_exists('sfu_room')) {
    /**
     * Get or create an SFU Conference Room.
     *
     * @param string $roomId
     * @param string $name
     * @return \NetFusion\Core\Media\SFU\SfuRoom
     */
    function sfu_room(string $roomId, string $name = 'Conference'): \NetFusion\Core\Media\SFU\SfuRoom
    {
        $server = media_server();
        if ($server->hasRoom($roomId)) {
            return $server->getRoom($roomId);
        }

        return $server->createRoom($roomId, $name);
    }
}

if (!function_exists('rtp_packet')) {
    /**
     * Create an RTP media packet.
     *
     * @param int $payloadType
     * @param int $sequenceNumber
     * @param int $timestamp
     * @param int $ssrc
     * @param string $payload
     * @param bool $marker
     * @return \NetFusion\Core\Media\Rtp\RtpPacket
     */
    function rtp_packet(
        int $payloadType,
        int $sequenceNumber,
        int $timestamp,
        int $ssrc,
        string $payload,
        bool $marker = false
    ): \NetFusion\Core\Media\Rtp\RtpPacket {
        return media_server()->makeRtpPacket($payloadType, $sequenceNumber, $timestamp, $ssrc, $payload, $marker);
    }
}

if (!function_exists('geo')) {
    /**
     * Resolve the GeoSpatialManager singleton.
     *
     * @return \NetFusion\Core\Spatial\GeoSpatialManager
     */
    function geo(): \NetFusion\Core\Spatial\GeoSpatialManager
    {
        return function_exists('app') && app()->has('geo')
            ? app('geo')
            : new \NetFusion\Core\Spatial\GeoSpatialManager();
    }
}

if (!function_exists('geo_distance')) {
    /**
     * Calculate geodesic distance between two points (in meters).
     *
     * @param \NetFusion\Core\Spatial\Geometry\Point $p1
     * @param \NetFusion\Core\Spatial\Geometry\Point $p2
     * @param string $method 'haversine' or 'vincenty'
     * @return float Distance in meters
     */
    function geo_distance(
        \NetFusion\Core\Spatial\Geometry\Point $p1,
        \NetFusion\Core\Spatial\Geometry\Point $p2,
        string $method = 'haversine'
    ): float {
        return geo()->distance($p1, $p2, $method);
    }
}

if (!function_exists('h3_index')) {
    /**
     * Convert geographic coordinates to Uber H3 hexagonal cell index.
     *
     * @param float $lat
     * @param float $lng
     * @param int $resolution
     * @return string
     */
    function h3_index(float $lat, float $lng, int $resolution = 8): string
    {
        return geo()->geoToH3($lat, $lng, $resolution);
    }
}

if (!function_exists('geo_fence')) {
    /**
     * Create or retrieve a spatial geo-fence.
     *
     * @param string $id
     * @param string $name
     * @param \NetFusion\Core\Spatial\Geometry\Polygon|\NetFusion\Core\Spatial\Geometry\Point $geometry
     * @param float|null $radiusMeters
     * @return \NetFusion\Core\Spatial\GeoFence\GeoFence
     */
    function geo_fence(
        string $id,
        string $name,
        \NetFusion\Core\Spatial\Geometry\Polygon|\NetFusion\Core\Spatial\Geometry\Point $geometry,
        ?float $radiusMeters = null
    ): \NetFusion\Core\Spatial\GeoFence\GeoFence {
        return geo()->createFence($id, $name, $geometry, $radiusMeters);
    }
}

if (!function_exists('sdui')) {
    /**
     * Get the Server-Driven UI (SDUI) Manager instance.
     */
    function sdui(): \NetFusion\Core\SDUI\SDUIManager
    {
        return app('sdui');
    }
}

if (!function_exists('sdui_screen')) {
    /**
     * Create or retrieve a Server-Driven UI Screen.
     */
    function sdui_screen(string $screenId, string $title = ''): \NetFusion\Core\SDUI\Layout\Screen
    {
        return sdui()->screen($screenId, $title);
    }
}

if (!function_exists('sdui_component')) {
    /**
     * Create a custom or dynamic Server-Driven UI component.
     */
    function sdui_component(string $name, array $props = [], ?string $id = null): \NetFusion\Core\SDUI\Schema\ComponentInterface
    {
        return sdui()->customComponent($name, $props, $id);
    }
}

if (!function_exists('sdui_action')) {
    /**
     * Create an SDUI client executable action.
     */
    function sdui_action(\NetFusion\Core\SDUI\Enums\ActionType|string $type, array $params = []): \NetFusion\Core\SDUI\Actions\Action
    {
        $enumType = is_string($type) ? \NetFusion\Core\SDUI\Enums\ActionType::from($type) : $type;
        return new \NetFusion\Core\SDUI\Actions\Action($enumType, $params);
    }
}

if (!function_exists('graph_rag')) {
    /**
     * Get the Multi-Modal Vector Knowledge Graph & Hybrid RAG Manager.
     */
    function graph_rag(): \NetFusion\Core\GraphRag\GraphRagManager
    {
        return app('graph_rag');
    }
}

if (!function_exists('graph_rag_ingest')) {
    /**
     * Ingest a chunk or entity node into GraphRAG.
     */
    function graph_rag_ingest(
        string $id,
        string $content,
        array $vector = [],
        array $properties = []
    ): \NetFusion\Core\GraphRag\Entity\KnowledgeNode {
        return graph_rag()->ingest($id, $content, $vector, properties: $properties);
    }
}

if (!function_exists('graph_rag_retrieve')) {
    /**
     * Execute Tri-Way hybrid semantic retrieval and graph reranking.
     */
    function graph_rag_retrieve(string $query, array $vector = [], int $limit = 5): array
    {
        return graph_rag()->retrieve($query, $vector, $limit);
    }
}

if (!function_exists('graph_rag_context')) {
    /**
     * Retrieve hybrid context and synthesize grounded LLM prompt string.
     */
    function graph_rag_context(string $query, array $vector = [], int $limit = 5): string
    {
        return graph_rag()->synthesizeContext($query, $vector, $limit);
    }
}

if (!function_exists('crdt_sync')) {
    /**
     * Get the CRDT and OT Synchronizer Manager.
     */
    function crdt_sync(): \NetFusion\Core\CRDT\CrdtManager
    {
        return app('crdt_sync');
    }
}

if (!function_exists('crdt_sync_doc')) {
    /**
     * Get or create a CRDT document instance.
     */
    function crdt_sync_doc(string $name, \NetFusion\Core\CRDT\Enums\CrdtType|string $type = \NetFusion\Core\CRDT\Enums\CrdtType::RGA_TEXT): \NetFusion\Core\CRDT\Types\CrdtInterface
    {
        return crdt_sync()->doc($name, $type);
    }
}

if (!function_exists('crdt_sync_counter')) {
    /**
     * Get or create a PN-Counter CRDT instance.
     */
    function crdt_sync_counter(string $name): \NetFusion\Core\CRDT\Types\PnCounter
    {
        return crdt_sync()->counter($name);
    }
}

if (!function_exists('crdt_sync_text')) {
    /**
     * Get or create an RGA Text CRDT document instance.
     */
    function crdt_sync_text(string $name): \NetFusion\Core\CRDT\Types\RgaText
    {
        return crdt_sync()->text($name);
    }
}

if (!function_exists('crdt_sync_ot')) {
    /**
     * Get or create an OT server engine for centralized text transformation.
     */
    function crdt_sync_ot(string $documentId, string $initialContent = ''): \NetFusion\Core\CRDT\OT\OtServerEngine
    {
        return crdt_sync()->ot($documentId, $initialContent);
    }
}

if (!function_exists('zkp')) {
    /**
     * Get the Zero-Knowledge Proof (ZKP) Engine Manager.
     */
    function zkp(): \NetFusion\Core\ZKP\ZkpManager
    {
        return app('zkp');
    }
}

if (!function_exists('zkp_circuit')) {
    /**
     * Get or build an arithmetic R1CS circuit.
     */
    function zkp_circuit(string $name, ?string $gadgetType = null): \NetFusion\Core\ZKP\Circuit\R1csCircuit
    {
        if ($gadgetType !== null) {
            return zkp()->gadget($gadgetType, $name);
        }
        return zkp()->circuit($name);
    }
}

if (!function_exists('zkp_prove')) {
    /**
     * Generate zero-knowledge proof for a circuit given a private witness.
     */
    function zkp_prove(\NetFusion\Core\ZKP\Circuit\R1csCircuit|string $circuit, array $witness): \NetFusion\Core\ZKP\Prover\Proof
    {
        return zkp()->prove($circuit, $witness);
    }
}

if (!function_exists('zkp_verify')) {
    /**
     * Verify zero-knowledge proof against verifying key.
     */
    function zkp_verify(\NetFusion\Core\ZKP\Prover\VerifyingKey|string $vk, \NetFusion\Core\ZKP\Prover\Proof $proof): bool
    {
        return zkp()->verify($vk, $proof);
    }
}

if (!function_exists('formal')) {
    /**
     * Resolve the Formal Verification Manager facade.
     */
    function formal(): \NetFusion\Core\Formal\FormalManager
    {
        return app(\NetFusion\Core\Formal\FormalManager::class);
    }
}

if (!function_exists('formal_verify')) {
    /**
     * Formally verify a method contract using Hoare logic and SMT solving.
     */
    function formal_verify(\NetFusion\Core\Formal\Contracts\ContractSpec $contract, array $assignments = []): array
    {
        return formal()->verifyMethodContract($contract, $assignments);
    }
}

if (!function_exists('smt_solve')) {
    /**
     * Solve SMT-LIB 2.6 formulas and check satisfiability.
     */
    function smt_solve(string $smtScript): array
    {
        return formal()->parseScript($smtScript);
    }
}

if (!function_exists('symbolic_run')) {
    /**
     * Perform symbolic execution analysis on a variable set.
     */
    function symbolic_run(array $varNames): \NetFusion\Core\Formal\Symbolic\SymbolicState
    {
        return formal()->createSymbolicEngine()->createInitialState($varNames);
    }
}

if (!function_exists('ebpf')) {
    /**
     * Resolve the eBPF Manager facade singleton.
     */
    function ebpf(): \NetFusion\Core\Network\Ebpf\EbpfManager
    {
        return app(\NetFusion\Core\Network\Ebpf\EbpfManager::class);
    }
}

if (!function_exists('ebpf_map')) {
    /**
     * Resolve or get an eBPF shared memory map by name.
     */
    function ebpf_map(string $name): ?\NetFusion\Core\Network\Ebpf\Maps\MapInterface
    {
        return ebpf()->getMap($name);
    }
}

if (!function_exists('xdp_filter')) {
    /**
     * Process a packet through the attached XDP pipeline.
     */
    function xdp_filter(\NetFusion\Core\Network\Ebpf\Xdp\Packet $packet): \NetFusion\Core\Network\Ebpf\Enums\XdpAction
    {
        return ebpf()->filterPacket($packet);
    }
}

if (!function_exists('wasm_component')) {
    /**
     * Get the WebAssembly Component Manager master facade.
     */
    function wasm_component(): \NetFusion\Core\Wasm\Component\ComponentManager
    {
        return function_exists('app') && app()->has('wasm.component')
            ? app('wasm.component')
            : \NetFusion\Core\Wasm\Component\ComponentManager::getInstance();
    }
}

if (!function_exists('wit_parse')) {
    /**
     * Parse and inspect a WebAssembly Interface Type (WIT) specification string.
     */
    function wit_parse(string $witContent): array
    {
        return wasm_component()->parseWit($witContent);
    }
}

if (!function_exists('wasi_p2_runtime')) {
    /**
     * Get the WASI Preview 2 runtime instance.
     */
    function wasi_p2_runtime(): \NetFusion\Core\Wasm\Component\WasiP2\WasiP2Runtime
    {
        return wasm_component()->getWasiP2();
    }
}

if (!function_exists('diff_privacy')) {
    /**
     * Get the Differential Privacy manager master facade.
     */
    function diff_privacy(): \NetFusion\Core\Privacy\Differential\DifferentialPrivacyManager
    {
        return function_exists('app') && app()->has('diff_privacy')
            ? app('diff_privacy')
            : \NetFusion\Core\Privacy\Differential\DifferentialPrivacyManager::getInstance();
    }
}

if (!function_exists('dp_noise')) {
    /**
     * Add calibrated differential privacy noise to a value.
     */
    function dp_noise(float|int|array $value, float $epsilon, float $sensitivity, string $mechanism = 'laplace'): float|int|array
    {
        $dp = diff_privacy();
        return match (strtolower($mechanism)) {
            'gaussian' => $dp->addGaussianNoise($value, $epsilon, $sensitivity),
            default    => $dp->addLaplaceNoise($value, $epsilon, $sensitivity),
        };
    }
}

if (!function_exists('dp_aggregate')) {
    /**
     * Perform private count, sum, or mean query.
     */
    function dp_aggregate(string $type, mixed $data, float $epsilon = 1.0, float $lower = 0.0, float $upper = 100.0): float
    {
        $dp = diff_privacy();
        return match (strtolower($type)) {
            'sum'  => $dp->sum((array)$data, $lower, $upper, $epsilon),
            'mean' => $dp->mean((array)$data, $lower, $upper, $epsilon),
            default => $dp->count((float)$data, $epsilon),
        };
    }
}

if (!function_exists('dp_budget')) {
    /**
     * Get or inspect privacy budget.
     */
    function dp_budget(string $identifier): \NetFusion\Core\Privacy\Differential\Accounting\PrivacyBudget
    {
        return diff_privacy()->budget($identifier);
    }
}

if (!function_exists('homomorphic')) {
    /**
     * Get the Homomorphic Encryption & SMPC manager master facade.
     */
    function homomorphic(): \NetFusion\Core\Security\Homomorphic\HomomorphicManager
    {
        return function_exists('app') && app()->has('homomorphic')
            ? app('homomorphic')
            : \NetFusion\Core\Security\Homomorphic\HomomorphicManager::getInstance();
    }
}

if (!function_exists('he_encrypt')) {
    /**
     * Encrypt an integer value into a BFV ciphertext.
     */
    function he_encrypt(int $value, ?\NetFusion\Core\Security\Homomorphic\Schemes\KeyPair $keyPair = null): \NetFusion\Core\Security\Homomorphic\Schemes\Ciphertext
    {
        $mgr = homomorphic();
        $kp = $keyPair ?? $mgr->generateBfvKeys();
        return $mgr->encryptInt($value, $kp);
    }
}

if (!function_exists('he_compute')) {
    /**
     * Perform homomorphic addition on two ciphertexts.
     */
    function he_compute(\NetFusion\Core\Security\Homomorphic\Schemes\Ciphertext $a, \NetFusion\Core\Security\Homomorphic\Schemes\Ciphertext $b, string $op = 'add'): \NetFusion\Core\Security\Homomorphic\Schemes\Ciphertext
    {
        $mgr = homomorphic();
        return match (strtolower($op)) {
            'sub'   => $mgr->bfv()->sub($a, $b),
            default => $mgr->bfv()->add($a, $b),
        };
    }
}

if (!function_exists('smpc_share')) {
    /**
     * Split a secret integer into n shares with threshold k.
     */
    function smpc_share(int $secret, int $threshold = 3, int $totalShares = 5): array
    {
        return homomorphic()->splitSecret($secret, $threshold, $totalShares);
    }
}

if (!function_exists('self_heal')) {
    /**
     * Get the Autonomous Self-Healing Infrastructure Manager.
     */
    function self_heal(): \NetFusion\Core\Infrastructure\SelfHealing\SelfHealingManager
    {
        return app('selfhealing');
    }
}

if (!function_exists('heal_diagnose')) {
    /**
     * Perform automated Root-Cause Analysis (RCA) on detected anomalies.
     *
     * @param \NetFusion\Core\Infrastructure\SelfHealing\Telemetry\AnomalyEvent[] $anomalies
     */
    function heal_diagnose(array $anomalies): \NetFusion\Core\Infrastructure\SelfHealing\Analysis\RootCauseReport
    {
        return self_heal()->diagnose($anomalies);
    }
}

if (!function_exists('heal_remediate')) {
    /**
     * Trigger canary remediation on an RCA report.
     */
    function heal_remediate(
        \NetFusion\Core\Infrastructure\SelfHealing\Analysis\RootCauseReport $report,
        ?callable $healthProbe = null,
        int $fleetSize = 10
    ): \NetFusion\Core\Infrastructure\SelfHealing\Remediation\RemediationAction {
        return self_heal()->remediate($report, $healthProbe, $fleetSize);
    }
}

if (!function_exists('enclave')) {
    /**
     * Get EnclaveManager or specific EnclaveContext.
     */
    function enclave(?string $enclaveId = null): \NetFusion\Core\Security\Enclave\EnclaveManager|\NetFusion\Core\Security\Enclave\Runtime\EnclaveContext
    {
        /** @var \NetFusion\Core\Security\Enclave\EnclaveManager $mgr */
        $mgr = app('enclave');
        if ($enclaveId !== null) {
            $ctx = $mgr->getEnclave($enclaveId);
            return $ctx ?? $mgr->createEnclave($enclaveId);
        }
        return $mgr;
    }
}

if (!function_exists('enclave_attest')) {
    /**
     * Generate remote attestation quote for an enclave.
     */
    function enclave_attest(string $enclaveId, string $userData = ''): \NetFusion\Core\Security\Enclave\Attestation\AttestationQuote
    {
        return enclave()->attest($enclaveId, $userData);
    }
}

if (!function_exists('enclave_seal')) {
    /**
     * Seal secret data to current hardware enclave measurement.
     */
    function enclave_seal(string $enclaveId, string $secret): \NetFusion\Core\Security\Enclave\Sealing\SealedSecret
    {
        return enclave()->seal($enclaveId, $secret);
    }
}

if (!function_exists('enclave_unseal')) {
    /**
     * Unseal secret data verifying current hardware enclave measurement.
     */
    function enclave_unseal(string $enclaveId, \NetFusion\Core\Security\Enclave\Sealing\SealedSecret $sealed): string
    {
        return enclave()->unseal($enclaveId, $sealed);
    }
}

if (!function_exists('qkd')) {
    /**
     * Get QuantumKeyManager instance.
     */
    function qkd(): \NetFusion\Core\Security\Quantum\QuantumKeyManager
    {
        return app('qkd');
    }
}

if (!function_exists('qkd_exchange')) {
    /**
     * Execute QKD key exchange with a peer node.
     */
    function qkd_exchange(string $peerId, int $photons = 256, string $protocol = 'bb84'): array
    {
        return strtolower($protocol) === 'e91'
            ? qkd()->exchangeE91($peerId, $photons)
            : qkd()->exchangeBb84($peerId, $photons);
    }
}

if (!function_exists('qkd_encrypt')) {
    /**
     * Encrypt message with QKD key using One-Time Pad.
     */
    function qkd_encrypt(string $peerId, string $message): array
    {
        return qkd()->encryptMessage($peerId, $message);
    }
}

if (!function_exists('qkd_decrypt')) {
    /**
     * Decrypt OTP message using consumed quantum key.
     */
    function qkd_decrypt(string $ciphertextHex, string $keyUsedHex): string
    {
        return qkd()->decryptMessage($ciphertextHex, $keyUsedHex);
    }
}

if (!function_exists('neuromorphic')) {
    /**
     * Get the NeuromorphicManager instance or retrieve a registered SNN.
     *
     * @param string|null $networkName
     * @return \NetFusion\Core\AI\Neuromorphic\NeuromorphicManager|\NetFusion\Core\AI\Neuromorphic\Network\SpikingNeuralNetwork
     */
    function neuromorphic(?string $networkName = null): \NetFusion\Core\AI\Neuromorphic\NeuromorphicManager|\NetFusion\Core\AI\Neuromorphic\Network\SpikingNeuralNetwork
    {
        /** @var \NetFusion\Core\AI\Neuromorphic\NeuromorphicManager $mgr */
        $mgr = app()->has('neuromorphic')
            ? app('neuromorphic')
            : \NetFusion\Core\AI\Neuromorphic\NeuromorphicManager::getInstance();

        if ($networkName !== null) {
            $net = $mgr->getNetwork($networkName);
            if ($net !== null) {
                return $net;
            }
        }

        return $mgr;
    }
}

if (!function_exists('snn_encode')) {
    /**
     * Encode continuous float features into discrete temporal spike train.
     *
     * @param float[] $values
     * @param \NetFusion\Core\AI\Neuromorphic\Enums\EncodingScheme $scheme
     * @param int $timesteps
     * @return array<int, array<int, int>>
     */
    function snn_encode(
        array $values,
        \NetFusion\Core\AI\Neuromorphic\Enums\EncodingScheme $scheme = \NetFusion\Core\AI\Neuromorphic\Enums\EncodingScheme::RATE,
        int $timesteps = 50
    ): array {
        return neuromorphic()->encode($values, $scheme, $timesteps);
    }
}

if (!function_exists('snn_infer')) {
    /**
     * Perform real-time Spiking Neural Network classification inference.
     *
     * @param string $networkName
     * @param float[] $features
     * @param int $timesteps
     * @return array{
     *   predicted_class: int,
     *   confidence: float,
     *   total_spikes: int,
     *   firing_rates: float[],
     *   synaptic_operations: int
     * }
     */
    function snn_infer(string $networkName, array $features, int $timesteps = 30): array
    {
        return neuromorphic()->infer($networkName, $features, $timesteps);
    }
}

if (!function_exists('snn_train')) {
    /**
     * Train SNN model on temporal sequence dataset.
     *
     * @param string $networkName
     * @param array<int, array{input: float[], target: int}> $dataset
     * @param int $epochs
     * @param float $learningRate
     * @param int $timesteps
     * @return array{epochs: int, final_loss: float, accuracy: float}
     */
    function snn_train(
        string $networkName,
        array $dataset,
        int $epochs = 5,
        float $learningRate = 0.05,
        int $timesteps = 30
    ): array {
        return neuromorphic()->train($networkName, $dataset, $epochs, $learningRate, $timesteps);
    }
}

if (!function_exists('swarm')) {
    /**
     * Get the SwarmManager instance or retrieve a registered SwarmColony.
     *
     * @param string|null $colonyId
     * @return \NetFusion\Core\AI\Swarm\SwarmManager|\NetFusion\Core\AI\Swarm\SwarmColony
     */
    function swarm(?string $colonyId = null): \NetFusion\Core\AI\Swarm\SwarmManager|\NetFusion\Core\AI\Swarm\SwarmColony
    {
        /** @var \NetFusion\Core\AI\Swarm\SwarmManager $mgr */
        $mgr = app()->has('swarm')
            ? app('swarm')
            : \NetFusion\Core\AI\Swarm\SwarmManager::getInstance();

        if ($colonyId !== null) {
            $colony = $mgr->getColony($colonyId);
            if ($colony !== null) {
                return $colony;
            }
        }

        return $mgr;
    }
}

if (!function_exists('swarm_auction')) {
    /**
     * Conduct decentralized task auction via Contract Net Protocol.
     *
     * @param string $colonyId
     * @param string $taskId
     * @param string $taskType
     * @param float $effort
     * @param array<string, mixed> $payload
     * @return array
     */
    function swarm_auction(
        string $colonyId,
        string $taskId,
        string $taskType = 'compute',
        float $effort = 1.0,
        array $payload = []
    ): array {
        $proposal = new \NetFusion\Core\AI\Swarm\Auction\TaskProposal($taskId, $taskType, estimatedEffort: $effort, payload: $payload);
        return swarm()->auction($colonyId, $proposal);
    }
}

if (!function_exists('swarm_pheromone')) {
    /**
     * Deposit spatial pheromone concentration on colony grid.
     */
    function swarm_pheromone(
        string $colonyId,
        int $x,
        int $y,
        \NetFusion\Core\AI\Swarm\Enums\PheromoneType $type,
        float $amount
    ): void {
        swarm()->deposit($colonyId, $x, $y, $type, $amount);
    }
}

if (!function_exists('swarm_agent')) {
    /**
     * Create and attach an autonomous agent to a colony.
     */
    function swarm_agent(
        string $colonyId,
        string $agentId,
        \NetFusion\Core\AI\Swarm\Enums\AgentRole $role = \NetFusion\Core\AI\Swarm\Enums\AgentRole::WORKER,
        int $x = 0,
        int $y = 0
    ): \NetFusion\Core\AI\Swarm\Agents\SwarmAgent {
        $mgr = swarm();
        $colony = $mgr->getColony($colonyId);
        if ($colony === null) {
            $colony = $mgr->createColony($colonyId);
        }

        $agent = new \NetFusion\Core\AI\Swarm\Agents\SwarmAgent($agentId, $role, $x, $y);
        $colony->addAgent($agent);
        return $agent;
    }
}

if (!function_exists('did')) {
    /**
     * Resolve DID or return IdentityManager
     */
    function did(?string $identifier = null): \NetFusion\Core\Security\Identity\IdentityManager|\NetFusion\Core\Security\Identity\Did\DidDocument {
        /** @var \NetFusion\Core\Security\Identity\IdentityManager $manager */
        $manager = app()->has('identity')
            ? app('identity')
            : new \NetFusion\Core\Security\Identity\IdentityManager();

        if ($identifier !== null) {
            return $manager->resolveDid($identifier);
        }

        return $manager;
    }
}

if (!function_exists('vc_issue')) {
    /**
     * Issue and cryptographically sign a Verifiable Credential
     */
    function vc_issue(
        string $issuerDid,
        array $credentialSubject,
        string $secretKeyHex,
        array $type = ['VerifiableCredential'],
        ?string $expirationDate = null
    ): \NetFusion\Core\Security\Identity\Credentials\VerifiableCredential {
        /** @var \NetFusion\Core\Security\Identity\IdentityManager $manager */
        $manager = did();
        return $manager->issueCredential(
            issuerDid: $issuerDid,
            credentialSubject: $credentialSubject,
            secretKeyHex: $secretKeyHex,
            type: $type,
            expirationDate: $expirationDate
        );
    }
}

if (!function_exists('vc_verify')) {
    /**
     * Verify a Verifiable Credential
     */
    function vc_verify(
        \NetFusion\Core\Security\Identity\Credentials\VerifiableCredential $vc,
        ?string $ecosystemId = null
    ): array {
        /** @var \NetFusion\Core\Security\Identity\IdentityManager $manager */
        $manager = did();
        return $manager->verifyCredential($vc, $ecosystemId);
    }
}

if (!function_exists('bbs_disclose')) {
    /**
     * Create BBS+ zero-knowledge selective disclosure proof
     */
    function bbs_disclose(
        array $bbsCredential,
        array $discloseKeys,
        string $nonce
    ): \NetFusion\Core\Security\Identity\Bbs\SelectiveDisclosureProof {
        /** @var \NetFusion\Core\Security\Identity\IdentityManager $manager */
        $manager = did();
        return $manager->createSelectiveDisclosurePresentation($bbsCredential, $discloseKeys, $nonce);
    }
}

if (!function_exists('spatial')) {
    /**
     * Get SpatialManager instance or get specific scene
     */
    function spatial(?string $sceneId = null): \NetFusion\Core\Spatial\SpatialManager|\NetFusion\Core\Spatial\SceneGraph\SceneGraph {
        /** @var \NetFusion\Core\Spatial\SpatialManager $manager */
        $manager = app()->has('spatial')
            ? app('spatial')
            : new \NetFusion\Core\Spatial\SpatialManager();

        if ($sceneId !== null) {
            $scene = $manager->getScene($sceneId);
            return $scene ?? $manager->createScene($sceneId);
        }

        return $manager;
    }
}

if (!function_exists('spatial_scene')) {
    /**
     * Create or retrieve a 3D SceneGraph
     */
    function spatial_scene(string $id, string $name = 'Scene'): \NetFusion\Core\Spatial\SceneGraph\SceneGraph {
        /** @var \NetFusion\Core\Spatial\SpatialManager $manager */
        $manager = spatial();
        $scene = $manager->getScene($id);
        return $scene ?? $manager->createScene($id, $name);
    }
}

if (!function_exists('spatial_transform')) {
    /**
     * Create 3D Transform
     */
    function spatial_transform(
        float $x = 0.0,
        float $y = 0.0,
        float $z = 0.0,
        float $pitch = 0.0,
        float $yaw = 0.0,
        float $roll = 0.0
    ): \NetFusion\Core\Spatial\SceneGraph\Transform3D {
        return new \NetFusion\Core\Spatial\SceneGraph\Transform3D(
            position: new \NetFusion\Core\Spatial\Math\Vector3($x, $y, $z),
            rotation: \NetFusion\Core\Spatial\Math\Quaternion::fromEuler($pitch, $yaw, $roll)
        );
    }
}

if (!function_exists('spatial_splat')) {
    /**
     * Create or retrieve Gaussian Splat Cloud
     */
    function spatial_splat(string $id, string $name = 'Splat Cloud'): \NetFusion\Core\Spatial\Gaussian\SplatCloud {
        /** @var \NetFusion\Core\Spatial\SpatialManager $manager */
        $manager = spatial();
        $cloud = $manager->getSplatCloud($id);
        return $cloud ?? $manager->createSplatCloud($id, $name);
    }
}

if (!function_exists('dna')) {
    /**
     * Get MolecularManager instance
     */
    function dna(): \NetFusion\Core\Storage\Molecular\MolecularManager {
        /** @var \NetFusion\Core\Storage\Molecular\MolecularManager $manager */
        return app()->has('molecular')
            ? app('molecular')
            : \NetFusion\Core\Storage\Molecular\MolecularManager::getInstance();
    }
}

if (!function_exists('dna_encode')) {
    /**
     * Encode binary string to DNA OligoPool
     */
    function dna_encode(string $data, int $chunkSize = 16, int $rsParity = 6): \NetFusion\Core\Storage\Molecular\Dna\OligoPool {
        return dna()->encodeDna($data, $chunkSize, $rsParity);
    }
}

if (!function_exists('dna_decode')) {
    /**
     * Decode DNA OligoPool to binary string with error correction
     */
    function dna_decode(\NetFusion\Core\Storage\Molecular\Dna\OligoPool $pool, int $rsParity = 6): string {
        return dna()->decodeDna($pool, $rsParity);
    }
}

if (!function_exists('holographic_store')) {
    /**
     * Store data on a holographic crystal page
     */
    function holographic_store(string $crystalId, string $payload, int $pageIndex, float $braggAngle): \NetFusion\Core\Storage\Molecular\Holographic\HolographicPage {
        return dna()->storeHolographicPage($crystalId, $payload, $pageIndex, $braggAngle);
    }
}

if (!function_exists('bci')) {
    /**
     * Get the BciManager instance.
     */
    function bci(): \NetFusion\Core\Neural\Bci\BciManager {
        return app('bci');
    }
}

if (!function_exists('bci_filter')) {
    /**
     * Clean multi-channel EEG signals using Notch, Bandpass and CAR spatial filter
     *
     * @param array<string, float[]> $channelSignals
     * @return array<string, float[]>
     */
    function bci_filter(array $channelSignals, float $lowCut = 8.0, float $highCut = 30.0): array {
        return bci()->cleanSignals($channelSignals, $lowCut, $highCut);
    }
}

if (!function_exists('bci_decode')) {
    /**
     * Decode cognitive intent or motor imagery from multi-channel trial
     *
     * @param float[][] $trial
     * @return array{intent: string, confidence: float, score: float, features: float[]}
     */
    function bci_decode(array $trial, ?\NetFusion\Core\Neural\Bci\Decoder\MotorImageryDecoder $decoder = null): array {
        $dec = $decoder ?? bci()->getMotorImageryDecoder();
        return $dec->predict($trial);
    }
}

if (!function_exists('bci_feedback')) {
    /**
     * Evaluate real-time closed-loop neurofeedback on an incoming EEG stream
     *
     * @param float[] $signal
     * @return array{state: \NetFusion\Core\Neural\Bci\Enums\MentalState, relaxationIndex: float, focusIndex: float, feedbackLevel: float, bandPowers: array<string, float>, recommendation: string}
     */
    function bci_feedback(array $signal): array {
        return bci()->evaluateFeedback($signal);
    }
}

if (!function_exists('photonic')) {
    /**
     * Get the PhotonicManager instance.
     */
    function photonic(): \NetFusion\Core\Photonic\PhotonicManager {
        return app('photonic');
    }
}

if (!function_exists('photonic_mzi')) {
    /**
     * Create or retrieve an InterferometerMesh.
     */
    function photonic_mzi(string $meshId = 'mesh_default', int $ports = 4): \NetFusion\Core\Photonic\Mesh\InterferometerMesh {
        $mgr = photonic();
        return $mgr->getMesh($meshId) ?? $mgr->createMesh($meshId, $ports);
    }
}

if (!function_exists('photonic_matmul')) {
    /**
     * Execute optical tensor matrix-vector multiplication.
     *
     * @param float[][] $weights
     * @param float[] $input
     * @param \NetFusion\Core\Photonic\Enums\OpticalActivation $activation
     * @return array{output: float[], latencyPicoseconds: float, energyFemtojoules: float, topsPerWatt: float}
     */
    function photonic_matmul(array $weights, array $input, \NetFusion\Core\Photonic\Enums\OpticalActivation $activation = \NetFusion\Core\Photonic\Enums\OpticalActivation::LINEAR): array {
        return photonic()->matmul($weights, $input, $activation);
    }
}

if (!function_exists('photonic_complex')) {
    /**
     * Instantiate a ComplexNumber.
     */
    function photonic_complex(float $real, float $imag = 0.0): \NetFusion\Core\Photonic\Physics\ComplexNumber {
        return new \NetFusion\Core\Photonic\Physics\ComplexNumber($real, $imag);
    }
}

if (!function_exists('ris')) {
    /**
     * Get the RisManager instance.
     */
    function ris(): \NetFusion\Core\Wireless\Ris\RisManager {
        return app('ris');
    }
}

if (!function_exists('ris_array')) {
    /**
     * Create or retrieve a RIS metasurface array.
     */
    function ris_array(
        string $surfaceId = 'ris_primary',
        int $rows = 8,
        int $cols = 8,
        \NetFusion\Core\Wireless\Ris\Enums\FrequencyBand $band = \NetFusion\Core\Wireless\Ris\Enums\FrequencyBand::Terahertz,
        \NetFusion\Core\Wireless\Ris\Enums\PhaseResolution $resolution = \NetFusion\Core\Wireless\Ris\Enums\PhaseResolution::Continuous
    ): \NetFusion\Core\Wireless\Ris\Surface\RisArray {
        $mgr = ris();
        if ($mgr->hasArray($surfaceId)) {
            return $mgr->getArray($surfaceId);
        }
        return $mgr->createArray($surfaceId, $rows, $cols, $band, $resolution);
    }
}


if (!function_exists('ris_steer')) {
    /**
     * Configure RIS phase shift to steer an incident beam toward a target angle.
     *
     * @return array{surface_id: string, band: string, elements: int, in_azimuth_deg: float, in_elevation_deg: float, out_azimuth_deg: float, out_elevation_deg: float, average_quantization_error_rad: float}
     */
    function ris_steer(
        string $surfaceId,
        float $inAzimuthDeg,
        float $inElevationDeg,
        float $outAzimuthDeg,
        float $outElevationDeg
    ): array {
        return ris()->steerBeam($surfaceId, $inAzimuthDeg, $inElevationDeg, $outAzimuthDeg, $outElevationDeg);
    }
}

if (!function_exists('ris_channel')) {
    /**
     * Simulate cascaded RIS channel capacity, SNR gain and molecular absorption.
     *
     * @return array{surface_id: string, carrier_freq_ghz: float, direct_capacity_bps_hz: float, effective_capacity_bps_hz: float, snr_gain_db: float, quantization_error_rad: float, total_elements: int, algorithm: string}
     */
    function ris_channel(
        string $surfaceId,
        float $txDistanceMeters = 15.0,
        float $rxDistanceMeters = 10.0,
        float $directDistanceMeters = 20.0
    ): array {
        return ris()->simulateChannelLink($surfaceId, $txDistanceMeters, $rxDistanceMeters, $directDistanceMeters);
    }
}

if (!function_exists('relativity')) {
    /**
     * Get the RelativityManager instance.
     */
    function relativity(): \NetFusion\Core\Relativity\RelativityManager {
        return app('relativity');
    }
}

if (!function_exists('relativity_metric')) {
    /**
     * Create a 4D Spacetime Metric Tensor.
     */
    function relativity_metric(
        \NetFusion\Core\Relativity\Enums\MetricType $type = \NetFusion\Core\Relativity\Enums\MetricType::Schwarzschild,
        float $massSolar = 10.0,
        float $radiusMeters = 100000.0,
        float $spinParameter = 0.7
    ): \NetFusion\Core\Relativity\Tensor\MetricTensor {
        return relativity()->createMetric($type, $massSolar, $radiusMeters, $spinParameter);
    }
}

if (!function_exists('relativity_chirp')) {
    /**
     * Simulate a binary black hole chirp system.
     */
    function relativity_chirp(
        float $m1Solar = 36.0,
        float $m2Solar = 29.0,
        float $distanceMpc = 410.0
    ): \NetFusion\Core\Relativity\Waveform\BinaryChirpSystem {
        return new \NetFusion\Core\Relativity\Waveform\BinaryChirpSystem($m1Solar, $m2Solar, $distanceMpc);
    }
}

if (!function_exists('relativity_waveform')) {
    /**
     * Generate inspiral-merger-ringdown gravitational waveform strain time series.
     *
     * @return array{time: float[], h_plus: float[], h_cross: float[], frequency: float[], phases: string[], peak_strain: float}
     */
    function relativity_waveform(
        float $m1Solar = 36.0,
        float $m2Solar = 29.0,
        float $distanceMpc = 410.0,
        float $durationSec = 0.2,
        float $sampleRateHz = 2048.0
    ): array {
        $sim = relativity()->simulateBinaryChirp($m1Solar, $m2Solar, $distanceMpc, $durationSec, $sampleRateHz);
        return $sim['waveform'];
    }
}

if (!function_exists('tda')) {
    /**
     * Get the TdaManager instance.
     */
    function tda(): \NetFusion\Core\Analytics\Tda\TdaManager {
        if (\NetFusion\Core\Container\Container::getInstance()->has('tda')) {
            return app('tda');
        }
        return app(\NetFusion\Core\Analytics\Tda\TdaManager::class);
    }
}


if (!function_exists('tda_rips')) {
    /**
     * Construct a Vietoris-Rips simplicial complex from a point cloud.
     *
     * @param array<int, float[]> $pointCloud
     */
    function tda_rips(
        array $pointCloud,
        float $maxFiltration = 2.0,
        int $maxDimension = 2
    ): \NetFusion\Core\Analytics\Tda\Simplicial\VietorisRipsComplex {
        return tda()->buildRipsComplex($pointCloud, $maxFiltration, $maxDimension);
    }
}

if (!function_exists('tda_homology')) {
    /**
     * Compute persistent homology intervals and Betti numbers.
     *
     * @param array<int, float[]> $pointCloud
     * @return array{num_points: int, num_simplices: int, intervals: array, betti_numbers: array{h0: int, h1: int, h2: int}}
     */
    function tda_homology(
        array $pointCloud,
        float $maxFiltration = 2.0,
        int $maxDimension = 2
    ): array {
        return tda()->computePersistentHomology($pointCloud, $maxFiltration, $maxDimension);
    }
}

if (!function_exists('tda_betti')) {
    /**
     * Compute Betti curve over filtration steps.
     *
     * @param array<int, float[]> $pointCloud
     * @param float[] $filtrationSteps
     * @return array<int, array{epsilon: float, betti_0: int, betti_1: int}>
     */
    function tda_betti(array $pointCloud, array $filtrationSteps): array {
        return tda()->computeBettiCurve($pointCloud, $filtrationSteps);
    }
}

if (!function_exists('cognitive')) {
    /**
     * Get the CognitiveManager instance.
     */
    function cognitive(): \NetFusion\Core\Cognitive\CognitiveManager {
        static $instance = null;
        if (\NetFusion\Core\Container\Container::getInstance()->has('cognitive')) {
            return app('cognitive');
        }
        if ($instance === null) {
            $instance = new \NetFusion\Core\Cognitive\CognitiveManager();
        }
        return $instance;
    }
}


if (!function_exists('cognitive_chunk')) {
    /**
     * Create and store a fact chunk in declarative memory.
     *
     * @param array<string, mixed> $slots
     */
    function cognitive_chunk(string $id, string $type, array $slots): \NetFusion\Core\Cognitive\Memory\Chunk {
        return cognitive()->learnFact($id, $type, $slots);
    }
}

if (!function_exists('cognitive_recall')) {
    /**
     * Retrieve a fact chunk matching query slots from declarative memory.
     *
     * @param array<string, mixed> $query
     */
    function cognitive_recall(array $query): \NetFusion\Core\Cognitive\Memory\Chunk {
        return cognitive()->declarative()->retrieve($query);
    }
}

if (!function_exists('cognitive_cycle')) {
    /**
     * Step through N cognitive Recognize-Act cycles.
     *
     * @return array<int, array{cycle: int, fired_rule: string, utility: float, result: mixed}>
     */
    function cognitive_cycle(int $maxCycles = 3): array {
        return cognitive()->runCycles($maxCycles);
    }
}

if (!function_exists('rollup')) {
    /**
     * Get the RollupManager singleton instance.
     *
     * @return \NetFusion\Core\Rollup\RollupManager
     */
    function rollup(): \NetFusion\Core\Rollup\RollupManager {
        static $instance = null;
        if (class_exists(\NetFusion\Core\Container\Container::class) && \NetFusion\Core\Container\Container::getInstance()->has('rollup')) {
            return app('rollup');
        }
        if ($instance === null) {
            $depth = 16;
            try {
                if (function_exists('config')) {
                    $depth = (int) (config('rollup.tree_depth') ?? 16);
                }
            } catch (\Throwable $e) {
                $depth = 16;
            }
            $instance = new \NetFusion\Core\Rollup\RollupManager($depth);
        }
        return $instance;
    }
}

if (!function_exists('rollup_account')) {
    /**
     * Register or retrieve a Rollup account.
     *
     * @return \NetFusion\Core\Rollup\State\AccountLeaf
     */
    function rollup_account(string $accountId, ?string $publicKey = null, float $initialBalance = 0.0): \NetFusion\Core\Rollup\State\AccountLeaf {
        $acc = rollup()->getAccount($accountId);
        if ($acc !== null) {
            return $acc;
        }
        return rollup()->registerAccount($accountId, $publicKey, $initialBalance);
    }
}

if (!function_exists('rollup_transfer')) {
    /**
     * Create and submit a transfer transaction to the Rollup mempool.
     *
     * @return \NetFusion\Core\Rollup\Execution\StateTransition
     */
    function rollup_transfer(string $from, string $to, float $amount, ?string $secretKey = null): \NetFusion\Core\Rollup\Execution\StateTransition {
        return rollup()->transfer($from, $to, $amount, $secretKey);
    }
}

if (!function_exists('rollup_verify')) {
    /**
     * Verify SMT inclusion proof for an account.
     */
    function rollup_verify(string $accountId): bool {
        return rollup()->verifyAccountInclusion($accountId);
    }
}

if (!function_exists('lbm')) {
    /**
     * Get the LbmManager singleton instance.
     *
     * @return \NetFusion\Core\Physics\Fluid\LbmManager
     */
    function lbm(): \NetFusion\Core\Physics\Fluid\LbmManager {
        static $instance = null;
        if (class_exists(\NetFusion\Core\Container\Container::class) && \NetFusion\Core\Container\Container::getInstance()->has('lbm')) {
            return app('lbm');
        }
        if ($instance === null) {
            $w = 40;
            $h = 40;
            $tau = 0.6;
            try {
                if (function_exists('config')) {
                    $w = (int) (config('lbm.grid_width') ?? 40);
                    $h = (int) (config('lbm.grid_height') ?? 40);
                    $tau = (float) (config('lbm.tau') ?? 0.6);
                }
            } catch (\Throwable $e) {
                $w = 40;
                $h = 40;
                $tau = 0.6;
            }
            $instance = new \NetFusion\Core\Physics\Fluid\LbmManager($w, $h, $tau);
        }
        return $instance;
    }
}

if (!function_exists('lbm_grid')) {
    /**
     * Create or retrieve an LBM grid mesh.
     *
     * @return \NetFusion\Core\Physics\Fluid\Lattice\LbmGrid
     */
    function lbm_grid(?int $nx = null, ?int $ny = null): \NetFusion\Core\Physics\Fluid\Lattice\LbmGrid {
        if ($nx !== null && $ny !== null) {
            return lbm()->createGrid($nx, $ny);
        }
        return lbm()->getGrid();
    }
}

if (!function_exists('lbm_step')) {
    /**
     * Advance LBM fluid simulation by N timesteps.
     */
    function lbm_step(int $iterations = 1): int {
        return lbm()->step($iterations);
    }
}

if (!function_exists('lbm_reynolds')) {
    /**
     * Compute current Reynolds number.
     */
    function lbm_reynolds(float $charLength = 10.0): float {
        return lbm()->getReynoldsNumber($charLength);
    }
}

if (!function_exists('biocad')) {
    /**
     * Get the BioCadManager singleton instance.
     *
     * @return \NetFusion\Core\Bio\BioCadManager
     */
    function biocad(): \NetFusion\Core\Bio\BioCadManager {
        static $instance = null;
        if (class_exists(\NetFusion\Core\Container\Container::class) && \NetFusion\Core\Container\Container::getInstance()->has('biocad')) {
            return app('biocad');
        }
        if ($instance === null) {
            $instance = new \NetFusion\Core\Bio\BioCadManager();
        }
        return $instance;
    }
}

if (!function_exists('biocad_crispr')) {
    /**
     * Design CRISPR candidate guide RNAs targeting a DNA locus.
     *
     * @return array
     */
    function biocad_crispr(string $sequence, \NetFusion\Core\Bio\Enums\CasVariant $variant = \NetFusion\Core\Bio\Enums\CasVariant::SpCas9): array {
        return biocad()->designCrisprGuides($sequence, $variant);
    }
}

if (!function_exists('biocad_optimize')) {
    /**
     * Optimize coding sequence codons for a host organism.
     *
     * @return array
     */
    function biocad_optimize(string $codingDna, \NetFusion\Core\Bio\Enums\OrganismHost $host = \NetFusion\Core\Bio\Enums\OrganismHost::E_COLI): array {
        return biocad()->optimizeCodons($codingDna, $host);
    }
}

if (!function_exists('biocad_circuit')) {
    /**
     * Simulate genetic toggle switch bistability.
     *
     * @return array
     */
    function biocad_circuit(float $initU = 2.0, float $initV = 0.2): array {
        return biocad()->simulateToggle($initU, $initV);
    }
}

if (!function_exists('astro')) {
    /**
     * Get the AstroManager singleton instance.
     *
     * @return \NetFusion\Core\Astro\AstroManager
     */
    function astro(): \NetFusion\Core\Astro\AstroManager {
        static $instance = null;
        if (class_exists(\NetFusion\Core\Container\Container::class) && \NetFusion\Core\Container\Container::getInstance()->has('astro')) {
            return app('astro');
        }
        if ($instance === null) {
            $instance = new \NetFusion\Core\Astro\AstroManager();
        }
        return $instance;
    }
}

if (!function_exists('astro_kepler')) {
    /**
     * Convert Keplerian elements to 3D Cartesian position and velocity state vector.
     *
     * @return array{position: array{x: float, y: float, z: float}, velocity: array{x: float, y: float, z: float}}
     */
    function astro_kepler(
        float $semiMajorAxis,
        float $eccentricity = 0.0,
        float $trueAnomaly = 0.0,
        float $inclination = 0.0,
        float $mu = \NetFusion\Core\Astro\Orbit\KeplerianElements::MU_SUN
    ): array {
        $elements = new \NetFusion\Core\Astro\Orbit\KeplerianElements(
            $semiMajorAxis,
            $eccentricity,
            $inclination,
            0.0,
            0.0,
            $trueAnomaly,
            $mu
        );
        return astro()->getOrbitalMechanics()->elementsToCartesian($elements);
    }
}

if (!function_exists('astro_nbody')) {
    /**
     * Propagate N-body gravitational system across multiple timesteps.
     *
     * @return array
     */
    function astro_nbody(
        int $steps = 10,
        float $dt = 86400.0,
        \NetFusion\Core\Astro\Enums\IntegratorType $type = \NetFusion\Core\Astro\Enums\IntegratorType::LEAPFROG
    ): array {
        $manager = astro();
        if (empty($manager->getBodies())) {
            $manager->loadMiniSolarSystem();
        }
        return $manager->propagate($steps, $dt, $type);
    }
}

if (!function_exists('astro_hohmann')) {
    /**
     * Compute Hohmann interplanetary transfer between two circular orbits.
     *
     * @return array
     */
    function astro_hohmann(
        float $r1,
        float $r2,
        float $mu = \NetFusion\Core\Astro\Orbit\KeplerianElements::MU_SUN
    ): array {
        return astro()->calculateHohmann($r1, $r2, $mu);
    }
}

if (!function_exists('plasma')) {
    /**
     * Get the PlasmaManager singleton instance.
     *
     * @return \NetFusion\Core\Physics\Plasma\PlasmaManager
     */
    function plasma(): \NetFusion\Core\Physics\Plasma\PlasmaManager {
        static $instance = null;
        if (class_exists(\NetFusion\Core\Container\Container::class) && \NetFusion\Core\Container\Container::getInstance()->has('plasma')) {
            return app('plasma');
        }
        if ($instance === null) {
            $instance = new \NetFusion\Core\Physics\Plasma\PlasmaManager();
        }
        return $instance;
    }
}

if (!function_exists('plasma_tokamak')) {
    /**
     * Create or retrieve a Tokamak magnetic confinement geometry.
     *
     * @return \NetFusion\Core\Physics\Plasma\Tokamak\ToroidalField
     */
    function plasma_tokamak(
        ?float $majorRadius = null,
        ?float $minorRadius = null,
        ?float $toroidalB0 = null,
        ?float $plasmaCurrent = null
    ): \NetFusion\Core\Physics\Plasma\Tokamak\ToroidalField {
        if ($majorRadius !== null && $minorRadius !== null && $toroidalB0 !== null && $plasmaCurrent !== null) {
            $field = new \NetFusion\Core\Physics\Plasma\Tokamak\ToroidalField($majorRadius, $minorRadius, $toroidalB0, $plasmaCurrent);
            plasma()->setTokamak($field);
            return $field;
        }
        return plasma()->getTokamak();
    }
}

if (!function_exists('plasma_cross_section')) {
    /**
     * Compute Maxwellian thermonuclear reactivity <sigma*v> in m^3/s.
     */
    function plasma_cross_section(
        float $temperatureKeV = 15.0,
        \NetFusion\Core\Physics\Plasma\Enums\FuelMixture $fuel = \NetFusion\Core\Physics\Plasma\Enums\FuelMixture::D_T
    ): float {
        return plasma()->getCrossSection()->getReactivity($temperatureKeV, $fuel);
    }
}

if (!function_exists('plasma_lawson')) {
    /**
     * Evaluate Lawson triple product and Q-gain factor.
     *
     * @return array
     */
    function plasma_lawson(
        float $density = 1e20,
        float $temperatureKeV = 15.0,
        float $confinementTimeSec = 3.5,
        float $auxHeatingMw = 50.0,
        \NetFusion\Core\Physics\Plasma\Enums\FuelMixture $fuel = \NetFusion\Core\Physics\Plasma\Enums\FuelMixture::D_T
    ): array {
        return plasma()->evaluateLawson($density, $temperatureKeV, $confinementTimeSec, $auxHeatingMw, $fuel);
    }
}

if (!function_exists('supercond')) {
    /**
     * Get the SuperconductivityManager singleton instance.
     *
     * @return \NetFusion\Core\Physics\Superconductivity\SuperconductivityManager
     */
    function supercond(): \NetFusion\Core\Physics\Superconductivity\SuperconductivityManager {
        static $instance = null;
        if (class_exists(\NetFusion\Core\Container\Container::class) && \NetFusion\Core\Container\Container::getInstance()->has('superconductivity')) {
            return app('superconductivity');
        }
        if ($instance === null) {
            $instance = new \NetFusion\Core\Physics\Superconductivity\SuperconductivityManager();
        }
        return $instance;
    }
}

if (!function_exists('supercond_gl')) {
    /**
     * Evaluate Ginzburg-Landau state of a superconductor.
     *
     * @return array
     */
    function supercond_gl(
        \NetFusion\Core\Physics\Superconductivity\Enums\SuperconductorMaterial $mat = \NetFusion\Core\Physics\Superconductivity\Enums\SuperconductorMaterial::YBCO,
        float $temperatureK = 77.0,
        float $appliedFieldT = 1.0
    ): array {
        return supercond()->evaluateState($mat, $temperatureK, $appliedFieldT);
    }
}

if (!function_exists('supercond_vortex')) {
    /**
     * Evaluate Abrikosov vortex lattice density and pinning force.
     *
     * @return array
     */
    function supercond_vortex(
        float $appliedFieldTesla,
        float $transportCurrentDensity = 1.0e9,
        float $pinningForceDensity = 1.0e9
    ): array {
        return supercond()->getVortexLattice()->evaluateVortexPinning($appliedFieldTesla, $transportCurrentDensity, $pinningForceDensity);
    }
}

if (!function_exists('supercond_squid')) {
    /**
     * Create or read a DC-SQUID magnetometer.
     *
     * @return \NetFusion\Core\Physics\Superconductivity\Josephson\DCSquidMagnetometer
     */
    function supercond_squid(float $loopArea = 1e-8, float $loopInductance = 100e-12): \NetFusion\Core\Physics\Superconductivity\Josephson\DCSquidMagnetometer {
        return supercond()->createSquid($loopArea, $loopInductance);
    }
}

// ---------------------------------------------------------------------------
// Phase 119: Quantum Thermodynamics, Open Quantum Systems & Quantum Heat Engines
// ---------------------------------------------------------------------------

if (!function_exists('qtherm')) {
    function qtherm(): \NetFusion\Core\Physics\QuantumThermodynamics\QuantumThermodynamicsManager {
        global $app;
        if ($app && $app->has('qtherm')) {
            return $app->make('qtherm');
        }
        $mgr = new \NetFusion\Core\Physics\QuantumThermodynamics\QuantumThermodynamicsManager();
        if ($app) {
            $app->instance('qtherm', $mgr);
        }
        return $mgr;
    }
}

if (!function_exists('qtherm_lindblad')) {
    /**
     * Evolve a qubit under the Lindblad master equation.
     *
     * @param float $omega_hz    Transition frequency (rad/s)
     * @param array $dissipators [[DissipationChannel, float $gamma_hz], ...]
     * @param float $dt          Timestep (seconds)
     * @param int   $steps       Number of RK4 integration steps
     * @return \NetFusion\Core\Physics\QuantumThermodynamics\State\DensityMatrix
     */
    function qtherm_lindblad(float $omega_hz, array $dissipators = [], float $dt = 1e-9, int $steps = 100): \NetFusion\Core\Physics\QuantumThermodynamics\State\DensityMatrix {
        return qtherm()->lindblad($omega_hz, $dissipators, $dt, $steps);
    }
}

if (!function_exists('qtherm_engine')) {
    /**
     * Run a Quantum Otto heat engine cycle.
     *
     * @param float $omegaCold   Cold stroke frequency (rad/s)
     * @param float $omegaHot    Hot stroke frequency (rad/s)
     * @param float $tempCold    Cold bath temperature (K)
     * @param float $tempHot     Hot bath temperature (K)
     * @return array             Otto cycle result array
     */
    function qtherm_engine(float $omegaCold, float $omegaHot, float $tempCold, float $tempHot): array {
        return qtherm()->otto($omegaCold, $omegaHot, $tempCold, $tempHot);
    }
}

if (!function_exists('qtherm_landauer')) {
    /**
     * Compute the Landauer minimum erasure heat dissipation.
     *
     * @param float $bits         Number of bits to erase
     * @param float $temperatureK Bath temperature (K)
     * @return array              Landauer erasure result
     */
    function qtherm_landauer(float $bits = 1.0, float $temperatureK = 300.0): array {
        return qtherm()->landauer($bits, $temperatureK);
    }
}

// ---------------------------------------------------------------------------
// Phase 120: NetFusion Framework Official v1.0.0 Global GA Release Fabric
// ---------------------------------------------------------------------------

if (!function_exists('netfusion_version')) {
    /**
     * Retrieve the official NetFusion Framework version string.
     */
    function netfusion_version(): string {
        return \NetFusion\Core\Foundation\Release\FrameworkReleaseManifest::VERSION;
    }
}

if (!function_exists('netfusion_manifest')) {
    /**
     * Retrieve the complete release manifest metadata array.
     */
    function netfusion_manifest(): array {
        return \NetFusion\Core\Foundation\Release\FrameworkReleaseManifest::getReleaseInfo();
    }
}

if (!function_exists('netfusion_verify')) {
    /**
     * Run Golden Master architectural self-inspection.
     */
    function netfusion_verify(?string $basePath = null): array {
        $verifier = new \NetFusion\Core\Foundation\Release\GoldenMasterVerifier();
        return $verifier->verifyAll($basePath);
    }
}

// ---------------------------------------------------------------------------
// DotNetBD License CMS (API Engine v5.1.8) Integration Fabric
// ---------------------------------------------------------------------------

if (!function_exists('license')) {
    /**
     * Retrieve the LicenseManager instance.
     */
    function license(): \NetFusion\Core\Foundation\License\LicenseManager {
        global $app;
        if ($app && $app->has('license')) {
            return $app->make('license');
        }
        $mgr = new \NetFusion\Core\Foundation\License\LicenseManager();
        if ($app) {
            $app->instance('license', $mgr);
        }
        return $mgr;
    }
}

if (!function_exists('license_active')) {
    /**
     * Quickly determine if the current installation has an active valid license.
     */
    function license_active(): bool {
        $status = license()->status();
        return !empty($status['is_active']);
    }
}

if (!function_exists('license_check')) {
    /**
     * Validate current license status (cached for 24h).
     */
    function license_check(bool $forceRemote = false): array {
        return license()->validate($forceRemote);
    }
}
























































