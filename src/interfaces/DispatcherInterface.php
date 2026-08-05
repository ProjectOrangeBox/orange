<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

use orange\framework\property\RouterCallback;

/**
 * Instantiates the matched controller and calls the matched method.
 *
 * The last step of the request pipeline, and the narrowest: one method, taking
 * the RouterCallback the router produced and returning whatever the controller
 * rendered. It does not choose the controller - that decision arrived already
 * made - and it does not send the result, which is output's job.
 *
 * Controllers are only ever constructed here. That is what lets #[AttachService]
 * work without a constructor: the dispatcher builds the instance and the
 * container populates the attributed properties, so a controller written with a
 * hand-rolled `new` gets neither.
 *
 * A non-public method is reported as MethodNotFound rather than a fatal, and
 * only positionally-keyed arguments are unpacked - named capture groups in a
 * route regex are dropped rather than passed, since PHP cannot unpack a
 * positional argument after a named one.
 *
 * CONTROLLER and METHOD index the two halves of a route's callback array,
 * ['App\HomeController', 'index'], before it becomes a RouterCallback.
 */
interface DispatcherInterface
{
    public const CONTROLLER = 0;
    public const METHOD = 1;

    public function call(RouterCallback $routerCallback): string;
}
