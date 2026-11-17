<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\KnownConstructionNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\ListUnplannedClassesCommand;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\PlanFactory;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Leaf;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\FuturePropertyNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\FuturePropertyNormalizerSubclass;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\FuturePropertyNormalizerWithRequiredParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\TestKernel;
use DigitalCraftsman\SelfAwareNormalizers\Testing\KnownConstructionNormalizer\SerializerComparison;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;

require_once dirname(__DIR__).'/Test/KnownConstructionNormalizer/Fixture/ReadModel/fixtures.php';

/**
 * The wiring of the known construction normalizer through the configuration of the bundle, in a kernel like the one of an app (TestKernel).
 */
#[CoversClass(KnownConstructionNormalizerCompilerPass::class)]
final class KnownConstructionNormalizerCompilerPassTest extends TestCase
{
    /**
     * @var list<TestKernel>
     */
    private array $kernels = [];

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->kernels as $kernel) {
            $kernel->shutdown();
        }
        $this->kernels = [];

        parent::tearDown();
    }

    #[Test]
    public function known_construction_normalizer_uses_the_type_extractor_and_default_context_of_the_fallback(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('valid');

        // -- Act
        $comparison = SerializerComparison::fromSerializer($kernel->getContainer()->get('test.service_container')->get('serializer'));
        $knownConstructionNormalizer = $comparison->knownConstructionNormalizer;
        $fallbackNormalizer = SerializerComparison::unwrap(new \ReflectionProperty(KnownConstructionNormalizer::class, 'fallbackNormalizer')->getValue($knownConstructionNormalizer));
        $planFactory = new \ReflectionProperty(KnownConstructionNormalizer::class, 'planFactory')->getValue($knownConstructionNormalizer);
        self::assertInstanceOf(PlanFactory::class, $planFactory);
        self::assertInstanceOf(PropertyNormalizer::class, $fallbackNormalizer);

        // -- Assert
        self::assertSame(
            new \ReflectionProperty(AbstractObjectNormalizer::class, 'propertyTypeExtractor')->getValue($fallbackNormalizer),
            new \ReflectionProperty(PlanFactory::class, 'propertyTypeExtractor')->getValue($planFactory),
        );
        self::assertSame(
            [
                'skip_null_values' => false,
                'preserve_empty_objects' => true,
            ],
            new \ReflectionProperty(KnownConstructionNormalizer::class, 'defaultContext')->getValue($knownConstructionNormalizer),
        );
        // Without a pool, the plans are only kept per instance
        self::assertNull(new \ReflectionProperty(KnownConstructionNormalizer::class, 'planCache')->getValue($knownConstructionNormalizer));
        self::assertTrue($knownConstructionNormalizer->supportsDenormalization([], Leaf::class));
        self::assertTrue(new Application($kernel)->has(ListUnplannedClassesCommand::NAME));
    }

    #[Test]
    public function plans_are_cached_in_the_configured_pool_under_the_build_id_of_the_container(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('cached', null, [
            'plan_cache_pool' => TestKernel::PLAN_CACHE_POOL_ID,
        ]);
        $container = $kernel->getContainer()->get('test.service_container');
        $comparison = SerializerComparison::fromSerializer($container->get('serializer'));
        $planCache = $container->get(TestKernel::PLAN_CACHE_POOL_ID);
        self::assertInstanceOf(CacheItemPoolInterface::class, $planCache);

        // -- Act
        $leaf = $comparison->plan->denormalize(['name' => 'Example'], Leaf::class);

        // -- Assert
        self::assertEquals(new Leaf('Example'), $leaf);
        self::assertSame($planCache, new \ReflectionProperty(KnownConstructionNormalizer::class, 'planCache')->getValue($comparison->knownConstructionNormalizer));
        $buildId = $container->getParameter('container.build_id');
        self::assertIsString($buildId);
        self::assertSame($buildId, new \ReflectionProperty(KnownConstructionNormalizer::class, 'planCacheVersion')->getValue($comparison->knownConstructionNormalizer));
        self::assertTrue($planCache->hasItem(sprintf('plan-v2.%s.%s', $buildId, strtr(Leaf::class, '\\', '.'))));
    }

    #[Test]
    public function known_construction_normalizer_is_disabled_by_default(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('disabled', null, [
            'enabled' => false,
        ]);

        // -- Act
        $container = $kernel->getContainer()->get('test.service_container');

        // -- Assert
        self::assertFalse($container->has(SelfAwareNormalizersExtension::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID));
        self::assertFalse($container->has(SelfAwareNormalizersExtension::PLAN_FACTORY_SERVICE_ID));
        self::assertFalse(new Application($kernel)->has(ListUnplannedClassesCommand::NAME));
    }

    /**
     * @return iterable<string, array{
     *   0: \Closure(ContainerBuilder): void,
     *   1: array<string, mixed>,
     *   2: string,
     * }>
     */
    public static function invalidFallbacks(): iterable
    {
        yield 'missing fallback' => [
            static function (ContainerBuilder $container): void {
            },
            [
                'fallback_normalizer' => 'app.does_not_exist',
            ],
            'The fallback normalizer "app.does_not_exist" of the known construction normalizer (self_aware_normalizers.known_construction_normalizer.fallback_normalizer) is not equivalent to it. The service does not exist.',
        ];

        yield 'other class' => [
            static function (ContainerBuilder $container): void {
                $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                    ->setClass(ObjectNormalizer::class)
                    ->setArguments([]);
            },
            [],
            sprintf('It must be a %s (not a subclass), but is %s.', PropertyNormalizer::class, ObjectNormalizer::class),
        ];

        yield 'method call' => [
            static function (ContainerBuilder $container): void {
                $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                    ->addMethodCall('setSerializer', [new Reference('serializer')]);
            },
            [],
            'It must be built through its constructor only (no factory, no method calls).',
        ];

        yield 'autowired class metadata factory' => [
            static function (ContainerBuilder $container): void {
                $definition = $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID);
                $arguments = $definition->getArguments();
                unset($arguments['$classMetadataFactory']);
                $definition->setArguments($arguments);
            },
            [],
            'It must be built without class metadata factory ($classMetadataFactory)',
        ];

        yield 'name converter' => [
            static function (ContainerBuilder $container): void {
                $container->setDefinition(CamelCaseToSnakeCaseNameConverter::class, new Definition(CamelCaseToSnakeCaseNameConverter::class));
                $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                    ->setArgument('$nameConverter', new Reference(CamelCaseToSnakeCaseNameConverter::class));
            },
            [],
            'It must be built without name converter ($nameConverter)',
        ];

        yield 'class discriminator resolver' => [
            static function (ContainerBuilder $container): void {
                $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                    ->setArgument('$classDiscriminatorResolver', new Reference('serializer.mapping.class_discriminator_resolver'));
            },
            [],
            'It must be built without class discriminator resolver ($classDiscriminatorResolver)',
        ];

        yield 'object class resolver' => [
            static function (ContainerBuilder $container): void {
                $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                    ->setArgument('$objectClassResolver', 'get_class');
            },
            [],
            'It must be built without object class resolver ($objectClassResolver)',
        ];

        yield 'without type extractor' => [
            static function (ContainerBuilder $container): void {
                $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                    ->setArgument('$propertyTypeExtractor', null);
            },
            [],
            'It must be built with a property type extractor ($propertyTypeExtractor), which the known construction normalizer uses as well.',
        ];

        yield 'other default context' => [
            static function (ContainerBuilder $container): void {
                $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                    ->setArgument('$defaultContext', [
                        'skip_null_values' => true,
                    ]);
            },
            [],
            'It must have the same default context as the known construction normalizer',
        ];

        yield 'not tagged' => [
            static function (ContainerBuilder $container): void {
                $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                    ->clearTag('serializer.normalizer');
            },
            [],
            'It must be tagged as "serializer.normalizer", so the serializer falls back to it.',
        ];

        yield 'priority in front of the known construction normalizer' => [
            static function (ContainerBuilder $container): void {
            },
            [
                'priority' => -60,
            ],
            'Its priority (-50) must be lower than the one of the known construction normalizer (-60, self_aware_normalizers.known_construction_normalizer.priority).',
        ];

        yield 'same priority as the known construction normalizer' => [
            static function (ContainerBuilder $container): void {
            },
            [
                'priority' => -50,
            ],
            'Its priority (-50) must be lower than the one of the known construction normalizer (-50, self_aware_normalizers.known_construction_normalizer.priority).',
        ];

        // Like the SerializerPass, the first tag decides
        yield 'tagged without priority before the tag with priority' => [
            static function (ContainerBuilder $container): void {
                $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                    ->clearTag('serializer.normalizer')
                    ->addTag('serializer.normalizer')
                    ->addTag('serializer.normalizer', [
                        'priority' => -50,
                    ]);
            },
            [],
            'Its priority (0) must be lower than the one of the known construction normalizer (-45, self_aware_normalizers.known_construction_normalizer.priority).',
        ];

        yield 'tagged without priority' => [
            static function (ContainerBuilder $container): void {
                $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                    ->clearTag('serializer.normalizer')
                    ->addTag('serializer.normalizer');
            },
            [],
            'Its priority (0) must be lower than the one of the known construction normalizer (-45, self_aware_normalizers.known_construction_normalizer.priority).',
        ];
    }

    /**
     * @param \Closure(ContainerBuilder): void $configure
     * @param array<string, mixed>             $knownConstructionNormalizerConfig
     */
    #[Test]
    #[DataProvider('invalidFallbacks')]
    public function container_build_fails_with_a_fallback_that_is_not_equivalent(
        \Closure $configure,
        array $knownConstructionNormalizerConfig,
        string $expectedMessagePart,
    ): void {
        // -- Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessagePart);

        // -- Act
        $this->bootedKernel(sprintf('invalid_%s', md5($expectedMessagePart)), $configure, $knownConstructionNormalizerConfig);
    }

    /**
     * Autoconfiguration (e.g. _defaults: autoconfigure: true in the services.yaml of an app) adds a second tag without priority to the
     * fallback, which the serializer ignores, as the first tag decides.
     */
    #[Test]
    public function fallback_with_an_additional_tag_through_autoconfiguration_is_accepted(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('autoconfigured', static function (ContainerBuilder $container): void {
            $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                ->setAutoconfigured(true);
        });

        // -- Act
        $comparison = SerializerComparison::fromSerializer($kernel->getContainer()->get('test.service_container')->get('serializer'));

        // -- Assert
        $fallbackTags = array_values(array_filter(
            array_map(
                static fn (object $normalizer): string => SerializerComparison::unwrap($normalizer)::class,
                new \ReflectionProperty(Serializer::class, 'normalizers')->getValue($comparison->plan),
            ),
            static fn (string $class): bool => in_array($class, [KnownConstructionNormalizer::class, PropertyNormalizer::class], true),
        ));
        // The known construction normalizer comes first, the fallback follows
        self::assertSame([KnownConstructionNormalizer::class, PropertyNormalizer::class], array_slice($fallbackTags, 0, 2));
        self::assertTrue($comparison->knownConstructionNormalizer->supportsDenormalization([], Leaf::class));
    }

    /**
     * @return iterable<string, array{
     *   0: array<string, mixed>,
     *   1: string,
     * }>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'without scope' => [
            [
                'supported_class_name_pattern' => null,
            ],
            'The known construction normalizer needs a scope: configure the supported class name pattern or supported parent types.',
        ];

        yield 'accepted context key the plan does not implement' => [
            [
                'accepted_context_keys' => ['app_option', 'groups'],
            ],
            'The accepted context keys must not contain options the known construction normalizer does not implement or only accepts with a specific value (see KnownConstructionNormalizer::NOT_IMPLEMENTED_CONTEXT_OPTIONS)',
        ];

        yield 'accepted context key with an accepted value' => [
            [
                'accepted_context_keys' => ['allow_extra_attributes'],
            ],
            'The accepted context keys must not contain options the known construction normalizer does not implement or only accepts with a specific value',
        ];

        yield 'invalid pattern' => [
            [
                'supported_class_name_pattern' => 'ReadModel',
            ],
            'The supported class name pattern must be a valid regular expression, "ReadModel" given.',
        ];
    }

    /**
     * @param array<string, mixed> $knownConstructionNormalizerConfig
     */
    #[Test]
    #[DataProvider('invalidConfigurations')]
    public function container_build_fails_with_an_invalid_configuration(
        array $knownConstructionNormalizerConfig,
        string $expectedMessagePart,
    ): void {
        // -- Assert
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessagePart);

        // -- Act
        $this->bootedKernel(sprintf('invalid_configuration_%s', md5($expectedMessagePart)), null, $knownConstructionNormalizerConfig);
    }

    #[Test]
    public function scope_can_be_configured_through_parent_types_only(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('parent_types', null, [
            'supported_class_name_pattern' => null,
            'supported_parent_types' => [
                \Countable::class,
            ],
        ]);

        // -- Act
        $comparison = SerializerComparison::fromSerializer($kernel->getContainer()->get('test.service_container')->get('serializer'));

        // -- Assert
        self::assertFalse($comparison->knownConstructionNormalizer->supportsDenormalization([], Leaf::class));
        self::assertTrue($comparison->planFactory->isInScope(\ArrayObject::class));
    }

    #[Test]
    public function configured_accepted_context_keys_are_accepted_by_the_known_construction_normalizer(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('accepted_context_keys', null, [
            'accepted_context_keys' => ['app_option'],
        ]);
        $defaultKernel = $this->bootedKernel('valid');

        // -- Act
        $comparison = SerializerComparison::fromSerializer($kernel->getContainer()->get('test.service_container')->get('serializer'));
        $defaultComparison = SerializerComparison::fromSerializer($defaultKernel->getContainer()->get('test.service_container')->get('serializer'));

        // -- Assert
        self::assertSame(['app_option'], $comparison->acceptedContextKeys);
        self::assertTrue($comparison->knownConstructionNormalizer->supportsDenormalization([], Leaf::class, null, ['app_option' => true]));
        self::assertTrue($comparison->knownConstructionNormalizer->supportsNormalization(new Leaf('Example'), null, ['app_option' => true]));
        self::assertSame([], $defaultComparison->acceptedContextKeys);
        self::assertFalse($defaultComparison->knownConstructionNormalizer->supportsDenormalization([], Leaf::class, null, ['app_option' => true]));
    }

    /**
     * @return iterable<string, array{
     *   0: class-string,
     *   1: array<array-key, mixed>,
     *   2: string,
     * }>
     */
    public static function fallbacksOfALaterVersionWithInvalidArguments(): iterable
    {
        yield 'unknown parameter with another value' => [
            FuturePropertyNormalizer::class,
            [null, null, new Reference('app.type_extractor'), null, null, [], true],
            'The argument $newOption of its constructor is not known to the known construction normalizer, so it must keep its default value. Remove the argument or disable the known construction normalizer.',
        ];

        yield 'unknown parameter with another value by name' => [
            FuturePropertyNormalizer::class,
            [
                '$propertyTypeExtractor' => new Reference('app.type_extractor'),
                '$newOption' => true,
            ],
            'The argument $newOption of its constructor is not known to the known construction normalizer, so it must keep its default value.',
        ];

        yield 'unknown parameter with a parameter of another value' => [
            FuturePropertyNormalizer::class,
            [null, null, new Reference('app.type_extractor'), null, null, [], '%app.enabled%'],
            'The argument $newOption of its constructor is not known to the known construction normalizer, so it must keep its default value.',
        ];

        yield 'unknown required parameter' => [
            FuturePropertyNormalizerWithRequiredParameter::class,
            [false, new Reference('app.type_extractor')],
            'The argument $requiredOption of its constructor is not known to the known construction normalizer, so it must keep its default value.',
        ];

        yield 'argument without parameter' => [
            FuturePropertyNormalizer::class,
            [null, null, new Reference('app.type_extractor'), null, null, [], false, 'surplus'],
            'Its constructor has no parameter for the argument at position 7.',
        ];

        yield 'named argument without parameter' => [
            FuturePropertyNormalizer::class,
            [
                '$propertyTypeExtractor' => new Reference('app.type_extractor'),
                '$doesNotExist' => true,
            ],
            'Its constructor has no parameter for the argument $doesNotExist.',
        ];

        yield 'subclass' => [
            FuturePropertyNormalizerSubclass::class,
            [null, null, new Reference('app.type_extractor')],
            sprintf('It must be a %s (not a subclass), but is %s.', FuturePropertyNormalizer::class, FuturePropertyNormalizerSubclass::class),
        ];

        yield 'known parameter by name' => [
            FuturePropertyNormalizer::class,
            [
                '$propertyTypeExtractor' => new Reference('app.type_extractor'),
                '$nameConverter' => new Reference('app.name_converter'),
            ],
            'It must be built without name converter ($nameConverter)',
        ];
    }

    /**
     * The constructor of the fallback of a later version of the serializer may have parameters the pass doesn't know. Simulated through
     * a fallback class of its own (the PropertyNormalizer is final), with the pass expecting it instead of the PropertyNormalizer.
     *
     * @param class-string            $fallbackClass
     * @param array<array-key, mixed> $arguments
     */
    #[Test]
    #[DataProvider('fallbacksOfALaterVersionWithInvalidArguments')]
    public function container_build_fails_with_an_argument_the_pass_does_not_know(
        string $fallbackClass,
        array $arguments,
        string $expectedMessagePart,
    ): void {
        // -- Arrange
        $container = self::containerWithFallback($fallbackClass, $arguments);

        // -- Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessagePart);

        // -- Act
        new KnownConstructionNormalizerCompilerPass($fallbackClass === FuturePropertyNormalizerWithRequiredParameter::class ? $fallbackClass : FuturePropertyNormalizer::class)
            ->process($container);
    }

    /**
     * @return iterable<string, array{0: array<array-key, mixed>}>
     */
    public static function fallbacksOfALaterVersionWithValidArguments(): iterable
    {
        yield 'unknown parameter not configured' => [
            [null, null, new Reference('app.type_extractor')],
        ];

        yield 'unknown parameter with its default value' => [
            [null, null, new Reference('app.type_extractor'), null, null, [], false],
        ];

        yield 'unknown parameter with a parameter of its default value' => [
            [null, null, new Reference('app.type_extractor'), null, null, [], '%app.disabled%'],
        ];

        yield 'by name' => [
            [
                '$classMetadataFactory' => null,
                '$propertyTypeExtractor' => new Reference('app.type_extractor'),
                '$defaultContext' => [],
                '$newOption' => false,
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    #[Test]
    #[DataProvider('fallbacksOfALaterVersionWithValidArguments')]
    public function unknown_arguments_with_their_default_value_are_accepted(
        array $arguments,
    ): void {
        // -- Arrange
        $container = self::containerWithFallback(FuturePropertyNormalizer::class, $arguments);

        // -- Act
        new KnownConstructionNormalizerCompilerPass(FuturePropertyNormalizer::class)->process($container);

        // -- Assert
        self::assertEquals(
            new Reference('app.type_extractor'),
            $container->getDefinition(SelfAwareNormalizersExtension::PLAN_FACTORY_SERVICE_ID)->getArgument(0),
        );
    }

    // -- Helpers

    /**
     * Container with the services of the known construction normalizer as the extension defines them, after the arguments are resolved.
     *
     * @param class-string            $fallbackClass
     * @param array<array-key, mixed> $arguments
     */
    private static function containerWithFallback(
        string $fallbackClass,
        array $arguments,
    ): ContainerBuilder {
        $container = new ContainerBuilder();
        $container->setParameter('app.enabled', true);
        $container->setParameter('app.disabled', false);
        $container->setDefinition(SelfAwareNormalizersExtension::PLAN_FACTORY_SERVICE_ID, new Definition(PlanFactory::class, [null]));
        $container->setDefinition(SelfAwareNormalizersExtension::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID, new Definition(KnownConstructionNormalizer::class, [
            new Reference(SelfAwareNormalizersExtension::PLAN_FACTORY_SERVICE_ID),
            new Reference('app.fallback'),
            null,
            'uncached',
        ]))
            ->addTag('serializer.normalizer', [
                'priority' => -45,
            ]);
        $container->setDefinition('app.fallback', new Definition($fallbackClass, $arguments))
            ->addTag('serializer.normalizer', [
                'priority' => -50,
            ]);

        return $container;
    }

    /**
     * @param (\Closure(ContainerBuilder): void)|null $configure
     * @param array<string, mixed>                    $knownConstructionNormalizerConfig
     */
    private function bootedKernel(
        string $environment,
        ?\Closure $configure = null,
        array $knownConstructionNormalizerConfig = [],
    ): TestKernel {
        $kernel = new TestKernel($environment, true, $configure, $knownConstructionNormalizerConfig);
        $this->kernels[] = $kernel;
        $kernel->boot();

        return $kernel;
    }
}
