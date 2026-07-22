<?php

declare(strict_types=1);

namespace orange\framework\controllers;

use orange\framework\attributes\AttachService;
use orange\framework\exceptions\filesystem\FileNotFound;
use orange\framework\helpers\DirectorySearch;
use orange\framework\interfaces\ConfigInterface;
use orange\framework\interfaces\InputInterface;
use orange\framework\interfaces\OutputInterface;
use orange\framework\interfaces\ViewInterface;
use ReflectionClass;

/**
 * Base controller that application controllers may extend.
 *
 * Not required, but provides commonly used behavior shared across controllers:
 * auto-attaching services declared with the #[AttachService] attribute, loading
 * controller-local libraries listed in $libraries, registering a controller-local
 * view directory (when a $view property is present), and invoking an optional
 * beforeMethodCalled() hook on the extending controller after construction.
 */
abstract class BaseController
{
    #[AttachService('config')]
    protected ConfigInterface $config;

    #[AttachService('input')]
    protected InputInterface $input;

    #[AttachService('output')]
    protected OutputInterface $output;

    // this is the reflection of the extending controller class
    protected ReflectionClass $reflection;

    /**
     * This array holds the local libraries you want to autoload on instantiation.
     *
     * @var array
     */
    protected array $libraries = [];

    /**
     * BaseController constructor.
     *
     * @throws FileNotFound
     */
    public function __construct()
    {
        $this->reflection = new ReflectionClass(static::class);

        // auto attach services defined with the #[AttachService] Attribute
        $this->autoAttachService();

        // path to the parent directory of the parent class
        $parentPath = dirname($this->reflection->getFileName(), 2);

        // try to load (local to extending controller) libraries
        foreach ($this->libraries as $library) {
            $libraryFilePath = $parentPath . '/libraries/' . $library . '.php';

            if (!file_exists($libraryFilePath)) {
                throw new FileNotFound($libraryFilePath);
            }

            logMsg('DEBUG', 'INCLUDE FILE "' . $libraryFilePath . '"');

            include_once $libraryFilePath;
        }

        /* @disregard P1014 Undefined property '$view'. */
        if (isset($this->view) && $this->view instanceof ViewInterface) {
            // Attach Local view path
            if ($viewPath = realpath($parentPath . '/views')) {
                $this->view->search()->addDirectory($viewPath, DirectorySearch::FIRST);
            }
        }

        // call the extending controller "construct"
        if (\method_exists($this, 'beforeMethodCalled')) {
            /** @disregard P1013 Undefined method 'beforeMethodCalled'. */
            $this->beforeMethodCalled();
        }
    }

    protected function autoAttachService(): void
    {
        foreach ($this->reflection->getProperties() as $property) {
            $attribute = $property->getAttributes(AttachService::class);

            if (isset($attribute[0])) {
                logMsg('DEBUG', 'Attach ' . $attribute[0]->getArguments()[0] . ' to ' . $property->getName() . ' property of ' . static::class);

                $this->{$property->getName()} = container()->get($attribute[0]->getArguments()[0]);
            }
        }
    }
}
