<?php

declare(strict_types=1);

namespace AriAva\DependencyInjection;

use AriAva\Autowire\AutowireProxy;
use AriAva\Contracts\DependencyInjection\ContainerInterface;
use AriAva\DependencyInjection\Attribute\DependencyAutowire;
use \SplFileInfo;

final class Container implements ContainerInterface
{
    private const array POSSIBLE_PARAMETERS = [
        'string',
        'int',
        'bool',
        'float',
        'array',
    ];

    private array $initialized;

    public function __construct(public array $bindings = [])
    {
        $this->initialized = [];
    }

    public function add(string $id, callable $factory): void
    {
        if ($this->has($id)) {
            return;
        }
        $this->bindings[$id] = $factory;
    }

    public function autowireFolderByAttribute(string $path): void
    {
        $directory = new \RecursiveDirectoryIterator($path);
        $iterator = new \RecursiveIteratorIterator($directory);

        /** @var \SplFileInfo $info */
        foreach ($iterator as $info) {
            $this->autowire($info);
        }
    }

    /**
     * @throws NotFoundException
     */
    public function get(string $id): mixed
    {
        if (!array_key_exists($id, $this->bindings)) {
            throw new NotFoundException("Target binding [$id] does not exist.");
        }

        if (array_key_exists($id, $this->initialized)) {
            return $this->initialized[$id];
        }

        $factory = $this->bindings[$id];
        $instance = $factory($this);
        $this->initialized[$id] = $instance;

        return $instance;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->bindings);
    }

    public function addParameters(array $parameters): void
    {
        foreach ($parameters as $parameterKey => $parametersValue) {
            $this->add($parameterKey, static function () use ($parametersValue) {
                return $parametersValue;
            });
        }
    }

    private function autowire(SplFileInfo $fileInfo): void
    {
        if ($fileInfo->isDir() && ($fileInfo->getFilename() === '.' || $fileInfo->getFilename() === '..')) {
            return;
        }

        if ($fileInfo->isDir()) {
            $this->autowireFolderByAttribute($fileInfo->getPathname());

            return;
        }

        $autowireProxy = new AutowireProxy($fileInfo, DependencyAutowire::class);

        if (false === $autowireProxy->canAutowire()) {
            return;
        }

        $arguments = $autowireProxy->getArguments();
        $dependencyKey = null !== $autowireProxy->qualifiedName() ? $autowireProxy->qualifiedName() : $autowireProxy->getNamespace();

        if (null === $arguments) {
            $this->add($dependencyKey, static function () use ($autowireProxy) {

                return $autowireProxy->getReflection()->newInstanceWithoutConstructor();
            });

            return;
        }

        if (0 < count($autowireProxy->customConstructorArguments())) {
            $this->add($dependencyKey, static function () use ($autowireProxy) {
                return $autowireProxy->getReflection()->newInstanceArgs(
                    $autowireProxy->customConstructorArguments()
                );
            });

            return;
        }

        $this->addToBag($dependencyKey, $autowireProxy);
    }

    private function addToBag(string $dependencyKey, AutowireProxy $autowireProxy): void
    {
        $parameters = $autowireProxy->getArguments()->getParameters();
        $constructorArguments = [];

        foreach ($parameters as $parameter) {
            $reflectionClass = $parameter->getType();
            $name = $reflectionClass->getName();

            if (in_array($reflectionClass->getName(), self::POSSIBLE_PARAMETERS, true)) {
                $name = $parameter->getName();
            }

            $constructorArguments[] = $name;
        }

        $this->add($dependencyKey, function () use ($autowireProxy, $constructorArguments) {
            $containerArguments = [];

            foreach ($constructorArguments as $constructorArgument) {
                $containerArguments[] = $this->get($constructorArgument);
            }

            return $autowireProxy->getReflection()->newInstanceArgs($containerArguments);
        });
    }
}
