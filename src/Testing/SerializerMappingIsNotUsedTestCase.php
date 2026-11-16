<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Testing;

use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Mapping\Loader\LoaderChain;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * For apps using the plan normalizer: its fallback, the PropertyNormalizer, must be built without the class metadata factory (the bundle
 * enforces it, see PlanNormalizerCompilerPass), and the plan normalizer reads no mapping either. It handles every object with a
 * non-static property that no other normalizer handles. Therefore:
 * - The serializer mapping is silently ignored for all of these classes: the attributes of Symfony\Component\Serializer\Attribute
 *   (#[Groups], #[SerializedName], #[SerializedPath], #[Ignore], #[MaxDepth], #[Context], #[DiscriminatorMap], ...) as well as XML/YAML
 *   mapping files. A class using them would be (de)normalized as if they weren't there, without any error.
 * - The context option ALLOW_EXTRA_ATTRIBUTES = false throws a LogicException, as it needs a class metadata factory.
 *
 * This test case fails as soon as a file of the source directories or of a non-dev vendor package references the attribute namespace of
 * the serializer, or a mapping file is registered (the framework only loads them into the loader chain of the class metadata factory).
 * Extend it in the tests of the app (requires phpunit/phpunit 12 or 13 and a kernel for KernelTestCase) and implement sourceDirectories().
 *
 * Limits: It's a pattern check on the source (`Serializer\Attribute`). Grouped imports and an alias of the attribute namespace are found,
 * an alias of the serializer namespace itself (`use Symfony\Component\Serializer as S;` with `#[S\Attribute\Groups]`) is not. Test
 * directories of vendor packages, the serializer itself and dev-only packages (never deployed) aren't scanned. The allowlisted vendor files
 * (allowedVendorFiles()) were checked and never reach the PropertyNormalizer; an entry that isn't found anymore fails the test, so the
 * list doesn't go stale.
 */
abstract class SerializerMappingIsNotUsedTestCase extends KernelTestCase
{
    private const string SERIALIZER_ATTRIBUTE_PATTERN = '/Serializer\\\\Attribute\b/';

    // -- Configuration of the app

    /**
     * Directories of the app whose files must not use serializer attributes (recursively), e.g. the src directory.
     *
     * @return list<string>
     */
    abstract protected static function sourceDirectories(): array;

    /**
     * Vendor files (relative to the project directory) that reference serializer attributes, but whose classes are never serialized.
     *
     * @return list<string>
     */
    protected static function allowedVendorFiles(): array
    {
        return [
            // Registers the serializer attributes for autoconfiguration, no class with mapping
            'vendor/symfony/framework-bundle/DependencyInjection/FrameworkExtension.php',
            // #[Ignore] on the state of lazy decorators, used for proxies of services, which are never serialized
            'vendor/symfony/var-exporter/Internal/LazyDecoratorTrait.php',
        ];
    }

    protected static function projectDirectory(): string
    {
        /** @var string */
        return static::getContainer()->getParameter('kernel.project_dir');
    }

    // -- Tests

    #[Test]
    public function no_class_of_the_source_directories_uses_serializer_attributes(): void
    {
        // -- Arrange
        $sourceDirectories = static::sourceDirectories();

        // -- Act
        $numberOfScannedFiles = 0;
        $filesUsingSerializerAttributes = [];
        foreach (new Finder()->files()->in($sourceDirectories)->name('*.php') as $file) {
            ++$numberOfScannedFiles;
            if (preg_match(self::SERIALIZER_ATTRIBUTE_PATTERN, $file->getContents()) === 1) {
                $filesUsingSerializerAttributes[] = $file->getPathname();
            }
        }

        // -- Assert
        // Makes sure the scan actually finds the files, so the test doesn't pass by finding nothing
        self::assertGreaterThan(0, $numberOfScannedFiles);

        self::assertSame(
            [],
            $filesUsingSerializerAttributes,
            'Serializer attributes are ignored by the PropertyNormalizer, as it is built without the class metadata factory for the plan '
            .'normalizer. Use the constructor and public properties or a normalizer of its own instead.',
        );
    }

    #[Test]
    public function no_class_of_a_vendor_package_uses_serializer_attributes(): void
    {
        // -- Arrange
        $projectDirectory = static::projectDirectory();
        $vendorDirectory = $projectDirectory.'/vendor/';

        $packageDirectories = [];
        foreach (InstalledVersions::getAllRawData() as $installed) {
            /** @var array<string, array{dev_requirement: bool, install_path?: string}> $packages */
            $packages = $installed['versions'];
            foreach ($packages as $packageName => $package) {
                if ($package['dev_requirement']
                    || !isset($package['install_path'])
                    || $packageName === 'symfony/serializer'
                ) {
                    continue;
                }

                $packageDirectory = realpath($package['install_path']);
                if ($packageDirectory !== false
                    && str_starts_with($packageDirectory, $vendorDirectory)
                ) {
                    $packageDirectories[$packageDirectory] = $packageDirectory;
                }
            }
        }

        // -- Act
        $filesUsingSerializerAttributes = [];
        if ($packageDirectories !== []) {
            $finder = new Finder()
                ->files()
                ->in(array_values($packageDirectories))
                ->name('*.php')
                ->notPath('#(^|/)[Tt]ests?/#');
            foreach ($finder as $file) {
                $contents = $file->getContents();
                if (str_contains($contents, 'Serializer\\Attribute')
                    && preg_match(self::SERIALIZER_ATTRIBUTE_PATTERN, $contents) === 1
                ) {
                    $filesUsingSerializerAttributes[] = substr($file->getPathname(), strlen($projectDirectory) + 1);
                }
            }
        }

        // -- Assert
        // Makes sure the allowlist doesn't go stale and the scan actually finds files, so the test doesn't pass by finding nothing
        self::assertSame(
            [],
            array_values(array_diff(static::allowedVendorFiles(), $filesUsingSerializerAttributes)),
            'These allowlisted files don\'t reference serializer attributes anymore (or weren\'t scanned). Remove them from allowedVendorFiles().',
        );

        self::assertSame(
            [],
            array_values(array_diff($filesUsingSerializerAttributes, static::allowedVendorFiles())),
            'Serializer attributes are ignored by the PropertyNormalizer, as it is built without the class metadata factory for the plan '
            .'normalizer. Check whether objects of the class can reach the serializer: if not, add the file to allowedVendorFiles(), '
            .'otherwise it needs a normalizer of its own.',
        );
    }

    #[Test]
    public function no_serializer_mapping_file_is_registered(): void
    {
        // -- Arrange
        static::bootKernel();
        $classMetadataFactory = static::getContainer()->get('serializer.mapping.class_metadata_factory');

        // The loader chain is inlined in the compiled container, so it's only reachable through the class metadata factory. In prod it's
        // wrapped by the CacheClassMetadataFactory, in test (debug) it's used directly.
        self::assertInstanceOf(
            ClassMetadataFactory::class,
            $classMetadataFactory,
            'The loaders are read from the private "loader" property of the ClassMetadataFactory. Adapt the test to the new structure.',
        );

        // -- Act
        /**
         * @var LoaderInterface $loader
         */
        $loader = new \ReflectionProperty(ClassMetadataFactory::class, 'loader')->getValue($classMetadataFactory);
        $loaders = $loader instanceof LoaderChain
            ? $loader->getLoaders()
            : [$loader];
        $classesOfLoaders = array_map(
            static fn (LoaderInterface $loader): string => $loader::class,
            $loaders,
        );

        // -- Assert
        self::assertSame(
            [
                AttributeLoader::class,
            ],
            $classesOfLoaders,
            'Serializer mapping files (config/serializer/, serialization.* of bundles, framework.serializer.mapping.paths) are ignored by '
            .'the PropertyNormalizer, as it is built without the class metadata factory for the plan normalizer.',
        );
    }

    #[Test]
    public function property_normalizer_ignores_serializer_attributes(): void
    {
        // -- Arrange
        static::bootKernel();
        /**
         * @var NormalizerInterface $normalizer
         */
        $normalizer = static::getContainer()->get('serializer');

        $object = new class('Example', 'secret') {
            public function __construct(
                public string $name,
                #[Ignore]
                public string $ignoredValue,
            ) {
            }
        };

        // -- Act
        $normalizedObject = $normalizer->normalize($object);

        // -- Assert
        // Pins the configuration: with the class metadata factory, "ignoredValue" would be missing
        self::assertSame(
            [
                'name' => 'Example',
                'ignoredValue' => 'secret',
            ],
            $normalizedObject,
        );
    }
}
