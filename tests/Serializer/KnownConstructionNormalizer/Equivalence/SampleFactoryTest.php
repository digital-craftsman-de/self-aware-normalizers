<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Equivalence;

use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Equivalence\SampleContext;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Equivalence\SampleFactory;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Equivalence\SampleProvider;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ElementType;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ImageElement;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Page;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\Sample\ElementImplementation;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\Sample\Gallery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

require_once dirname(__DIR__, 3).'/Test/KnownConstructionNormalizer/Fixture/ReadModel/fixtures.php';

final class SampleFactoryTest extends TestCase
{
    #[Test]
    public function providers_get_the_class_the_parameter_belongs_to(): void
    {
        // -- Arrange
        $requests = [];
        $sampleFactory = self::sampleFactory(self::provider($requests));

        // -- Act
        $page = $sampleFactory->create(Page::class, false);

        // -- Assert
        self::assertInstanceOf(Page::class, $page);
        self::assertNotSame([], $page->elements);
        self::assertSame(ElementType::IMAGE, $page->cover?->type);
        // The class under test itself has no class of a parameter, the elements of the list and the nullable object belong to Page
        self::assertContains([Page::class, '', null], $requests);
        self::assertContains([ImageElement::class, 'elements', Page::class], $requests);
        self::assertContains([ImageElement::class, 'cover', Page::class], $requests);
        self::assertContains([ElementType::class, 'type', ImageElement::class], $requests);
    }

    #[Test]
    public function providers_get_the_implementation_built_for_an_interface_as_class_of_its_parameters(): void
    {
        // -- Arrange
        $requests = [];
        $sampleFactory = self::sampleFactory(self::provider($requests));

        // -- Act
        $gallery = $sampleFactory->create(Gallery::class, false);

        // -- Assert
        self::assertInstanceOf(Gallery::class, $gallery);
        self::assertContainsOnlyInstancesOf(ElementImplementation::class, $gallery->elements);
        self::assertContains([ImageElement::class, 'image', ElementImplementation::class], $requests);
        self::assertContains([ElementType::class, 'type', ImageElement::class], $requests);
    }

    #[Test]
    public function without_provider_the_first_or_last_enum_case_is_used(): void
    {
        // -- Arrange
        $sampleFactory = self::sampleFactory();

        // -- Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('An image element must be of type IMAGE.');

        // -- Act
        $sampleFactory->create(ImageElement::class, false);
    }

    #[Test]
    public function samples_of_other_classes_are_built_with_the_class_of_their_parameter(): void
    {
        // -- Arrange
        $requests = [];
        $recordingProvider = self::provider($requests);
        $sampleFactory = self::sampleFactory(
            new class($recordingProvider) implements SampleProvider {
                public function __construct(
                    private readonly SampleProvider $recordingProvider,
                ) {
                }

                #[\Override]
                public function sample(
                    string $class,
                    string $parameterName,
                    SampleContext $context,
                ): ?object {
                    return $class === Page::class
                        ? new Page([], $context->sampleOf(ImageElement::class, 'cover', Page::class))
                        : null;
                }

                #[\Override]
                public function string(string $parameterName): ?string
                {
                    return null;
                }
            },
            $recordingProvider,
        );

        // -- Act
        $page = $sampleFactory->create(Page::class, false);

        // -- Assert
        self::assertInstanceOf(Page::class, $page);
        self::assertSame(ElementType::IMAGE, $page->cover?->type);
        self::assertContains([ImageElement::class, 'cover', Page::class], $requests);
    }

    // -- Helpers

    private static function sampleFactory(SampleProvider ...$sampleProviders): SampleFactory
    {
        return new SampleFactory(
            new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]),
            [ElementImplementation::class, Gallery::class, ImageElement::class, Page::class],
            [],
            array_values($sampleProviders),
        );
    }

    /**
     * Records every request (class, name of the parameter, class of the parameter) and picks the case IMAGE for the type of an image
     * element.
     *
     * @param list<array{0: string, 1: string, 2: ?string}> $requests
     */
    private static function provider(array &$requests): SampleProvider
    {
        return new class($requests) implements SampleProvider {
            /**
             * @param list<array{0: string, 1: string, 2: ?string}> $requests
             */
            public function __construct(
                private array &$requests,
            ) {
            }

            #[\Override]
            public function sample(
                string $class,
                string $parameterName,
                SampleContext $context,
            ): ?object {
                $this->requests[] = [$class, $parameterName, $context->classOfParameter];

                return $class === ElementType::class
                    && $context->classOfParameter === ImageElement::class
                        ? ElementType::IMAGE
                        : null;
            }

            #[\Override]
            public function string(string $parameterName): ?string
            {
                return null;
            }
        };
    }
}
