<?php

declare(strict_types=1);

namespace orange\framework;

use orange\framework\exceptions\InvalidValue;
use orange\framework\exceptions\MissingRequired;
use orange\framework\exceptions\IncorrectInterface;
use orange\framework\interfaces\ContainerInterface;
use orange\framework\exceptions\filesystem\FileNotFound;
use orange\framework\exceptions\config\ConfigFileNotFound;
use orange\framework\exceptions\filesystem\DirectoryNotFound;
use orange\framework\exceptions\config\InvalidConfigurationValue;
use orange\framework\exceptions\config\ConfigFileDidNotReturnAnArray;

/**
 * Overview of Application.php
 *
 * This file defines the Application class in the orange\framework namespace.
 * It acts as the entry point and bootstrapper for Orange applications, providing both HTTP and CLI modes.
 * It's main responsibility is to initialize the environment, load configuration, prepare services, and
 * then run the application lifecycle.
 *
 * ⸻
 *
 * 1. Core Responsibilities
 *   1. Bootstrap the application
 *    •  Defines constants like UNDEFINED, RUN_MODE, DEBUG, CHARSET.
 *    •  Verifies the root directory (__ROOT__).
 *    •  Loads environment variables (from system and .env files).
 *    •  Loads configuration files and merges them.
 *    •  Sets PHP runtime settings (errors, encoding, timezone, umask).
 *   2. Start different modes
 *    •  http() – runs the full HTTP lifecycle (routing, controller dispatching, output, shutdown).
 *    •  cli() – sets up the environment and returns the container for CLI usage.
 *   3. Dependency Injection (DI) Container setup
 *    •  Bootstraps services (from config files).
 *    •  Ensures a valid container is created via a closure.
 *    •  Makes the container globally available through Application::$container.
 *    •  Exposes configuration values as $application.KEY inside the container.
 *
 * ⸻
 *
 * 2. Lifecycle for HTTP Application
 *   1. Call http() → calls bootstrap('http', $config).
 *   2.  Triggers events in sequence:
 *     •  before.router → before routing.
 *     •  before.controller → before dispatching the matched route.
 *     •  before.output → before sending response.
 *     •  before.shutdown → before shutdown.
 *   3.  Handles routing, dispatching controllers, writing and sending output.
 *
 * This structure makes the application event-driven, letting developers hook into different stages.
 *
 * ⸻
 *
 * 3. Configuration Handling
 *     •  Environment (loadEnvironment)
 * Loads environment variables into a $env array. Parses .ini files if provided.
 * Defines the ENVIRONMENT constant (default: production).
 *     •  Config Files (loadConfig)
 * Loads application configs and merges them with defaults.
 * Supports cascading configs from multiple directories and environment-specific files.
 *     •  Constants
 * Loads config-defined constants, enforcing uppercase.
 *
 * ⸻
 *
 * 4. Error & Exception Handling
 *     •  In preContainer(), if user-defined errorHandler / exceptionHandler exist, they are registered.
 *     •  Ensures errors and exceptions are centrally managed.
 *
 * ⸻
 *
 * 5. Extensibility Hooks
 *     •  preContainer() – allows adding helpers, error handlers, constants before container setup.
 *     •  postContainer() – injects application config values into the container.
 *     •  Can be extended to override behavior without changing core.
 *
 * ⸻
 *
 * In short:
 * Application.php is the framework bootstrapper that:
 *     •  Sets up environment and config.
 *     •  Prepares services in a DI container.
 *     •  Provides a controlled lifecycle for both HTTP and CLI execution.
 *     •  Hooks into events and error handling.
 *
 * It’s the backbone of running an Orange-based application.
 *
 * @package orange\framework
 * @phpstan-consistent-constructor Subclasses hook into preContainer()/postContainer(),
 *     never redeclare __construct(), so make()'s `new static()` is safe.
 */

class Application
{
    // singleton instance
    protected static Application $self;
    // application (this class) config
    protected array $config;
    // Dependency Injection Container
    protected ContainerInterface $container;
    // this classes configuration array
    protected array $configDirectories;
    // environment variables
    protected array $env = [];

    // singleton pattern
    protected function __construct()
    {
        // empty to make protected & singleton
        // only use the Make static function
    }

    /**
     * singleton pattern
     *
     * @param null|array $environmentalFiles
     * @param null|array $configDirectories
     * @return Application
     */
    public static function make(?array $environmentalFiles = null, ?array $configDirectories = null): Application
    {
        // if we don't have an instance yet, make one
        if (!isset(static::$self)) {
            static::$self = new static();
        }

        if ($environmentalFiles) {
            static::$self->loadEnvironment(...$environmentalFiles);
        }

        if ($configDirectories) {
            static::$self->setConfigDirectories(...$configDirectories);
        }

        // return the instance
        return static::$self;
    }

    /**
     * Wrapper for make
     * to make it seem like you are getting the instance
     * not "making" an instance.
     *
     * @return Application
     */
    public static function get(): Application
    {
        return static::make();
    }

    /**
     * start a http application
     *
     * @param array $config
     * @return ContainerInterface
     * @throws InvalidValue
     * @throws DirectoryNotFound
     * @throws ConfigFileNotFound
     * @throws MissingRequired
     * @throws FileNotFound
     * @throws IncorrectInterface
     */
    public function http(array $config = []): ContainerInterface
    {
        // call bootstrap function which creates the container
        $this->bootstrap('http', $config);

        // the container is now setup so we can use services

        // call event
        $this->container->events->trigger('before.router', $this->container->input);

        // match uri & method to route
        $this->container->router->match($this->container->input->requestUri(), $this->container->input->requestMethod());

        // call event
        $this->container->events->trigger('before.controller', $this->container->router, $this->container->input);

        // dispatch route
        $this->container->output->write($this->container->dispatcher->call($this->container->router->getRouterCallback()));

        // call event
        $this->container->events->trigger('before.output', $this->container->router, $this->container->input, $this->container->output);

        // send header, status code and output
        $this->container->output->send();

        // call event
        $this->container->events->trigger('before.shutdown', $this->container->router, $this->container->input, $this->container->output);

        // return the container
        return $this->container;
    }

    /**
     * start a cli application
     *
     * either pass in the config directory OR let it guess (__ROOT__ . '/config')
     *
     * @return ContainerInterface
     * @throws InvalidValue
     * @throws DirectoryNotFound
     * @throws ConfigFileNotFound
     * @throws MissingRequired
     * @throws FileNotFound
     * @throws IncorrectInterface
     */
    public function run(array $config = []): ContainerInterface
    {
        // call bootstrap function which returns a container
        return $this->bootstrap('cli', $config);
    }

    /**
     * Bootstraps the application environment
     *
     * @param string $mode
     * @return ContainerInterface
     * @throws InvalidValue
     * @throws DirectoryNotFound
     * @throws ConfigFileNotFound
     * @throws MissingRequired
     * @throws FileNotFound
     * @throws IncorrectInterface
     */
    protected function bootstrap(string $mode, array $config): ContainerInterface
    {
        // set a undefined value which is not NULL
        if (!defined('UNDEFINED')) {
            define('UNDEFINED', chr(0));
        }

        // setup a constant to indicate how this application was started
        if (!defined('RUN_MODE')) {
            define('RUN_MODE', mb_strtolower($mode));
        }

        // let's make sure they setup __ROOT__
        if (!defined('__ROOT__')) {
            throw new InvalidValue('The "__ROOT__" constant must be defined to indicate the root directory.');
        }

        // is root a real directory?
        if (!realpath(__ROOT__) || !is_dir(__ROOT__)) {
            throw new DirectoryNotFound(__ROOT__);
        }

        // switch to root
        chdir(__ROOT__);

        // try to setup the environment if it hasn't been loaded already
        // this also sets the ENVIRONMENT constant
        $this->loadEnvironment();

        // try to setup the application config if it hasn't been loaded already
        // this also setups up the config directories
        $this->setConfigDirectories();

        // replace anything loaded with anything sent in
        $this->config = array_replace($this->loadConfigFile('application'), $config);

        // set the application config in the container
        $this->config['config directories'] = $this->configDirectories;

        // config also has some additional application setup variables
        ini_set('display_errors', $this->config['display_errors']);
        ini_set('display_startup_errors', $this->config['display_startup_errors']);
        error_reporting($this->config['error_reporting']);

        // set timezone
        date_default_timezone_set($this->config['timezone'] ??  date_default_timezone_get());

        // Set internal encoding.
        ini_set('default_charset', $this->config['encoding']);
        mb_internal_encoding($this->config['encoding']);
        if (!defined('CHARSET')) {
            define('CHARSET', $this->config['encoding']);
        }

        // set umask to a known state
        umask($this->config['umask']);

        // this extension is required and now part of php 8+
        if (!extension_loaded('mbstring')) {
            throw new MissingRequired('extension: mbstring');
        }

        // default to NO character on substitute
        mb_substitute_character($this->config['mb_substitute_character']);

        // the developer can extend this class and override these methods
        $this->preContainer();
        $this->bootstrapContainer($this->loadConfigFile('services'));
        $this->postContainer();

        // return the container
        return $this->container;
    }

    /**
     * run before the container is created
     * load helpers, error handlers, constants, etc...
     *
     * @return void
     * @throws FileNotFound
     * @throws ConfigFileNotFound
     * @throws InvalidValue
     */
    protected function preContainer(): void
    {
        // include the user supplied helpers
        // include the orange required helpers
        $helpers = ($this->config['helpers'] ?? []) + ($this->config['required helpers'] ?? []);

        // now include each helper file
        foreach ($helpers as $helperFile) {
            // get the real path of the helper file
            $helperFileRealPath = realpath($helperFile);
            // if the file doesn't exist
            if (!$helperFileRealPath) {
                throw new FileNotFound($helperFile);
            }
            // include the helper file
            include_once $helperFileRealPath;
        }

        // now errorHandler() & exceptionHandler() should be setup
        // try to attach the exception and error handler
        if (function_exists('errorHandler')) {
            set_error_handler('errorHandler');
        }

        if (function_exists('exceptionHandler')) {
            set_exception_handler('exceptionHandler');
        }

        // load the constants and apply them
        foreach ($this->loadConfigFile('constants') as $name => $value) {
            // Constants should all be uppercase - not an option!
            $name = strtoupper($name);
            // If the constant is not already defined, define it
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    /**
     * Initializes the DI container using service configuration
     *
     * @return void
     * @throws ConfigFileNotFound
     * @throws InvalidValue
     * @throws IncorrectInterface
     */
    protected function bootstrapContainer(array $services): void
    {
        // make sure we have a container service
        if (!isset($services['container'])) {
            throw new InvalidValue('Container Service not found.');
        }

        // Make sure container is a Closure
        if (!$services['container'] instanceof \Closure) {
            throw new IncorrectInterface('Container services not a closure.');
        }

        // call the closure into a plain local first - $this->container is typed
        // ContainerInterface, so assigning straight into it would let PHP throw a raw
        // TypeError for a bad return value before we get a chance to throw our own
        // IncorrectInterface with a useful message
        $container = $services['container']($services);

        // make sure the container is an instance of the ContainerInterface
        if (!$container instanceof ContainerInterface) {
            throw new IncorrectInterface('The service "container" did not return an object using the container interface.');
        }

        // now save the validated container
        $this->container = $container;
    }

    /**
     * Run after the container is created
     * setup application config values in the container
     *
     * @return void
     */
    protected function postContainer(): void
    {
        // make the application config available as $application
        $this->container->set('$application', $this->config);
    }

    /**
     * Get the application env values
     * $_ENV has already been unset for security
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function env(string $key, mixed $default = null): mixed
    {
        return $this->env[$key] ?? $default;
    }

    /**
     * Load the application environment
     *
     * @return void
     * @throws FileNotFound
     */
    public function loadEnvironment(): void
    {
        // load from the system
        if (empty($this->env)) {
            // load the system environment variables into our env array
            $this->env = $_ENV;

            // clear this out so we don't try to read from it but make sure it is still a array
            unset($_ENV);

            // get the list of environmental files to load
            foreach (func_get_args() as $environmentalFile) {
                $this->loadEnvironmentFile($environmentalFile);
            }

            // set ENVIRONMENT constant - defaults to production if not set in .env
            if (!defined('ENVIRONMENT')) {
                define('ENVIRONMENT', mb_strtolower($this->env['ENVIRONMENT'] ?? 'production'));
            }

            // set DEBUG default to false (production)
            if (!defined('DEBUG')) {
                define('DEBUG', $this->env['DEBUG'] ?? false);
            }
        }
    }

    /**
     * set the config directories
     * or use the defaults if none provided
     *
     * @return void
     * @throws FileNotFound
     */
    public function setConfigDirectories(): void
    {
        // Did we setup the config directories already?
        if (!isset($this->configDirectories)) {
            // initialize the config directories array
            $this->configDirectories = [];

            // make sure the environment is loaded
            $this->loadEnvironment();

            // get the list of application config files to load
            $arrayOfConfigDirectories = func_get_args();

            // if no config directories were provided load the defaults
            // ../config/* & ../config/{ENVIRONMENT}/*
            if (empty($arrayOfConfigDirectories)) {
                // default location of application config folder
                $arrayOfConfigDirectories[] = __ROOT__ . DIRECTORY_SEPARATOR . 'config';
                // default location of application config folder
                $arrayOfConfigDirectories[] = __ROOT__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . ENVIRONMENT;
            }
            // use the provided config directories
            $this->configDirectories = $arrayOfConfigDirectories;

            // orange config folder is always checked first and then the others are merged in after
            array_unshift($this->configDirectories, __DIR__ . DIRECTORY_SEPARATOR . 'config');
        }
    }

    /**
     * load a config file from the config directories
     *
     * @param string $configFilename
     * @return array
     * @throws ConfigFileDidNotReturnAnArray
     */
    protected function loadConfigFile(string $configFilename): array
    {
        // make sure we have the config directories setup
        $this->setConfigDirectories();

        // initialize the config array
        $fileConfig = [];

        // go through each config directory and load the config file if it exists
        foreach ($this->configDirectories as $directory) {
            // build the config file path
            $configFile = $directory . DIRECTORY_SEPARATOR . $configFilename . '.php';
            // does the config file exist?
            if (realpath($configFile)) {
                // Include the config file
                $includedConfig = include $configFile;
                // Check if the included config is an array
                if (!is_array($includedConfig)) {
                    throw new ConfigFileDidNotReturnAnArray($configFile);
                }
                // replace the included config with the existing config
                $fileConfig = array_replace_recursive($fileConfig, $includedConfig);
            }
        }

        // return the final config array
        return $fileConfig;
    }

    /**
     * Load the environmental files provided
     *
     * @param string $environmentalFile
     * @return void
     * @throws FileNotFound
     * @throws InvalidConfigurationValue
     */
    protected function loadEnvironmentFile(string $environmentalFile): void
    {
        // get the real path of the environmental file
        $environmentalFileRealPath = realpath($environmentalFile);

        // if the file doesn't exist
        if (!$environmentalFileRealPath) {
            throw new FileNotFound($environmentalFile);
        }

        // parse the ini file and merge it into the env array
        $iniArray = parse_ini_file($environmentalFileRealPath, true, INI_SCANNER_TYPED);

        // make sure we got an array back
        if (!is_array($iniArray)) {
            throw new InvalidConfigurationValue($environmentalFileRealPath . ' Invalid INI file format or empty file.');
        }
        // merge the new values in - recursive to handle sections
        $this->env = array_replace_recursive($this->env, $iniArray);
    }
}
