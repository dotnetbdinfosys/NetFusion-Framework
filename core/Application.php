<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Application.php
| Module    : Core
| Namespace : NetFusion\Core
| Purpose   : Central application container and lifecycle manager extending PSR-11 DI Container.
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
| File      : core/Application.php
| Module    : Core
| Namespace : NetFusion\Core
| Purpose   : Central application container and lifecycle manager extending PSR-11 DI Container.
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
| File      : core/Application.php
| Module    : Core
| Namespace : NetFusion\Core
| Purpose   : Central application container and lifecycle manager extending PSR-11 DI Container.
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

namespace NetFusion\Core;

use NetFusion\Core\Config\Config;
use NetFusion\Core\Config\Env;
use NetFusion\Core\Container\Container;
use NetFusion\Core\Container\ContainerInterface;

/**
 * NetFusion Application
 *
 * The central bootstrap class and lifecycle manager for the framework.
 * Responsibilities:
 *
 *  1. Initialize directory paths (base, core, app, config, storage, public).
 *  2. Parse the .env file.
 *  3. Configure PHP runtime settings (error reporting, timezone, encoding).
 *  4. Register core services into the DI Container (Config, Logger, etc.).
 *  5. Boot registered Service Providers.
 *  6. Expose path helpers and framework metadata.
 *
 * The Application class itself IS the DI Container (it extends Container),
 * making it the central service locator for the entire application.
 *
 * @package NetFusion\Core
 * @version 1.0.0
 */
class Application extends Container
{
    /**
     * NetFusion Framework version.
     */
    public const VERSION = '1.1.0';

    /**
     * The absolute base path of the application (project root).
     */
    protected readonly string $basePath;

    /**
     * Registered service providers awaiting boot.
     *
     * @var array<int, ServiceProviderInterface>
     */
    protected array $providers = [];

    /**
     * Indicates whether the application has been fully booted.
     */
    protected bool $booted = false;

    /**
     * High-resolution start time for request lifecycle tracking.
     */
    protected float $startTime;

    /**
     * Bootstrap the NetFusion application.
     *
     * @param string $basePath Absolute path to the application root directory.
     */
    public function __construct(string $basePath)
    {
        $this->basePath  = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->startTime = microtime(true);

        // Register this Application instance as the shared container
        static::setInstance($this);

        // Register core path bindings immediately
        $this->registerPathBindings();

        // Load the .env file
        $this->loadEnvironment();

        // Configure PHP runtime
        $this->configureRuntime();

        // Register core framework services into the container
        $this->registerCoreBindings();
    }

    // =========================================================================
    //  Environment & Runtime Configuration
    // =========================================================================

    /**
     * Load and parse the .env environment file.
     */
    protected function loadEnvironment(): void
    {
        $env = new Env($this->basePath);
        $env->load();
    }

    /**
     * Configure PHP runtime settings based on the environment.
     * This should run as early as possible after env loading.
     */
    protected function configureRuntime(): void
    {
        $isDebug = Env::get('APP_DEBUG', false) === true;

        // Error reporting
        if ($isDebug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }

        // Timezone
        $timezone = (string) Env::get('APP_TIMEZONE', 'UTC');
        date_default_timezone_set($timezone);

        // Internal character encoding
        mb_internal_encoding('UTF-8');

        // Session security defaults (can be overridden by Session service provider)
        if (! headers_sent()) {
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.use_only_cookies', '1');
        }
    }

    // =========================================================================
    //  Core Service Bindings
    // =========================================================================

    /**
     * Register absolute path helpers into the container.
     * These allow any service to access paths without magic constants.
     */
    protected function registerPathBindings(): void
    {
        $this->instance('path',         $this->basePath);
        $this->instance('path.core',    $this->basePath . DIRECTORY_SEPARATOR . 'core');
        $this->instance('path.app',     $this->basePath . DIRECTORY_SEPARATOR . 'app');
        $this->instance('path.config',  $this->basePath . DIRECTORY_SEPARATOR . 'config');
        $this->instance('path.modules', $this->basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Modules');
        $this->instance('path.public',  $this->basePath . DIRECTORY_SEPARATOR . 'public');
        $this->instance('path.storage', $this->basePath . DIRECTORY_SEPARATOR . 'storage');
        $this->instance('path.views',   $this->basePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
        $this->instance('path.lang',    $this->basePath . DIRECTORY_SEPARATOR . 'lang');
    }

    /**
     * Register the essential core framework services.
     *
     * Everything registered here is available immediately after construction,
     * before any Service Providers have been booted.
     */
    protected function registerCoreBindings(): void
    {
        // Self-reference: allows app()->make(Application::class) to work
        $this->instance(Application::class, $this);
        $this->instance(ContainerInterface::class, $this);
        $this->instance('app', $this);

        // Config Manager — singleton, shared across the app
        $this->singleton(Config::class, function () {
            return new Config($this->configPath());
        });
        $this->instance('config', $this->make(Config::class));

        // Router — singleton, shared across the app
        $this->singleton(\NetFusion\Core\Routing\Router::class, function () {
            return new \NetFusion\Core\Routing\Router();
        });
        $this->instance('router', $this->make(\NetFusion\Core\Routing\Router::class));

        // HTTP Kernel — singleton, orchestrates request lifecycle
        $this->singleton(\NetFusion\Core\Http\Kernel::class, function () {
            return new \NetFusion\Core\Http\Kernel(
                app: $this,
                router: $this->make(\NetFusion\Core\Routing\Router::class),
            );
        });
        $this->instance('kernel', $this->make(\NetFusion\Core\Http\Kernel::class));

        // Module Repository & Dynamic Loader — singletons
        $this->singleton(\NetFusion\Core\Modules\ModuleRepository::class, function () {
            return new \NetFusion\Core\Modules\ModuleRepository();
        });
        $this->instance('modules', $this->make(\NetFusion\Core\Modules\ModuleRepository::class));

        $this->singleton(\NetFusion\Core\Modules\ModuleLoader::class, function () {
            return new \NetFusion\Core\Modules\ModuleLoader(
                app: $this,
                repository: $this->make(\NetFusion\Core\Modules\ModuleRepository::class),
            );
        });
        $this->instance('module.loader', $this->make(\NetFusion\Core\Modules\ModuleLoader::class));

        // Security: CSRF Protection
        $this->singleton(\NetFusion\Core\Security\Csrf::class, function () {
            return new \NetFusion\Core\Security\Csrf();
        });
        $this->instance('csrf', $this->make(\NetFusion\Core\Security\Csrf::class));

        // Database Manager — multi-connection & tenancy
        $this->singleton(\NetFusion\Core\Database\DatabaseManager::class, function () {
            return new \NetFusion\Core\Database\DatabaseManager($this);
        });
        $this->instance('db', $this->make(\NetFusion\Core\Database\DatabaseManager::class));

        // Multi-Tenancy Manager
        $this->singleton(\NetFusion\Core\Tenancy\TenantManager::class, function () {
            return new \NetFusion\Core\Tenancy\TenantManager(
                app: $this,
                db : $this->make(\NetFusion\Core\Database\DatabaseManager::class),
            );
        });
        $this->singleton('tenant', function () {
            return $this->make(\NetFusion\Core\Tenancy\TenantManager::class);
        });
        $this->singleton('tenancy', function () {
            return $this->make(\NetFusion\Core\Tenancy\TenantManager::class);
        });
        $this->singleton('tenant.manager', function () {
            return $this->make(\NetFusion\Core\Tenancy\TenantManager::class);
        });

        // HMVC View Factory
        $this->singleton(\NetFusion\Core\View\ViewFactory::class, function () {
            return new \NetFusion\Core\View\ViewFactory($this);
        });
        $this->instance('view', $this->make(\NetFusion\Core\View\ViewFactory::class));

        // Event Dispatcher
        $this->singleton(\NetFusion\Core\Events\Dispatcher::class, function () {
            return new \NetFusion\Core\Events\Dispatcher($this);
        });
        $this->instance('events', $this->make(\NetFusion\Core\Events\Dispatcher::class));

        // Session Manager
        $this->singleton(\NetFusion\Core\Session\Session::class, function () {
            return new \NetFusion\Core\Session\Session();
        });
        $this->instance('session', $this->make(\NetFusion\Core\Session\Session::class));

        // Multi-Guard Authentication Manager
        $this->singleton(\NetFusion\Core\Auth\AuthManager::class, function () {
            return new \NetFusion\Core\Auth\AuthManager($this);
        });
        $this->instance('auth', $this->make(\NetFusion\Core\Auth\AuthManager::class));

        // JWT Token Manager — Zero-dependency HS256 JWT engine
        $this->singleton(\NetFusion\Core\Auth\JwtTokenManager::class, function () {
            $secret = env('JWT_SECRET', 'netfusion-default-secret-key-change-in-production-32ch');
            $ttl    = (int) env('JWT_TTL', 3600);
            return new \NetFusion\Core\Auth\JwtTokenManager($secret, $ttl);
        });
        $this->instance('jwt', $this->make(\NetFusion\Core\Auth\JwtTokenManager::class));

        // Rate Limiter — Sliding window request throttling
        $this->singleton(\NetFusion\Core\Http\RateLimiter::class, function () {
            return new \NetFusion\Core\Http\RateLimiter(
                maxAttempts: (int) env('RATE_LIMIT_MAX', 60),
                decaySeconds: (int) env('RATE_LIMIT_DECAY', 60),
            );
        });
        $this->instance('rate.limiter', $this->make(\NetFusion\Core\Http\RateLimiter::class));

        // Queue Manager — Asynchronous background job processing
        $this->singleton(\NetFusion\Core\Queue\QueueManager::class, function () {
            return new \NetFusion\Core\Queue\QueueManager($this);
        });
        $this->singleton('queue', function () {
            return $this->make(\NetFusion\Core\Queue\QueueManager::class);
        });

        // Failed Job Provider
        $this->singleton(\NetFusion\Core\Queue\FailedJobProvider::class, function () {
            $db = $this->make(\NetFusion\Core\Database\DatabaseManager::class)->connection();
            return new \NetFusion\Core\Queue\FailedJobProvider($db);
        });
        $this->singleton('queue.failed', function () {
            return $this->make(\NetFusion\Core\Queue\FailedJobProvider::class);
        });

        // Cache Manager — Multi-store PSR-16 compliant caching layer
        $this->singleton(\NetFusion\Core\Cache\CacheManager::class, function () {
            return new \NetFusion\Core\Cache\CacheManager($this);
        });
        $this->singleton('cache', function () {
            return $this->make(\NetFusion\Core\Cache\CacheManager::class);
        });
        $this->singleton(\NetFusion\Core\Cache\Contracts\CacheInterface::class, function () {
            return $this->make('cache');
        });

        // Mailer Engine — Multi-transport zero-dependency email delivery
        $this->singleton(\NetFusion\Core\Mail\Mailer::class, function () {
            return new \NetFusion\Core\Mail\Mailer($this);
        });
        $this->singleton('mailer', function () {
            return $this->make(\NetFusion\Core\Mail\Mailer::class);
        });

        // Notification Manager — Multi-channel notification dispatcher
        $this->singleton(\NetFusion\Core\Notifications\NotificationManager::class, function () {
            return new \NetFusion\Core\Notifications\NotificationManager($this);
        });
        $this->singleton('notifications', function () {
            return $this->make(\NetFusion\Core\Notifications\NotificationManager::class);
        });
        $this->singleton('notification', function () {
            return $this->make(\NetFusion\Core\Notifications\NotificationManager::class);
        });

        // Storage Manager — Multi-disk filesystem storage engine
        $this->singleton(\NetFusion\Core\Filesystem\StorageManager::class, function () {
            return new \NetFusion\Core\Filesystem\StorageManager($this);
        });
        $this->singleton('storage', function () {
            return $this->make(\NetFusion\Core\Filesystem\StorageManager::class);
        });

        // Webhook Manager — Enterprise outgoing webhook event bus
        $this->singleton(\NetFusion\Core\Webhooks\WebhookManager::class, function () {
            return new \NetFusion\Core\Webhooks\WebhookManager($this);
        });
        $this->singleton('webhook', function () {
            return $this->make(\NetFusion\Core\Webhooks\WebhookManager::class);
        });

        // Audit Manager — Enterprise audit trail and revision tracker
        $this->singleton(\NetFusion\Core\Audit\AuditManager::class, function () {
            return new \NetFusion\Core\Audit\AuditManager();
        });
        $this->singleton('audit', function () {
            return $this->make(\NetFusion\Core\Audit\AuditManager::class);
        });

        // Automated Backup & Disaster Recovery Manager
        $this->singleton(\NetFusion\Core\Backup\BackupManager::class, function () {
            return new \NetFusion\Core\Backup\BackupManager($this->storagePath('backups'));
        });
        $this->singleton('backup', function () {
            return $this->make(\NetFusion\Core\Backup\BackupManager::class);
        });

        // Internationalization (i18n) & Localization (L10n) Translator
        $this->singleton(\NetFusion\Core\Translation\Translator::class, function () {
            $loader = new \NetFusion\Core\Translation\FileLoader($this->langPath());
            $locale = (string) Env::get('APP_LOCALE', 'en');
            $fallback = (string) Env::get('APP_FALLBACK_LOCALE', 'en');
            return new \NetFusion\Core\Translation\Translator($loader, $locale, $fallback);
        });
        $this->singleton('translator', function () {
            return $this->make(\NetFusion\Core\Translation\Translator::class);
        });

        // Incoming Webhook Gateway
        $this->singleton(\NetFusion\Core\Webhooks\Incoming\WebhookGateway::class, function () {
            return new \NetFusion\Core\Webhooks\Incoming\WebhookGateway($this);
        });
        $this->singleton('webhook.gateway', function () {
            return $this->make(\NetFusion\Core\Webhooks\Incoming\WebhookGateway::class);
        });

        // Real-Time Event Broadcasting & SSE Manager
        $this->singleton(\NetFusion\Core\Broadcasting\BroadcastManager::class, function () {
            return new \NetFusion\Core\Broadcasting\BroadcastManager($this);
        });
        $this->singleton('broadcast', function () {
            return $this->make(\NetFusion\Core\Broadcasting\BroadcastManager::class);
        });

        // Personal Access Token (PAT) Manager
        $this->singleton(\NetFusion\Core\Auth\Tokens\TokenManager::class, function () {
            $db = $this->has(\NetFusion\Core\Database\DatabaseManager::class)
                ? $this->make(\NetFusion\Core\Database\DatabaseManager::class)
                : null;
            return new \NetFusion\Core\Auth\Tokens\TokenManager($db);
        });
        $this->singleton('token.manager', function () {
            return $this->make(\NetFusion\Core\Auth\Tokens\TokenManager::class);
        });

        // Token Bucket & Cache-backed Rate Limiter
        $this->singleton(\NetFusion\Core\Http\RateLimiting\RateLimiter::class, function () {
            $cache = $this->has('cache') ? $this->make('cache') : null;
            return new \NetFusion\Core\Http\RateLimiting\RateLimiter($cache);
        });

        // OpenTelemetry-aligned Distributed Tracing
        $this->singleton(\NetFusion\Core\Tracing\Tracer::class, function () {
            return new \NetFusion\Core\Tracing\Tracer();
        });
        $this->singleton('tracer', function () {
            return $this->make(\NetFusion\Core\Tracing\Tracer::class);
        });

        // Application Performance Monitoring (APM)
        $this->singleton(\NetFusion\Core\Tracing\ApmManager::class, function () {
            return new \NetFusion\Core\Tracing\ApmManager();
        });
        $this->singleton('apm', function () {
            return $this->make(\NetFusion\Core\Tracing\ApmManager::class);
        });

        // Feature Flags & A/B Testing Engine
        $this->singleton(\NetFusion\Core\Features\FeatureManager::class, function () {
            $driver = null;
            if ($this->has(\NetFusion\Core\Database\DatabaseManager::class)) {
                $driver = new \NetFusion\Core\Features\Drivers\DatabaseDriver(
                    $this->make(\NetFusion\Core\Database\DatabaseManager::class)
                );
            } elseif ($this->has('cache')) {
                $driver = new \NetFusion\Core\Features\Drivers\CacheDriver($this->make('cache'));
            }
            return new \NetFusion\Core\Features\FeatureManager($this, $driver);
        });
        $this->singleton('features', function () {
            return $this->make(\NetFusion\Core\Features\FeatureManager::class);
        });

        // Enterprise Multi-Channel Full-Text Search Engine
        $this->singleton(\NetFusion\Core\Search\SearchManager::class, function () {
            return new \NetFusion\Core\Search\SearchManager($this);
        });
        $this->singleton('search', function () {
            return $this->make(\NetFusion\Core\Search\SearchManager::class);
        });

        // Enterprise Multi-Currency, FX Rates & Financial Ledger Engine
        $this->singleton(\NetFusion\Core\Currency\CurrencyManager::class, function () {
            return new \NetFusion\Core\Currency\CurrencyManager($this);
        });
        $this->singleton('currency', function () {
            return $this->make(\NetFusion\Core\Currency\CurrencyManager::class);
        });

        // Enterprise Dynamic Form Engine & Schema Builder
        $this->singleton(\NetFusion\Core\Forms\FormSchema::class, function () {
            $db = $this->has(\NetFusion\Core\Database\DatabaseManager::class)
                ? $this->make(\NetFusion\Core\Database\DatabaseManager::class)
                : null;
            return new \NetFusion\Core\Forms\FormSchema($db);
        });
        $this->singleton('form.schema', function () {
            return $this->make(\NetFusion\Core\Forms\FormSchema::class);
        });

        // Enterprise Document Generation, PDF & Excel/CSV Exporter Engine
        $this->singleton(\NetFusion\Core\Documents\DocumentManager::class, function () {
            return new \NetFusion\Core\Documents\DocumentManager($this);
        });
        $this->singleton('document', function () {
            return $this->make(\NetFusion\Core\Documents\DocumentManager::class);
        });
        $this->singleton('documents', function () {
            return $this->make(\NetFusion\Core\Documents\DocumentManager::class);
        });

        // Enterprise Workflow Automation, State Machine & Approval Pipeline Engine
        $this->singleton(\NetFusion\Core\Workflow\WorkflowManager::class, function () {
            return new \NetFusion\Core\Workflow\WorkflowManager($this);
        });
        $this->singleton('workflow', function () {
            return $this->make(\NetFusion\Core\Workflow\WorkflowManager::class);
        });
        $this->singleton('workflows', function () {
            return $this->make(\NetFusion\Core\Workflow\WorkflowManager::class);
        });

        // Enterprise Event Sourcing, Aggregate Roots & CQRS Engine
        $this->singleton(\NetFusion\Core\EventSourcing\ProjectionManager::class, function () {
            return new \NetFusion\Core\EventSourcing\ProjectionManager();
        });
        $this->singleton(\NetFusion\Core\EventSourcing\CommandBus::class, function () {
            return new \NetFusion\Core\EventSourcing\CommandBus();
        });
        $this->singleton(\NetFusion\Core\EventSourcing\QueryBus::class, function () {
            return new \NetFusion\Core\EventSourcing\QueryBus();
        });
        $this->singleton(\NetFusion\Core\EventSourcing\Contracts\EventStoreInterface::class, function () {
            $db = $this->make(\NetFusion\Core\Database\DatabaseManager::class);
            $projections = $this->make(\NetFusion\Core\EventSourcing\ProjectionManager::class);
            return new \NetFusion\Core\EventSourcing\EventStore($db, $projections);
        });
        $this->singleton(\NetFusion\Core\EventSourcing\EventStore::class, function () {
            return $this->make(\NetFusion\Core\EventSourcing\Contracts\EventStoreInterface::class);
        });
        $this->singleton(\NetFusion\Core\EventSourcing\AggregateRepository::class, function () {
            return new \NetFusion\Core\EventSourcing\AggregateRepository(
                $this->make(\NetFusion\Core\EventSourcing\Contracts\EventStoreInterface::class)
            );
        });
        $this->singleton(\NetFusion\Core\EventSourcing\EventSourcingManager::class, function () {
            return new \NetFusion\Core\EventSourcing\EventSourcingManager(
                $this->make(\NetFusion\Core\EventSourcing\Contracts\EventStoreInterface::class),
                $this->make(\NetFusion\Core\EventSourcing\ProjectionManager::class),
                $this->make(\NetFusion\Core\EventSourcing\CommandBus::class),
                $this->make(\NetFusion\Core\EventSourcing\QueryBus::class),
                $this->make(\NetFusion\Core\EventSourcing\AggregateRepository::class)
            );
        });
        $this->singleton('eventsource', function () {
            return $this->make(\NetFusion\Core\EventSourcing\EventSourcingManager::class);
        });
        $this->singleton('command.bus', function () {
            return $this->make(\NetFusion\Core\EventSourcing\CommandBus::class);
        });
        $this->singleton('query.bus', function () {
            return $this->make(\NetFusion\Core\EventSourcing\QueryBus::class);
        });

        // Enterprise Distributed Lock, Mutex & High-Concurrency Guard Engine
        $this->singleton(\NetFusion\Core\Lock\LockManager::class, function () {
            return new \NetFusion\Core\Lock\LockManager($this);
        });
        $this->singleton('lock', function () {
            return $this->make(\NetFusion\Core\Lock\LockManager::class);
        });

        // Enterprise Multi-Tenant Data Isolation & Tenant Routing Engine
        $this->singleton(\NetFusion\Core\Tenancy\TenantManager::class, function () {
            $db = $this->make(\NetFusion\Core\Database\DatabaseManager::class);
            return new \NetFusion\Core\Tenancy\TenantManager($this, $db);
        });
        $this->singleton('tenant', function () {
            return $this->make(\NetFusion\Core\Tenancy\TenantManager::class);
        });
        $this->singleton('tenancy', function () {
            return $this->make(\NetFusion\Core\Tenancy\TenantManager::class);
        });
        $this->singleton('tenant.manager', function () {
            return $this->make(\NetFusion\Core\Tenancy\TenantManager::class);
        });

        // Enterprise Real-Time WebSockets Server & Native Socket Channel Engine
        $this->singleton(\NetFusion\Core\Sockets\SocketManager::class, function () {
            return new \NetFusion\Core\Sockets\SocketManager($this);
        });
        $this->singleton('socket', function () {
            return $this->make(\NetFusion\Core\Sockets\SocketManager::class);
        });
        $this->singleton('websocket', function () {
            return $this->make(\NetFusion\Core\Sockets\SocketManager::class);
        });

        // Enterprise GraphQL Engine, SDL Schema Builder & Query Execution Pipeline
        $this->singleton(\NetFusion\Core\GraphQL\GraphQLManager::class, function () {
            return new \NetFusion\Core\GraphQL\GraphQLManager($this);
        });
        $this->singleton('graphql', function () {
            return $this->make(\NetFusion\Core\GraphQL\GraphQLManager::class);
        });

        // Dynamic Microservices Service Mesh, RPC Engine & Circuit Breaker
        $this->singleton(\NetFusion\Core\RPC\RpcManager::class, function () {
            return new \NetFusion\Core\RPC\RpcManager($this);
        });
        $this->singleton('rpc', function () {
            return $this->make(\NetFusion\Core\RPC\RpcManager::class);
        });
        $this->singleton('service.mesh', function () {
            return $this->make(\NetFusion\Core\RPC\RpcManager::class);
        });

        // Enterprise AI Agent Engine, LLM Tool Calling & Vector Memory Pipeline
        $this->singleton(\NetFusion\Core\AI\AiManager::class, function () {
            return new \NetFusion\Core\AI\AiManager($this);
        });
        $this->singleton('ai', function () {
            return $this->make(\NetFusion\Core\AI\AiManager::class);
        });
        $this->singleton('ai.agent', function () {
            return $this->make(\NetFusion\Core\AI\AiManager::class);
        });

        // Enterprise Saga Orchestrator, Distributed Transactions & Compensation Engine
        $this->singleton(\NetFusion\Core\Saga\SagaManager::class, function () {
            return new \NetFusion\Core\Saga\SagaManager($this);
        });
        $this->singleton('saga', function () {
            return $this->make(\NetFusion\Core\Saga\SagaManager::class);
        });

        // Enterprise PBAC, ABAC & ReBAC (Google Zanzibar) Authorization Engine
        $this->singleton(\NetFusion\Core\Auth\Policy\AccessManager::class, function () {
            return new \NetFusion\Core\Auth\Policy\AccessManager($this);
        });
        $this->singleton('access.manager', function () {
            return $this->make(\NetFusion\Core\Auth\Policy\AccessManager::class);
        });
        $this->singleton('policy', function () {
            return $this->make(\NetFusion\Core\Auth\Policy\AccessManager::class)->engine();
        });
        $this->singleton('rebac', function () {
            return $this->make(\NetFusion\Core\Auth\Policy\AccessManager::class)->rebac();
        });

        // Enterprise OpenTelemetry Distributed Tracing, Metrics & Prometheus Exporter Engine
        $this->singleton(\NetFusion\Core\Telemetry\TelemetryManager::class, function () {
            return new \NetFusion\Core\Telemetry\TelemetryManager($this);
        });
        $this->singleton('telemetry', function () {
            return $this->make(\NetFusion\Core\Telemetry\TelemetryManager::class);
        });
        $this->singleton('metrics', function () {
            return $this->make(\NetFusion\Core\Telemetry\TelemetryManager::class);
        });

        // Enterprise WebAuthn / Passkeys & FIDO2 Biometric Authentication Engine
        $this->singleton(\NetFusion\Core\Auth\WebAuthn\WebAuthnManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('webauthn', []) : [];
            $storeDriver = $config['driver'] ?? 'database';
            $table = $config['table'] ?? 'netfusion_webauthn_credentials';

            $store = match ($storeDriver) {
                'array' => new \NetFusion\Core\Auth\WebAuthn\Store\ArrayCredentialStore(),
                default => $this->has(\NetFusion\Core\Database\DatabaseManager::class)
                    ? new \NetFusion\Core\Auth\WebAuthn\Store\DatabaseCredentialStore($this->make(\NetFusion\Core\Database\DatabaseManager::class)->connection(), $table)
                    : new \NetFusion\Core\Auth\WebAuthn\Store\ArrayCredentialStore(),
            };

            return new \NetFusion\Core\Auth\WebAuthn\WebAuthnManager(
                store: $store,
                config: $config
            );
        });
        $this->singleton('webauthn', function () {
            return $this->make(\NetFusion\Core\Auth\WebAuthn\WebAuthnManager::class);
        });
        $this->singleton('passkey', function () {
            return $this->make(\NetFusion\Core\Auth\WebAuthn\WebAuthnManager::class);
        });

        // Enterprise Multi-Region Edge Data Replication, CRDT & Geo-Routing Engine
        $this->singleton(\NetFusion\Core\Data\Replication\ReplicationManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('replication', []) : [];
            $storeDriver = $config['driver'] ?? 'database';
            $table = $config['table'] ?? 'netfusion_replication_changelog';

            $store = match ($storeDriver) {
                'array' => new \NetFusion\Core\Data\Replication\Store\ArrayReplicationStore(),
                default => $this->has(\NetFusion\Core\Database\DatabaseManager::class)
                    ? new \NetFusion\Core\Data\Replication\Store\DatabaseReplicationStore($this->make(\NetFusion\Core\Database\DatabaseManager::class)->connection(), $table)
                    : new \NetFusion\Core\Data\Replication\Store\ArrayReplicationStore(),
            };

            return new \NetFusion\Core\Data\Replication\ReplicationManager(
                app: $this,
                store: $store,
                config: $config
            );
        });
        $this->singleton('replication', function () {
            return $this->make(\NetFusion\Core\Data\Replication\ReplicationManager::class);
        });

        // Enterprise Real-Time Collaborative Document Editing, OT & WebRTC Signaling Engine
        $this->singleton(\NetFusion\Core\Collaboration\CollabManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('collab', []) : [];
            $storeDriver = $config['driver'] ?? 'database';

            $store = match ($storeDriver) {
                'array' => new \NetFusion\Core\Collaboration\Document\Store\ArrayDocumentStore(),
                default => $this->has(\NetFusion\Core\Database\DatabaseManager::class)
                    ? new \NetFusion\Core\Collaboration\Document\Store\DatabaseDocumentStore($this->make(\NetFusion\Core\Database\DatabaseManager::class)->connection())
                    : new \NetFusion\Core\Collaboration\Document\Store\ArrayDocumentStore(),
            };

            return new \NetFusion\Core\Collaboration\CollabManager(
                store: $store
            );
        });
        $this->singleton('collab', function () {
            return $this->make(\NetFusion\Core\Collaboration\CollabManager::class);
        });

        // Enterprise Time-Series Database, Anomaly Detection & Predictive Auto-Scaling Engine
        $this->singleton(\NetFusion\Core\Data\TimeSeries\TimeSeriesManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('timeseries', []) : [];
            $storeDriver = $config['driver'] ?? 'database';

            $store = match ($storeDriver) {
                'array' => new \NetFusion\Core\Data\TimeSeries\Store\ArrayTimeSeriesStore(),
                default => $this->has(\NetFusion\Core\Database\DatabaseManager::class)
                    ? new \NetFusion\Core\Data\TimeSeries\Store\DatabaseTimeSeriesStore($this->make(\NetFusion\Core\Database\DatabaseManager::class)->connection())
                    : new \NetFusion\Core\Data\TimeSeries\Store\ArrayTimeSeriesStore(),
            };

            return new \NetFusion\Core\Data\TimeSeries\TimeSeriesManager(
                store: $store
            );
        });
        $this->singleton('tsdb', function () {
            return $this->make(\NetFusion\Core\Data\TimeSeries\TimeSeriesManager::class);
        });

        // Enterprise Zero-Trust Secret Management, Envelope Encryption & Dynamic Key Rotation Vault
        $this->singleton(\NetFusion\Core\Security\Vault\VaultManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('vault', []) : [];
            $storeDriver = $config['driver'] ?? 'database';
            $masterKeys = $config['master_keys'] ?? [1 => env('VAULT_MASTER_KEY', env('APP_KEY', 'default-netfusion-master-key-32-chars!'))];
            $activeVer = (int)($config['active_key_version'] ?? 1);

            $keyProvider = new \NetFusion\Core\Security\Vault\Encryption\LocalKeyProvider($masterKeys, $activeVer);

            $store = match ($storeDriver) {
                'array' => new \NetFusion\Core\Security\Vault\Store\ArrayVaultStore(),
                default => $this->has(\NetFusion\Core\Database\DatabaseManager::class)
                    ? new \NetFusion\Core\Security\Vault\Store\DatabaseVaultStore($this->make(\NetFusion\Core\Database\DatabaseManager::class)->connection())
                    : new \NetFusion\Core\Security\Vault\Store\ArrayVaultStore(),
            };

            return new \NetFusion\Core\Security\Vault\VaultManager(
                store: $store,
                keyProvider: $keyProvider
            );
        });
        $this->singleton('vault', function () {
            return $this->make(\NetFusion\Core\Security\Vault\VaultManager::class);
        });

        // Enterprise Global Feature Flagging, Multivariate Canary Rollouts & Real-Time Experimentation Engine
        $this->singleton(\NetFusion\Core\Experimentation\ExperimentManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('experimentation', []) : [];
            $storeDriver = $config['default'] ?? 'database';

            $store = match ($storeDriver) {
                'array' => new \NetFusion\Core\Experimentation\Store\ArrayExperimentStore(),
                default => $this->has(\NetFusion\Core\Database\DatabaseManager::class)
                    ? new \NetFusion\Core\Experimentation\Store\DatabaseExperimentStore($this->make(\NetFusion\Core\Database\DatabaseManager::class)->connection())
                    : new \NetFusion\Core\Experimentation\Store\ArrayExperimentStore(),
            };

            return new \NetFusion\Core\Experimentation\ExperimentManager(
                store: $store,
                app: $this
            );
        });
        $this->singleton('experiment', function () {
            return $this->make(\NetFusion\Core\Experimentation\ExperimentManager::class);
        });
        $this->singleton('experimentation', function () {
            return $this->make(\NetFusion\Core\Experimentation\ExperimentManager::class);
        });

        // Enterprise Distributed Actor Model, Virtual Actors & Reactive Message Mesh Engine
        $this->singleton(\NetFusion\Core\Actors\ActorSystem::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('actors', []) : [];
            $systemName = $config['name'] ?? 'netfusion-system';
            $driver = $config['driver'] ?? 'database';

            $store = match ($driver) {
                'array' => new \NetFusion\Core\Actors\Persistence\Store\ArrayActorStateStore(),
                default => $this->has(\NetFusion\Core\Database\DatabaseManager::class)
                    ? new \NetFusion\Core\Actors\Persistence\Store\DatabaseActorStateStore($this->make(\NetFusion\Core\Database\DatabaseManager::class)->connection())
                    : new \NetFusion\Core\Actors\Persistence\Store\ArrayActorStateStore(),
            };

            return new \NetFusion\Core\Actors\ActorSystem(
                name: $systemName,
                stateStore: $store,
                app: $this
            );
        });
        $this->singleton('actors', function () {
            return $this->make(\NetFusion\Core\Actors\ActorSystem::class);
        });
        $this->singleton('actor.system', function () {
            return $this->make(\NetFusion\Core\Actors\ActorSystem::class);
        });

        // Enterprise Real-Time Complex Event Processing (CEP) & Streaming Pipeline Engine
        $this->singleton(\NetFusion\Core\CEP\CEPManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('cep', []) : [];
            $storeDriver = $config['default_store'] ?? 'database';

            $store = new \NetFusion\Core\CEP\Store\ArrayCEPStore();
            if ($storeDriver === 'database' && $this->has(\NetFusion\Core\Database\DatabaseManager::class)) {
                try {
                    $conn = $this->make(\NetFusion\Core\Database\DatabaseManager::class)->connection();
                    $store = new \NetFusion\Core\CEP\Store\DatabaseCEPStore($conn);
                } catch (\Throwable) {
                    $store = new \NetFusion\Core\CEP\Store\ArrayCEPStore();
                }
            }

            return new \NetFusion\Core\CEP\CEPManager(
                store: $store
            );
        });
        $this->singleton('cep', function () {
            return $this->make(\NetFusion\Core\CEP\CEPManager::class);
        });
        $this->singleton('cep.manager', function () {
            return $this->make(\NetFusion\Core\CEP\CEPManager::class);
        });

        // Enterprise Zero-Trust Service Mesh, Sidecar Proxy & Distributed mTLS Policy Gateway
        $this->singleton(\NetFusion\Core\ServiceMesh\ServiceMeshManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('mesh', []) : [];
            $trustDomain = (string) ($config['trust_domain'] ?? 'cluster.local');
            $storeDriver = (string) ($config['default_store'] ?? 'database');

            $store = new \NetFusion\Core\ServiceMesh\Store\ArrayMeshStore();
            if ($storeDriver === 'database' && $this->has(\NetFusion\Core\Database\DatabaseManager::class)) {
                try {
                    $conn = $this->make(\NetFusion\Core\Database\DatabaseManager::class)->connection();
                    $store = new \NetFusion\Core\ServiceMesh\Store\DatabaseMeshStore($conn);
                } catch (\Throwable) {
                    $store = new \NetFusion\Core\ServiceMesh\Store\ArrayMeshStore();
                }
            }

            return new \NetFusion\Core\ServiceMesh\ServiceMeshManager(
                trustDomain: $trustDomain,
                store: $store
            );
        });
        $this->singleton('mesh', function () {
            return $this->make(\NetFusion\Core\ServiceMesh\ServiceMeshManager::class);
        });
        $this->singleton('service_mesh', function () {
            return $this->make(\NetFusion\Core\ServiceMesh\ServiceMeshManager::class);
        });

        // Enterprise Dynamic Business Rule Engine, RETE Algorithm & DMN 1.3 Engine
        $this->singleton(\NetFusion\Core\RuleEngine\RuleEngineManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('rules', []) : [];
            $storeDriver = (string) ($config['store'] ?? 'array');

            $store = new \NetFusion\Core\RuleEngine\Store\ArrayRuleStore();
            if ($storeDriver === 'database' && $this->has(\NetFusion\Core\Database\DatabaseManager::class)) {
                try {
                    $conn = $this->make(\NetFusion\Core\Database\DatabaseManager::class)->connection();
                    $store = new \NetFusion\Core\RuleEngine\Store\DatabaseRuleStore($conn);
                } catch (\Throwable) {
                    $store = new \NetFusion\Core\RuleEngine\Store\ArrayRuleStore();
                }
            }

            return new \NetFusion\Core\RuleEngine\RuleEngineManager(
                store: $store
            );
        });
        $this->singleton('rule_engine', function () {
            return $this->make(\NetFusion\Core\RuleEngine\RuleEngineManager::class);
        });
        $this->singleton('rules', function () {
            return $this->make(\NetFusion\Core\RuleEngine\RuleEngineManager::class);
        });

        // Enterprise Semantic Vector Search, HNSW Graph Indexing & RAG Knowledge Pipeline Engine
        $this->singleton(\NetFusion\Core\Vector\VectorEngineManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('vector', []) : [];
            $storeDriver = (string) ($config['store'] ?? 'array');

            $store = new \NetFusion\Core\Vector\Store\ArrayVectorStore();
            if ($storeDriver === 'database' && $this->has(\NetFusion\Core\Database\DatabaseManager::class)) {
                try {
                    $conn = $this->make(\NetFusion\Core\Database\DatabaseManager::class)->connection();
                    $embedMgr = new \NetFusion\Core\Vector\Embedding\EmbeddingManager($config['embeddings'] ?? []);
                    $store = new \NetFusion\Core\Vector\Store\DatabaseVectorStore($conn, $embedMgr->driver());
                } catch (\Throwable) {
                    $store = new \NetFusion\Core\Vector\Store\ArrayVectorStore();
                }
            }

            return new \NetFusion\Core\Vector\VectorEngineManager(
                config: $config,
                store: $store
            );
        });
        $this->singleton('vector', function () {
            return $this->make(\NetFusion\Core\Vector\VectorEngineManager::class);
        });
        $this->singleton('vector.manager', function () {
            return $this->make(\NetFusion\Core\Vector\VectorEngineManager::class);
        });

        // Enterprise Web Application Firewall (WAF) & Layer 7 DDoS Mitigation Engine
        $this->singleton(\NetFusion\Core\WAF\WAFManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('waf', []) : [];
            $storeDriver = (string) ($config['store'] ?? 'database');

            $store = new \NetFusion\Core\WAF\Store\ArrayWAFStore();
            if ($storeDriver === 'database' && $this->has(\NetFusion\Core\Database\DatabaseManager::class)) {
                try {
                    $conn = $this->make(\NetFusion\Core\Database\DatabaseManager::class)->connection();
                    $store = new \NetFusion\Core\WAF\Store\DatabaseWAFStore($conn);
                } catch (\Throwable) {
                    $store = new \NetFusion\Core\WAF\Store\ArrayWAFStore();
                }
            }

            return new \NetFusion\Core\WAF\WAFManager(
                config: $config,
                store: $store
            );
        });
        $this->singleton('waf', function () {
            return $this->make(\NetFusion\Core\WAF\WAFManager::class);
        });
        $this->singleton('waf.manager', function () {
            return $this->make(\NetFusion\Core\WAF\WAFManager::class);
        });

        // Enterprise Data Warehouse, OLAP Columnar Storage & Dynamic BI Pivot Engine
        $this->singleton(\NetFusion\Core\DataWarehouse\DataWarehouseManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('datawarehouse', []) : [];
            $pdo = null;
            if ($this->has(\NetFusion\Core\Database\DatabaseManager::class)) {
                try {
                    $pdo = $this->make(\NetFusion\Core\Database\DatabaseManager::class)->connection()->getPdo();
                } catch (\Throwable) {
                    $pdo = null;
                }
            }

            return new \NetFusion\Core\DataWarehouse\DataWarehouseManager(
                config: $config,
                pdo: $pdo
            );
        });
        $this->singleton('datawarehouse', function () {
            return $this->make(\NetFusion\Core\DataWarehouse\DataWarehouseManager::class);
        });
        $this->singleton('dw', function () {
            return $this->make(\NetFusion\Core\DataWarehouse\DataWarehouseManager::class);
        });

        // Enterprise Cloud-Native Distributed Key-Value Store (etcd v3 Architecture)
        $this->singleton(\NetFusion\Core\Etcd\EtcdManager::class, function () {
            return new \NetFusion\Core\Etcd\EtcdManager();
        });
        $this->singleton('etcd', function () {
            return $this->make(\NetFusion\Core\Etcd\EtcdManager::class);
        });
        $this->singleton('etcd.manager', function () {
            return $this->make(\NetFusion\Core\Etcd\EtcdManager::class);
        });

        // Enterprise Distributed Event Streaming Platform (Apache Kafka Architecture)
        $this->singleton(\NetFusion\Core\Kafka\KafkaManager::class, function () {
            return new \NetFusion\Core\Kafka\KafkaManager();
        });
        $this->singleton('kafka', function () {
            return $this->make(\NetFusion\Core\Kafka\KafkaManager::class);
        });
        $this->singleton('kafka.manager', function () {
            return $this->make(\NetFusion\Core\Kafka\KafkaManager::class);
        });

        // Enterprise Cloud-Native Service Discovery, Health Probing & Dynamic Service Catalog (HashiCorp Consul Architecture)
        $this->singleton(\NetFusion\Core\Consul\ConsulManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('consul', []) : [];
            return new \NetFusion\Core\Consul\ConsulManager($config);
        });
        $this->singleton('consul', function () {
            return $this->make(\NetFusion\Core\Consul\ConsulManager::class);
        });
        $this->singleton('consul.manager', function () {
            return $this->make(\NetFusion\Core\Consul\ConsulManager::class);
        });

        // Enterprise Distributed Real-Time OLAP & Analytical Engine (Apache Druid & ClickHouse Architecture)
        $this->singleton(\NetFusion\Core\Analytics\AnalyticsManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('olap', []) : [];
            return new \NetFusion\Core\Analytics\AnalyticsManager($config);
        });
        $this->singleton('analytics', function () {
            return $this->make(\NetFusion\Core\Analytics\AnalyticsManager::class);
        });
        $this->singleton('druid', function () {
            return $this->make(\NetFusion\Core\Analytics\AnalyticsManager::class);
        });
        $this->singleton('olap', function () {
            return $this->make(\NetFusion\Core\Analytics\AnalyticsManager::class);
        });

        // Enterprise Distributed Consensus Key-Value Store & Distributed Coordination Service (Apache ZooKeeper Architecture)
        $this->singleton(\NetFusion\Core\ZooKeeper\ZooKeeperManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('zookeeper', []) : [];
            return new \NetFusion\Core\ZooKeeper\ZooKeeperManager($config);
        });
        $this->singleton('zookeeper', function () {
            return $this->make(\NetFusion\Core\ZooKeeper\ZooKeeperManager::class);
        });
        $this->singleton('zk', function () {
            return $this->make(\NetFusion\Core\ZooKeeper\ZooKeeperManager::class);
        });

        // Enterprise Full-Stack Micro-Frontend & Dynamic Module Federation Engine (Webpack 5 Architecture)
        $this->singleton(\NetFusion\Core\MFE\MfeManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('mfe', []) : [];
            return new \NetFusion\Core\MFE\MfeManager($config);
        });
        $this->singleton('mfe', function () {
            return $this->make(\NetFusion\Core\MFE\MfeManager::class);
        });
        $this->singleton('microfrontend', function () {
            return $this->make(\NetFusion\Core\MFE\MfeManager::class);
        });

        // Enterprise Edge WebAssembly (Wasm) Edge Computing Gateway & Serverless Worker Fabric
        $this->singleton(\NetFusion\Core\Edge\EdgeWorkerManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('edge_worker', []) : [];
            return new \NetFusion\Core\Edge\EdgeWorkerManager($config);
        });
        $this->singleton('edge', function () {
            return $this->make(\NetFusion\Core\Edge\EdgeWorkerManager::class);
        });
        $this->singleton('edge.manager', function () {
            return $this->make(\NetFusion\Core\Edge\EdgeWorkerManager::class);
        });
        $this->singleton('edge_worker', function () {
            return $this->make(\NetFusion\Core\Edge\EdgeWorkerManager::class);
        });

        // Enterprise Real-Time WebRTC Media Server, SFU & Adaptive Bitrate Video Transcoding Engine
        $this->singleton(\NetFusion\Core\Media\MediaServerManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('media_server', []) : [];
            return new \NetFusion\Core\Media\MediaServerManager($config);
        });
        $this->singleton('media', function () {
            return $this->make(\NetFusion\Core\Media\MediaServerManager::class);
        });
        $this->singleton('media.server', function () {
            return $this->make(\NetFusion\Core\Media\MediaServerManager::class);
        });
        $this->singleton('sfu', function () {
            return $this->make(\NetFusion\Core\Media\MediaServerManager::class);
        });

        // Enterprise Geospatial Engine, Spatial Indexing (R-Tree / H3) & Geo-Fencing Fabric
        $this->singleton(\NetFusion\Core\Spatial\GeoSpatialManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('geospatial', []) : [];
            return new \NetFusion\Core\Spatial\GeoSpatialManager($config);
        });
        $this->singleton('geo', function () {
            return $this->make(\NetFusion\Core\Spatial\GeoSpatialManager::class);
        });
        $this->singleton('spatial', function () {
            return $this->make(\NetFusion\Core\Spatial\GeoSpatialManager::class);
        });
        $this->singleton('geospatial', function () {
            return $this->make(\NetFusion\Core\Spatial\GeoSpatialManager::class);
        });

        // Enterprise Server-Driven UI (SDUI), Dynamic Layout Component Schema & Real-Time Client Hydration Fabric
        $this->singleton(\NetFusion\Core\SDUI\SDUIManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('sdui', []) : [];
            return new \NetFusion\Core\SDUI\SDUIManager($config);
        });
        $this->singleton('sdui', function () {
            return $this->make(\NetFusion\Core\SDUI\SDUIManager::class);
        });

        // Enterprise Multi-Modal Vector Knowledge Graph, Hybrid Semantic RAG & Dynamic Graph Reranking Engine
        $this->singleton(\NetFusion\Core\GraphRag\GraphRagManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('graph_rag', []) : [];
            return new \NetFusion\Core\GraphRag\GraphRagManager($config);
        });
        $this->singleton('graph_rag', function () {
            return $this->make(\NetFusion\Core\GraphRag\GraphRagManager::class);
        });
        // Enterprise Real-Time Collaborative CRDT & Operational Transformation (OT) Synchronizer Fabric
        $this->singleton(\NetFusion\Core\CRDT\CrdtManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('crdt', []) : [];
            return new \NetFusion\Core\CRDT\CrdtManager($config);
        });
        $this->singleton('crdt_sync', function () {
            return $this->make(\NetFusion\Core\CRDT\CrdtManager::class);
        });
        $this->singleton('crdt.sync', function () {
            return $this->make(\NetFusion\Core\CRDT\CrdtManager::class);
        });

        // Enterprise Zero-Knowledge Proof (ZKP) Engine & Verifiable Credential Privacy Fabric
        $this->singleton(\NetFusion\Core\ZKP\ZkpManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('zkp', []) : [];
            return new \NetFusion\Core\ZKP\ZkpManager($config);
        });
        $this->singleton('zkp', function () {
            return $this->make(\NetFusion\Core\ZKP\ZkpManager::class);
        });

        // Enterprise Formal Verification, SMT Solver (DPLL/T) & Symbolic Execution Engine
        $this->singleton(\NetFusion\Core\Formal\FormalManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('formal', []) : [];
            return new \NetFusion\Core\Formal\FormalManager($config);
        });
        $this->singleton('formal', function () {
            return $this->make(\NetFusion\Core\Formal\FormalManager::class);
        });
        $this->singleton('formal.manager', function () {
            return $this->make(\NetFusion\Core\Formal\FormalManager::class);
        });

        // Enterprise eBPF-Style Programmable Packet Filter & XDP Fabric
        $this->singleton(\NetFusion\Core\Network\Ebpf\EbpfManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('ebpf', []) : [];
            return new \NetFusion\Core\Network\Ebpf\EbpfManager($config);
        });
        $this->singleton('ebpf', function () {
            return $this->make(\NetFusion\Core\Network\Ebpf\EbpfManager::class);
        });
        $this->singleton('ebpf.manager', function () {
            return $this->make(\NetFusion\Core\Network\Ebpf\EbpfManager::class);
        });
        $this->singleton('xdp', function () {
            return $this->make(\NetFusion\Core\Network\Ebpf\EbpfManager::class)->getXdpPipeline();
        });

        // Enterprise WebAssembly Interface Types (WIT), Component Model & Multi-Module Orchestration
        $this->singleton(\NetFusion\Core\Wasm\Component\ComponentManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('wasm_component', []) : [];
            return new \NetFusion\Core\Wasm\Component\ComponentManager($config);
        });
        $this->singleton('wasm.component', function () {
            return $this->make(\NetFusion\Core\Wasm\Component\ComponentManager::class);
        });
        $this->singleton('wasm_component', function () {
            return $this->make(\NetFusion\Core\Wasm\Component\ComponentManager::class);
        });
        $this->singleton('wit', function () {
            return $this->make(\NetFusion\Core\Wasm\Component\ComponentManager::class);
        });

        // Enterprise Differential Privacy, Laplace/Gaussian Mechanisms & Federated Aggregation
        $this->singleton(\NetFusion\Core\Privacy\Differential\DifferentialPrivacyManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('privacy', []) : [];
            return new \NetFusion\Core\Privacy\Differential\DifferentialPrivacyManager($config);
        });
        $this->singleton('diff_privacy', function () {
            return $this->make(\NetFusion\Core\Privacy\Differential\DifferentialPrivacyManager::class);
        });
        $this->singleton('dp', function () {
            return $this->make(\NetFusion\Core\Privacy\Differential\DifferentialPrivacyManager::class);
        });
        $this->singleton('privacy', function () {
            return $this->make(\NetFusion\Core\Privacy\Differential\DifferentialPrivacyManager::class);
        });

        // Enterprise Homomorphic Encryption (BFV/CKKS), SMPC & Encrypted Compute Engine
        $this->singleton(\NetFusion\Core\Security\Homomorphic\HomomorphicManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('homomorphic', []) : [];
            return new \NetFusion\Core\Security\Homomorphic\HomomorphicManager($config);
        });
        $this->singleton('homomorphic', function () {
            return $this->make(\NetFusion\Core\Security\Homomorphic\HomomorphicManager::class);
        });
        $this->singleton('he', function () {
            return $this->make(\NetFusion\Core\Security\Homomorphic\HomomorphicManager::class);
        });
        $this->singleton('smpc', function () {
            return $this->make(\NetFusion\Core\Security\Homomorphic\HomomorphicManager::class);
        });

        // Enterprise Autonomous Self-Healing Infrastructure, RCA & Canary Remediation
        $this->singleton(\NetFusion\Core\Infrastructure\SelfHealing\SelfHealingManager::class, function () {
            return \NetFusion\Core\Infrastructure\SelfHealing\SelfHealingManager::getInstance();
        });
        $this->singleton('selfhealing', function () {
            return $this->make(\NetFusion\Core\Infrastructure\SelfHealing\SelfHealingManager::class);
        });
        $this->singleton('self_heal', function () {
            return $this->make(\NetFusion\Core\Infrastructure\SelfHealing\SelfHealingManager::class);
        });

        // Enterprise Confidential Computing Enclave, Remote Attestation & Memory Sealing
        $this->singleton(\NetFusion\Core\Security\Enclave\EnclaveManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('enclave', []) : [];
            return new \NetFusion\Core\Security\Enclave\EnclaveManager($config);
        });
        $this->singleton('enclave', function () {
            return $this->make(\NetFusion\Core\Security\Enclave\EnclaveManager::class);
        });
        $this->singleton('confidential', function () {
            return $this->make(\NetFusion\Core\Security\Enclave\EnclaveManager::class);
        });

        // Enterprise Quantum Key Distribution (QKD / BB84 & E91) & Entanglement Mesh
        $this->singleton(\NetFusion\Core\Security\Quantum\QuantumKeyManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('qkd', []) : [];
            return new \NetFusion\Core\Security\Quantum\QuantumKeyManager($config);
        });
        $this->singleton('qkd', function () {
            return $this->make(\NetFusion\Core\Security\Quantum\QuantumKeyManager::class);
        });
        $this->singleton('quantum', function () {
            return $this->make(\NetFusion\Core\Security\Quantum\QuantumKeyManager::class);
        });

        // Enterprise Neuromorphic Event-Driven Computing & Spiking Neural Network (SNN) Fabric
        $this->singleton(\NetFusion\Core\AI\Neuromorphic\NeuromorphicManager::class, function () {
            return \NetFusion\Core\AI\Neuromorphic\NeuromorphicManager::getInstance();
        });
        $this->singleton('neuromorphic', function () {
            return $this->make(\NetFusion\Core\AI\Neuromorphic\NeuromorphicManager::class);
        });
        $this->singleton('snn', function () {
            return $this->make(\NetFusion\Core\AI\Neuromorphic\NeuromorphicManager::class);
        });

        // Enterprise Autonomous Multi-Agent Swarm Intelligence & Stigmergy Fabric
        $this->singleton(\NetFusion\Core\AI\Swarm\SwarmManager::class, function () {
            return \NetFusion\Core\AI\Swarm\SwarmManager::getInstance();
        });
        $this->singleton('swarm', function () {
            return $this->make(\NetFusion\Core\AI\Swarm\SwarmManager::class);
        });
        $this->singleton('swarm.manager', function () {
            return $this->make(\NetFusion\Core\AI\Swarm\SwarmManager::class);
        });

        // Enterprise Post-Quantum Decentralized Identity (DID), W3C VC 2.0 & BBS+ Mesh Fabric
        $this->singleton(\NetFusion\Core\Security\Identity\IdentityManager::class, function () {
            return new \NetFusion\Core\Security\Identity\IdentityManager();
        });
        $this->singleton('identity', function () {
            return $this->make(\NetFusion\Core\Security\Identity\IdentityManager::class);
        });
        $this->singleton('did', function () {
            return $this->make(\NetFusion\Core\Security\Identity\IdentityManager::class);
        });

        // Enterprise Spatial Computing & WebXR Hyper-Spatial Digital Twin Fabric
        $this->singleton(\NetFusion\Core\Spatial\SpatialManager::class, function () {
            return new \NetFusion\Core\Spatial\SpatialManager();
        });
        $this->singleton('spatial', function () {
            return $this->make(\NetFusion\Core\Spatial\SpatialManager::class);
        });

        // Enterprise Holographic Storage & DNA Molecular Cold-Vault Fabric
        $this->singleton(\NetFusion\Core\Storage\Molecular\MolecularManager::class, function () {
            return \NetFusion\Core\Storage\Molecular\MolecularManager::getInstance();
        });
        $this->singleton('molecular', function () {
            return $this->make(\NetFusion\Core\Storage\Molecular\MolecularManager::class);
        });
        $this->singleton('dna', function () {
            return $this->make(\NetFusion\Core\Storage\Molecular\MolecularManager::class);
        });

        // Enterprise Brain-Computer Interface (BCI) & Neural Signal Decoding Fabric
        $this->singleton(\NetFusion\Core\Neural\Bci\BciManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('bci', []) : [];
            $rate = (float) ($config['sampling_rate'] ?? 250.0);
            $notch = (float) ($config['notch_frequency'] ?? 50.0);
            $maxImp = (float) ($config['max_electrode_impedance_kohms'] ?? 10.0);
            $relThresh = (float) ($config['neurofeedback']['relaxation_threshold'] ?? 1.5);
            $focThresh = (float) ($config['neurofeedback']['focus_threshold'] ?? 1.2);
            $hyst = (float) ($config['neurofeedback']['hysteresis'] ?? 0.1);
            $ctrl = new \NetFusion\Core\Neural\Bci\Feedback\NeuroFeedbackController($rate, $relThresh, $focThresh, $hyst);
            return new \NetFusion\Core\Neural\Bci\BciManager($rate, $notch, $maxImp, $ctrl);
        });
        $this->singleton('bci', function () {
            return $this->make(\NetFusion\Core\Neural\Bci\BciManager::class);
        });

        // Enterprise Optical Photonic Computing & Tensor Light-Interferometer Fabric
        $this->singleton(\NetFusion\Core\Photonic\PhotonicManager::class, function () {
            $config = $this->has(Config::class) ? $this->make(Config::class)->get('photonic', []) : [];
            $wlStr = (string) ($config['default_wavelength'] ?? '1550nm');
            $wl = match ($wlStr) {
                '850nm' => \NetFusion\Core\Photonic\Enums\LaserWavelength::NIR_850NM,
                '1310nm' => \NetFusion\Core\Photonic\Enums\LaserWavelength::O_BAND_1310NM,
                '2000nm' => \NetFusion\Core\Photonic\Enums\LaserWavelength::MID_IR_2000NM,
                default => \NetFusion\Core\Photonic\Enums\LaserWavelength::C_BAND_1550NM,
            };
            return new \NetFusion\Core\Photonic\PhotonicManager($wl);
        });
        $this->singleton('photonic', function () {
            return $this->make(\NetFusion\Core\Photonic\PhotonicManager::class);
        });

        // Enterprise Reconfigurable Intelligent Surfaces (RIS) & 6G THz Metamaterial Fabric
        $this->singleton(\NetFusion\Core\Wireless\Ris\RisManager::class, function () {
            return new \NetFusion\Core\Wireless\Ris\RisManager();
        });
        $this->singleton('ris', function () {
            return $this->make(\NetFusion\Core\Wireless\Ris\RisManager::class);
        });

        // Enterprise Gravitational Waveform Simulation & Spacetime Relativity Fabric
        $this->singleton(\NetFusion\Core\Relativity\RelativityManager::class, function () {
            return new \NetFusion\Core\Relativity\RelativityManager();
        });
        $this->singleton('relativity', function () {
            return $this->make(\NetFusion\Core\Relativity\RelativityManager::class);
        });

        // Enterprise Topological Data Analysis (TDA) & Persistent Homology Fabric
        $this->singleton(\NetFusion\Core\Analytics\Tda\TdaManager::class, function () {
            return new \NetFusion\Core\Analytics\Tda\TdaManager();
        });
        $this->singleton('tda', function () {
            return $this->make(\NetFusion\Core\Analytics\Tda\TdaManager::class);
        });

        // Enterprise Cognitive Architecture & Synthetic Memory (ACT-R / SOAR Fabric)
        $this->singleton(\NetFusion\Core\Cognitive\CognitiveManager::class, function () {
            return new \NetFusion\Core\Cognitive\CognitiveManager();
        });
        $this->singleton('cognitive', function () {
            return $this->make(\NetFusion\Core\Cognitive\CognitiveManager::class);
        });
    }










    // =========================================================================
    //  Service Provider Lifecycle
    // =========================================================================

    /**
     * Register a Service Provider with the application.
     * The provider's register() method is called immediately.
     *
     * @param  ServiceProviderInterface $provider
     * @return $this (fluent API)
     */
    public function register(ServiceProviderInterface $provider): static
    {
        $provider->register($this);
        $this->providers[] = $provider;
        return $this;
    }

    /**
     * Boot all registered Service Providers.
     * Should be called after all providers have been registered.
     * This is a one-time operation.
     *
     * @return $this (fluent API)
     */
    public function boot(): static
    {
        if ($this->booted) {
            return $this;
        }

        // Auto-discover and register all enabled HMVC modules
        $this->make(\NetFusion\Core\Modules\ModuleLoader::class)->registerModules();

        foreach ($this->providers as $provider) {
            $provider->boot($this);
        }

        $this->booted = true;
        return $this;
    }

    /**
     * Register and immediately boot a provider in one call.
     *
     * @param  ServiceProviderInterface $provider
     * @return $this
     */
    public function registerAndBoot(ServiceProviderInterface $provider): static
    {
        return $this->register($provider)->boot();
    }

    // =========================================================================
    //  Path Accessors
    // =========================================================================

    /**
     * Get the application base (root) path.
     *
     * @param  string $append Optional sub-path to append.
     * @return string
     */
    public function basePath(string $append = ''): string
    {
        return $append
            ? $this->basePath . DIRECTORY_SEPARATOR . ltrim($append, DIRECTORY_SEPARATOR)
            : $this->basePath;
    }

    /**
     * Get the core framework directory path.
     *
     * @param  string $append Optional sub-path to append.
     * @return string
     */
    public function corePath(string $append = ''): string
    {
        $core = $this->basePath . DIRECTORY_SEPARATOR . 'core';
        return $append ? $core . DIRECTORY_SEPARATOR . ltrim($append, DIRECTORY_SEPARATOR) : $core;
    }

    /**
     * Get the application directory path (app/).
     *
     * @param  string $append Optional sub-path to append.
     * @return string
     */
    public function appPath(string $append = ''): string
    {
        $app = $this->basePath . DIRECTORY_SEPARATOR . 'app';
        return $append ? $app . DIRECTORY_SEPARATOR . ltrim($append, DIRECTORY_SEPARATOR) : $app;
    }

    /**
     * Get the config directory path.
     *
     * @param  string $append Optional sub-path to append.
     * @return string
     */
    public function configPath(string $append = ''): string
    {
        $config = $this->basePath . DIRECTORY_SEPARATOR . 'config';
        return $append ? $config . DIRECTORY_SEPARATOR . ltrim($append, DIRECTORY_SEPARATOR) : $config;
    }

    /**
     * Get the modules directory path.
     *
     * @param  string $append Optional sub-path to append.
     * @return string
     */
    public function modulesPath(string $append = ''): string
    {
        $modules = $this->basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Modules';
        return $append ? $modules . DIRECTORY_SEPARATOR . ltrim($append, DIRECTORY_SEPARATOR) : $modules;
    }

    /**
     * Get the public web directory path.
     *
     * @param  string $append Optional sub-path to append.
     * @return string
     */
    public function publicPath(string $append = ''): string
    {
        $public = $this->basePath . DIRECTORY_SEPARATOR . 'public';
        return $append ? $public . DIRECTORY_SEPARATOR . ltrim($append, DIRECTORY_SEPARATOR) : $public;
    }

    /**
     * Get the storage directory path.
     *
     * @param  string $append Optional sub-path to append.
     * @return string
     */
    public function storagePath(string $append = ''): string
    {
        $storage = $this->basePath . DIRECTORY_SEPARATOR . 'storage';
        return $append ? $storage . DIRECTORY_SEPARATOR . ltrim($append, DIRECTORY_SEPARATOR) : $storage;
    }

    /**
     * Get the resources/views directory path.
     *
     * @param  string $append Optional sub-path to append.
     * @return string
     */
    public function viewsPath(string $append = ''): string
    {
        $views = $this->basePath
            . DIRECTORY_SEPARATOR . 'resources'
            . DIRECTORY_SEPARATOR . 'views';
        return $append ? $views . DIRECTORY_SEPARATOR . ltrim($append, DIRECTORY_SEPARATOR) : $views;
    }

    /**
     * Get the translations directory path (lang/).
     *
     * @param  string $append Optional sub-path to append.
     * @return string
     */
    public function langPath(string $append = ''): string
    {
        $lang = $this->basePath . DIRECTORY_SEPARATOR . 'lang';
        return $append ? $lang . DIRECTORY_SEPARATOR . ltrim($append, DIRECTORY_SEPARATOR) : $lang;
    }

    // =========================================================================
    //  Environment Helpers
    // =========================================================================

    /**
     * Get the current application environment (local, staging, production, etc.)
     */
    public function environment(): string
    {
        return (string) Env::get('APP_ENV', 'production');
    }

    /**
     * Check if the application is running in debug mode.
     */
    public function isDebug(): bool
    {
        return Env::get('APP_DEBUG', false) === true;
    }

    /**
     * Check if the application is running in a production environment.
     */
    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }

    /**
     * Check if the application is running from the CLI (artisan/netfusion).
     */
    public function isRunningInConsole(): bool
    {
        return \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';
    }

    // =========================================================================
    //  Metadata & Performance Diagnostics
    // =========================================================================

    /**
     * Return the framework version string.
     */
    public function version(): string
    {
        return static::VERSION;
    }

    /**
     * Get elapsed time in milliseconds since application boot.
     */
    public function elapsedTime(): float
    {
        return round((microtime(true) - $this->startTime) * 1000, 4);
    }

    /**
     * Get peak memory usage in megabytes.
     */
    public function peakMemory(): float
    {
        return round(memory_get_peak_usage(true) / 1048576, 4);
    }
}
