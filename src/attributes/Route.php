<?php

declare(strict_types=1);

namespace orange\framework\attributes;

use Attribute;

/**
 * Purpose
 * This is a PHP attribute (PHP 8.0+) that allows developers to annotate controller methods to define HTTP routes directly on the methods themselves, rather than in separate routing configuration files.
 *
 * Key Components
 * Namespace: orange\framework\attributes - Part of the framework's attribute system.
 * Attribute Declaration: #[Attribute(Attribute::TARGET_METHOD)] - This attribute can only be applied to class methods (typically controller methods).
 * Constructor Parameters:
 * $method: HTTP methods (e.g., 'GET', 'POST', ['GET', 'POST']) - can be a string or array.
 * $url: The URL pattern/path for the route (e.g., '/users/{id}').
 * $name: An optional name for the route (useful for URL generation).
 *
 * How It Works
 * The framework's router scans controller classes for methods annotated with #[Route].
 * It reads the method, URL, and name from each attribute.
 * It registers these routes in the routing system, mapping URLs to controller methods.
 * During request handling, the router matches incoming requests to these annotated methods.
 * Benefits
 * Declarative: Routes are defined right next to the code that handles them.
 * Type-Safe: PHP's attribute system provides compile-time validation.
 * Self-Documenting: Route definitions are co-located with their handlers.
 * Flexible: Supports multiple HTTP methods, URL parameters, and named routes.
 * Framework Integration: Works seamlessly with OrangeFramework's routing and controller systems.
 *
 * class UserController {
 *     #[Route(['GET', 'POST'], '/users', 'users.index')]
 *     public function index() {
 *         // Handles GET and POST requests to /users
 *         // Route name: users.index
 *     }
 *
 *     #[Route('GET', '/user/(?<id>.*)/view', 'users.show')]
 *     public function show($id) {
 *         // Handles GET /user/123/view
 *         // Route name: users.show
 *     }
 *
 *     #[Route('POST', '/users', 'users.store')]
 *     public function store() {
 *         // Handles POST /users
 *     }
 * }
 */

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(public string|array $method = [], public string $url = '', public string $name = '')
    {
    }
}
