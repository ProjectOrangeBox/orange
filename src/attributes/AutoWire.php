<?php

declare(strict_types=1);

namespace orange\framework\attributes;

use Attribute;

/**
 * Purpose
 * This is a PHP attribute (PHP 8.0+) that allows developers to annotate class methods to indicate they should be automatically wired with services from the dependency injection (DI) container.
 *
 * Key Components
 * Namespace: orange\framework\attributes - Part of the framework's attribute system.
 * Attribute Declaration: #[Attribute(Attribute::TARGET_METHOD)] - This attribute can only be applied to class methods.
 * Constructor: Takes a single string $service parameter, which specifies the service name/key to inject.
 *
 * How It Works
 * When the DI container processes a class, it looks for methods marked with #[AutoWire].
 * It reads the service name from the attribute's $service property.
 * It retrieves the corresponding service from the container and injects it into the method.
 * Benefits
 * Method-Level Injection: Allows dependency injection at the method level, not just constructor or property level.
 * Flexible: Can be used for setter methods, factory methods, or any method that needs service injection.
 * Type-Safe: Uses PHP's attribute system for compile-time validation.
 * Framework Integration: Works with OrangeFramework's container system for automatic wiring.
 *
 * class MyService {
 *   #[AutoWire('database')]
 *   #[AutoWire('user')]
 *   #[AutoWire('logger')]
 *   public function getInstance(PDO $db, User $user, Logger $logger) {
 *       // The database, user, logger services will be automatically injected
 *   }
 *
 */

#[Attribute(Attribute::TARGET_METHOD)]
class AutoWire
{
    public function __construct(public string $service)
    {
    }
}
