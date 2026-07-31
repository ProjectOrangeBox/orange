<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
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

        // Rector 2.5.9 wants to rewrite the service closures in
        // src/config/services.php, e.g.
        //
        //     'dispatcher' => fn(): DispatcherInterface => Dispatcher::getInstance()
        //  -> 'dispatcher' => Dispatcher::getInstance(...)
        //
        // That is not equivalent here. Container::get() invokes every service
        // closure as $closure($this), so the arrow function deliberately
        // *discards* that argument and calls getInstance() with none. The
        // first-class callable forwards it instead, and SingletonTraits::
        // getInstance() passes func_get_args() straight to the constructor - so
        // the container itself would arrive as the service's first constructor
        // argument. Verified: given getInstance(...$args), the arrow function
        // yields 0 args and the callable yields 1.
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class,
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
    ]);
