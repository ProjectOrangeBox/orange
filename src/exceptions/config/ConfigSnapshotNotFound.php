<?php

declare(strict_types=1);

namespace orange\framework\exceptions\config;

use orange\framework\exceptions\config\Config;

/**
 * Production expects a pre-built configuration snapshot and there isn't one.
 *
 * Thrown rather than quietly falling back to discovering the cascade: a missing
 * snapshot means the deploy step that writes it never ran, and running the site
 * on whatever the directories happen to hold would hide that.
 */
class ConfigSnapshotNotFound extends Config
{
}
