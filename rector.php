<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withSkip([
        // plain-PHP error view templates: ViewAbstract::generate() extract()s
        // data into scope right before require-ing these, so Rector can't
        // safely reason about what's "unused"
        __DIR__ . '/src/views',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
    ]);
