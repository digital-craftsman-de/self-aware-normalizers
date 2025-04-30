<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(SelfAwareNormalizersExtension::class)]
final class SelfAwareNormalizersExtensionTest extends TestCase
{
    #[Test]
    public function load_works(): void
    {
        // -- Arrange
        $container = new ContainerBuilder();
        $selfAwareNormalizersExtension = new SelfAwareNormalizersExtension();

        // -- Act
        $selfAwareNormalizersExtension->load([], $container);

        // -- Assert
        // No data is supplied as config. Therefore, the parameters are set, but empty.
        $doctrineTypeDirectories = $container->getParameter(Configuration::DOCTRINE_TYPE_DIRECTORIES_CONFIGURATION_PARAMETER);
        self::assertIsArray($doctrineTypeDirectories);
        self::assertCount(0, $doctrineTypeDirectories);
    }
}
