<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

use DigitalCraftsman\SelfAwareNormalizers\Doctrine\ArrayNormalizableThroughLookupType;
use DigitalCraftsman\SelfAwareNormalizers\Doctrine\BoolNormalizableThroughLookupType;
use DigitalCraftsman\SelfAwareNormalizers\Doctrine\FloatNormalizableThroughLookupType;
use DigitalCraftsman\SelfAwareNormalizers\Doctrine\IntNormalizableThroughLookupType;
use DigitalCraftsman\SelfAwareNormalizers\Doctrine\StringNormalizableThroughLookupType;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\ArrayNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\BoolNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\FloatNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\IntNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\StringNormalizable;
use Doctrine\DBAL\Types\Type;
use League\ConstructFinder\ConstructFinder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class DoctrineTypeRegisterCompilerPass implements CompilerPassInterface
{
    private const string TYPE_DEFINITION_PARAMETER = 'doctrine.dbal.connection_factory.types';
    private const string TYPE_NAME_METHOD_NAME = 'getTypeName';

    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        /**
         * @var array<string, array{class: class-string}> $typeDefinitions
         */
        $typeDefinitions = $container->getParameter(self::TYPE_DEFINITION_PARAMETER);

        /**
         * @var list<string> $interfaceDirectories
         */
        $interfaceDirectories = $container->getParameter(Configuration::IMPLEMENTATION_DIRECTORIES_CONFIGURATION_PARAMETER);

        foreach ($interfaceDirectories as $interfaceDirectory) {
            $classes = $this->findClassesImplementingInterfacesInDirectory($interfaceDirectory);

            foreach ($classes as $implementation) {
                $className = $implementation['className'];
                $typeClass = match ($implementation['interface']) {
                    StringNormalizable::class => StringNormalizableThroughLookupType::class,
                    ArrayNormalizable::class => ArrayNormalizableThroughLookupType::class,
                    BoolNormalizable::class => BoolNormalizableThroughLookupType::class,
                    FloatNormalizable::class => FloatNormalizableThroughLookupType::class,
                    IntNormalizable::class => IntNormalizableThroughLookupType::class,
                };

                if (array_key_exists($className, $typeDefinitions)) {
                    continue;
                }

                $typeDefinitions[$className] = ['class' => $typeClass];
            }
        }

        /**
         * @var list<string> $doctrineTypeDirectories
         */
        $doctrineTypeDirectories = $container->getParameter(Configuration::DOCTRINE_TYPE_DIRECTORIES_CONFIGURATION_PARAMETER);

        foreach ($doctrineTypeDirectories as $doctrineTypeDirectory) {
            $classes = $this->findTypesInDirectory($doctrineTypeDirectory);

            foreach ($classes as $type) {
                $typeName = $type['name'];
                $typeClass = $type['class'];

                if (array_key_exists($typeName, $typeDefinitions)) {
                    continue;
                }

                $typeDefinitions[$typeName] = ['class' => $typeClass];
            }
        }

        $container->setParameter(self::TYPE_DEFINITION_PARAMETER, $typeDefinitions);
    }

    /**
     * @return \Generator<int, array{interface: class-string, className: string}>
     */
    private function findClassesImplementingInterfacesInDirectory(string $pathToDoctrineTypeDirectory): iterable
    {
        $constructFinder = ConstructFinder::locatedIn($pathToDoctrineTypeDirectory);

        $classNames = $constructFinder->findClassNames();
        foreach ($classNames as $className) {
            $reflectionClass = new \ReflectionClass($className);

            if ($reflectionClass->implementsInterface(StringNormalizable::class)) {
                yield [
                    'interface' => StringNormalizable::class,
                    'className' => $className,
                ];
                continue;
            }

            if ($reflectionClass->implementsInterface(ArrayNormalizable::class)) {
                yield [
                    'interface' => ArrayNormalizable::class,
                    'className' => $className,
                ];
                continue;
            }

            if ($reflectionClass->implementsInterface(BoolNormalizable::class)) {
                yield [
                    'interface' => BoolNormalizable::class,
                    'className' => $className,
                ];
                continue;
            }

            if ($reflectionClass->implementsInterface(FloatNormalizable::class)) {
                yield [
                    'interface' => FloatNormalizable::class,
                    'className' => $className,
                ];
                continue;
            }

            if ($reflectionClass->implementsInterface(IntNormalizable::class)) {
                yield [
                    'interface' => IntNormalizable::class,
                    'className' => $className,
                ];
            }
        }

        $enumNames = $constructFinder->findEnums();
        foreach ($enumNames as $enumName) {
            $reflectionEnum = new \ReflectionEnum($enumName);

            /**
             * @psalm-suppress TypeDoesNotContainType
             */
            if ($reflectionEnum->implementsInterface(StringNormalizable::class)) {
                yield [
                    'interface' => StringNormalizable::class,
                    'className' => $enumName,
                ];
            }
        }
    }

    /**
     * @return \Generator<int, array{class: class-string, name: string}>
     */
    private function findTypesInDirectory(string $pathToDoctrineTypeDirectory): iterable
    {
        $classNames = ConstructFinder::locatedIn($pathToDoctrineTypeDirectory)->findClassNames();

        foreach ($classNames as $className) {
            $reflection = new \ReflectionClass($className);
            if (!$reflection->isSubclassOf(Type::class)) {
                continue;
            }

            // Don't register parent types
            if ($reflection->isAbstract()) {
                continue;
            }

            // Only register types that have the relevant method
            if (!$reflection->hasMethod(self::TYPE_NAME_METHOD_NAME)) {
                continue;
            }

            $typeName = call_user_func([$className, self::TYPE_NAME_METHOD_NAME]);

            yield [
                'class' => $reflection->getName(),
                'name' => $typeName,
            ];
        }
    }
}
