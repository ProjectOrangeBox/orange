<?php

declare(strict_types=1);

// Fixture used only by ApplicationHttpTest to exercise Application::http()
// end to end. It replaces the framework's default 'container' service closure
// with a minimal, self-contained one wired to a single matching route, so the
// full router -> dispatcher -> output lifecycle runs without needing a real
// web server request or the framework's default 404/home controllers.
require_once MOCKDIR . '/mockController.php';

return [
    'container' => function (array $services): \orange\framework\interfaces\ContainerInterface {
        // Container itself must be the FIRST orange singleton constructed here:
        // any other singleton's constructor calls logMsg() -> container(), which
        // calls Container::getInstance() with no args - if that fires before we
        // build the real one, it permanently caches an empty container (Container
        // is a getInstance()-cached singleton too, so our own getInstance() call
        // below would then silently return that stale empty instance instead).
        $container = \orange\framework\Container::getInstance();

        $input = \orange\framework\Input::getInstance([
            'server' => ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/apphttptest'],
        ]);

        $router = \orange\framework\Router::getInstance([
            'site url' => 'example.com',
            '404' => [],
            'home' => [],
            'routes' => [
                ['method' => 'get', 'url' => '/apphttptest', 'callback' => ['mockController', 'index']],
            ],
        ], $input);

        $output = \orange\framework\Output::getInstance([], $input);
        $events = \orange\framework\Event::getInstance([]);
        $dispatcher = \orange\framework\Dispatcher::getInstance();

        $container->set('input', $input);
        $container->set('router', $router);
        $container->set('output', $output);
        $container->set('events', $events);
        $container->set('dispatcher', $dispatcher);

        return $container;
    },
];
