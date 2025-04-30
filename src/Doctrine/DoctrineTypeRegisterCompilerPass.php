<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\DependencyInjection\Configuration;
use Doctrine\DBAL\Types\Type;
use League\ConstructFinder\ConstructFinder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
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
         * @var array<int, string> $doctrineTypeDirectories
         */
        $doctrineTypeDirectories = $container->getParameter(Configuration::DOCTRINE_TYPE_DIRECTORIES_CONFIGURATION_PARAMETER);

        foreach ($doctrineTypeDirectories as $doctrineTypeDirectory) {
            $types = $this->findTypesInDirectory($doctrineTypeDirectory);

            foreach ($types as $type) {
                $name = $type['name'];
                $class = $type['class'];

                if (array_key_exists($name, $typeDefinitions)) {
                    continue;
                }

                $typeDefinitions[$name] = ['class' => $class];
            }
        }

        $container->setParameter(self::TYPE_DEFINITION_PARAMETER, $typeDefinitions);
    }

    /** @return \Generator<int, array{class: class-string, name: string}> */
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
