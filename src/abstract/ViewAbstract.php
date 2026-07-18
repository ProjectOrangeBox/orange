<?php

declare(strict_types=1);

namespace orange\framework\abstract;

use Throwable;
use orange\framework\base\Singleton;
use orange\framework\exceptions\InvalidValue;
use orange\framework\exceptions\filesystem\Directory;
use orange\framework\helpers\DirectorySearch;
use orange\framework\interfaces\DataInterface;
use orange\framework\interfaces\ViewInterface;
use orange\framework\traits\ConfigurationTrait;
use orange\framework\interfaces\RouterInterface;
use orange\framework\exceptions\ResourceNotFound;
use orange\framework\exceptions\view\ViewNotFound;
use orange\framework\interfaces\DirectorySearchInterface;
use orange\framework\exceptions\filesystem\FileNotWritable;
use orange\framework\exceptions\filesystem\DirectoryNotWritable;
use orange\framework\exceptions\IncorrectInterface;

/**
 * Overview of ViewAbstract.php
 *
 * This file defines the ViewAbstract class in the orange\framework\abstract namespace.
 * It is the base class for all view rendering engines in the Orange framework.
 * Concrete engines (like PHP views, Twig, Markdown, etc.)
 * extend it to provide specific rendering behavior, while sharing the same core setup and utilities.
 * It also enforces the Singleton pattern and implements the ViewInterface.
 *
 * ⸻
 *
 * 1. Core Purpose
 *  •   Provide a foundation for rendering views (templates) in different formats.
 *  •   Manage configuration, view paths, aliases, caching, and data injection.
 *  •   Allow support for dynamic view resolution based on routing.
 *  •   Handle file-based and string-based rendering consistently.
 *
 * ⸻
 *
 * 2. Key Properties
 *  •   $search → a DirectorySearch instance for locating view files.
 *  •   $data → a data source (implements DataInterface) passed into views.
 *  •   $router → optional router instance for resolving dynamic view names.
 *  •   $debug → toggles debug mode (forces re-rendering, no caching).
 *  •   $allowDynamicViews → enables placeholders like $c/$m in view paths.
 *  •   $tempDirectory → directory for temporary cached view files (string templates).
 *  •   $alias → maps view names to alternate paths.
 *  •   $subPathSize → determines subdirectory depth for hashing string templates.
 *  •   $changeableTypeCheck → defines which properties can be updated at runtime and their type checks.
 *
 * ⸻
 *
 * 3. Initialization
 *  •   Constructor merges config, sets debug/dynamic flags, validates the temp directory, loads aliases, and builds the DirectorySearch utility with configured view paths.
 *  •   Can also preload resources into the search utility.
 *
 * ⸻
 *
 * 4. Key Methods
 *  1.  Searching and Aliasing
 *  •   search() → returns the directory search utility.
 *  •   addAlias($view, $aliasView) → registers a view alias.
 *  •   resolveAlias($view) → replaces a view name with its alias.
 *  2.  Rendering Views
 *  •   render($view, $data, $options) → locates a view file and renders it with merged data.
 *  •   renderString($string, $data, $options) → renders directly from a string (compiled into a temp file).
 *  •   generate($__viewFilePath, $__dataArray) → internal method that executes the view file with provided data.
 *  3.  Dynamic View Resolution
 *  •   resolveDynamicView($view) → replaces placeholders like $c (controller), $m (method), $1, $2 (namespace segments) using the matched route.
 *  4.  Data Handling
 *  •   data($data) → merges new data with the view’s internal DataInterface.
 *  5.  Configuration
 *  •   change($name, $value) → safely update configurable properties (e.g., enable debug mode, change temp directory).
 *  6.  File Safety
 *  •   isFileWritable($file) → ensures the target file or directory is writable (creates directories if needed).
 *
 * ⸻
 *
 * 5. Error Handling
 *  •   Throws ViewNotFound if a view file cannot be located.
 *  •   Throws DirectoryNotWritable or FileNotWritable if caching directories are inaccessible.
 *  •   Throws InvalidValue for incorrect config changes.
 *  •   Wraps low-level errors into framework exceptions for consistency.
 *
 * ⸻
 *
 * 6. Big Picture
 *  •   ViewAbstract is the backbone of Orange’s view system.
 *  •   It standardizes how views are located, resolved, cached, and rendered, while allowing flexible engines to be built on top.
 *  •   By combining search, aliasing, dynamic resolution, and rendering, it ensures all view engines behave consistently across the framework.
 *
 * ⸻
 *
 * Recommendation: Treat ViewAbstract as the template engine foundation.
 * All custom view renderers should extend it to inherit search, caching, and rendering logic.
 */
abstract class ViewAbstract extends Singleton implements ViewInterface
{
    /** include ConfigurationTrait methods */
    use ConfigurationTrait;

    /**
     * View file search utility
     */
    public DirectorySearch $search;

    /**
     * Debug mode toggle
     */
    protected bool $debug = false;

    /**
     * Allow dynamic views toggle
     */
    protected bool $allowDynamicViews = false;

    /**
     * Temporary directory for cached view files
     */
    protected string $tempDirectory = '';

    /**
     * Aliases for view names
     */
    protected array $alias = [];

    /**
     * Number of characters for sub-directory path hashing
     */
    protected int $subPathSize = 6;

    /**
     * Validations for changeable properties
     */
    protected array $changeableTypeCheck = [
        'tempDirectory' => 'is_string',
        'debug' => 'is_bool',
        'allowDynamicViews' => 'is_bool',
    ];

    /**
     * Constructor is protected to enforce Singleton pattern.
     * Use Singleton::getInstance() to create an instance.
     *
     * @param array $config Configuration array.
     * @param DataInterface|null $data Optional data source for the view.
     * @throws IncorrectInterface|Directory
     */
    protected function __construct(array $config, protected ?DataInterface $data = null, protected ?RouterInterface $router = null)
    {
        logMsg('INFO', __METHOD__);

        $this->config = $this->mergeConfigWith($config, false);

        if ($data) {
            $this->data = $data;
        }

        if ($router) {
            $this->router = $router;
        }

        $this->debug = $this->config['debug'];
        $this->allowDynamicViews = $this->config['allow dynamic views'];
        $this->tempDirectory = rtrim($this->config['temp directory'], DIRECTORY_SEPARATOR);

        if (!is_dir($this->tempDirectory)) {
            throw new Directory('Unknown Directory "' . $this->tempDirectory . '".');
        }

        $this->alias = $this->config['view aliases'];
        $this->subPathSize = $this->config['sub path size'];

        // Initialize DirectorySearch for locating views
        $this->search = new DirectorySearch([
            'quiet' => false,
            'directories' => $this->config['view paths'] + $this->config['default view paths'],
            'match' => '*.' . trim($this->config['extension'], '.'),
            'recursive' => true,
            'lock after scan' => false,
            'normalize keys' => true,
            'resource key style' => 'view',
            'pend' => DirectorySearchInterface::PREPEND,
        ]);
        if (isset($this->config['resources'])) {
            $this->search->addResources($this->config['resources']);
        }
    }

    /**
     * Returns the search utility.
     *
     * @return DirectorySearchInterface
     */
    public function search(): DirectorySearchInterface
    {
        logMsg('INFO', __METHOD__);
        return $this->search;
    }

    /**
     * Add an alias for a view.
     *
     * @param string $view Original view name.
     * @param string $aliasView Alias name.
     */
    public function addAlias(string $view, string $aliasView): void
    {
        logMsg('INFO', __METHOD__ . ' ' . $view . ' ' . $aliasView);
        $this->alias[$view] = $aliasView;
    }

    /**
     * Render a view file.
     *
     * @param string $view View name or path.
     * @param array $data Data to pass into the view.
     * @param array $options Rendering options.
     * @return string Rendered view content.
     * @throws ViewNotFound|ResourceNotFound
     */
    public function render(string $view = '', array $data = [], array $options = []): string
    {
        logMsg('INFO', __METHOD__);
        logMsg('DEBUG', '', ['view' => $view, 'data' => $data, 'options' => $options]);


        // allow dynamic views only if router ALSO provided
        if ($this->allowDynamicViews && isset($this->router)) {
            $view = $this->resolveDynamicView($view);
        }

        $view = $this->resolveAlias($view);

        try {
            $found = $this->search->findFirst($view);
        } catch (ResourceNotFound $e) {
            // convert Resource Not Found into View Not Found Exception
            // because the resource is a view when used in this context
            throw new ViewNotFound($view, 500, $e);
        }

        // generate the view based on the found view file
        return $this->generate($found, $this->data($data));
    }

    /**
     * Render a view from a string.
     *
     * @param string $string Template content.
     * @param array $data Data for the template.
     * @param array $options Rendering options.
     * @return string Rendered output.
     * @throws FileNotWritable
     */
    public function renderString(string $string, array $data = [], array $options = []): string
    {
        logMsg('INFO', __METHOD__);
        logMsg('DEBUG', '', ['string' => $string, 'data' => $data, 'options' => $options]);

        // convert the view into a unique hash
        // and make sure it's not binary value!
        $filename = sha1($string, false);

        // are we putting the template file in a sub directory?
        // this is usually a good idea so your OS doesn't have a directory with 10,000 files in it
        $subPath = ($this->subPathSize > 0) ? DIRECTORY_SEPARATOR . substr($filename, 0, $this->subPathSize) : '';

        // use the same file extension as the file based "normal" views
        // because we save this as a file in order to "load" it
        $templatePath = $this->tempDirectory . $subPath . DIRECTORY_SEPARATOR . $filename . $this->config['extension'];

        // if the file doesn't exist and debug is not true
        if (!\file_exists($templatePath) || $this->debug === true) {
            // throws error
            $this->isFileWritable($templatePath);

            // write the file in a way to not run into
            // somebody else writing the same file at the same time
            if (file_put_contents_atomic($templatePath, $string) === false) {
                // didn't write anything?
                throw new FileNotWritable();
            }
        }

        return $this->generate($templatePath, $this->data($data));
    }

    /**
     * Change a configurable property.
     *
     * @param string $name Property name.
     * @param mixed $value New value.
     * @return self
     * @throws InvalidValue
     */
    public function change(string $name, mixed $value): self
    {
        logMsg('INFO', __METHOD__);
        logMsg('DEBUG', '', ['name' => $name, 'value' => $value]);

        if (!isset($this->changeableTypeCheck[$name])) {
            throw new InvalidValue($name);
        }

        // convert 'Shipping Carrier' to 'shippingCarrier'
        $typeCheckFunction = $this->changeableTypeCheck[$name];
        if (!$typeCheckFunction($value)) {
            // arrays trigger an "Array to string conversion" warning and non-Stringable
            // objects fatal outright if concatenated directly - describe by type instead
            throw new InvalidValue((is_scalar($value) ? (string)$value : get_debug_type($value)) . ' is not ' . $typeCheckFunction);
        }

        // convert a human readable name to a variable name
        $variableName = str_replace(' ', '', lcfirst(ucwords($name)));

        // set value
        $this->$variableName = $value;

        return $this;
    }

    /**
     * Generate the final rendered view content.
     *
     * @param string $__viewFilePath File path to the view.
     * @param array $__dataArray Data for rendering.
     * @return string Rendered output.
     */
    protected function generate(string $__viewFilePath, array $__dataArray): string
    {
        logMsg('INFO', __METHOD__ . ' ' . $__viewFilePath);
        logMsg('DEBUG', '', ['__viewFilePath' => $__viewFilePath, '__dataArray' => $__dataArray]);

        // what file are we looking for?
        if (!\file_exists($__viewFilePath)) {
            throw new ViewNotFound('View "' . $__viewFilePath . '" Not Found.');
        }

        // extract out view data and make it in scope
        extract((array)$__dataArray, \EXTR_OVERWRITE);

        // start output cache
        ob_start();

        // load in view (which now has access to the in scope view data
        require $__viewFilePath;

        // capture cache and return
        return ob_get_clean();
    }

    /**
     * Check if a file is writable, and if not, attempt to make its directory writable.
     *
     * @param string $file The file path to check.
     * @return bool Returns true if the file or directory is writable.
     * @throws DirectoryNotWritable If the directory cannot be created or is not writable.
     * @throws FileNotWritable If the file cannot be written to.
     */
    protected function isFileWritable(string $file): bool
    {
        // Get the directory of the file
        $dir = dirname($file);

        // If the directory doesn't exist, attempt to create it
        if (!file_exists($dir)) {
            try {
                // 0755, not 0777: this directory holds compiled string-templates that
                // get require()'d as executable PHP (see renderString()), so a
                // world-writable cache dir would let any other local user on a
                // permissive-umask host plant code that gets executed on next render
                mkdir($dir, 0755, true);
            } catch (Throwable $e) {
                throw new DirectoryNotWritable($dir);
            }
        }

        // Check if the directory is writable
        if (!is_writable($dir)) {
            throw new FileNotWritable($dir);
        }

        return true;
    }

    /**
     * Resolve an alias to its mapped view path if an alias exists.
     *
     * @param string $view The original view name.
     * @return string The resolved view name after alias mapping.
     */
    protected function resolveAlias(string $view): string
    {
        // Check if an alias exists for the given view
        $alias = $this->alias[$view] ?? $view;

        logMsg('INFO', __METHOD__ . ' ' . $view . ' ' . $alias);

        return $alias;
    }

    /**
     * Merge incoming data with the view's existing data source, if available.
     *
     * @param array $data Incoming data array for the view.
     * @return array The merged data array.
     */
    protected function data(array $data): array
    {
        // If view-level data is set, merge it with the provided data
        if ($this->data) {
            $data = array_replace((array)$this->data, $data);
        }

        // Ensure the result is an array, not a Data Object
        return $data;
    }

    /**
     * Resolve dynamic view paths based on router callback information.
     *
     * Dynamic placeholders in the view string (e.g., $c, $m, $1, $2) are replaced with
     * controller, method, or namespace segments dynamically.
     *
     * @param string $view The view string with possible dynamic placeholders.
     * @return string The dynamically resolved view string.
     * @throws InvalidValue If controller or method is missing while resolving placeholders.
     */
    protected function resolveDynamicView(string $view): string
    {
        logMsg('INFO', __METHOD__ . ' argument: "' . $view . '"');

        // Define dynamic placeholders
        $prefix = '$';
        $controllerString = $prefix . 'c';
        $methodString = $prefix . 'm';

        // Retrieve controller and method from the router's matched callback
        list($controller, $method) = $this->router->getMatched('callback');

        // Check if placeholders exist or if the view string is dynamic
        if (strpos($view, $prefix) !== false || strpos($view, '*') !== false || $view === '') {
            // Handle default controller and method placeholders
            if ($view == '') {
                $view = $controllerString . '/' . $methodString;
            } elseif (str_ends_with($view, '*/*')) {
                $view = substr($view, 0, -3) . $controllerString . '/' . $methodString;
            }

            if (str_ends_with($view, '/*')) {
                $view = substr($view, 0, -2) . '/' . $methodString;
            }

            // Replace method placeholder
            if (strpos($view, $methodString) !== false) {
                if (!isset($method)) {
                    throw new InvalidValue('Missing Method and therefore cannot generate dynamic view.');
                }
                $view = str_replace($methodString, $method, $view);
            }

            // Replace controller placeholder and namespace segments
            if (strpos($view, $prefix) !== false) {
                if (!isset($controller)) {
                    throw new InvalidValue('Missing Controller and therefore cannot generate dynamic view.');
                }

                // Normalize the controller string
                $namespacedController = mb_strtolower($controller);

                // Remove "controller" suffix if it exists
                if (str_ends_with($namespacedController, 'controller')) {
                    $namespacedController = substr($namespacedController, 0, -10);
                }

                // Break controller namespace into segments
                foreach (explode('/', str_replace('\\', '/', $namespacedController)) as $index => $segment) {
                    $view = str_replace($prefix . ($index + 1), $segment, $view);

                    // Store the last segment
                    $controllerName = $segment;
                }

                // Replace the controller placeholder with the final segment
                $view = str_replace($controllerString, $controllerName, $view);
            }
        }

        logMsg('INFO', __METHOD__ . ' return: "' . $view . '"');

        return $view;
    }
}
