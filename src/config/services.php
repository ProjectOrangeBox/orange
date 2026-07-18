<?php

declare(strict_types=1);

use orange\framework\Env;
use orange\framework\Log;
use orange\framework\Data;
use orange\framework\View;
use orange\framework\Event;
use orange\framework\Input;
use orange\framework\Config;
use orange\framework\Output;
use orange\framework\Router;
use orange\framework\Container;
use orange\framework\Dispatcher;
use orange\framework\interfaces\EnvInterface;
use orange\framework\interfaces\LogInterface;
use orange\framework\interfaces\DataInterface;
use orange\framework\interfaces\ViewInterface;
use orange\framework\interfaces\EventInterface;
use orange\framework\interfaces\InputInterface;
use orange\framework\interfaces\ConfigInterface;
use orange\framework\interfaces\OutputInterface;
use orange\framework\interfaces\RouterInterface;
use orange\framework\interfaces\ContainerInterface;
use orange\framework\interfaces\DispatcherInterface;

/*
 * By placing the services inside a closure they are not created UNTIL they are called
 * This way you don't need to:
 * 1. connect to 1 or more databases if you don't need a database connection
 * 2. Setup a session if you don't need a session
 * 3. instantiate any class until it's needed
 * 4. allow easier mocking for testing
 * 5. allow easier overriding of any class as long as it follows the same interface
 * 6. create service alias if for example you use the same database connection on development but different ones on production
 *
 * This saves resources and make faster applications
 *
 * you can use anything for a service name
 * model.foo or $value
 * you can "get" those in 1 of 2 ways
 * $container->{'$test'}
 * $container->get('$test');
 *
 * 'uuid' => fn() => bin2hex(random_bytes(16)),
 */

return [
    // alias's
    '@event' => 'events',
    '@request' => 'input',
    '@response' => 'output',

    '$mimes' => include_once __DIR__ . '/mimes.php',
    'container' => Container::getInstance(...),
    'config' => fn(ContainerInterface $container): ConfigInterface => Config::getInstance($container->get('$application')),
    'log' => fn(ContainerInterface $container): LogInterface => Log::getInstance($container->config->log),
    'events' => fn(ContainerInterface $container): EventInterface => Event::getInstance($container->config->event),
    'input' => fn(ContainerInterface $container): InputInterface => Input::getInstance($container->config->input),
    'output' => fn(ContainerInterface $container): OutputInterface => Output::getInstance($container->config->output, $container->input),
    'router' => fn(ContainerInterface $container): RouterInterface => Router::getInstance($container->config->routes, $container->input),
    'data' => fn(ContainerInterface $container): DataInterface => Data::getInstance($container->config->data),
    'view' => fn(ContainerInterface $container): ViewInterface => View::getInstance($container->config->view, $container->data, $container->router),
    'dispatcher' => Dispatcher::getInstance(...),
];
