<?php

declare(strict_types=1);

namespace orange\framework\attributes;

use Attribute;

/**
 * Purpose
 * This is a PHP attribute (introduced in PHP 8.0) that allows developers to annotate class properties to indicate they should be automatically populated with services from the dependency injection (DI) container.
 *
 * Key Components
 * Namespace: orange\framework\attributes - Part of the framework's attribute system.
 * Attribute Declaration: #[Attribute(Attribute::TARGET_PROPERTY)] - This attribute can only be applied to class properties.
 * Constructor: Takes a single string $attachService parameter, which specifies the service name/key to inject.
 *
 * How It Works
 * BaseController (not the DI container) scans its own properties for #[AttachService].
 * It reads the service name from the attribute's $attachService property.
 * It retrieves the corresponding service from the container and assigns it to the property.
 * Benefits
 * Declarative: Makes service dependencies explicit and self-documenting.
 * Type-Safe: Leverages PHP's attribute system for compile-time validation.
 * Framework Integration: Works seamlessly with OrangeFramework's container system.
 * This attribute simplifies dependency injection by eliminating the need for manual constructor parameters or setter methods for common services.
 *
 * Usage Example
 * class MyController extends BaseController {
 *   #[AttachService('database')]
 *   public $db;
 *
 *   #[AttachService('logger')]
 *   private $logger;
 * }
 */

#[Attribute(Attribute::TARGET_PROPERTY)]
class AttachService
{
    public function __construct(public string $attachService)
    {
    }
}
