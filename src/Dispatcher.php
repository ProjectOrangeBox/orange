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
 *  •   MethodNotFound → method missing on controller.
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
     * @throws MethodNotFound If the specified method does not exist in the controller class.
     * @throws InvalidValue If the controller's method does not return a string.
     */
    public function call(RouterCallback $routerCallback): string
    {
        logMsg('INFO', __METHOD__ . var_export($routerCallback, true));

        // let's make sure the controller is present and autoload it
        if (!class_exists($routerCallback->controller)) {
            throw new ControllerClassNotFound($routerCallback->controller);
        }

        // let's make sure the controller has this method
        if (!method_exists($routerCallback->controller, $routerCallback->method)) {
            throw new MethodNotFound($routerCallback->controller . '::' . $routerCallback->method);
        }

        // method_exists() doesn't check visibility - calling a private/protected method
        // from here would throw an uncaught fatal Error instead of a clean MethodNotFound,
        // so treat non-public methods the same as missing ones
        if (!(new ReflectionMethod($routerCallback->controller, $routerCallback->method))->isPublic()) {
            throw new MethodNotFound($routerCallback->controller . '::' . $routerCallback->method);
        }

        // ok now instantiate the class and call the method
        try {
            // The router callback arguments can contain non-numeric keys if the end user used named capture groups
            // so we need to filter out the named keys before unpacking them to pass them to the controller method
            // so we don't get a "Cannot use positional argument after named argument during unpacking" error
            // arguments are always passed as they are captured
            // this protects the developer from accidentally using named capture groups
            $routerCallback->arguments = array_filter(
                $routerCallback->arguments,
                function ($key) {
                    return is_int($key);
                },
                ARRAY_FILTER_USE_KEY
            );

            $output = (new $routerCallback->controller())->{$routerCallback->method}(...$routerCallback->arguments);
        } catch (\ArgumentCountError $e) {
            // if we get an argument count error it means the method is missing a required argument which means the route is not properly defined so throw a method not found exception
            throw new ArgumentMissMatch($routerCallback->controller . '::' . $routerCallback->method . ' is missing required arguments. ' . $e->getMessage());
        }

        // if they didn't return anything set output to an empty string
        $output = $output ?? '';

        // make sure they returned a string
        if (!is_string($output)) {
            // they returned something other than a string which is what the method and the output service expects so throw an error
            throw new InvalidValue('Controller "' . $routerCallback->controller . '" method "' . $routerCallback->method . '" did not return a string. ' . gettype($output) . ' returned.');
        }

        // return the output
        return $output;
    }
}
