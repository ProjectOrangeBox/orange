<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

/**
 * A view engine renders a template with data. That is the whole contract.
 *
 * It is handed a path, not a name: turning a name into a path - aliases, module
 * namespaces, the fallback a package's views live under - belongs to
 * ViewFinderInterface, and reaching it is the caller's job. Controllers get it
 * through BaseController::renderView(); everyone else through findView().
 */
interface ViewInterface
{
    public function render(string $viewFile = '', array $data = [], array $options = []): string;
    public function renderString(string $string, array $data = [], array $options = []): string;
    public function change(string $name, mixed $value): self;
}
