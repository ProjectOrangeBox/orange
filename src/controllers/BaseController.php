<?php

declare(strict_types=1);

namespace orange\framework\controllers;

use orange\framework\attributes\AttachService;
use orange\framework\exceptions\InvalidValue;
use orange\framework\exceptions\filesystem\FileNotFound;
use orange\framework\interfaces\ConfigInterface;
use orange\framework\interfaces\InputInterface;
use orange\framework\interfaces\OutputInterface;
use orange\framework\interfaces\RouterInterface;
use orange\framework\interfaces\ViewFinderInterface;
use orange\framework\interfaces\ViewInterface;
use ReflectionClass;

/**
 * Base controller that application controllers may extend.
 *
 * Not required, but provides commonly used behavior shared across controllers:
 * auto-attaching services declared with the #[AttachService] attribute, loading
 * controller-local libraries listed in $libraries, resolving and rendering views
 * through renderView(), and invoking an optional beforeMethodCalled() hook on the
 * extending controller after construction.
 */
abstract class BaseController
{
    #[AttachService('config')]
    protected ConfigInterface $config;

    #[AttachService('input')]
    protected InputInterface $input;

    #[AttachService('output')]
    protected OutputInterface $output;

    #[AttachService('router')]
    protected RouterInterface $router;

    #[AttachService('viewFinder')]
    protected ViewFinderInterface $viewFinder;

    // this is the reflection of the extending controller class
    protected ReflectionClass $reflection;

    /**
     * This array holds the local libraries you want to autoload on instantiation.
     *
     * @var array
     */
    protected array $libraries = [];

    /**
     * BaseController constructor.
     *
     * @throws FileNotFound
     */
    public function __construct()
    {
        $this->reflection = new ReflectionClass(static::class);

        // auto attach services defined with the #[AttachService] Attribute
        $this->autoAttachService();

        // path to the parent directory of the parent class
        $parentPath = dirname($this->reflection->getFileName(), 2);

        // try to load (local to extending controller) libraries
        foreach ($this->libraries as $library) {
            $libraryFilePath = $parentPath . '/libraries/' . $library . '.php';

            if (!file_exists($libraryFilePath)) {
                throw new FileNotFound($libraryFilePath);
            }

            logMsg('DEBUG', 'INCLUDE FILE "' . $libraryFilePath . '"');

            include_once $libraryFilePath;
        }

        // the controller's own views directory used to be registered with the
        // view engine here, at FIRST priority, so a module's copy of a view beat
        // a shared one. That is now decided by name rather than by search order:
        // renderView() asks ViewFinder for this controller's namespace, and the
        // module's own view is simply a different key. See viewNamespace().

        // call the extending controller "construct"
        if (\method_exists($this, 'beforeMethodCalled')) {
            /** @disregard P1013 Undefined method 'beforeMethodCalled'. */
            $this->beforeMethodCalled();
        }
    }

    /**
     * Render a view: resolve route-derived placeholders in its name, turn that
     * name into a file, and hand the file to the view engine.
     *
     * Those are three jobs and they used to be one. The view engine resolved
     * $c/$m (so it needed a router) and searched directories for the result (so
     * it needed to know where views live). Both moved out: the routing context
     * is here, where the controller already is, and locating a file is
     * ViewFinder's. What is left for the engine is rendering.
     *
     * The namespace passed to the finder is this controller's own, so a module
     * always gets its own copy of a view when it has one - see viewNamespace().
     *
     * A name with no placeholders is passed straight through, so this can be used
     * for every render rather than only the dynamic ones:
     *
     *     return $this->renderView('main/index');   // as-is
     *     return $this->renderView();               // '' -> '$c/$m' -> 'main/index'
     *     return $this->renderView('admin/$m');     // -> 'admin/edit'
     *
     * @param string $view View name, optionally containing $c/$m/$1/$2 or *
     * @param array $data Data to pass into the view
     * @param array $options Rendering options, forwarded untouched
     * @throws InvalidValue When the controller has no $view property, or the
     *     matched route is missing the controller/method a placeholder needs
     */
    protected function renderView(string $view = '', array $data = [], array $options = []): string
    {
        /* @disregard P1014 Undefined property '$view'. */
        if (!isset($this->view) || !$this->view instanceof ViewInterface) {
            throw new InvalidValue(static::class . ' has no $view property, so renderView() has nothing to render with.');
        }

        // two separate questions, answered by two separate services: what is
        // this view called, then which file is that. The view engine is handed
        // the answer rather than working either of them out itself
        $viewFile = $this->viewFinder->find($this->resolveDynamicView($view), $this->viewNamespace());

        /* @disregard P1014 Undefined property '$view'. */
        return $this->view->render($viewFile, $data, $options);
    }

    /**
     * The namespace ViewFinder should look under for this controller's own views.
     *
     * Derived from the controller's PSR-4 namespace with the trailing
     * \controllers segment dropped, which lands on exactly the prefix the view
     * detector builds from the same module's views/ directory:
     *
     *     application\welcome\controllers\MainController
     *       -> 'application/welcome'
     *       -> matches application/welcome/views/main/index.php
     *
     * A controller outside a \controllers\ namespace falls back to its full
     * namespace. One with no namespace at all gets '', which simply means no
     * module-local views - every lookup goes straight to the shared fallbacks.
     */
    protected function viewNamespace(): string
    {
        $namespace = $this->reflection->getNamespaceName();

        if ($namespace === '') {
            return '';
        }

        $segments = explode('\\', $namespace);
        $last = array_search('controllers', array_map(strtolower(...), $segments), true);

        if ($last !== false) {
            $segments = array_slice($segments, 0, $last);
        }

        return mb_convert_case(implode('/', $segments), MB_CASE_LOWER, 'UTF-8');
    }

    /**
     * Resolve dynamic view paths based on router callback information.
     *
     * Dynamic placeholders in the view string (e.g., $c, $m, $1, $2) are replaced with
     * controller, method, or namespace segments dynamically.
     *
     * @param string $view The view string with possible dynamic placeholders.
     * @return string The dynamically resolved view string.
     * @throws InvalidValue If controller or method is missing while resolving placeholders.
     */
    protected function resolveDynamicView(string $view): string
    {
        logMsg('DEBUG', __METHOD__ . ' argument: "' . $view . '"');

        // Define dynamic placeholders
        $prefix = '$';
        $controllerString = $prefix . 'c';
        $methodString = $prefix . 'm';

        // Retrieve controller and method from the router's matched callback
        [$controller, $method] = $this->router->getMatched('callback');

        // Check if placeholders exist or if the view string is dynamic
        if (str_contains($view, $prefix) || str_contains($view, '*') || $view === '') {
            // Handle default controller and method placeholders
            if ($view == '') {
                $view = $controllerString . '/' . $methodString;
            } elseif (str_ends_with($view, '*/*')) {
                $view = substr($view, 0, -3) . $controllerString . '/' . $methodString;
            }

            if (str_ends_with($view, '/*')) {
                $view = substr($view, 0, -2) . '/' . $methodString;
            }

            // Replace method placeholder
            if (str_contains($view, $methodString)) {
                if (!isset($method)) {
                    throw new InvalidValue('Missing Method and therefore cannot generate dynamic view.');
                }
                $view = str_replace($methodString, $method, $view);
            }

            // Replace controller placeholder and namespace segments
            if (str_contains($view, $prefix)) {
                if (!isset($controller)) {
                    throw new InvalidValue('Missing Controller and therefore cannot generate dynamic view.');
                }

                // Normalize the controller string
                $namespacedController = mb_strtolower((string) $controller);

                // Remove "controller" suffix if it exists
                if (str_ends_with($namespacedController, 'controller')) {
                    $namespacedController = substr($namespacedController, 0, -10);
                }

                // Break controller namespace into segments
                foreach (explode('/', str_replace('\\', '/', $namespacedController)) as $index => $segment) {
                    $view = str_replace($prefix . ($index + 1), $segment, $view);

                    // Store the last segment
                    $controllerName = $segment;
                }

                // Replace the controller placeholder with the final segment
                $view = str_replace($controllerString, $controllerName, $view);
            }
        }

        logMsg('DEBUG', __METHOD__ . ' return: "' . $view . '"');

        return $view;
    }

    protected function autoAttachService(): void
    {
        foreach ($this->reflection->getProperties() as $property) {
            $attribute = $property->getAttributes(AttachService::class);

            if (isset($attribute[0])) {
                logMsg('DEBUG', 'Attach ' . $attribute[0]->getArguments()[0] . ' to ' . $property->getName() . ' property of ' . static::class);

                $this->{$property->getName()} = container()->get($attribute[0]->getArguments()[0]);
            }
        }
    }
}
