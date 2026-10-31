<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

use DigitalCraftsman\SelfAwareNormalizers\Test\ConstructFinder\AbstractReadonlyClass;
use DigitalCraftsman\SelfAwareNormalizers\Test\ConstructFinder\ClassWithCommentBeforeName;
use DigitalCraftsman\SelfAwareNormalizers\Test\ConstructFinder\FinalClass;
use DigitalCraftsman\SelfAwareNormalizers\Test\ConstructFinder\Nested\ClassInFirstNamespace;
use DigitalCraftsman\SelfAwareNormalizers\Test\ConstructFinder\Nested\Deeper\DeeplyNestedClass;
use DigitalCraftsman\SelfAwareNormalizers\Test\ConstructFinder\Nested\UnitEnum;
use DigitalCraftsman\SelfAwareNormalizers\Test\ConstructFinder\StringEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConstructFinder::class)]
final class ConstructFinderTest extends TestCase
{
    private const string FIXTURE_DIRECTORY = __DIR__.'/../Test/ConstructFinder';

    #[Test]
    public function find_class_names_finds_all_declared_classes_recursively(): void
    {
        // -- Act
        $classNames = ConstructFinder::findClassNames(self::FIXTURE_DIRECTORY);

        // -- Assert
        self::assertEqualsCanonicalizing([
            AbstractReadonlyClass::class,
            ClassWithCommentBeforeName::class,
            FinalClass::class,
            ClassInFirstNamespace::class,
            'ConstructFinderClassInGlobalNamespace',
            'ConstructFinderSingleSegmentNamespace\ClassInSingleSegmentNamespace',
            DeeplyNestedClass::class,
        ], $classNames);
    }

    #[Test]
    public function find_enum_names_finds_all_declared_enums_recursively(): void
    {
        // -- Act
        $enumNames = ConstructFinder::findEnumNames(self::FIXTURE_DIRECTORY);

        // -- Assert
        self::assertEqualsCanonicalizing([
            StringEnum::class,
            UnitEnum::class,
        ], $enumNames);
    }

    #[Test]
    public function find_class_names_finds_nothing_in_empty_directory(): void
    {
        // -- Act
        $classNames = ConstructFinder::findClassNames(self::FIXTURE_DIRECTORY.'/Empty');

        // -- Assert
        self::assertSame([], $classNames);
    }
}
