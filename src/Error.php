<?php

declare(strict_types=1);

namespace orange\framework;

use Throwable;
use orange\framework\base\Singleton;
use orange\framework\interfaces\DataInterface;
use orange\framework\interfaces\ViewInterface;
use orange\framework\interfaces\ViewFinderInterface;
use orange\framework\interfaces\InputInterface;
use orange\framework\traits\ConfigurationTrait;
use orange\framework\interfaces\OutputInterface;
use orange\framework\interfaces\ContainerInterface;
use orange\framework\exceptions\container\ServiceNotFound;

/**
 * Last-resort handler for uncaught exceptions.
 *
 * Application::preContainer() registers exceptionHandler() (helpers/errors.php) with
 * set_exception_handler(), and that builds this class. It is not a general-purpose
 * error reporter for application code - see the note on construction below.
 *
 * **Constructing this class sends the response and exits.** The constructor resolves
 * its services, renders an error body, writes it, and ends with sendOutput(), which
 * always exit()s. There is no way to build an Error and inspect it, and nothing after
 * the call site runs. That is deliberate for a terminal handler, but it means this
 * class cannot be used to produce an ordinary response from inside a controller - a
 * controller returns its body and lets the normal pipeline (before.output, then
 * Output::send()) deliver it.
 *
 * A thrown exception can steer the response through three optional methods, checked
 * in this order (see the constructor):
 *
 *   getHttpCode(): int     the HTTP status to send, overriding the exception code
 *                          (orange\framework\exceptions\http\Http supplies this)
 *   getOutput(): string    a ready-made body, skipping view resolution entirely
 *   decorate(Error $self)  free rein over this object before it renders - the catch-all
 *                          when the two above are not enough (Http301 uses it to add a
 *                          Location header)
 *
 * That contract is why this class's state is public rather than protected.
 *
 * The body comes from the first error view found by findView(), searching most to
 * least specific - errors/{env}/{requestType}/404, errors/{requestType}/{env}/404,
 * errors/{requestType}/404, errors/404, errors, 404. With no match it falls back to
 * viewRaw(), which formats the collected data by request type: JSON for ajax/json,
 * an escaped <pre> block for html, print_r for CLI.
 *
 * @package orange\framework
 */
class Error extends Singleton
{
    /** include ConfigurationTrait methods */
    use ConfigurationTrait;

    /**
     * Everything below is public so a thrown exception's decorate() can reach it -
     * see the class docblock. Treat it as this class's extension surface, not as
     * incidentally-exposed internals.
     */

    /** resolved from the container, or built directly when nothing is registered */
    public DataInterface $data;
    public InputInterface $input;
    public ViewInterface $view;

    /** turns an error view name into a path; the view engine only renders */
    public ViewFinderInterface $viewFinder;
    public OutputInterface $output;

    /** application error code - the exception's own code when it is non-zero */
    public int $code = 500;

    /** HTTP status from the exception's getHttpCode(); 0 means "fall back to $code" */
    public int $httpCode = 0;

    /** 'cli', 'html' or 'ajax' - also used as a directory segment when finding a view */
    public string $requestType = '';

    /** path segments findView() assembles a candidate error view path from */
    public string $errorViewDirectory = '';
    public string $envDirectory = '';
    public string $requestTypeDirectory = '';

    /**
     * set either of these from decorate() to take over what gets rendered/sent.
     * $viewFile takes a view name ('errors/html/404') or an absolute path -
     * resolveViewFile() accepts both, since decorate() hooks predate the view
     * engine wanting a path
     */
    public string $viewFile = '';
    public string $outputContent = '';

    /**
     * Constructor
     *
     * Initializes the Error class with the given configuration and optional exception.
     *
     * @param array $config Configuration options.
     * @param ContainerInterface|null $container Optional DI container used to resolve the
     *        data/input/view/output services; falls back to the container() helper, and
     *        then to Orange's own default classes, when not provided.
     * @param Throwable|null $thrown Optional exception causing the error.
     */
    protected function __construct(array $config = [], public ?ContainerInterface $container = null, ?Throwable $thrown = null)
    {
        logMsg('DEBUG', __METHOD__);

        // if they didn't send in a container we try to attach the default
        $this->container = $container ?? container();

        // merge defaults with passed in config
        $this->config = $this->mergeConfigWith($config);

        // try to setup our services
        // these are loaded from the service container or
        // if it's not loaded we manually load the orange ones
        $this->data = $this->getService('data', []);
        $this->input = $this->getService('input', [[]]);
        $this->view = $this->getService('view', [[], $this->data]);
        // resolves an error view name to a file - see findView()
        $this->viewFinder = $this->getService('viewFinder', [[]], 'ViewFinder');
        $this->output = $this->getService('output', [[], $this->input]);

        // base view directory to search for error views
        $this->errorViewDirectory = $this->config['error view directory'];

        // assume worst case it's production - also make lowercase because we use this as a directory in the path
        $this->envDirectory = defined('ENVIRONMENT') ? mb_strtolower((string) ENVIRONMENT) : 'production';

        // let's try to determine the output type
        // the output class will auto convert this to a mime type for output
        // html, ajax, cli
        // request type as lowercase (true)
        $this->requestType = $this->input->requestType(true);

        // Use this as a directory when looking for an error view file
        $this->requestTypeDirectory = $this->requestType;

        // do we have a exception attached?
        if ($thrown) {
            // if an exception is attached then an exception instanced this object
            // so grab the code and message
            $this->data->merge([
                'message' => $thrown->getMessage(),
                'code' => $thrown->getCode(),
                'options' => $thrown->getTrace(),
                'line' => $thrown->getLine(),
                'file' => $thrown->getFile(),
            ]);

            // if the thrown exceptions error code
            // is great than 0 then use that as the code
            if ((int)$thrown->getCode() > 0) {
                $this->code = (int)$thrown->getCode();
            }

            // if the thrown exception has the method getHttpCode
            // then call it and use it's output as the httpCode
            if (method_exists($thrown, 'getHttpCode')) {
                /** @disregard */
                $this->httpCode = $thrown->getHttpCode();
            }

            // if the thrown exception has the method getOutput
            // then call it and write it's output in output
            if (method_exists($thrown, 'getOutput')) {
                /** @disregard */
                $this->outputContent = $thrown->getOutput();
            }

            // if the thrown exception has the method decorate
            // allow the exception the chance to "decorate" the error class
            // this is a catch all in case getHttpCode & getOutput aren't enough
            if (method_exists($thrown, 'decorate')) {
                /** @disregard */
                $thrown->decorate($this);
            }
        }

        // if no output content set up by $thrown
        // then try to figure out a viewFile
        if (empty($this->outputContent) && empty($this->viewFile)) {
            $this->outputContent = $this->renderViewBasedOnCode($this->code, $this->httpCode);
        }

        // if a view file is setup by $thrown or from renderViewBasedOnCode use that
        if (!empty($this->viewFile)) {
            $resolved = $this->resolveViewFile($this->viewFile);

            // never let a missing error view become a second, harder to read
            // error - fall through to the raw dump instead
            $this->outputContent = $resolved !== '' ? $this->view->render($resolved) : $this->viewRaw();
        }

        // if we still don't have output content then use the raw fallback
        $this->sendOutput($this->outputContent);
    }

    /**
     * Displays an error with the given code and message.
     *
     * @param int $code Error code.
     * @param string $message Error message.
     * @param array|null $options Additional options for error details.
     */
    public function show(int $code = 500, string $message = '', ?array $options = null): void
    {
        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', __METHOD__, ['code' => $code, 'message' => $message, 'options' => $options]);
        }

        $this->data->merge([
            'code' => $code,
            'message' => $message,
            'options' => $options,
        ]);

        $this->sendOutput($this->renderViewBasedOnCode($code));
    }

    /**
     * Sends the appropriate HTTP response code based on the error or HTTP code.
     */
    public function sendResponseCode(): void
    {
        logMsg('DEBUG', __METHOD__);

        if ($this->httpCode > 0) {
            $responseCode = $this->httpCode;
        } elseif ($this->code > 0) {
            $responseCode = $this->code;
        } else {
            $responseCode = 500;
        }

        if ($this->requestType != 'cli') {
            $this->output->responseCode($responseCode);
        }
    }

    /**
     * Sends the appropriate MIME type for the response.
     */
    public function sendMimeType(): void
    {
        logMsg('DEBUG', __METHOD__);

        $type = ($this->requestType == 'ajax') ? 'json' : 'html';

        $this->output->contentType($type);
    }

    /**
     * Sends the output content to the client and terminates the script.
     *
     * @param string $content Content to send as the response.
     * @param int $exitCode Exit code for script termination.
     */
    public function sendOutput(string $content, int $exitCode = 1): void
    {
        logMsg('DEBUG', __METHOD__ . ' ' . $exitCode);
        logMsg('DEBUG', $content);

        $this->output->flush();

        $this->output->write($content);

        $this->sendResponseCode();
        $this->sendMimeType();

        $this->output->send($exitCode);

        // fail safe exit "with error"
        exit($exitCode);
    }

    /**
     * Renders a view based on the error code and optional HTTP code.
     *
     * @param int $code Error code.
     * @param int $httpCode Optional HTTP status code.
     * @return string Rendered view content.
     */
    protected function renderViewBasedOnCode(int $code, int $httpCode = 0): string
    {
        logMsg('DEBUG', __METHOD__ . ' ' . $code . ' ' . $httpCode);

        // use the code as the view we are looking for
        $viewName = ($httpCode != 0) ? (string)$httpCode : (string)$code;

        $foundViewFile = $this->findView($viewName);

        return !empty($foundViewFile) ? $this->view->render($foundViewFile) : $this->viewRaw();
    }

    /**
     * Finds a suitable view file for the error.
     *
     * @param string $view Name of the view file.
     * @return string Path to the view file or an empty string if not found.
     */
    protected function findView(string $view): string
    {
        logMsg('DEBUG', __METHOD__ . ' ' . $view);

        $foundViewPath = '';

        // did someone already attach output?
        $searchPaths = [
            // search env directory /errors/dev/html/404.php
            implode(DIRECTORY_SEPARATOR, [$this->errorViewDirectory, $this->envDirectory, $this->requestTypeDirectory, $view]),
            // search env directory /errors/html/dev/404.php
            implode(DIRECTORY_SEPARATOR, [$this->errorViewDirectory, $this->requestTypeDirectory, $this->envDirectory, $view]),
            // then search non env directory /errors/html/404.php
            implode(DIRECTORY_SEPARATOR, [$this->errorViewDirectory, $this->requestTypeDirectory, $view]),
            // then just error code directory /errors/404.php
            implode(DIRECTORY_SEPARATOR, [$this->errorViewDirectory, $view]),
            // then just error code directory /errors.php
            implode(DIRECTORY_SEPARATOR, [$this->errorViewDirectory]),
            // then just error code directory /404.php
            implode(DIRECTORY_SEPARATOR, [$view]),
        ];

        foreach ($searchPaths as $searchPath) {
            if ($foundViewPath = $this->locateView($searchPath)) {
                break;
            }
        }

        logMsg('DEBUG', __METHOD__ . ' ' . $foundViewPath);

        return $foundViewPath;
    }

    /**
     * Turn one error view name into a path, or '' when there is no such view.
     *
     * @param string $name eg. 'errors/html/404'
     * @return string absolute path, or '' when nothing has it
     */
    protected function locateView(string $name): string
    {
        // the finder first, so an application can override any error view just
        // by owning its name, exactly like any other view
        if ($this->viewFinder->exists($name)) {
            return $this->viewFinder->find($name);
        }

        // then the copies that ship next to this class. These are the
        // last-resort pages an error handler cannot afford to be missing, and
        // an application that has not generated a view map yet has nothing in
        // the finder at all - so they are addressed directly rather than
        // looked up
        $bundled = __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $name . '.php';

        return is_file($bundled) ? $bundled : '';
    }

    /**
     * Resolve whatever ended up in $viewFile to a path the view engine can render.
     *
     * Two things set that property. renderViewBasedOnCode() puts a path there,
     * and a thrown exception's decorate() hook has always put a *name* there -
     * that hook is a documented extension surface, so both keep working rather
     * than the name silently rendering nothing.
     *
     * @return string absolute path, or '' when nothing has it
     */
    protected function resolveViewFile(string $viewFile): string
    {
        return is_file($viewFile) ? $viewFile : $this->locateView($viewFile);
    }

    /**
     * Retrieves a service instance by its name.
     *
     * @param string $name Service name.
     * @param array $arguments Arguments to pass to the service constructor, if necessary.
     * @param string|null $className Short class name backing this service. Only
     *        needed when it is not simply the ucfirst'd service name - deriving
     *        it that way lower cases the rest, which turns 'viewFinder' into
     *        'Viewfinder' and stops matching the file on a case-sensitive
     *        filesystem (it happens to work on macOS, which hides the bug).
     * @return mixed The service instance.
     */
    protected function getService(string $name, array $arguments, ?string $className = null): mixed
    {
        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', __METHOD__ . ' ' . $name, ['name' => $name, 'arguments' => $arguments]);
        }

        $service = null;

        try {
            // try to get the service from the container first
            $service = $this->container->get($name);
        } catch (ServiceNotFound) {
            // only fall back to orange's own default classes/services when the container
            // genuinely has nothing registered under this name - catching every Throwable
            // here would also swallow a real failure while building an actually-registered
            // service (e.g. a bad autowired dependency), silently masking it behind a
            // fallback instance instead of letting the real error surface
            $className ??= ucfirst(mb_strtolower($name));

            // same folder as this class
            require_once __DIR__ . DIRECTORY_SEPARATOR . $className . '.php';

            // default orange namespace
            $namespace = '\\orange\\framework\\' . $className;

            // if no arguments just get instance otherwise pass arguments to instance
            if (empty($arguments)) {
                $service = $namespace::getInstance();
            } else {
                $service = $namespace::getInstance(...$arguments);
            }
        }

        return $service;
    }

    /**
     * Provides a fallback raw view if no suitable template is found.
     *
     * @return string Raw view content.
     */
    protected function viewRaw(): string
    {
        logMsg('DEBUG', __METHOD__);

        // cast to array
        $finalData = (array)$this->data;

        // fall back to hard coded response format
        // 'ajax' is the value Input::requestType() actually returns for AJAX requests
        // (sendMimeType() sends a "json" content type for it) - 'json' is kept too since
        // it's a reasonable requestType value for any other caller of this method
        return match ($this->requestType) {
            'json', 'ajax' => json_encode($finalData, JSON_PRETTY_PRINT),
            'html' => $this->viewRawBuildHtml($finalData),
            default => print_r($finalData, true) . PHP_EOL,
        };
    }

    protected function viewRawBuildHtml(array $finalData): string
    {
        logMsg('DEBUG', __METHOD__);

        $finalOutput = '<pre>';

        // exception messages, file paths, and trace data can echo back attacker-controlled
        // input (a bad route, a bad header, a validation message quoting the request value),
        // so every field is HTML-escaped before being embedded in this raw fallback view
        if (isset($finalData['code'])) {
            $finalOutput .= htmlspecialchars((string)$finalData['code'], ENT_QUOTES) . PHP_EOL;
        }

        if (isset($finalData['message'])) {
            $finalOutput .= htmlspecialchars((string)$finalData['message'], ENT_QUOTES) . PHP_EOL;
        }

        if (isset($finalData['file'])) {
            $finalOutput .= 'File: ' . htmlspecialchars((string)$finalData['file'], ENT_QUOTES) . PHP_EOL;
        }

        if (isset($finalData['line'])) {
            $finalOutput .= 'Line: ' . htmlspecialchars((string)$finalData['line'], ENT_QUOTES) . PHP_EOL;
        }

        if (isset($finalData['options'])) {
            $finalOutput .= htmlspecialchars(print_r($finalData['options'], true), ENT_QUOTES) . PHP_EOL;
        }

        $finalOutput .= '</pre>';

        return $finalOutput;
    }
}
