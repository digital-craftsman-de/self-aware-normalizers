<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Debug;

use DigitalCraftsman\SelfAwareNormalizers\DependencyInjection\KnownConstructionNormalizerProfilerPass;
use DigitalCraftsman\SelfAwareNormalizers\DependencyInjection\SelfAwareNormalizersExtension;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Diagnosis\PlanLimitationReason;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\KnownConstructionNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\ParameterKind;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Chain\PerCallNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Hooked;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Leaf;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Mutable;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Objects;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ValueObjects;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\Count;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\Flag;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\Weight;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Profiler\LaterBundle;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Profiler\LaterDataCollector;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Profiler\MappedReadModel;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

require_once dirname(__DIR__, 3).'/Test/KnownConstructionNormalizer/Fixture/ReadModel/fixtures.php';
require_once dirname(__DIR__, 3).'/Test/KnownConstructionNormalizer/Chain/fixtures.php';

/**
 * The profiler panel of the KnownConstructionNormalizer, in a kernel with the profiler like an app in dev.
 */
#[CoversClass(KnownConstructionNormalizerDataCollector::class)]
#[CoversClass(Recorder::class)]
#[CoversClass(KnownConstructionNormalizerProfilerPass::class)]
#[CoversClass(KnownConstructionNormalizer::class)]
#[CoversClass(PlanLimitationReason::class)]
#[CoversClass(ParameterKind::class)]
final class KnownConstructionNormalizerDataCollectorTest extends TestCase
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
    public function the_panel_shows_how_the_classes_of_the_request_were_handled(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('profiler', true, null, [
            'plan_cache_pool' => TestKernel::PLAN_CACHE_POOL_ID,
            'accepted_context_keys' => ['app_option'],
        ]);
        $serializer = self::serializer($kernel);
        $collector = self::collector($kernel);

        // -- Act
        // Through a plan, twice
        $serializer->normalize(new Leaf('Example'));
        $serializer->denormalize(['name' => 'Example'], Leaf::class);
        $serializer->denormalize(['name' => 'Example'], Leaf::class, JsonEncoder::FORMAT);
        // Value objects inline, a value through the serializer
        $serializer->denormalize(['count' => 1, 'weight' => 2.5, 'flag' => true, 'optionalWeight' => null, 'counts' => [1], 'flagsByKey' => [], 'weightsByAnyKey' => []], ValueObjects::class);
        $serializer->normalize(new Objects(new \ArrayObject(), [new \DateTimeImmutable('2026-01-01')]));
        // The plan tried, but the data doesn't match
        self::outcome(static fn () => $serializer->denormalize(['name' => 1], Leaf::class));
        // No plan, as the class could get one
        $serializer->denormalize(['name' => 'Example'], Hooked::class);
        // No plan for the normalization only
        $serializer->normalize(new Mutable('Example'));
        // No plan by design
        $serializer->normalize(new \DateTimeImmutable('2026-01-01'));
        // Context and format
        $serializer->normalize(new Leaf('Example'), null, ['groups' => ['group']]);
        // The PropertyNormalizer fails without class metadata factory
        self::outcome(static fn () => $serializer->normalize(new Leaf('Example'), null, ['allow_extra_attributes' => false, 'app_option' => true]));
        $serializer->normalize(new Leaf('Example'), 'xml');
        // Data that isn't an array
        self::outcome(static fn () => $serializer->denormalize('Example', Leaf::class));
        $collector->collect(new Request(), new Response());
        $unserializedCollector = unserialize(serialize($collector));
        self::assertInstanceOf(KnownConstructionNormalizerDataCollector::class, $unserializedCollector);

        // -- Assert
        self::assertSame('self_aware_normalizers', $unserializedCollector->getName());
        self::assertSame(
            [
                Recorder::NORMALIZE => [
                    'plan' => 2,
                    'fallback' => 0,
                    // Including the values of Objects, which the serializer asks the known construction normalizer for
                    'notHandled' => 7,
                ],
                Recorder::DENORMALIZE => [
                    'plan' => 3,
                    'fallback' => 1,
                    'notHandled' => 2,
                ],
            ],
            $unserializedCollector->getSummary(),
        );
        self::assertSame(5, $unserializedCollector->getFastCount());
        self::assertSame(
            [
                [
                    'class' => Leaf::class,
                    'direction' => Recorder::DENORMALIZE,
                    'count' => 1,
                    'kind' => KnownConstructionNormalizerDataCollector::KIND_RUNTIME_FALLBACK,
                    'reason' => sprintf('%s::$name: string expected, int given', Leaf::class),
                    'hint' => null,
                ],
                [
                    'class' => Mutable::class,
                    'direction' => Recorder::NORMALIZE,
                    'count' => 1,
                    'kind' => KnownConstructionNormalizerDataCollector::KIND_NO_PLAN,
                    'reason' => 'property is mutable: $name',
                    'hint' => 'Make every property readonly (the class is only denormalized through its plan).',
                ],
                [
                    'class' => Leaf::class,
                    'direction' => Recorder::NORMALIZE,
                    'count' => 1,
                    'kind' => KnownConstructionNormalizerDataCollector::KIND_NOT_SUPPORTED,
                    'reason' => 'the context option "groups" is unknown',
                    'hint' => null,
                ],
                [
                    'class' => Leaf::class,
                    'direction' => Recorder::NORMALIZE,
                    'count' => 1,
                    'kind' => KnownConstructionNormalizerDataCollector::KIND_NOT_SUPPORTED,
                    'reason' => 'the context option "allow_extra_attributes" is only supported with true',
                    'hint' => null,
                ],
                [
                    'class' => Leaf::class,
                    'direction' => Recorder::NORMALIZE,
                    'count' => 1,
                    'kind' => KnownConstructionNormalizerDataCollector::KIND_NOT_SUPPORTED,
                    'reason' => 'the format "xml" is not supported',
                    'hint' => null,
                ],
                [
                    'class' => Hooked::class,
                    'direction' => Recorder::DENORMALIZE,
                    'count' => 1,
                    'kind' => KnownConstructionNormalizerDataCollector::KIND_NO_PLAN,
                    'reason' => 'property has hooks: $name',
                    'hint' => 'Remove the hooks of the property.',
                ],
                [
                    'class' => Leaf::class,
                    'direction' => Recorder::DENORMALIZE,
                    'count' => 1,
                    'kind' => KnownConstructionNormalizerDataCollector::KIND_NOT_SUPPORTED,
                    'reason' => 'the data is not an array',
                    'hint' => null,
                ],
            ],
            $unserializedCollector->getMissed(),
        );
        self::assertSame(7, $unserializedCollector->getFallbackCount());
        self::assertSame(
            [
                [
                    'class' => \ArrayObject::class,
                    'direction' => Recorder::NORMALIZE,
                    'count' => 1,
                    'kind' => KnownConstructionNormalizerDataCollector::KIND_NO_PLAN,
                    'reason' => 'internal class',
                    'hint' => null,
                ],
                [
                    'class' => \DateTimeImmutable::class,
                    'direction' => Recorder::NORMALIZE,
                    'count' => 2,
                    'kind' => KnownConstructionNormalizerDataCollector::KIND_NO_PLAN,
                    'reason' => 'internal class',
                    'hint' => null,
                ],
            ],
            $unserializedCollector->getByDesign(),
        );
        self::assertSame(
            [
                [
                    'class' => Objects::class,
                    'parameter' => 'countable',
                    'type' => \Countable::class,
                    'reason' => 'interface',
                ],
                [
                    'class' => Objects::class,
                    'parameter' => 'moments',
                    'type' => \DateTimeImmutable::class.'[]',
                    'reason' => 'internal class',
                ],
            ],
            $unserializedCollector->getDelegated(),
        );
        self::assertSame([Count::class, Flag::class, Weight::class], $unserializedCollector->getValueObjectClasses());
        $plans = $unserializedCollector->getPlans();
        // Every plan attempt, also the ones without plan (in the order of the attempts)
        self::assertSame([Leaf::class, ValueObjects::class, \Countable::class, \DateTimeImmutable::class, Objects::class, \ArrayObject::class, Hooked::class, Mutable::class], array_keys($plans['built']));
        self::assertSame([], $plans['loadedFromPool']);
        self::assertSame([], $plans['fromMemory']);
        $configuration = $unserializedCollector->getConfiguration();
        self::assertSame(TestKernel::FALLBACK_NORMALIZER_ID, $configuration['fallbackNormalizer'] ?? null);
        self::assertSame(-50, $configuration['fallbackPriority'] ?? null);
        self::assertSame(-45, $configuration['priority'] ?? null);
        self::assertSame(TestKernel::PLAN_CACHE_POOL_ID, $configuration['planCachePool'] ?? null);
        self::assertTrue($configuration['isPlanCachePoolDefined'] ?? null);
        $normalizers = array_column($configuration['normalizers'] ?? [], null, 'id');
        self::assertSame('before', $normalizers['serializer.normalizer.backed_enum']['position'] ?? null);
        self::assertSame(-40, $normalizers['serializer.normalizer.backed_enum']['priority'] ?? null);
        self::assertSame('self', $normalizers[SelfAwareNormalizersExtension::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID]['position'] ?? null);
        self::assertSame('after', $normalizers[TestKernel::FALLBACK_NORMALIZER_ID]['position'] ?? null);
        self::assertFalse($normalizers['serializer.normalizer.backed_enum']['isNeutral'] ?? null);
        $acceptedContextOptions = $unserializedCollector->getAcceptedContextOptions();
        self::assertSame('any value', $acceptedContextOptions['app_option'] ?? null);
        self::assertSame('any value', $acceptedContextOptions['deserialization_path'] ?? null);
        self::assertSame('true', $acceptedContextOptions['allow_extra_attributes'] ?? null);
        self::assertSame('false', $acceptedContextOptions['enable_type_conversion'] ?? null);
    }

    #[Test]
    public function plans_of_earlier_requests_are_shown_as_loaded_from_the_pool_or_kept_in_memory(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('profiler_pool', true, null, [
            'plan_cache_pool' => TestKernel::PLAN_CACHE_POOL_ID,
        ]);
        $container = $kernel->getContainer()->get('test.service_container');
        $serializer = self::serializer($kernel);
        $collector = self::collector($kernel);
        $knownConstructionNormalizer = $container->get(SelfAwareNormalizersExtension::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID);
        self::assertInstanceOf(KnownConstructionNormalizer::class, $knownConstructionNormalizer);
        // An earlier request of the same instance
        $serializer->normalize(new Leaf('Example'));
        $collector->reset();
        // Another instance (e.g. an earlier process) wrote the plan of ValueObjects into the pool
        $otherKnownConstructionNormalizer = new KnownConstructionNormalizer(
            new \ReflectionProperty(KnownConstructionNormalizer::class, 'planFactory')->getValue($knownConstructionNormalizer),
            $knownConstructionNormalizer,
            $container->get(TestKernel::PLAN_CACHE_POOL_ID),
            (string) $container->getParameter('container.build_id'),
        );
        $otherKnownConstructionNormalizer->supportsDenormalization([], ValueObjects::class);

        // -- Act
        $serializer->normalize(new Leaf('Example'));
        $serializer->denormalize(['count' => 1, 'weight' => 2.5, 'flag' => true, 'optionalWeight' => null, 'counts' => [], 'flagsByKey' => [], 'weightsByAnyKey' => []], ValueObjects::class);
        $collector->collect(new Request(), new Response());

        // -- Assert
        self::assertSame(
            [
                'built' => [],
                'loadedFromPool' => [ValueObjects::class],
                'fromMemory' => [Leaf::class],
            ],
            $collector->getPlans(),
        );
    }

    #[Test]
    public function classes_of_another_normalizer_are_shown_with_it(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('profiler_claimed', true, static function (ContainerBuilder $container): void {
            $container->setDefinition('app.per_call_normalizer', new Definition(PerCallNormalizer::class))
                ->addTag('serializer.normalizer');
        });
        $serializer = self::serializer($kernel);
        $collector = self::collector($kernel);

        // -- Act
        $serializer->denormalize(['name' => 'Example'], Leaf::class);
        $collector->collect(new Request(), new Response());

        // -- Assert
        self::assertSame(
            [
                [
                    'class' => Leaf::class,
                    'direction' => Recorder::DENORMALIZE,
                    'count' => 1,
                    'kind' => KnownConstructionNormalizerDataCollector::KIND_CLAIMED,
                    'reason' => 'claimed by a normalizer with a higher priority (possibly claimed by the normalizer "app.per_call_normalizer", which decides per call (declare it as neutral normalizer if it never handles the class))',
                    'hint' => null,
                ],
            ],
            $collector->getMissed(),
        );
        // Not counted as missed, as another normalizer handles the class
        self::assertSame(0, $collector->getFallbackCount());
    }

    #[Test]
    public function reset_removes_the_data_of_the_request(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('profiler_reset', true);
        $serializer = self::serializer($kernel);
        $collector = self::collector($kernel);
        $serializer->normalize(new Leaf('Example'));
        $collector->collect(new Request(), new Response());
        self::assertSame(1, $collector->getFastCount());

        // -- Act
        $collector->reset();
        $collector->collect(new Request(), new Response());

        // -- Assert
        self::assertSame(0, $collector->getFastCount());
        self::assertSame([], $collector->getMissed());
        self::assertSame([], $collector->getPlans()['built']);
    }

    #[Test]
    public function an_empty_collector_has_no_data(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('profiler_empty', true);
        $collector = self::collector($kernel);

        // -- Act & Assert
        self::assertSame(0, $collector->getFastCount());
        self::assertSame(0, $collector->getFallbackCount());
        self::assertSame([], $collector->getSummary());
        self::assertSame([], $collector->getByDesign());
        self::assertSame([], $collector->getDelegated());
        self::assertSame([], $collector->getValueObjectClasses());
        self::assertSame(['built' => [], 'loadedFromPool' => [], 'fromMemory' => []], $collector->getPlans());
        self::assertSame([], $collector->getConfiguration());
        self::assertSame([], $collector->getAcceptedContextOptions());
        self::assertSame(KnownConstructionNormalizerDataCollector::TEMPLATE, KnownConstructionNormalizerDataCollector::getTemplate());
    }

    #[Test]
    public function ignored_serializer_mapping_of_the_classes_of_the_request_is_listed(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('profiler_mapping', true);
        $serializer = self::serializer($kernel);
        $collector = self::collector($kernel);

        // -- Act
        $serializer->normalize(new MappedReadModel('Example', 1));
        $serializer->normalize(new Leaf('Example'));
        $collector->collect(new Request(), new Response());

        // -- Assert
        self::assertSame(
            [
                [
                    'class' => MappedReadModel::class,
                    'member' => 'class',
                    'attribute' => sprintf('#[%s]', Context::class),
                ],
                [
                    'class' => MappedReadModel::class,
                    'member' => '$name',
                    'attribute' => sprintf('#[%s]', Groups::class),
                ],
                [
                    'class' => MappedReadModel::class,
                    'member' => '$name',
                    'attribute' => sprintf('#[%s]', SerializedName::class),
                ],
                [
                    'class' => MappedReadModel::class,
                    'member' => 'label()',
                    'attribute' => sprintf('#[%s]', Ignore::class),
                ],
            ],
            $collector->getIgnoredMapping(),
        );
        // The classes take the fast path, the mapping alone is a warning
        self::assertSame(0, $collector->getFallbackCount());
        self::assertTrue($collector->hasWarning());
    }

    #[Test]
    public function without_mapping_and_misses_there_is_no_warning(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('profiler_no_warning', true);
        $serializer = self::serializer($kernel);
        $collector = self::collector($kernel);

        // -- Act
        $serializer->normalize(new Leaf('Example'));
        $collector->collect(new Request(), new Response());

        // -- Assert
        self::assertSame([], $collector->getIgnoredMapping());
        self::assertFalse($collector->hasWarning());
    }

    /**
     * The profiler orders panels of the same priority by their registration, which follows the order of the bundles.
     */
    #[Test]
    public function the_panel_follows_the_order_of_the_bundles_in_the_menu(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('profiler_menu', true, null, [], true, [new LaterBundle()]);

        // -- Act
        $templates = $kernel->getContainer()->getParameter('data_collector.templates');
        self::assertIsArray($templates);
        $ids = array_keys($templates);

        // -- Assert
        $positionOfSerializer = array_search('serializer.data_collector', $ids, true);
        $positionOfPanel = array_search(KnownConstructionNormalizerProfilerPass::DATA_COLLECTOR_SERVICE_ID, $ids, true);
        $positionOfLaterBundle = array_search(LaterDataCollector::class, $ids, true);
        self::assertIsInt($positionOfSerializer);
        self::assertIsInt($positionOfPanel);
        self::assertIsInt($positionOfLaterBundle);
        self::assertLessThan($positionOfPanel, $positionOfSerializer);
        self::assertLessThan($positionOfLaterBundle, $positionOfPanel);
    }

    #[Test]
    public function nothing_is_registered_or_recorded_without_debug_or_profiler(): void
    {
        // -- Arrange
        $withoutDebug = $this->bootedKernel('profiler_without_debug', false);
        $withoutProfiler = $this->bootedKernel('without_profiler', true, null, [], false);

        // -- Act & Assert
        foreach ([$withoutDebug, $withoutProfiler] as $kernel) {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertFalse($container->has(KnownConstructionNormalizerProfilerPass::DATA_COLLECTOR_SERVICE_ID));
            self::assertFalse($container->has(KnownConstructionNormalizerProfilerPass::RECORDER_SERVICE_ID));
            $knownConstructionNormalizer = $container->get(SelfAwareNormalizersExtension::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID);
            self::assertInstanceOf(KnownConstructionNormalizer::class, $knownConstructionNormalizer);
            self::assertNull(new \ReflectionProperty(KnownConstructionNormalizer::class, 'recorder')->getValue($knownConstructionNormalizer));
        }
    }

    #[Test]
    public function the_panel_renders(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('profiler_template', true);
        $serializer = self::serializer($kernel);
        $collector = self::collector($kernel);
        $serializer->normalize(new Leaf('Example'));
        $serializer->normalize(new Objects(new \ArrayObject(), [new \DateTimeImmutable('2026-01-01')]));
        self::outcome(static fn () => $serializer->denormalize(['name' => 1], Leaf::class));
        $serializer->normalize(new \DateTimeImmutable('2026-01-01'));
        $collector->collect(new Request(), new Response());
        $twig = new Environment(new ChainLoader([
            new ArrayLoader([
                '@WebProfiler/Profiler/layout.html.twig' => '{% block toolbar %}{% endblock %}|{% block menu %}{% endblock %}|{% block panel %}{% endblock %}',
                '@WebProfiler/Profiler/toolbar_item.html.twig' => '{{ icon }}{{ text }}[{{ status }}]',
            ]),
            self::bundleTemplates(),
        ]), [
            'strict_variables' => true,
        ]);
        $twig->addFunction(new TwigFunction('path', static fn (string $route, array $parameters): string => sprintf('/%s/%s', $route, http_build_query($parameters))));

        // -- Act
        $html = $twig->render(KnownConstructionNormalizerDataCollector::TEMPLATE, [
            'collector' => $collector,
            'profiler_url' => '/_profiler/token',
            'token' => 'token',
        ]);

        // -- Assert
        self::assertStringContainsString('fallback', $html);
        self::assertStringContainsString('[yellow]', $html);
        self::assertStringContainsString('Self-Aware Norm.', $html);
        self::assertStringContainsString(sprintf('%s::$name: string expected, int given', Leaf::class), $html);
        self::assertStringContainsString('Not handled by design', $html);
        self::assertStringContainsString('$moments', $html);
        self::assertStringContainsString('built in', $html);
        self::assertStringContainsString('deserialization_path', $html);
        self::assertStringContainsString('Ignored serializer mapping', $html);
    }

    // -- Helpers

    private static function bundleTemplates(): FilesystemLoader
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 4).'/src/Resources/views', 'SelfAwareNormalizers');

        return $loader;
    }

    /**
     * @param \Closure(): mixed $call
     */
    private static function outcome(\Closure $call): void
    {
        try {
            $call();
        } catch (\Throwable) {
            // The failure of the fallback isn't relevant here
        }
    }

    private static function serializer(TestKernel $kernel): NormalizerInterface & DenormalizerInterface
    {
        $serializer = $kernel->getContainer()->get('test.service_container')->get('serializer');
        // The TraceableSerializer in debug mode, which adds its trace id to the context
        self::assertInstanceOf(NormalizerInterface::class, $serializer);
        self::assertInstanceOf(DenormalizerInterface::class, $serializer);

        return $serializer;
    }

    private static function collector(TestKernel $kernel): KnownConstructionNormalizerDataCollector
    {
        $collector = $kernel->getContainer()->get('test.service_container')->get(KnownConstructionNormalizerProfilerPass::DATA_COLLECTOR_SERVICE_ID);
        self::assertInstanceOf(KnownConstructionNormalizerDataCollector::class, $collector);

        return $collector;
    }

    /**
     * @param (\Closure(ContainerBuilder): void)|null $configure
     * @param array<string, mixed>                    $knownConstructionNormalizerConfig
     * @param list<BundleInterface>                   $additionalBundles
     */
    private function bootedKernel(
        string $environment,
        bool $debug,
        ?\Closure $configure = null,
        array $knownConstructionNormalizerConfig = [],
        bool $isProfilerEnabled = true,
        array $additionalBundles = [],
    ): TestKernel {
        $kernel = new TestKernel($environment, $debug, static function (ContainerBuilder $container) use ($configure, $isProfilerEnabled): void {
            if ($isProfilerEnabled) {
                $container->loadFromExtension('framework', [
                    'profiler' => [
                        'enabled' => true,
                        'collect' => true,
                    ],
                ]);
            }
            if ($configure !== null) {
                $configure($container);
            }
        }, $knownConstructionNormalizerConfig, $additionalBundles);
        $this->kernels[] = $kernel;
        $kernel->boot();

        return $kernel;
    }
}
