<?php

declare(strict_types=1);

namespace orange\framework\abstract;

use Throwable;
use orange\framework\base\Singleton;
use orange\framework\exceptions\InvalidValue;
use orange\framework\exceptions\filesystem\Directory;
use orange\framework\interfaces\DataInterface;
use orange\framework\interfaces\ViewInterface;
use orange\framework\traits\ConfigurationTrait;
use orange\framework\exceptions\view\ViewNotFound;
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
 *  •   Handle file-based and string-based rendering consistently.
 *
 * One concern, stated as a sentence: render this template with this data.
 *
 * Everything about *which* file that is has moved out. Routing did first - the
 * $c/$m/$1/$2 placeholders live on BaseController::renderView(), where the
 * routing context already is. Naming followed: aliases, module namespaces and
 * the fallback a package's views live under are ViewFinder's, and so is the
 * directory searching this class used to do on every render.
 *
 * So render() takes a path. It does not search, and it does not interpret the
 * string it is given - if no file is there, that is a ViewNotFound and not
 * something to go looking around for.
 *
 * ⸻
 *
 * 2. Key Properties
 *  •   $data → a data source (implements DataInterface) passed into views.
 *  •   $debug → toggles debug mode (forces re-rendering, no caching).
 *  •   $tempDirectory → directory for temporary cached view files (string templates).
 *  •   $subPathSize → determines subdirectory depth for hashing string templates.
 *  •   $changeableTypeCheck → defines which properties can be updated at runtime and their type checks.
 *
 * ⸻
 *
 * 3. Initialization
 *  •   Constructor merges config, sets the debug flag and validates the temp directory.
 *
 * ⸻
 *
 * 4. Key Methods
 *  1.  Rendering Views
 *  •   render($viewFile, $data, $options) → renders the given view file with merged data.
 *  •   renderString($string, $data, $options) → renders directly from a string (compiled into a temp file).
 *  •   generate($__viewFilePath, $__dataArray) → internal method that executes the view file with provided data.
 *  2.  Data Handling
 *  •   data($data) → merges new data with the view’s internal DataInterface.
 *  3.  Configuration
 *  •   change($name, $value) → safely update configurable properties (e.g., enable debug mode, change temp directory).
 *  4.  File Safety
 *  •   isFileWritable($file) → ensures the target file or directory is writable (creates directories if needed).
 *
 * ⸻
 *
 * 5. Error Handling
 *  •   Throws Directory (constructor) if the configured temp directory does not exist.
 *  •   Throws ViewNotFound if the given view file does not exist.
 *  •   Throws DirectoryNotWritable or FileNotWritable if caching directories are inaccessible.
 *  •   Throws InvalidValue for incorrect config changes.
 *  •   Wraps low-level errors into framework exceptions for consistency.
 *
 * ⸻
 *
 * 6. Big Picture
 *  •   ViewAbstract is the backbone of Orange’s view system.
 *  •   It standardizes how templates are compiled, cached and executed, while allowing flexible engines to be built on top.
 *  •   By sharing rendering, it ensures all view engines behave consistently across the framework.
 *
 * ⸻
 *
 * Recommendation: Treat ViewAbstract as the template engine foundation.
 * All custom view renderers should extend it to inherit caching and rendering logic.
 */
abstract class ViewAbstract extends Singleton implements ViewInterface
{
    /** include ConfigurationTrait methods */
    use ConfigurationTrait;

    /**
     * Debug mode toggle
     */
    protected bool $debug = false;

    /**
     * Temporary directory for cached view files
     */
    protected string $tempDirectory = '';

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
    ];

    /**
     * Constructor is protected to enforce Singleton pattern.
     * Use Singleton::getInstance() to create an instance.
     *
     * @param array $config Configuration array.
     * @param DataInterface|null $data Optional data source for the view.
     * @throws Directory If the configured temp directory does not exist.
     */
    protected function __construct(array $config, protected ?DataInterface $data = null)
    {
        logMsg('DEBUG', __METHOD__);

        $this->config = $this->mergeConfigWith($config, false);

        $this->debug = $this->config['debug'];
        $this->tempDirectory = rtrim($this->config['temp directory'], DIRECTORY_SEPARATOR);

        if (!is_dir($this->tempDirectory)) {
            throw new Directory('Unknown Directory "' . $this->tempDirectory . '".');
        }

        $this->subPathSize = $this->config['sub path size'];
    }

    /**
     * Render a view file.
     *
     * Takes a path, not a name. Working out which file a name means - module
     * namespaces, package fallbacks, aliases - is ViewFinder's job, and getting
     * that path here is the caller's: BaseController::renderView() resolves it
     * for controllers, findView() for everyone else.
     *
     * @param string $viewFile Absolute path to the view file.
     * @param array $data Data to pass into the view.
     * @param array $options Rendering options.
     * @return string Rendered view content.
     * @throws ViewNotFound If the file does not exist.
     */
    public function render(string $viewFile = '', array $data = [], array $options = []): string
    {
        logMsg('DEBUG', __METHOD__);
        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', '', ['viewFile' => $viewFile, 'data' => $data, 'options' => $options]);
        }

        // generate() is what checks the file exists and throws ViewNotFound
        return $this->generate($viewFile, $this->data($data));
    }

    /**
     * Render a view from a string.
     *
     * @param string $string Template content.
     * @param array $data Data for the template.
     * @param array $options Rendering options.
     * @return string Rendered output.
     * @throws DirectoryNotWritable If the temp directory does not exist and cannot be created.
     * @throws FileNotWritable If the temp directory exists but is not writable, or the compiled
     *         template file cannot be written.
     */
    public function renderString(string $string, array $data = [], array $options = []): string
    {
        logMsg('DEBUG', __METHOD__);
        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', '', ['string' => $string, 'data' => $data, 'options' => $options]);
        }

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
        logMsg('DEBUG', __METHOD__);
        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', '', ['name' => $name, 'value' => $value]);
        }

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
        logMsg('DEBUG', __METHOD__ . ' ' . $__viewFilePath);
        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', '', ['__viewFilePath' => $__viewFilePath, '__dataArray' => $__dataArray]);
        }

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
     * @throws DirectoryNotWritable If the directory does not exist and cannot be created.
     * @throws FileNotWritable If the directory exists but is not writable.
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
            } catch (Throwable) {
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
}
