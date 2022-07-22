<?php

/**
 * @file classes/core/PKPContainer.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContainer
 * @ingroup core
 *
 * @brief Bootstraps Laravel services, application-level parts and creates bindings
 */

namespace PKP\core;

use APP\core\AppServiceProvider;

use Exception;
use Illuminate\Config\Repository;

use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel as KernelContract;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Console\Kernel;
use Illuminate\Log\LogServiceProvider;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use PKP\config\Config;
use PKP\Domains\Jobs\Providers\JobServiceProvider;
use PKP\file\PrivateFileManager;
use PKP\i18n\LocaleServiceProvider;
use PKP\Support\ProxyParser;

use Throwable;

class PKPContainer extends Container
{
    /**
     * @brief The base path of the application, needed for base_path helper
     */
    protected string $basePath;

    /**
     * @brief The base storage path of the application, might be used internally by Laravel
     */
    protected string $storagePath;

    /**
     * @brief Create own container instance, initialize bindings
     */
    public function __construct()
    {
        $this->basePath = BASE_SYS_DIR;
        $this->settingProxyForStreamContext();
        $this->registerBaseBindings();
        $this->registerCoreContainerAliases();
    }

    /**
     * @brief Bind the current container and set it globally
     * let helpers, facades and services know to which container refer to
     */
    protected function registerBaseBindings()
    {
        static::setInstance($this);
        $this->instance('app', $this);
        $this->instance(Container::class, $this);
        $this->instance('path', $this->basePath);
        $this->singleton(ExceptionHandler::class, function () {
            return new class() implements ExceptionHandler {
                public function shouldReport(Throwable $e)
                {
                    return true;
                }

                public function report(Throwable $e)
                {
                    error_log((string) $e->getTraceAsString());
                }

                public function render($request, Throwable $e)
                {
                    return null;
                }

                public function renderForConsole($output, Throwable $e)
                {
                    echo (string) $e;
                }
            };
        });
        $this->singleton(
            KernelContract::class,
            Kernel::class
        );

        $this->singleton(
            'queue.failer',
            function ($app) {
                return new DatabaseFailedJobProvider(
                    $app['db'],
                    config('queue.failed.database'),
                    config('queue.failed.table')
                );
            }
        );

        $this->useStoragePath((new PrivateFileManager())->getBasePath());
        Facade::setFacadeApplication($this);
    }

    /**
     * @brief Register used service providers within the container
     */
    public function registerConfiguredProviders()
    {
        // Load main settings, this should be done before registering services, e.g., it's used by Database Service
        $this->loadConfiguration();

        $this->register(new PKPEventServiceProvider($this));
        $this->register(new LogServiceProvider($this));
        $this->register(new \Illuminate\Database\DatabaseServiceProvider($this));
        $this->register(new \Illuminate\Bus\BusServiceProvider($this));
        $this->register(new \Illuminate\Queue\QueueServiceProvider($this));
        $this->register(new PKPQueueProvider());
        $this->register(new MailServiceProvider($this));
        $this->register(new AppServiceProvider($this));
        $this->register(new JobServiceProvider($this));
        $this->register(new \Illuminate\Cache\CacheServiceProvider($this));
        $this->register(new \Illuminate\Filesystem\FilesystemServiceProvider($this));
        $this->register(new \ElcoBvg\Opcache\ServiceProvider($this));
        $this->register(new LocaleServiceProvider($this));
    }

    /**
     * @param \Illuminate\Support\ServiceProvider $provider
     * @brief Simplified service registration
     */
    public function register($provider)
    {
        $provider->register();
        if (method_exists($provider, 'boot')) {
            $provider->boot();
        }
    }

    /**
     * @brief Bind aliases with contracts
     */
    public function registerCoreContainerAliases()
    {
        foreach ([
            'app' => [self::class, \Illuminate\Contracts\Container\Container::class, \Psr\Container\ContainerInterface::class],
            'config' => [\Illuminate\Config\Repository::class, \Illuminate\Contracts\Config\Repository::class],
            'cache' => [\Illuminate\Cache\CacheManager::class, \Illuminate\Contracts\Cache\Factory::class],
            'cache.store' => [\Illuminate\Cache\Repository::class, \Illuminate\Contracts\Cache\Repository::class, \Psr\SimpleCache\CacheInterface::class],
            'cache.psr6' => [\Symfony\Component\Cache\Adapter\Psr16Adapter::class, \Symfony\Component\Cache\Adapter\AdapterInterface::class, \Psr\Cache\CacheItemPoolInterface::class],
            'db' => [\Illuminate\Database\DatabaseManager::class, \Illuminate\Database\ConnectionResolverInterface::class],
            'db.connection' => [\Illuminate\Database\Connection::class, \Illuminate\Database\ConnectionInterface::class],
            'files' => [\Illuminate\Filesystem\Filesystem::class],
            'filesystem' => [\Illuminate\Filesystem\FilesystemManager::class, \Illuminate\Contracts\Filesystem\Factory::class],
            'filesystem.disk' => [\Illuminate\Contracts\Filesystem\Filesystem::class],
            'filesystem.cloud' => [\Illuminate\Contracts\Filesystem\Cloud::class],
            'maps' => [MapContainer::class, MapContainer::class],
            'events' => [\Illuminate\Events\Dispatcher::class, \Illuminate\Contracts\Events\Dispatcher::class],
            'queue' => [\Illuminate\Queue\QueueManager::class, \Illuminate\Contracts\Queue\Factory::class, \Illuminate\Contracts\Queue\Monitor::class],
            'queue.connection' => [\Illuminate\Contracts\Queue\Queue::class],
            'queue.failer' => [\Illuminate\Queue\Failed\FailedJobProviderInterface::class],
            'log' => [\Illuminate\Log\LogManager::class, \Psr\Log\LoggerInterface::class],
        ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    /**
     * @brief Bind and load container configurations
     * usage from Facade, see Illuminate\Support\Facades\Config
     */
    protected function loadConfiguration()
    {
        $items = [];

        // Database connection
        $driver = stripos(Config::getVar('database', 'driver'), 'postgres') === 0 ? 'pgsql' : 'mysql';
        $items['database']['default'] = $driver;
        $items['database']['connections'][$driver] = [
            'driver' => $driver,
            'host' => Config::getVar('database', 'host'),
            'database' => Config::getVar('database', 'name'),
            'username' => Config::getVar('database', 'username'),
            'port' => Config::getVar('database', 'port'),
            'unix_socket' => Config::getVar('database', 'unix_socket'),
            'password' => Config::getVar('database', 'password'),
            'charset' => Config::getVar('i18n', 'connection_charset', 'utf8'),
            'collation' => Config::getVar('database', 'collation', 'utf8_general_ci'),
        ];

        // Queue connection
        $items['queue']['default'] = 'database';
        $items['queue']['connections']['sync']['driver'] = 'sync';
        $items['queue']['connections']['database'] = [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => true,
        ];
        $items['queue']['failed'] = [
            'driver' => 'database',
            'database' => $driver,
            'table' => 'failed_jobs',
        ];

        // Logging
        $items['logging']['default'] = 'errorlog';
        $items['logging']['channels']['errorlog'] = [
            'driver' => 'errorlog',
            'level' => Config::getVar('logging', 'level', 'debug'),
        ];
        $logPath = Config::getVar('logging', 'path');
        if (strlen($logPath)) {
            $items['logging']['channels']['errorlog']['driver'] = 'single';
            $items['logging']['channels']['errorlog']['path'] = $logPath;
        }

        $items['logging']['channels']['maillog'] = [
            'driver' => 'errorlog',
            'level' => Config::getVar('email', 'log_level', 'error'),
        ];
        $mailLogPath = Config::getVar('email', 'log_path');
        if (strlen($mailLogPath)) {
            $items['logging']['channels']['maillog']['driver'] = 'single';
            $items['logging']['channels']['maillog']['path'] = $mailLogPath;
        }

        // Mail Service
        $items['mail']['default'] = static::getDefaultMailer();
        $items['mail']['mailers']['sendmail'] = [
            'transport' => 'sendmail',
            'path' => Config::getVar('email', 'sendmail_path'),
        ];
        $items['mail']['mailers']['smtp'] = [
            'transport' => 'smtp',
            'host' => Config::getVar('email', 'smtp_server'),
            'port' => Config::getVar('email', 'smtp_port'),
            'encryption' => Config::getVar('email', 'smtp_auth'),
            'username' => Config::getVar('email', 'smtp_username'),
            'password' => Config::getVar('email', 'smtp_password'),
            'timeout' => null,
            'auth_mode' => null,
        ];
        $items['mail']['mailers']['log'] = [
            'transport' => 'log',
            'channel' => 'maillog',
        ];

        // Cache configuration
        $items['cache'] = [
            'default' => 'opcache',
            'stores' => [
                'opcache' => [
                    'driver' => 'opcache',
                    'path' => Core::getBaseDir() . '/cache/opcache'
                ]
            ]
        ];

        // Create instance and bind to use globally
        $this->instance('config', new Repository($items));
    }

    /**
     * @param string $path appended to the base path
     * @brief see Illuminate\Foundation\Application::basePath
     */
    public function basePath($path = '')
    {
        return $this->basePath . ($path ? "/$path" : $path);
    }

    /**
     * @param string $path appended to the path
     * @brief alias of basePath(), Laravel app path differs from installation path
     */
    public function path($path = '')
    {
        return $this->basePath($path);
    }

    /**
     * Retrieves default mailer driver depending on the configuration
     *
     * @throws Exception
     */
    protected static function getDefaultMailer(): string
    {
        $default = Config::getVar('email', 'default');

        if (!$default) {
            throw new Exception('Mailer driver isn\'t specified in the application\'s config');
        }

        return $default;
    }

    /**
     * Setting a proxy on the stream_context_set_default when configuration [proxy] is filled
     */
    protected function settingProxyForStreamContext(): void
    {
        $proxy = new ProxyParser();

        if ($httpProxy = Config::getVar('proxy', 'http_proxy')) {
            $proxy->parseFQDN($httpProxy);
        }

        if ($httpsProxy = Config::getVar('proxy', 'https_proxy')) {
            $proxy->parseFQDN($httpsProxy);
        }

        if ($proxy->isEmpty()) {
            return;
        }

        /**
         * `Connection close` here its to avoid slowness. More info at https://www.php.net/manual/en/context.http.php#114867
         * `request_fulluri` its related to avoid proxy errors. More info at https://www.php.net/manual/en/context.http.php#110449
         */
        $opts = [
            'http' => [
                'protocol_version' => 1.1,
                'header' => [
                    'Connection: close',
                ],
                'proxy' => $proxy->getProxy(),
                'request_fulluri' => true,
            ],
        ];

        if ($proxy->getAuth()) {
            $opts['http']['header'][] = 'Proxy-Authorization: Basic ' . $proxy->getAuth();
        }

        $context = stream_context_create($opts);
        stream_context_set_default($opts);
        libxml_set_streams_context($context);
    }

    /**
     * Override Laravel method; always false.
     * Prevents the undefined method error when the Log Manager tries to determine the driver
     *
     * @return bool
     */
    public function runningUnitTests()
    {
        return false;
    }

    /**
     * Set the storage directory.
     */
    public function useStoragePath(string $path): static
    {
        $this->storagePath = $path;
        $this->instance('path.storage', $path);
        return $this;
    }

    /**
     * Get the path to the storage directory.
     */
    public function storagePath($path = ''): string
    {
        return ($this->storagePath ?: (new PrivateFileManager())->getBasePath()) . ($path != '' ? "/$path" : '');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPContainer', '\PKPContainer');
}
