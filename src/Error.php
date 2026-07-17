<?php

declare(strict_types=1);

namespace orange\framework;

use Throwable;
use orange\framework\base\Singleton;
use orange\framework\helpers\DirectorySearch;
use orange\framework\interfaces\DataInterface;
use orange\framework\interfaces\ViewInterface;
use orange\framework\interfaces\InputInterface;
use orange\framework\traits\ConfigurationTrait;
use orange\framework\interfaces\OutputInterface;
use orange\framework\interfaces\ContainerInterface;

/**
 * Overview of Error.php
 *
 * This file defines the Error class in the orange\framework namespace.
 * It is a centralized error handler for the framework, designed to capture exceptions,
 * render meaningful error responses, and send them back to the client in a format appropriate to
 * the request type (HTML, JSON/AJAX, CLI). It extends Singleton, ensuring a single instance is used application-wide,
 * and uses the ConfigurationTrait for flexible setup.
 *
 * ⸻
 *
 * 1. Core Purpose
 *  •   Capture exceptions or error codes.
 *  •   Load error details into a data service.
 *  •   Select an appropriate error view (based on environment, request type, or error code).
 *  •   Render the view or provide a raw fallback output.
 *  •   Send the response to the client with correct HTTP code, content type, and exit code.
 *
 * ⸻
 *
 * 2. Key Properties
 *  •   Service instances:
 *  •   $data → holds error data (message, code, trace, etc.).
 *  •   $input → provides request type information (HTML, AJAX, CLI).
 *  •   $view → used to render error templates.
 *  •   $output → sends headers, status codes, and content to the client.
 *  •   $container → dependency injection container for resolving services.
 *  •   Error context:
 *  •   $code (default 500) → application error code.
 *  •   $httpCode → optional HTTP status code from the exception.
 *  •   $requestType → request channel (cli, html, ajax).
 *  •   $errorViewDirectory, $envDirectory, $requestTypeDirectory → directories to search for error views.
 *  •   $viewFile, $outputContent → resolved error template file or rendered output.
 *
 * ⸻
 *
 * 3. Constructor Behavior
 *
 * When instantiated, the class:
 *  1.  Loads configuration and resolves dependencies (data, input, view, output).
 *  2.  Determines the environment (production, development, etc.) and request type.
 *  3.  If given a Throwable, extracts details (message, code, file, line, stack trace).
 *  4.  Supports custom exception methods (getHttpCode, getOutput, decorate) for enhanced control.
 *  5.  Attempts to resolve an error view file based on error/HTTP code.
 *  6.  If no template is found, falls back to raw output formatting (plain text, JSON, or HTML <pre>).
 *  7.  Immediately sends output to the client and terminates execution.
 *
 * ⸻
 *
 * 4. Key Methods
 *  •   show($code, $message, $options) → manually trigger and render an error with code and message.
 *  •   sendResponseCode() → sends appropriate HTTP status (defaults to 500).
 *  •   sendMimeType() → sets response type (html, json, or defaults).
 *  •   sendOutput($content, $exitCode) → flushes output buffer, writes content, sends headers, and exits.
 *  •   renderViewBasedOnCode($code, $httpCode) → looks for an error template by code or status.
 *  •   findView($view) → searches directories in a defined order (env + request type fallbacks).
 *  •   viewRaw() → fallback plain output if no template found (formats based on request type).
 *  •   getService($name, $arguments) → resolves dependencies from the container, falling back to Orange defaults.
 *
 * ⸻
 *
 * 5. Error Rendering Logic
 *  1.  Preferred → environment + request type-specific error view (e.g., errors/dev/html/404.php).
 *  2.  Fallbacks → environment-specific or general error views.
 *  3.  Last resort → raw inline response (JSON, HTML <pre>, or CLI print).
 *
 * ⸻
 *
 * 6. Big Picture
 *
 * Error.php is the last line of defense in the framework.
 * It ensures that all errors and exceptions result in a consistent,
 * informative, and environment-appropriate response.
 * It combines dependency-injected services (Input, Output, View, Data) with configuration to control behavior,
 * and it guarantees the response is always flushed and sent.
 *
 * @package orange\framework
 */
class Error extends Singleton
{
    /** include ConfigurationTrait methods */
    use ConfigurationTrait;

    /**
     * Data service instance
     */
    public DataInterface $data;

    /**
     * Input service instance
     */
    public InputInterface $input;

    /**
     * View service instance
     */
    public ViewInterface $view;

    /**
     * Output service instance
     */
    public OutputInterface $output;

    /**
     * Default error code
     */
    public int $code = 500;

    /**
     * HTTP status code
     */
    public int $httpCode = 0;

    /**
     * Request type (e.g., 'cli', 'html', 'ajax')
     */
    public string $requestType = '';

    /**
     * Directory for error views
     */
    public string $errorViewDirectory = '';

    /**
     * Directory for environment-specific views
     */
    public string $envDirectory = '';

    /**
     * Directory for request type-specific views
     */
    public string $requestTypeDirectory = '';

    /**
     * The view file used for rendering
     */
    public string $viewFile = '';

    /**
     * Content to be sent as output
     */
    public string $outputContent = '';

    /**
     * Constructor
     *
     * Initializes the Error class with the given configuration and optional exception.
     *
     * @param array $config Configuration options.
     * @param Throwable|null $thrown Optional exception causing the error.
     */
    protected function __construct(array $config = [], public ?ContainerInterface $container = null, ?Throwable $thrown = null)
    {
        logMsg('INFO', __METHOD__);

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
        $this->output = $this->getService('output', [[], $this->input]);

        // base view directory to search for error views
        $this->errorViewDirectory = $this->config['error view directory'];

        // assume worst case it's production - also make lowercase because we use this as a directory in the path
        $this->envDirectory = defined('ENVIRONMENT') ? mb_strtolower(ENVIRONMENT) : 'production';

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
            // this is a catch all incase getHttpCode & getOutput aren't enough
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
            $this->outputContent = $this->view->render($this->viewFile);
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
        logMsg('INFO', __METHOD__, ['code' => $code, 'message' => $message, 'options' => $options]);

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
        logMsg('INFO', __METHOD__);

        if (isset($this->httpCode) && $this->httpCode > 0) {
            $responseCode = (int)$this->httpCode;
        } elseif (isset($this->code) && $this->code > 0) {
            $responseCode = (int)$this->code;
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
        logMsg('INFO', __METHOD__);

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
        logMsg('INFO', __METHOD__ . ' ' . $exitCode);
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
        logMsg('INFO', __METHOD__ . ' ' . $code . ' ' . $httpCode);

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
        logMsg('INFO', __METHOD__ . ' ' . $view);

        $foundViewPath = '';

        // let's make sure our local views directory is added to the search as a last alternative
        $this->view->search->addDirectory(__DIR__ . DIRECTORY_SEPARATOR . 'views', DirectorySearch::LAST);

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
            if ($this->view->search->exists($searchPath)) {
                $foundViewPath = $searchPath;
                break;
            }
        }

        logMsg('INFO', __METHOD__ . ' ' . $foundViewPath);

        return $foundViewPath;
    }

    /**
     * Retrieves a service instance by its name.
     *
     * @param string $name Service name.
     * @param array $arguments Arguments to pass to the service constructor, if necessary.
     * @return mixed The service instance.
     */
    protected function getService(string $name, array $arguments): mixed
    {
        logMsg('INFO', __METHOD__ . ' ' . $name, ['name' => $name, 'arguments' => $arguments]);

        $service = null;

        try {
            // try to get the service from the container first
            $service = $this->container->get($name);
        } catch (Throwable $e) {
            // fall back to orange classes / services
            $className = ucfirst(mb_strtolower($name));

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
        logMsg('INFO', __METHOD__);

        // default output
        $finalOutput = '';

        // cast to array
        $finalData = (array)$this->data;

        // fall back to hard coded response format
        switch ($this->requestType) {
            case 'json':
                $finalOutput = json_encode($finalData, JSON_PRETTY_PRINT);
                break;
            case 'html':
                $finalOutput = $this->viewRawBuildHtml($finalOutput, $finalData);
                break;
            case 'cli':
            default:
                $finalOutput = print_r($finalData, true) . PHP_EOL;
                break;
        }

        return $finalOutput;
    }

    protected function viewRawBuildHtml(string $finalOutput, array $finalData): string
    {
        logMsg('INFO', __METHOD__);

        $finalOutput .= '<pre>';

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
