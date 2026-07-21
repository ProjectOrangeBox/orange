<?php

declare(strict_types=1);

namespace orange\framework;

use orange\framework\base\Singleton;
use orange\framework\exceptions\dispatcher\ArgumentMissMatch;
use orange\framework\exceptions\dispatcher\ControllerClassNotFound;
use orange\framework\exceptions\dispatcher\MethodNotFound;
use orange\framework\exceptions\InvalidValue;
use orange\framework\interfaces\DispatcherInterface;
use orange\framework\property\RouterCallback;
use ReflectionMethod;

/**
 * Overview of Dispatcher.php
 *
 * This file defines the Dispatcher class inside the orange\framework namespace.
 * Its job is to take a route that has already been matched (via the routing system)
 * and call the correct controller method with the right arguments.
 * It follows the singleton pattern, meaning only one dispatcher instance exists at runtime.
 *
 * ⸻
 *
 * 1. Core Purpose
 *  •   Acts as the bridge between the router and controllers.
 *  •   Ensures the correct controller and method are executed after a route is matched.
 *  •   Passes along any arguments extracted from the URL.
 *  •   Enforces that controller methods return the correct type (a string).
 *
 * ⸻
 *
 * 2. Key Components
 *  1.  Singleton Pattern
 *  •   Inherits from Singleton.
 *  •   Constructor is protected → ensures it can only be instantiated via Singleton::getInstance().
 *  2.  Route Handling (call() method)
 *  •   Accepts a $routeMatched array containing details of the matched route:
 *  •   Controller class name.
 *  •   Method to invoke.
 *  •   URL arguments.
 *  •   Metadata (URI, name, etc.).
 *  •   Logs details of the matched route for debugging.
 *  •   Validates that:
 *  •   The controller class exists.
 *  •   The method exists on that class.
 *  3.  Controller Invocation
 *  •   Calls the specified method with decoded route arguments.
 *  •   Validates the return value: must be a string.
 *  •   If null → converted to empty string.
 *  •   If not string → throws InvalidValue exception.
 *
 * ⸻
 *
 * 3. Error Handling
 *
 * The dispatcher enforces correctness by throwing exceptions when something is wrong:
 *  •   ControllerClassNotFound → controller class missing.
 *  •   MethodNotFound → method missing or not public on the controller.
 *  •   ArgumentMissMatch → controller method invoked without its required arguments.
 *  •   InvalidValue → controller method returned something other than a string.
 *
 * This ensures failures are explicit and caught early.
 *
 * ⸻
 *
 * 4. Big Picture
 *  •   The router decides which controller and method should handle a request.
 *  •   The Dispatcher actually executes that controller method.
 *  •   It also injects dependencies and enforces strict return types.
 *
 * So, Dispatcher.php is the execution engine that turns a matched route into a controller call, while enforcing framework standards.
 *
 * @package orange\framework
 */
class Dispatcher extends Singleton implements DispatcherInterface
{
    /**
     * Memoized class_exists() results keyed by controller class name.
     *
     * A class can't become undefined or newly-defined-then-undefined during a
     * process's lifetime, so this is safe to keep for as long as this singleton
     * lives - which matters for long-running workers (Swoole/RoadRunner) that
     * re-dispatch the same route thousands of times without ever redefining
     * classes, and would otherwise pay for class_exists() on every one of them.
     *
     * @var array<string, bool>
     */
    protected array $controllerExistsCache = [];

    /**
     * Memoized "does this controller declare this method, and is it public"
     * results, keyed by "Controller::method". Same reasoning as
     * $controllerExistsCache: method existence/visibility can't change at
     * runtime, so method_exists() + a ReflectionMethod construction only need
     * to run once per distinct controller/method pair.
     *
     * @var array<string, bool>
     */
    protected array $methodIsCallableCache = [];

    /**
     * Calls the matched route's callback with the provided arguments.
     *
     * This method takes a RouterCallback object containing the controller class name,
     * method name, and URL arguments. It validates that the controller and method exist,
     * invokes the method with decoded arguments, and ensures the return value is a string.
     *
     * @param RouterCallback $routerCallback The matched route callback object containing the controller class, method name, and arguments.
     * @return string The output of the controller's method.
     *
     * @throws ControllerClassNotFound If the specified controller class does not exist.
     * @throws MethodNotFound If the specified method does not exist, or is not public, on the controller class.
     * @throws ArgumentMissMatch If the method is invoked without arguments required by its signature.
     * @throws InvalidValue If the controller's method does not return a string.
     */
    public function call(RouterCallback $routerCallback): string
    {
        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('INFO')) {
            logMsg('INFO', __METHOD__ . var_export($routerCallback, true));
        }

        // let's make sure the controller is present and autoload it
        if (!($this->controllerExistsCache[$routerCallback->controller] ??= class_exists($routerCallback->controller))) {
            throw new ControllerClassNotFound($routerCallback->controller);
        }

        $methodKey = $routerCallback->controller . '::' . $routerCallback->method;

        // let's make sure the controller has this method, and that it's public -
        // method_exists() doesn't check visibility, and calling a private/protected
        // method from here would throw an uncaught fatal Error instead of a clean
        // MethodNotFound, so treat non-public methods the same as missing ones
        $this->methodIsCallableCache[$methodKey] ??= method_exists($routerCallback->controller, $routerCallback->method)
            && new ReflectionMethod($routerCallback->controller, $routerCallback->method)->isPublic();

        if (!$this->methodIsCallableCache[$methodKey]) {
            throw new MethodNotFound($methodKey);
        }

        // The router callback arguments can contain non-numeric keys if the end user used named capture groups
        // so we need to filter out the named keys before unpacking them to pass them to the controller method
        // so we don't get a "Cannot use positional argument after named argument during unpacking" error
        // arguments are always passed as they are captured
        // this protects the developer from accidentally using named capture groups
        // (filtered into a local variable rather than written back onto $routerCallback,
        // which the caller may still hold a reference to)
        $arguments = array_filter(
            $routerCallback->arguments,
            is_int(...),
            ARRAY_FILTER_USE_KEY
        );

        // instantiate and call as two separate steps, each with its own catch, so an
        // ArgumentCountError is attributed to whichever one actually threw it - both
        // used to share one try/catch, so a controller whose constructor required
        // arguments was misreported as its method being the one missing arguments
        try {
            $controller = new $routerCallback->controller();
        } catch (\ArgumentCountError $e) {
            throw new ArgumentMissMatch($routerCallback->controller . '::__construct is missing required arguments. ' . $e->getMessage());
        }

        try {
            $output = $controller->{$routerCallback->method}(...$arguments);
        } catch (\ArgumentCountError $e) {
            throw new ArgumentMissMatch($methodKey . ' is missing required arguments. ' . $e->getMessage());
        }

        // if they didn't return anything set output to an empty string
        $output ??= '';

        // make sure they returned a string
        if (!is_string($output)) {
            // they returned something other than a string which is what the method and the output service expects so throw an error
            throw new InvalidValue('Controller "' . $routerCallback->controller . '" method "' . $routerCallback->method . '" did not return a string. ' . gettype($output) . ' returned.');
        }

        // return the output
        return $output;
    }
}
