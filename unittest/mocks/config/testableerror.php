<?php

declare(strict_types=1);

// TestableError extends orange\framework\Error for testing; ConfigurationTrait
// resolves the config file from the runtime class name, so it needs its own
// config file here that simply reuses Error's real defaults.
return require __DIR__ . '/../../../src/config/error.php';
