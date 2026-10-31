<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(DoctrineTypeRegisterCompilerPass::class)]
final class DoctrineTypeRegisterCompilerPassTest extends TestCase
{
    #[Test]
    public function process_registers_configured_directories_as_resources(): void
    {
        // -- Arrange
        $implementationDirectory = (string) realpath(__DIR__.'/../Test/ValueObject');
        $doctrineTypeDirectory = (string) realpath(__DIR__.'/../Test/Doctrine');

        $container = new ContainerBuilder();
        $container->setParameter('doctrine.dbal.connection_factory.types', []);
        $container->setParameter(Configuration::IMPLEMENTATION_DIRECTORIES_CONFIGURATION_PARAMETER, [$implementationDirectory]);
        $container->setParameter(Configuration::DOCTRINE_TYPE_DIRECTORIES_CONFIGURATION_PARAMETER, [$doctrineTypeDirectory]);

        $compilerPass = new DoctrineTypeRegisterCompilerPass();

        // -- Act
        $compilerPass->process($container);

        // -- Assert
        $directoryResources = array_values(array_filter(
            $container->getResources(),
            static fn (object $resource): bool => $resource instanceof DirectoryResource,
        ));

        self::assertEquals([
            new DirectoryResource($implementationDirectory, '/\.php$/'),
            new DirectoryResource($doctrineTypeDirectory, '/\.php$/'),
        ], $directoryResources);
    }
}
