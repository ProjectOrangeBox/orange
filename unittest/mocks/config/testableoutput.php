<?php

declare(strict_types=1);

// TestableOutput extends orange\framework\Output for testing; ConfigurationTrait
// resolves the config file from the runtime class name, so it needs its own
// config file here that simply reuses Output's real defaults.
return require __DIR__ . '/../../../src/config/output.php';
