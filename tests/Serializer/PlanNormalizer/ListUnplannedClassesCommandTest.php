<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer;

use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Leaf;
use DigitalCraftsman\SelfAwareNormalizers\Testing\PlanNormalizer\SerializerComparison;
use DigitalCraftsman\SelfAwareNormalizers\Testing\PlanNormalizer\ThrowingPropertyTypeExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;

require_once dirname(__DIR__, 2).'/Test/PlanNormalizer/Fixture/ReadModel/fixtures.php';

/**
 * The reasons come from the PlanFactory, so the fixtures (Fixture/ReadModel/fixtures.php) pin one class per limitation. The classes of
 * src/ change as they are put on the fast path, so for them only the consistency with the PlanNormalizer is asserted.
 */
#[CoversClass(ListUnplannedClassesCommand::class)]
#[CoversClass(PlanNormalizer::class)]
#[CoversClass(PlanFactory::class)]
#[CoversClass(Diagnosis\ClassDiagnosis::class)]
#[CoversClass(Diagnosis\DelegatedParameter::class)]
#[CoversClass(Diagnosis\PlanDiagnosis::class)]
#[CoversClass(Diagnosis\PlanLimitation::class)]
#[CoversClass(Diagnosis\PlanLimitationReason::class)]
#[CoversClass(Exception\TypeCanNotBePlanned::class)]
final class ListUnplannedClassesCommandTest extends KernelTestCase
{
    private const string FIXTURE_NAMESPACE = 'DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\\';

    /**
     * The width of the terminal is read from the environment variable COLUMNS, which some tests set.
     */
    private string | false $columnsBeforeTest;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->columnsBeforeTest = getenv('COLUMNS');
    }

    #[\Override]
    protected function tearDown(): void
    {
        putenv($this->columnsBeforeTest !== false
            ? sprintf('COLUMNS=%s', $this->columnsBeforeTest)
            : 'COLUMNS');

        parent::tearDown();
    }

    #[Test]
    public function fixtures_are_grouped_with_the_reasons_of_the_plan_factory(): void
    {
        // -- Arrange
        $commandTester = new CommandTester($this->commandForFixtures());

        // -- Act
        $exitCode = $commandTester->execute([
            '--format' => 'json',
        ]);

        // -- Assert
        self::assertSame(Command::SUCCESS, $exitCode);

        $result = json_decode($commandTester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($result);

        self::assertSame(41, $result['numberOfClassesInScope']);
        self::assertSame(2, $result['numberOfNotRelevantClasses']);
        self::assertSame(18, $result['numberOfPlannedClasses']);
        self::assertSame(
            [
                [
                    'class' => 'DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\NotLoadable\MissingParent',
                    'error' => 'Class "DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\NotLoadable\DoesNotExist" not found',
                ],
            ],
            $result['notLoadableClasses'],
        );
        self::assertSame([], $result['staleAcceptedClasses']);
        self::assertSame([null], array_values(array_unique(array_column($result['fallingBackClasses'], 'acceptedBecause'))));
        self::assertSame(
            [
                ['Base', 'ABSTRACT_CLASS', 'abstract class'],
                ['Kind', 'ENUM', 'enum'],
            ],
            array_map(
                static fn (array $notRelevantClass): array => [
                    self::shortName($notRelevantClass['class']),
                    $notRelevantClass['reason'],
                    $notRelevantClass['description'],
                ],
                $result['notRelevantClasses'],
            ),
        );

        // Self-aware value objects (NameValue), also an abstract class (SelfAwareBase) and an enum (SelfAwareKind) implementing an
        // interface of the self-aware normalizers, are always (de)normalized by their own normalizers, so they are never listed as classes
        $listedClasses = array_merge(
            array_column($result['notRelevantClasses'], 'class'),
            array_column($result['fallingBackClasses'], 'class'),
            array_column($result['delegatedParameters'], 'class'),
        );
        foreach (['NameValue', 'SelfAwareBase', 'SelfAwareKind'] as $selfAwareClass) {
            self::assertNotContains(self::FIXTURE_NAMESPACE.$selfAwareClass, $listedClasses);
        }

        $all = 'denormalization and normalization';
        self::assertSame(
            [
                ['AdditionalParameter', $all, 'PARAMETER_IS_NOT_PROMOTED', 'unused', null],
                ['CollectionClass', $all, 'COLLECTION_IS_NOT_AN_ARRAY', 'names', 'ArrayObject<int, string>'],
                ['Cycle', $all, 'RECURSIVE_CLASS', null, null],
                ['CycleBack', $all, 'RECURSIVE_CLASS', null, null],
                ['DefaultObject', $all, 'PARAMETER_HAS_OBJECT_DEFAULT_VALUE', 'createdAt', null],
                ['Hooked', $all, 'PROPERTY_HAS_HOOKS', 'name', null],
                ['Inherited', $all, 'PROPERTY_IS_INHERITED', 'name', 'declared in DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Base'],
                ['IntersectionParameter', $all, 'INTERSECTION_TYPE', 'value', 'Countable&Traversable'],
                ['IterableParameter', $all, 'COLLECTION_IS_NOT_AN_ARRAY', 'values', 'iterable'],
                ['Mutable', 'normalization', 'PROPERTY_IS_MUTABLE', 'name', null],
                ['NoConstructor', $all, 'NO_CONSTRUCTOR', null, null],
                ['NoProperties', $all, 'PARAMETERS_DIFFER_FROM_PROPERTIES', null, 'parameters: [], properties: []'],
                ['NonPublicProperty', $all, 'PROPERTY_IS_NOT_PUBLIC', 'secret', null],
                ['ObjectParameter', $all, 'UNSUPPORTED_TYPE', 'value', 'object'],
                ['ObjectShape', $all, 'UNSUPPORTED_TYPE', 'value', 'object'],
                ['ParameterWithoutProperty', $all, 'PROPERTY_IS_NOT_PROMOTED', 'upperCaseName', null],
                ['PrivateConstructor', $all, 'CONSTRUCTOR_IS_NOT_PUBLIC', null, null],
                ['ReferenceParameter', $all, 'PARAMETER_IS_PASSED_BY_REFERENCE', 'name', null],
                ['Tree', $all, 'RECURSIVE_CLASS', null, null],
                ['UnionParameter', $all, 'UNION_TYPE', 'value', 'int|string'],
                ['UntypedParameter', $all, 'PARAMETER_HAS_NO_TYPE', 'name', null],
                ['VariadicParameter', $all, 'PARAMETER_IS_VARIADIC', 'names', null],
            ],
            array_map(
                static fn (array $fallingBackClass): array => [
                    self::shortName($fallingBackClass['class']),
                    $fallingBackClass['fallsBackFor'],
                    $fallingBackClass['reason'],
                    $fallingBackClass['member'],
                    $fallingBackClass['detail'],
                ],
                $result['fallingBackClasses'],
            ),
        );

        self::assertSame(
            [
                // The template type is resolved as a class of the namespace, like by the PropertyNormalizer
                ['Box', 'content', self::FIXTURE_NAMESPACE.'T', '', 'NOT_IN_SCOPE'],
                ['Grove', 'tree', self::FIXTURE_NAMESPACE.'Tree', '', 'RECURSIVE_CLASS'],
                // Nested collections of value objects and of classes without plan
                ['NestedObjectCollections', 'counts', 'DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ValueObject\Count', '[][]', 'NOT_IN_SCOPE'],
                ['NestedObjectCollections', 'moments', \DateTimeImmutable::class, '[][]', 'NOT_IN_SCOPE'],
                // A nested collection of self-aware value objects goes through the serializer, although the value object is never listed
                ['NestedValueObjects', 'names', self::FIXTURE_NAMESPACE.'NameValue', '[][]', 'SELF_AWARE_VALUE_OBJECT'],
                ['Objects', 'countable', \Countable::class, '', 'NOT_IN_SCOPE'],
                ['Objects', 'moments', \DateTimeImmutable::class, '[]', 'NOT_IN_SCOPE'],
                ['Scalars', 'status', 'DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ValueObject\PlainStatus', '', 'NOT_IN_SCOPE'],
                ['Scalars', 'optionalStatus', 'DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ValueObject\PlainStatus', '', 'NOT_IN_SCOPE'],
                ['Scalars', 'createdAt', \DateTimeImmutable::class, '', 'NOT_IN_SCOPE'],
                ['Scalars', 'defaultStatus', 'DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ValueObject\PlainStatus', '', 'NOT_IN_SCOPE'],
            ],
            array_map(
                static fn (array $delegatedParameter): array => [
                    self::shortName($delegatedParameter['class']),
                    $delegatedParameter['parameter'],
                    $delegatedParameter['targetClass'],
                    $delegatedParameter['typeSuffix'],
                    $delegatedParameter['targetReason'],
                ],
                $result['delegatedParameters'],
            ),
        );
    }

    #[Test]
    public function fixtures_are_listed_as_text(): void
    {
        // -- Arrange
        putenv('COLUMNS=120');
        $commandTester = new CommandTester($this->commandForFixtures());

        // -- Act
        $exitCode = $commandTester->execute([]);

        // -- Assert
        self::assertSame(Command::SUCCESS, $exitCode);

        $display = $commandTester->getDisplay();
        self::assertStringContainsString('41 classes in scope (incl. 2 not relevant), 18 planned.', $display);
        self::assertStringContainsString(self::lines(
            'Skipped, as they can not be loaded',
            '----------------------------------',
            '',
            ' DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\NotLoadable\MissingParent',
            '   Error:  Class "DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\NotLoadable\DoesNotExist" not found',
        ), $display);
        // Grouped by reason, in the order of the reasons
        self::assertStringContainsString(self::lines(
            '1. Not relevant: never get a plan by design',
            '-------------------------------------------',
            '',
            ' enum (1)',
            sprintf('   %sKind', self::FIXTURE_NAMESPACE),
            '',
            ' abstract class (1)',
            sprintf('   %sBase', self::FIXTURE_NAMESPACE),
            '',
        ), $display);
        self::assertStringNotContainsString('SelfAwareKind', $display);
        self::assertStringNotContainsString('SelfAwareBase', $display);
        // One block per class, the class names within the reasons shortened
        self::assertStringContainsString(self::lines(
            '2. Falling back to the PropertyNormalizer',
            '-----------------------------------------',
            '',
            sprintf(' %sAdditionalParameter', self::FIXTURE_NAMESPACE),
            '   Falls back for:  denormalization and normalization',
            '   Reason:          parameter is not promoted: $unused',
            '',
            sprintf(' %sCollectionClass', self::FIXTURE_NAMESPACE),
            '   Falls back for:  denormalization and normalization',
            '   Reason:          collection is not an array: $names (ArrayObject<int, string>)',
        ), $display);
        self::assertStringContainsString(self::lines(
            sprintf(' %sInherited', self::FIXTURE_NAMESPACE),
            '   Falls back for:  denormalization and normalization',
            '   Reason:          property is inherited: $name (declared in Base)',
        ), $display);
        self::assertStringContainsString(self::lines(
            sprintf(' %sMutable', self::FIXTURE_NAMESPACE),
            '   Falls back for:  normalization',
            '   Reason:          property is mutable: $name',
        ), $display);
        self::assertStringNotContainsString('Accepted:', $display);
        // Grouped by the class of the value, the parameters with the full class names
        self::assertStringContainsString(self::lines(
            '3. Planned, with parameters (de)normalized through the serializer',
            '-----------------------------------------------------------------',
            '',
            // Sorted by the class of the value
            ' Countable',
            ' (not in the scope of the plan normalizer)',
            sprintf('   %sObjects::$countable', self::FIXTURE_NAMESPACE),
            '',
            ' DateTimeImmutable',
            ' (not in the scope of the plan normalizer)',
            sprintf('   %sNestedObjectCollections::$moments (DateTimeImmutable[][])', self::FIXTURE_NAMESPACE),
            sprintf('   %sObjects::$moments (DateTimeImmutable[])', self::FIXTURE_NAMESPACE),
            sprintf('   %sScalars::$createdAt', self::FIXTURE_NAMESPACE),
            '',
            // A self-aware value object only shows up as the class of the value of a delegated parameter
            ' DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\NameValue',
            ' (self-aware value object)',
            sprintf('   %sNestedValueObjects::$names (NameValue[][])', self::FIXTURE_NAMESPACE),
            '',
            ' DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\T',
            ' (not in the scope of the plan normalizer)',
            sprintf('   %sBox::$content', self::FIXTURE_NAMESPACE),
            '',
            ' DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Tree',
            ' (references itself (directly or through other classes))',
            sprintf('   %sGrove::$tree', self::FIXTURE_NAMESPACE),
            '',
            ' DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ValueObject\Count',
            ' (not in the scope of the plan normalizer)',
            sprintf('   %sNestedObjectCollections::$counts (Count[][])', self::FIXTURE_NAMESPACE),
            '',
            ' DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ValueObject\PlainStatus',
            ' (not in the scope of the plan normalizer)',
            sprintf('   %sScalars::$status', self::FIXTURE_NAMESPACE),
            sprintf('   %sScalars::$optionalStatus', self::FIXTURE_NAMESPACE),
            sprintf('   %sScalars::$defaultStatus', self::FIXTURE_NAMESPACE),
            '',
        ), $display);
    }

    #[Test]
    public function long_texts_are_wrapped_to_the_width_of_the_terminal(): void
    {
        // -- Arrange
        putenv('COLUMNS=80');
        $commandTester = new CommandTester($this->commandForFixtures([
            self::FIXTURE_NAMESPACE.'Tree' => 'References itself on purpose, as the tree is a recursive structure that the plan normalizer can not express',
        ]));

        // -- Act
        $commandTester->execute([]);

        // -- Assert
        self::assertStringContainsString(self::lines(
            sprintf(' %sTree', self::FIXTURE_NAMESPACE),
            '   Falls back for:  denormalization and normalization',
            '   Reason:          references itself (directly or through other classes)',
            '   Accepted:        References itself on purpose, as the tree is a recursive',
            '                    structure that the plan normalizer can not express',
            '',
        ), $commandTester->getDisplay());
    }

    #[Test]
    public function long_texts_keep_a_minimum_width_in_narrow_terminals(): void
    {
        // -- Arrange
        putenv('COLUMNS=20');
        $commandTester = new CommandTester($this->commandForFixtures([
            self::FIXTURE_NAMESPACE.'Tree' => 'References itself on purpose, as the tree is a recursive structure that the plan normalizer can not express',
        ]));

        // -- Act
        $commandTester->execute([]);

        // -- Assert
        self::assertStringContainsString(self::lines(
            '   Accepted:        References itself on purpose, as the',
            '                    tree is a recursive structure that the',
            '                    plan normalizer can not express',
            '',
        ), $commandTester->getDisplay());
    }

    #[Test]
    public function the_detail_of_a_type_that_can_not_be_resolved_keeps_the_full_class_names(): void
    {
        // -- Arrange
        putenv('COLUMNS=120');
        $planNormalizer = new PlanNormalizer(
            new PlanFactory(new ThrowingPropertyTypeExtractor(), '/\\\\Fixture\\\\ReadModel\\\\/', []),
            new PropertyNormalizer(),
            null,
            'test',
            [],
        );
        $commandTester = new CommandTester(new ListUnplannedClassesCommand(
            $planNormalizer,
            [dirname(__DIR__, 2).'/Test/PlanNormalizer/Fixture'],
            [],
        ));

        // -- Act
        $commandTester->execute([]);

        // -- Assert
        $display = self::normalizedDisplay($commandTester);
        // The message of the exception, in which e.g. a typo in a namespace must stay visible
        self::assertStringContainsString(sprintf('Reason: type is not resolvable: $name (The type of %s::$name must not be extracted.)', Leaf::class), $display);
        // Shortened for every other reason
        self::assertStringContainsString('Reason: property is inherited: $name (declared in Base)', $display);
    }

    /**
     * @return iterable<string, array{
     *   0: string,
     *   1: string,
     * }>
     */
    public static function textsWithClassNames(): iterable
    {
        yield 'qualified class name' => ['declared in App\\Foo\\Bar', 'declared in Bar'];
        yield 'fully qualified class name' => ['declared in \\App\\Foo\\Bar', 'declared in Bar'];
        yield 'fully qualified class name without namespace' => ['\\Countable&\\Traversable', 'Countable&Traversable'];
        yield 'generic type' => ['ArrayObject<int, App\\Foo\\Bar>', 'ArrayObject<int, Bar>'];
        yield 'short class name and parameter' => ['property is inherited: $name (declared in Base)', 'property is inherited: $name (declared in Base)'];
    }

    #[Test]
    #[DataProvider('textsWithClassNames')]
    public function class_names_within_reasons_are_shortened(
        string $text,
        string $expectedText,
    ): void {
        // -- Act
        $shortenedText = new \ReflectionMethod(ListUnplannedClassesCommand::class, 'withShortClassNames')->invoke(null, $text);

        // -- Assert
        self::assertSame($expectedText, $shortenedText);
    }

    #[Test]
    public function only_relevant_classes_are_listed(): void
    {
        // -- Arrange
        $commandTester = new CommandTester($this->commandForFixtures());

        // -- Act
        $exitCode = $commandTester->execute([
            '--only-relevant' => true,
        ]);

        // -- Assert
        self::assertSame(Command::SUCCESS, $exitCode);

        $display = self::normalizedDisplay($commandTester);
        self::assertStringNotContainsString('1. Not relevant', $display);
        self::assertStringNotContainsString('abstract class', $display);
        self::assertStringContainsString(sprintf('%sUnionParameter', self::FIXTURE_NAMESPACE), $display);
        // Only the parameter whose class could be put on the fast path is left
        self::assertStringContainsString(sprintf('%sGrove::$tree', self::FIXTURE_NAMESPACE), $display);
        self::assertStringNotContainsString('not in the scope of the plan normalizer', $display);
    }

    #[Test]
    public function sections_without_classes_are_listed_as_none(): void
    {
        // -- Arrange
        $commandTester = new CommandTester(new ListUnplannedClassesCommand(
            $this->planNormalizer(),
            [dirname(__DIR__, 2).'/Test/PlanNormalizer/Fixture/OutOfScope'],
            [],
        ));

        // -- Act
        $exitCode = $commandTester->execute([
            '--fail-on-unplanned' => true,
        ]);

        // -- Assert
        self::assertSame(Command::SUCCESS, $exitCode);

        $display = self::normalizedDisplay($commandTester);
        self::assertStringContainsString('0 classes in scope (incl. 0 not relevant), 0 planned.', $display);
        self::assertSame(4, substr_count($display, 'None.'));
    }

    #[Test]
    public function command_fails_on_unplanned_classes_that_are_not_accepted_when_requested(): void
    {
        // -- Arrange
        $commandTester = new CommandTester($this->commandForFixtures([
            self::FIXTURE_NAMESPACE.'Tree' => 'References itself',
        ]));

        // -- Act
        $exitCode = $commandTester->execute([
            '--fail-on-unplanned' => true,
        ]);

        // -- Assert
        self::assertSame(Command::FAILURE, $exitCode);

        $display = self::normalizedDisplay($commandTester);
        self::assertStringContainsString(sprintf('%sTree Falls back for: denormalization and normalization Reason: references itself (directly or through other classes) Accepted: References itself', self::FIXTURE_NAMESPACE), $display);
        // The error block wraps long class names, so it's compared without whitespace
        self::assertStringContainsString(self::withoutWhitespace(sprintf('%sUnionParameter falls back to the PropertyNormalizer. Put it on the fast path, or add it with the reason to the accepted unplanned classes', self::FIXTURE_NAMESPACE)), self::withoutWhitespace($display));
        self::assertStringNotContainsString(self::withoutWhitespace(sprintf('%sTree falls back', self::FIXTURE_NAMESPACE)), self::withoutWhitespace($display));
    }

    #[Test]
    public function command_succeeds_when_every_unplanned_class_is_accepted(): void
    {
        // -- Arrange
        $listingCommandTester = new CommandTester($this->commandForFixtures());
        $listingCommandTester->execute([
            '--format' => 'json',
        ]);
        $listing = json_decode($listingCommandTester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($listing);
        $acceptedUnplannedClasses = array_fill_keys(array_column($listing['fallingBackClasses'], 'class'), 'By design');

        $commandTester = new CommandTester($this->commandForFixtures($acceptedUnplannedClasses));

        // -- Act
        $exitCode = $commandTester->execute([
            '--fail-on-unplanned' => true,
            '--format' => 'json',
        ]);

        // -- Assert
        self::assertSame(Command::SUCCESS, $exitCode);

        $result = json_decode($commandTester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($result);
        self::assertSame(['By design'], array_values(array_unique(array_column($result['fallingBackClasses'], 'acceptedBecause'))));
        // Delegated parameters never fail
        self::assertNotSame([], $result['delegatedParameters']);
    }

    #[Test]
    public function command_fails_on_stale_accepted_classes_when_requested(): void
    {
        // -- Arrange
        $listingCommandTester = new CommandTester($this->commandForFixtures());
        $listingCommandTester->execute([
            '--format' => 'json',
        ]);
        $listing = json_decode($listingCommandTester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($listing);
        $acceptedUnplannedClasses = array_fill_keys(array_column($listing['fallingBackClasses'], 'class'), 'By design');
        // Planned
        $acceptedUnplannedClasses[Leaf::class] = 'By design';
        // Removed
        $acceptedUnplannedClasses[self::FIXTURE_NAMESPACE.'Removed'] = 'By design';

        $commandTester = new CommandTester($this->commandForFixtures($acceptedUnplannedClasses));

        // -- Act
        $exitCode = $commandTester->execute([
            '--fail-on-unplanned' => true,
        ]);

        // -- Assert
        self::assertSame(Command::FAILURE, $exitCode);

        // The error block wraps long class names, so it's compared without whitespace
        $display = self::withoutWhitespace($commandTester->getDisplay());
        self::assertStringContainsString(self::withoutWhitespace(sprintf('%s is an accepted unplanned class, but does not fall back anymore (planned or removed). Remove it from the accepted unplanned classes', Leaf::class)), $display);
        self::assertStringContainsString(self::withoutWhitespace(sprintf('%sRemoved is an accepted unplanned class, but does not fall back anymore', self::FIXTURE_NAMESPACE)), $display);
        self::assertStringNotContainsString(self::withoutWhitespace('falls back to the PropertyNormalizer. Put it'), $display);
    }

    #[Test]
    public function command_fails_with_an_invalid_format(): void
    {
        // -- Arrange
        $commandTester = new CommandTester($this->commandForFixtures());

        // -- Act
        $exitCode = $commandTester->execute([
            '--format' => 'xml',
        ]);

        // -- Assert
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('The format must be "text" or "json".', self::normalizedDisplay($commandTester));
    }

    #[Test]
    public function configured_command_lists_the_classes_like_the_plan_normalizer_handles_them(): void
    {
        // -- Arrange
        $kernel = self::bootKernel();
        $commandTester = new CommandTester(new Application($kernel)->find(ListUnplannedClassesCommand::NAME));
        $planNormalizer = $this->planNormalizer();

        // -- Act
        $exitCode = $commandTester->execute([
            '--format' => 'json',
        ]);

        // -- Assert
        self::assertSame(Command::SUCCESS, $exitCode, $commandTester->getDisplay());

        $result = json_decode($commandTester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($result);
        // The source directories of the configuration are the fixtures
        self::assertSame(18, $result['numberOfPlannedClasses']);

        foreach ($result['notRelevantClasses'] as $notRelevantClass) {
            self::assertFalse($planNormalizer->supportsDenormalization([], $notRelevantClass['class']), $notRelevantClass['class']);
        }
        foreach ($result['fallingBackClasses'] as $fallingBackClass) {
            self::assertSame(
                $fallingBackClass['fallsBackFor'] === 'normalization',
                $planNormalizer->supportsDenormalization([], $fallingBackClass['class']),
                $fallingBackClass['class'],
            );
        }
        foreach ($result['delegatedParameters'] as $delegatedParameter) {
            self::assertTrue($planNormalizer->supportsDenormalization([], $delegatedParameter['class']), $delegatedParameter['class']);
            self::assertFalse($planNormalizer->supportsDenormalization([], $delegatedParameter['targetClass']), $delegatedParameter['targetClass']);
        }
    }

    // -- Helpers

    /**
     * @param array<string, string> $acceptedUnplannedClasses
     */
    private function commandForFixtures(array $acceptedUnplannedClasses = []): ListUnplannedClassesCommand
    {
        return new ListUnplannedClassesCommand(
            $this->planNormalizer(),
            [dirname(__DIR__, 2).'/Test/PlanNormalizer/Fixture'],
            $acceptedUnplannedClasses,
        );
    }

    private function planNormalizer(): PlanNormalizer
    {
        // Boots the kernel unless it's already booted
        $serializer = self::getContainer()->get('serializer');
        self::assertInstanceOf(DenormalizerInterface::class, $serializer);

        return SerializerComparison::fromSerializer($serializer)->planNormalizer;
    }

    private static function lines(string ...$lines): string
    {
        return implode(PHP_EOL, $lines);
    }

    private static function shortName(string $class): string
    {
        return str_replace(self::FIXTURE_NAMESPACE, '', $class);
    }

    /**
     * The display with the whitespace collapsed, so wrapped texts (e.g. of the error messages) can be asserted as one line.
     */
    private static function normalizedDisplay(CommandTester $commandTester): string
    {
        return (string) preg_replace('/\s+/', ' ', $commandTester->getDisplay());
    }

    private static function withoutWhitespace(string $text): string
    {
        return (string) preg_replace('/\s+/', '', $text);
    }
}
