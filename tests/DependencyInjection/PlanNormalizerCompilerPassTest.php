<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer\ListUnplannedClassesCommand;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer\PlanFactory;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer\PlanNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Leaf;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\TestKernel;
use DigitalCraftsman\SelfAwareNormalizers\Testing\PlanNormalizer\SerializerComparison;
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

require_once dirname(__DIR__).'/Test/PlanNormalizer/Fixture/ReadModel/fixtures.php';

/**
 * The wiring of the plan normalizer through the configuration of the bundle, in a kernel like the one of an app (TestKernel).
 */
#[CoversClass(PlanNormalizerCompilerPass::class)]
final class PlanNormalizerCompilerPassTest extends TestCase
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
    public function plan_normalizer_uses_the_type_extractor_and_default_context_of_the_fallback(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('valid');

        // -- Act
        $comparison = SerializerComparison::fromSerializer($kernel->getContainer()->get('test.service_container')->get('serializer'));
        $planNormalizer = $comparison->planNormalizer;
        $fallbackNormalizer = SerializerComparison::unwrap(new \ReflectionProperty(PlanNormalizer::class, 'fallbackNormalizer')->getValue($planNormalizer));
        $planFactory = new \ReflectionProperty(PlanNormalizer::class, 'planFactory')->getValue($planNormalizer);
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
            new \ReflectionProperty(PlanNormalizer::class, 'defaultContext')->getValue($planNormalizer),
        );
        // Without a pool, the plans are only kept per instance
        self::assertNull(new \ReflectionProperty(PlanNormalizer::class, 'planCache')->getValue($planNormalizer));
        self::assertTrue($planNormalizer->supportsDenormalization([], Leaf::class));
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
        self::assertSame($planCache, new \ReflectionProperty(PlanNormalizer::class, 'planCache')->getValue($comparison->planNormalizer));
        $buildId = $container->getParameter('container.build_id');
        self::assertIsString($buildId);
        self::assertSame($buildId, new \ReflectionProperty(PlanNormalizer::class, 'planCacheVersion')->getValue($comparison->planNormalizer));
        self::assertTrue($planCache->hasItem(sprintf('plan-v2.%s.%s', $buildId, strtr(Leaf::class, '\\', '.'))));
    }

    #[Test]
    public function plan_normalizer_is_disabled_by_default(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('disabled', null, [
            'enabled' => false,
        ]);

        // -- Act
        $container = $kernel->getContainer()->get('test.service_container');

        // -- Assert
        self::assertFalse($container->has(SelfAwareNormalizersExtension::PLAN_NORMALIZER_SERVICE_ID));
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
            'The fallback normalizer "app.does_not_exist" of the plan normalizer (self_aware_normalizers.plan_normalizer.fallback_normalizer) is not equivalent to it. The service does not exist.',
        ];

        yield 'other class' => [
            static function (ContainerBuilder $container): void {
                $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                    ->setClass(ObjectNormalizer::class)
                    ->setArguments([]);
            },
            [],
            sprintf('It must be a %s, but is %s.', PropertyNormalizer::class, ObjectNormalizer::class),
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
            'It must be built with a property type extractor ($propertyTypeExtractor), which the plan normalizer uses as well.',
        ];

        yield 'other default context' => [
            static function (ContainerBuilder $container): void {
                $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                    ->setArgument('$defaultContext', [
                        'skip_null_values' => true,
                    ]);
            },
            [],
            'It must have the same default context as the plan normalizer',
        ];

        yield 'not tagged' => [
            static function (ContainerBuilder $container): void {
                $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                    ->clearTag('serializer.normalizer');
            },
            [],
            'It must be tagged as "serializer.normalizer", so the serializer falls back to it.',
        ];

        yield 'priority in front of the plan normalizer' => [
            static function (ContainerBuilder $container): void {
            },
            [
                'priority' => -60,
            ],
            'Its priority (-50) must be lower than the one of the plan normalizer (-60, self_aware_normalizers.plan_normalizer.priority).',
        ];

        yield 'same priority as the plan normalizer' => [
            static function (ContainerBuilder $container): void {
            },
            [
                'priority' => -50,
            ],
            'Its priority (-50) must be lower than the one of the plan normalizer (-50, self_aware_normalizers.plan_normalizer.priority).',
        ];

        yield 'tagged without priority' => [
            static function (ContainerBuilder $container): void {
                $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                    ->clearTag('serializer.normalizer')
                    ->addTag('serializer.normalizer');
            },
            [],
            'Its priority (0) must be lower than the one of the plan normalizer (-45, self_aware_normalizers.plan_normalizer.priority).',
        ];
    }

    /**
     * @param \Closure(ContainerBuilder): void $configure
     * @param array<string, mixed>             $planNormalizerConfig
     */
    #[Test]
    #[DataProvider('invalidFallbacks')]
    public function container_build_fails_with_a_fallback_that_is_not_equivalent(
        \Closure $configure,
        array $planNormalizerConfig,
        string $expectedMessagePart,
    ): void {
        // -- Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessagePart);

        // -- Act
        $this->bootedKernel(sprintf('invalid_%s', md5($expectedMessagePart)), $configure, $planNormalizerConfig);
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
            'The plan normalizer needs a scope: configure the supported class name pattern or supported parent types.',
        ];

        yield 'invalid pattern' => [
            [
                'supported_class_name_pattern' => 'ReadModel',
            ],
            'The supported class name pattern must be a valid regular expression, "ReadModel" given.',
        ];
    }

    /**
     * @param array<string, mixed> $planNormalizerConfig
     */
    #[Test]
    #[DataProvider('invalidConfigurations')]
    public function container_build_fails_with_an_invalid_configuration(
        array $planNormalizerConfig,
        string $expectedMessagePart,
    ): void {
        // -- Assert
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessagePart);

        // -- Act
        $this->bootedKernel(sprintf('invalid_configuration_%s', md5($expectedMessagePart)), null, $planNormalizerConfig);
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
        self::assertFalse($comparison->planNormalizer->supportsDenormalization([], Leaf::class));
        self::assertTrue($comparison->planFactory->isInScope(\ArrayObject::class));
    }

    // -- Helpers

    /**
     * @param (\Closure(ContainerBuilder): void)|null $configure
     * @param array<string, mixed>                    $planNormalizerConfig
     */
    private function bootedKernel(
        string $environment,
        ?\Closure $configure = null,
        array $planNormalizerConfig = [],
    ): TestKernel {
        $kernel = new TestKernel($environment, true, $configure, $planNormalizerConfig);
        $this->kernels[] = $kernel;
        $kernel->boot();

        return $kernel;
    }
}
