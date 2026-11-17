<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer;

use DigitalCraftsman\SelfAwareNormalizers\DependencyInjection\KnownConstructionNormalizerCompilerPass;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\StringNormalizableNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Chain\ClaimedLeaf;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Chain\ClaimedValue;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Chain\ClaimingNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Chain\JsonSerializableReadModel;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Chain\PerCallNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Chain\WithClaimedClasses;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Chain\WithClaimedUnion;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Equivalence\SerializerComparison;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Kind;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Leaf;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Scalars;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\Count;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;

require_once dirname(__DIR__, 2).'/Test/KnownConstructionNormalizer/Fixture/ReadModel/fixtures.php';
require_once dirname(__DIR__, 2).'/Test/KnownConstructionNormalizer/Chain/fixtures.php';

/**
 * The normalizers in front of the KnownConstructionNormalizer: a class one of them claims is neither planned nor handled inline, so the
 * results stay the ones of the serializer without the KnownConstructionNormalizer.
 */
#[CoversClass(NormalizerChain::class)]
#[CoversClass(PlanFactory::class)]
#[CoversClass(KnownConstructionNormalizer::class)]
#[CoversClass(KnownConstructionNormalizerCompilerPass::class)]
final class NormalizerChainTest extends TestCase
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

    // -- Claims

    #[Test]
    public function without_normalizers_in_front_nothing_is_claimed(): void
    {
        // -- Arrange
        $normalizerChain = new NormalizerChain();

        // -- Act & Assert
        self::assertNull($normalizerChain->claimOf(Leaf::class));
    }

    /**
     * @return iterable<string, array{0: array<string, bool|null>, 1: ?string}>
     */
    public static function supportedTypesAndClaims(): iterable
    {
        $claimed = 'claimed by the normalizer "app.normalizer"';
        $possiblyClaimed = 'possibly claimed by the normalizer "app.normalizer", which decides per call (declare it as neutral normalizer if it never handles the class)';

        yield 'class' => [[Leaf::class => true], $claimed];
        yield 'class per call' => [[Leaf::class => false], $possiblyClaimed];
        yield 'class not supported, overrules object' => [[Leaf::class => null, 'object' => true], null];
        yield 'interface' => [[\Stringable::class => false, \Countable::class => true], null];
        yield 'parent class' => [[\ArrayObject::class => true], null];
        yield 'object' => [['object' => true], $claimed];
        yield 'object per call' => [['object' => false], $possiblyClaimed];
        yield 'object not supported, overrules any type' => [['object' => null, '*' => false], null];
        yield 'any type' => [['*' => true], $claimed];
        yield 'any type per call' => [['*' => false], $possiblyClaimed];
        yield 'other classes' => [[\DateTimeInterface::class => true, \BackedEnum::class => true], null];
        yield 'nothing' => [[], null];
    }

    /**
     * @param array<string, bool|null> $supportedTypes
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('supportedTypesAndClaims')]
    public function normalizers_claim_classes_like_the_serializer_selects_them(
        array $supportedTypes,
        ?string $expectedClaim,
    ): void {
        // -- Arrange
        $normalizerChain = new NormalizerChain([
            'app.normalizer' => self::normalizer(static fn (): array => $supportedTypes),
        ]);

        // -- Act & Assert
        self::assertSame($expectedClaim, $normalizerChain->claimOf(Leaf::class));
    }

    #[Test]
    public function claims_of_a_parent_class_and_of_an_interface_apply_to_the_class(): void
    {
        // -- Arrange
        $parentClassChain = new NormalizerChain([
            'app.normalizer' => self::normalizer(static fn (): array => [\ArrayObject::class => true]),
        ]);
        $interfaceChain = new NormalizerChain([
            'app.normalizer' => self::normalizer(static fn (): array => [\Countable::class => true]),
        ]);
        /** @psalm-suppress MissingTemplateParam */
        $subclass = new class extends \ArrayObject {
        };

        // -- Act & Assert
        self::assertSame('claimed by the normalizer "app.normalizer"', $parentClassChain->claimOf($subclass::class));
        self::assertSame('claimed by the normalizer "app.normalizer"', $interfaceChain->claimOf($subclass::class));
    }

    #[Test]
    public function claims_for_the_supported_formats_count(): void
    {
        // -- Arrange
        $chain = static fn (\Closure $supportedTypes): NormalizerChain => new NormalizerChain([
            'app.normalizer' => self::normalizer($supportedTypes),
        ]);

        // -- Act & Assert
        self::assertSame('claimed by the normalizer "app.normalizer"', $chain(static fn (?string $format): array => $format === null ? ['object' => true] : [])->claimOf(Leaf::class));
        self::assertSame('claimed by the normalizer "app.normalizer"', $chain(static fn (?string $format): array => $format === JsonEncoder::FORMAT ? ['object' => true] : [])->claimOf(Leaf::class));
        self::assertNull($chain(static fn (?string $format): array => $format === 'xml' ? ['object' => true] : [])->claimOf(Leaf::class));
        // Cacheable for one format only: decided per call
        self::assertStringStartsWith('possibly claimed', (string) $chain(static fn (?string $format): array => ['object' => $format === null])->claimOf(Leaf::class));
        self::assertStringStartsWith('possibly claimed', (string) $chain(static fn (?string $format): array => ['object' => $format !== null])->claimOf(Leaf::class));
    }

    #[Test]
    public function the_first_normalizer_that_supports_the_class_decides(): void
    {
        // -- Arrange
        $normalizerChain = new NormalizerChain([
            'app.not_supporting' => self::normalizer(static fn (): array => [\DateTimeInterface::class => true]),
            'app.unwrapping' => new UnwrappingDenormalizer(),
            'app.neutral' => self::normalizer(static fn (): array => ['object' => false]),
            'app.normalizer' => self::normalizer(static fn (): array => ['object' => true]),
            'app.later' => self::normalizer(static fn (): array => ['object' => false]),
        ], [
            'app.neutral',
        ]);

        // -- Act & Assert
        self::assertSame('claimed by the normalizer "app.normalizer"', $normalizerChain->claimOf(Leaf::class));
    }

    #[Test]
    public function value_objects_are_handled_by_the_self_aware_normalizers_when_they_come_first(): void
    {
        // -- Arrange
        $selfAwareFirst = new NormalizerChain([
            'self_aware' => new StringNormalizableNormalizer(),
            'app.normalizer' => self::normalizer(static fn (): array => ['object' => true]),
        ]);
        $selfAwareLast = new NormalizerChain([
            'app.normalizer' => self::normalizer(static fn (): array => ['object' => true]),
            'self_aware' => new StringNormalizableNormalizer(),
        ]);

        // -- Act & Assert
        self::assertNull($selfAwareFirst->claimOf(ClaimedValue::class));
        // Not a value object of the self-aware normalizer
        self::assertSame('claimed by the normalizer "app.normalizer"', $selfAwareFirst->claimOf(Leaf::class));
        self::assertSame('claimed by the normalizer "app.normalizer"', $selfAwareLast->claimOf(ClaimedValue::class));
    }

    #[Test]
    public function services_that_are_no_normalizers_are_skipped(): void
    {
        // -- Arrange
        $normalizerChain = new NormalizerChain([
            'app.other' => new \ArrayObject(),
        ]);

        // -- Act & Assert
        self::assertNull($normalizerChain->claimOf(Leaf::class));
    }

    #[Test]
    public function normalizers_that_only_normalize_claim_classes_as_well(): void
    {
        // -- Arrange
        $normalizerChain = new NormalizerChain([
            'app.normalizer' => new class implements NormalizerInterface {
                #[\Override]
                public function normalize(mixed $data, ?string $format = null, array $context = []): never
                {
                    throw new \LogicException('Not used.');
                }

                #[\Override]
                public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
                {
                    throw new \LogicException('Not used.');
                }

                /**
                 * @return array<string, bool|null>
                 */
                #[\Override]
                public function getSupportedTypes(?string $format): array
                {
                    return [
                        Leaf::class => true,
                    ];
                }
            },
        ]);

        // -- Act & Assert
        self::assertSame('claimed by the normalizer "app.normalizer"', $normalizerChain->claimOf(Leaf::class));
    }

    #[Test]
    public function claims_are_determined_once_per_class(): void
    {
        // -- Arrange
        $calls = 0;
        $normalizerChain = new NormalizerChain([
            'app.normalizer' => self::normalizer(static function () use (&$calls): array {
                ++$calls;

                return [];
            }),
        ]);

        // -- Act
        $normalizerChain->claimOf(Leaf::class);
        $normalizerChain->claimOf(Leaf::class);

        // -- Assert
        self::assertSame(2, $calls, 'Once per format');
    }

    // -- In the serializer

    #[Test]
    public function classes_claimed_by_a_normalizer_in_front_are_handled_by_it(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('chain_claiming', static function (ContainerBuilder $container): void {
            $container->setDefinition('app.claiming_normalizer', new Definition(ClaimingNormalizer::class))
                // In front of the self-aware normalizers, so it claims the value object as well
                ->addTag('serializer.normalizer', [
                    'priority' => 10,
                ]);
        });
        $comparison = SerializerComparison::fromSerializer($kernel->getContainer()->get('test.service_container')->get('serializer'));
        $object = new WithClaimedClasses(
            new ClaimedLeaf('Leaf'),
            [new ClaimedLeaf('First leaf')],
            new ClaimedValue('Value'),
            [new ClaimedValue('First value')],
            'Example',
        );
        $data = [
            'leaf' => ['claimed' => 'Leaf'],
            'leaves' => [['claimed' => 'First leaf']],
            'value' => ['claimed' => 'Value'],
            'values' => [['claimed' => 'First value']],
            'name' => 'Example',
        ];

        // -- Act
        $normalized = $comparison->plan->normalize($object);
        $denormalized = $comparison->plan->denormalize($data, WithClaimedClasses::class);

        // -- Assert
        self::assertSame($data, $normalized);
        self::assertEquals($object, $denormalized);
        foreach ([null, JsonEncoder::FORMAT] as $format) {
            self::assertSame(
                SerializerComparison::outcome(static fn () => $comparison->reference->normalize($object, $format)),
                SerializerComparison::outcome(static fn () => $comparison->plan->normalize($object, $format)),
            );
            self::assertSame(
                SerializerComparison::outcome(static fn () => $comparison->reference->denormalize($data, WithClaimedClasses::class, $format)),
                SerializerComparison::outcome(static fn () => $comparison->plan->denormalize($data, WithClaimedClasses::class, $format)),
            );
        }
        // Elements of a union are dispatched by their runtime class: the claimed class through the serializer, the planned one inline
        $union = new WithClaimedUnion([new ClaimedLeaf('Leaf'), $object, 'Text']);
        self::assertSame(['items' => [['claimed' => 'Leaf'], $data, 'Text']], $comparison->plan->normalize($union));
        self::assertSame(
            SerializerComparison::outcome(static fn () => $comparison->reference->normalize($union, JsonEncoder::FORMAT)),
            SerializerComparison::outcome(static fn () => $comparison->plan->normalize($union, JsonEncoder::FORMAT)),
        );
        // The class with the claimed classes is planned, the claimed classes go through the serializer
        self::assertTrue($comparison->knownConstructionNormalizer->supportsDenormalization($data, WithClaimedClasses::class));
        self::assertTrue($comparison->knownConstructionNormalizer->supportsNormalization($object));
        self::assertSame(
            ['leaf', 'leaves', 'value', 'values'],
            array_map(
                static fn (Diagnosis\DelegatedParameter $parameter): string => $parameter->name,
                $comparison->knownConstructionNormalizer->diagnose(WithClaimedClasses::class)->delegatedParameters,
            ),
        );
        // The claimed class itself isn't planned
        self::assertFalse($comparison->knownConstructionNormalizer->supportsDenormalization(['name' => 'Leaf'], ClaimedLeaf::class));
        $diagnosis = $comparison->knownConstructionNormalizer->diagnose(ClaimedLeaf::class);
        self::assertSame(Diagnosis\PlanLimitationReason::CLAIMED_BY_OTHER_NORMALIZER, $diagnosis->limitation?->reason);
        self::assertSame('claimed by the normalizer "app.claiming_normalizer"', $diagnosis->limitation?->detail);
        // Classes the normalizer doesn't claim stay planned
        self::assertTrue($comparison->knownConstructionNormalizer->supportsDenormalization(['name' => 'Leaf'], Leaf::class));
    }

    #[Test]
    public function a_normalizer_that_decides_per_call_keeps_all_classes_from_the_plan_unless_it_is_neutral(): void
    {
        // -- Arrange
        $configure = static function (ContainerBuilder $container): void {
            $container->setDefinition('app.per_call_normalizer', new Definition(PerCallNormalizer::class))
                ->addTag('serializer.normalizer');
        };
        $conservativeKernel = $this->bootedKernel('chain_per_call', $configure);
        $neutralKernel = $this->bootedKernel('chain_per_call_neutral', $configure, [
            'neutral_normalizers' => ['app.per_call_normalizer'],
        ]);

        // -- Act
        $conservative = SerializerComparison::fromSerializer($conservativeKernel->getContainer()->get('test.service_container')->get('serializer'));
        $neutral = SerializerComparison::fromSerializer($neutralKernel->getContainer()->get('test.service_container')->get('serializer'));

        // -- Assert
        self::assertFalse($conservative->knownConstructionNormalizer->supportsDenormalization(['name' => 'Leaf'], Leaf::class));
        self::assertSame(
            'possibly claimed by the normalizer "app.per_call_normalizer", which decides per call (declare it as neutral normalizer if it never handles the class)',
            $conservative->knownConstructionNormalizer->diagnose(Leaf::class)->limitation?->detail,
        );
        self::assertSame(
            SerializerComparison::outcome(static fn () => $conservative->reference->denormalize(['name' => 'Leaf'], Leaf::class)),
            SerializerComparison::outcome(static fn () => $conservative->plan->denormalize(['name' => 'Leaf'], Leaf::class)),
        );
        self::assertTrue($neutral->knownConstructionNormalizer->supportsDenormalization(['name' => 'Leaf'], Leaf::class));
    }

    #[Test]
    public function container_build_fails_with_a_neutral_normalizer_that_is_not_in_front(): void
    {
        // -- Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The neutral normalizer "serializer.normalizer.datetime" (self_aware_normalizers.known_construction_normalizer.neutral_normalizers) is not a normalizer the serializer asks before the known construction normalizer. Remove it from the neutral normalizers.');

        // -- Act
        $this->bootedKernel('chain_invalid_neutral', null, [
            'neutral_normalizers' => ['serializer.normalizer.datetime'],
        ]);
    }

    #[Test]
    public function container_build_fails_when_the_known_construction_normalizer_is_not_a_normalizer_of_the_default_serializer(): void
    {
        // -- Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The known construction normalizer is not a normalizer of the default serializer.');

        // -- Act
        $this->bootedKernel('chain_not_tagged', static function (ContainerBuilder $container): void {
            // Before the SerializerPass, after the extension defined the known construction normalizer
            $container->addCompilerPass(new class implements CompilerPassInterface {
                #[\Override]
                public function process(ContainerBuilder $container): void
                {
                    $container->getDefinition(KnownConstructionNormalizer::class)
                        ->clearTag('serializer.normalizer');
                }
            }, PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
        });
    }

    /**
     * All built-in normalizers of the FrameworkBundle in front of the KnownConstructionNormalizer (it's placed right in front of the
     * ArrayDenormalizer and the ObjectNormalizer): only the classes they are made for are claimed.
     */
    #[Test]
    public function built_in_normalizers_only_claim_their_own_classes(): void
    {
        // -- Arrange
        $kernel = $this->bootedKernel('chain_all_built_ins', static function (ContainerBuilder $container): void {
            // Registers the UnwrappingDenormalizer
            $container->loadFromExtension('framework', [
                'property_access' => [
                    'enabled' => true,
                ],
            ]);
            $container->getDefinition(TestKernel::FALLBACK_NORMALIZER_ID)
                ->clearTag('serializer.normalizer')
                ->addTag('serializer.normalizer', [
                    'priority' => -999,
                ]);
            // The default of the FrameworkBundle
            $container->getDefinition('serializer.normalizer.backed_enum')
                ->clearTag('serializer.normalizer')
                ->addTag('serializer.normalizer', [
                    'priority' => -915,
                ]);
        }, [
            'priority' => -995,
        ]);
        $comparison = SerializerComparison::fromSerializer($kernel->getContainer()->get('test.service_container')->get('serializer'));
        $normalizerChain = new \ReflectionProperty(PlanFactory::class, 'normalizerChain')->getValue($comparison->planFactory);
        self::assertInstanceOf(NormalizerChain::class, $normalizerChain);
        $precedingNormalizers = new \ReflectionProperty(NormalizerChain::class, 'precedingNormalizers')->getValue($normalizerChain);
        self::assertIsIterable($precedingNormalizers);

        // -- Act
        $idsOfPrecedingNormalizers = array_keys(iterator_to_array($precedingNormalizers));

        // -- Assert
        foreach (['serializer.denormalizer.unwrapping', 'serializer.normalizer.problem', 'serializer.normalizer.uid', 'serializer.normalizer.datetime', 'serializer.normalizer.json_serializable', 'serializer.normalizer.backed_enum', 'serializer.denormalizer.array'] as $builtInNormalizer) {
            self::assertContains($builtInNormalizer, $idsOfPrecedingNormalizers);
        }
        self::assertNotContains('serializer.normalizer.object', $idsOfPrecedingNormalizers);

        self::assertNull($normalizerChain->claimOf(Leaf::class));
        self::assertNull($normalizerChain->claimOf(Scalars::class));
        self::assertNull($normalizerChain->claimOf(Count::class));
        self::assertSame('claimed by the normalizer "serializer.normalizer.datetime"', $normalizerChain->claimOf(\DateTimeImmutable::class));
        self::assertSame('claimed by the normalizer "serializer.normalizer.backed_enum"', $normalizerChain->claimOf(Kind::class));
        self::assertSame('claimed by the normalizer "serializer.normalizer.json_serializable"', $normalizerChain->claimOf(JsonSerializableReadModel::class));
        self::assertTrue($comparison->knownConstructionNormalizer->supportsDenormalization(['name' => 'Leaf'], Leaf::class));
        self::assertFalse($comparison->knownConstructionNormalizer->supportsDenormalization(['name' => 'Example'], JsonSerializableReadModel::class));
        self::assertSame(
            SerializerComparison::outcome(static fn () => $comparison->reference->normalize(new JsonSerializableReadModel('Example'))),
            SerializerComparison::outcome(static fn () => $comparison->plan->normalize(new JsonSerializableReadModel('Example'))),
        );
    }

    // -- Helpers

    /**
     * @param \Closure(?string): array<string, bool|null> $supportedTypes
     */
    private static function normalizer(\Closure $supportedTypes): NormalizerInterface & DenormalizerInterface
    {
        return new readonly class($supportedTypes) implements NormalizerInterface, DenormalizerInterface {
            /**
             * @param \Closure(?string): array<string, bool|null> $supportedTypes
             */
            public function __construct(
                private \Closure $supportedTypes,
            ) {
            }

            #[\Override]
            public function normalize(mixed $data, ?string $format = null, array $context = []): never
            {
                throw new \LogicException('Not used.');
            }

            #[\Override]
            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
            {
                throw new \LogicException('Not used.');
            }

            #[\Override]
            public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): never
            {
                throw new \LogicException('Not used.');
            }

            #[\Override]
            public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
            {
                throw new \LogicException('Not used.');
            }

            /**
             * @return array<string, bool|null>
             */
            #[\Override]
            public function getSupportedTypes(?string $format): array
            {
                return ($this->supportedTypes)($format);
            }
        };
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
