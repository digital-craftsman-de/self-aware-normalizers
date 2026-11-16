<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Testing\PlanNormalizer;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\StringNormalizable;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\CompositeTypeInterface;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\ReflectionTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

/**
 * Builds instances of arbitrary classes for PlanNormalizerEquivalenceTestCase: every constructor parameter gets a value of its type
 * (element types of collections from the PHPDoc), value objects and nested classes are built the same way, recursively. Constructor
 * validation still runs, so the generated values follow a few rules to be valid for most value objects:
 * - Samples of the SampleProviders of the app come first (e.g. value objects of dates whose start must be before their end, ids, files).
 * - Dates (\DateTimeImmutable) increase with every value, so the start of a range is before its end.
 * - Strings depend on the name of the parameter (email address, phone number, postal code, currency, ...), unless a SampleProvider
 *   returns one.
 * - An abstract class or interface is built through the first class of the source implementing it that can be built.
 * - Several strategies (int value, number of elements of a collection) are tried until the class can be built.
 *
 * Two variants: with every nullable value set, and with every nullable value null (except inside collections).
 */
final class SampleFactory
{
    private const int MAXIMUM_DEPTH = 12;

    /**
     * Tried in this order (each int with each number of elements) until the class can be built.
     */
    private const array INTS = [1, 0, 50, 60];
    private const array NUMBERS_OF_ELEMENTS = [2, 1, 0];
    private const array BOOLS = [true, false];
    private const array ARE_FIRST_ENUM_CASES = [true, false];

    private readonly ReflectionTypeResolver $reflectionTypeResolver;
    private readonly StringTypeResolver $stringTypeResolver;
    private readonly TypeContextFactory $typeContextFactory;

    private int $int = 1;
    private int $numberOfElements = 2;
    private int $sequence = 0;
    private bool $bool = true;
    private bool $isFirstEnumCase = true;
    private int $nullableValueCount = 0;
    /**
     * Which nullable values are null: all (in the null variant) / none (in the filled variant), or every other one (0 or 1).
     */
    private ?int $nullPattern = null;
    /**
     * Types of the constructor parameters by class and parameter, resolved once: every strategy of create() builds the same classes again.
     *
     * @var array<string, ?Type>
     */
    private array $typesOfParameters = [];

    /**
     * @param list<class-string>                      $classesOfSource  To find the implementations of abstract classes and interfaces
     * @param array<class-string, \Closure(): object> $samplesOfClasses Classes whose constructor validation needs a combination of values
     * @param list<SampleProvider>                    $sampleProviders  Samples of classes and strings of the app, asked in this order
     */
    public function __construct(
        private readonly PropertyTypeExtractorInterface $propertyTypeExtractor,
        private readonly array $classesOfSource,
        private readonly array $samplesOfClasses,
        private readonly array $sampleProviders = [],
    ) {
        $this->reflectionTypeResolver = new ReflectionTypeResolver();
        $this->stringTypeResolver = new StringTypeResolver();
        // Without a string type resolver, like AbstractObjectNormalizer::denormalizeParameter() (with one, it parses the type aliases)
        $this->typeContextFactory = new TypeContextFactory();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws \Throwable when the class can't be built
     */
    public function create(
        string $class,
        bool $areNullableValuesNull,
    ): object {
        $lastException = null;
        // Some classes need some nullable values null and others set (e.g. a time range only when a flag is set): every other one is
        // tried after all or none
        foreach ([null, 1, 0] as $nullPattern) {
            foreach (self::ARE_FIRST_ENUM_CASES as $isFirstEnumCase) {
                foreach (self::BOOLS as $bool) {
                    foreach (self::INTS as $int) {
                        foreach (self::NUMBERS_OF_ELEMENTS as $numberOfElements) {
                            $this->isFirstEnumCase = $isFirstEnumCase;
                            $this->int = $int;
                            $this->bool = $bool;
                            $this->numberOfElements = $numberOfElements;
                            $this->sequence = 0;
                            $this->nullableValueCount = 0;
                            $this->nullPattern = $nullPattern;
                            try {
                                /** @var T */
                                return $this->createObject($class, $areNullableValuesNull, 0, '', null);
                            } catch (\Throwable $exception) {
                                $lastException ??= $exception;
                            }
                        }
                    }
                }
            }
        }

        /**
         * @var \Throwable $lastException There is at least one strategy
         */
        throw $lastException;
    }

    /**
     * @param class-string      $class
     * @param string            $name             Name of the parameter the object is built for (empty for the class under test)
     * @param class-string|null $classOfParameter Class whose constructor the parameter belongs to (null for the class under test)
     */
    private function createObject(
        string $class,
        bool $areNullableValuesNull,
        int $depth,
        string $name,
        ?string $classOfParameter,
    ): object {
        if ($depth > self::MAXIMUM_DEPTH) {
            throw new \RuntimeException(sprintf('Maximum depth reached for %s', $class));
        }

        $reflectionClass = new \ReflectionClass($class);

        if (isset($this->samplesOfClasses[$class])) {
            return ($this->samplesOfClasses[$class])();
        }

        $sample = $this->sample($class, $name, $classOfParameter, $areNullableValuesNull, $depth);
        if ($sample !== null) {
            return $sample;
        }

        if (enum_exists($class)) {
            $cases = $class::cases();

            return $this->isFirstEnumCase
                ? $cases[0]
                : $cases[count($cases) - 1];
        }

        if (!$reflectionClass->isInstantiable()) {
            // E.g. a value object with a private constructor
            if (!$reflectionClass->isAbstract()
                && !$reflectionClass->isInterface()
                && is_subclass_of($class, StringNormalizable::class)
            ) {
                return $class::denormalize($this->string($name));
            }

            return $this->createImplementation($reflectionClass->name, $areNullableValuesNull, $depth, $name, $classOfParameter);
        }

        $arguments = [];
        foreach ($reflectionClass->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->isVariadic()) {
                break;
            }

            $key = sprintf('%s::%s', $reflectionClass->name, $parameter->name);
            if (!array_key_exists($key, $this->typesOfParameters)) {
                $this->typesOfParameters[$key] = $this->typeOfParameter($reflectionClass, $parameter);
            }
            $type = $this->typesOfParameters[$key];
            $arguments[] = $type !== null
                ? $this->valueOfType($type, $areNullableValuesNull, $depth + 1, $parameter->name, $reflectionClass->name)
                : null;
        }

        return $reflectionClass->newInstanceArgs($arguments);
    }

    /**
     * @param class-string      $class
     * @param class-string|null $classOfParameter
     */
    private function createImplementation(
        string $class,
        bool $areNullableValuesNull,
        int $depth,
        string $name,
        ?string $classOfParameter,
    ): object {
        $lastException = null;
        foreach ($this->classesOfSource as $candidate) {
            if (!is_subclass_of($candidate, $class)
                || !new \ReflectionClass($candidate)->isInstantiable()
            ) {
                continue;
            }

            try {
                return $this->createObject($candidate, $areNullableValuesNull, $depth, $name, $classOfParameter);
            } catch (\Throwable $exception) {
                $lastException ??= $exception;
            }
        }

        throw $lastException ?? new \RuntimeException(sprintf('%s has no implementation', $class));
    }

    /**
     * @param class-string      $class
     * @param class-string|null $classOfParameter
     */
    private function sample(
        string $class,
        string $name,
        ?string $classOfParameter,
        bool $areNullableValuesNull,
        int $depth,
    ): ?object {
        $sequence = ++$this->sequence;

        if ($this->sampleProviders !== []) {
            $context = new SampleContext(
                $sequence,
                $this->int,
                $this->bool,
                $this->numberOfElements,
                $classOfParameter,
                fn (string $otherClass, string $otherName, ?string $otherClassOfParameter): object => $this->createObject($otherClass, $areNullableValuesNull, $depth + 1, $otherName, $otherClassOfParameter),
            );
            foreach ($this->sampleProviders as $sampleProvider) {
                $sample = $sampleProvider->sample($class, $name, $context);
                if ($sample !== null) {
                    return $sample;
                }
            }
        }

        return match ($class) {
            // Increasing, so the start of a range is before its end
            \DateTimeImmutable::class => new \DateTimeImmutable('2027-01-01 10:30:00.123456')->modify(sprintf('+%d hours', $sequence)),
            \DateTimeZone::class => new \DateTimeZone('Europe/Berlin'),
            // E.g. the stream of a file generated while it's streamed. A closure has no state to (de)normalize, so both normalizers must
            // fail the same way (the plan delegates it to the serializer like any other object)
            \Closure::class => static function (): void {},
            default => null,
        };
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     */
    private function typeOfParameter(
        \ReflectionClass $reflectionClass,
        \ReflectionParameter $parameter,
    ): ?Type {
        if ($parameter->isPromoted()) {
            $type = $this->propertyTypeExtractor->getType($reflectionClass->name, $parameter->name);
            if ($type !== null) {
                return $type;
            }
        }

        $docComment = $parameter->getDeclaringFunction()->getDocComment();
        if ($docComment !== false
            && preg_match(sprintf('/@param\s+((?:(?!@param).)+?)\s+\$%s\b/s', preg_quote($parameter->name, '/')), $docComment, $match) === 1
        ) {
            try {
                return $this->stringTypeResolver->resolve(
                    $this->withTemplatesOfParentResolved((string) preg_replace('/\s*\*\s*/', ' ', $match[1]), $reflectionClass, $parameter),
                    $this->typeContextFactory->createFromClassName($reflectionClass->name, $parameter->getDeclaringClass()?->name),
                );
            } catch (\Throwable) {
                // Falls back to the native type
            }
        }

        $parameterType = $parameter->getType();

        return $parameterType !== null
            ? $this->reflectionTypeResolver->resolve(
                $parameterType,
                $this->typeContextFactory->createFromClassName($reflectionClass->name),
            )
            : null;
    }

    /**
     * Replaces the template types of a constructor of a parent class (e.g. T of IdentifiableCollection) with the classes given through
     * the @extends annotation of the class.
     *
     * @param \ReflectionClass<object> $reflectionClass
     */
    private function withTemplatesOfParentResolved(
        string $type,
        \ReflectionClass $reflectionClass,
        \ReflectionParameter $parameter,
    ): string {
        $declaringClass = $parameter->getDeclaringClass();
        if ($declaringClass === null
            || $declaringClass->name === $reflectionClass->name
            || preg_match('/@template\s+(\w+)/', (string) $declaringClass->getDocComment(), $templateMatch) !== 1
            || preg_match(sprintf('/@extends\s+%s<([^>]+)>/', preg_quote($declaringClass->getShortName(), '/')), (string) $reflectionClass->getDocComment(), $extendsMatch) !== 1
        ) {
            return $type;
        }

        $templateType = $this->stringTypeResolver->resolve(
            trim($extendsMatch[1]),
            $this->typeContextFactory->createFromClassName($reflectionClass->name),
        );

        return (string) preg_replace(sprintf('/\b%s\b/', $templateMatch[1]), '\\'.ltrim((string) $templateType, '\\'), $type);
    }

    /**
     * @param class-string $classOfParameter
     */
    private function valueOfType(
        Type $type,
        bool $areNullableValuesNull,
        int $depth,
        string $name,
        string $classOfParameter,
    ): mixed {
        if ($type->isNullable()) {
            ++$this->nullableValueCount;
            $isNull = $this->nullPattern === null
                ? $areNullableValuesNull
                : $this->nullPattern === $this->nullableValueCount % 2;
            if ($isNull) {
                return null;
            }
        }

        if ($type instanceof NullableType) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof CollectionType) {
            $isListOfIntKeys = $type->getCollectionKeyType()->isIdentifiedBy(TypeIdentifier::INT);
            $elements = [];
            for ($i = 0; $i < $this->numberOfElements; ++$i) {
                $element = $this->valueOfType($type->getCollectionValueType(), false, $depth + 1, $name, $classOfParameter);
                if ($isListOfIntKeys) {
                    $elements[] = $element;
                } else {
                    // Maps with string keys are mostly keyed by date
                    $elements[new \DateTimeImmutable('2026-11-15')->modify(sprintf('+%d days', $i))->format('Y-m-d')] = $element;
                }
            }

            return $elements;
        }

        if ($type instanceof CompositeTypeInterface) {
            foreach ($type->getTypes() as $memberType) {
                if (!$memberType->isIdentifiedBy(TypeIdentifier::NULL)) {
                    return $this->valueOfType($memberType, $areNullableValuesNull, $depth, $name, $classOfParameter);
                }
            }
        }

        while ($type instanceof WrappingTypeInterface) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof ObjectType) {
            // An unresolved template type
            if (!class_exists($type->getClassName())
                && !interface_exists($type->getClassName())
            ) {
                return 'mixed value';
            }

            return $this->createObject($type->getClassName(), $areNullableValuesNull, $depth, $name, $classOfParameter);
        }

        if ($type instanceof BuiltinType) {
            return match ($type->getTypeIdentifier()) {
                TypeIdentifier::STRING => $this->string($name),
                TypeIdentifier::INT => $this->int,
                TypeIdentifier::FLOAT => 2.5,
                TypeIdentifier::BOOL => $this->bool,
                TypeIdentifier::TRUE => true,
                TypeIdentifier::FALSE => false,
                TypeIdentifier::ARRAY, TypeIdentifier::ITERABLE => [],
                TypeIdentifier::MIXED => 'mixed value',
                default => throw new \RuntimeException(sprintf('No sample for type %s', (string) $type)),
            };
        }

        throw new \RuntimeException(sprintf('No sample for type %s', (string) $type));
    }

    private function string(string $name): string
    {
        foreach ($this->sampleProviders as $sampleProvider) {
            $string = $sampleProvider->string($name);
            if ($string !== null) {
                return $string;
            }
        }

        $name = strtolower($name);

        return match (true) {
            str_contains($name, 'email') => 'info@example.com',
            str_contains($name, 'phone') => '+4915112345678',
            str_contains($name, 'postal'),
            str_contains($name, 'zip') => '12345',
            str_contains($name, 'currency') => 'EUR',
            str_contains($name, 'country') => 'DE',
            str_contains($name, 'locale') => 'de-DE',
            str_contains($name, 'language') => 'de',
            str_contains($name, 'iban') => 'DE89370400440532013000',
            str_contains($name, 'bic') => 'COBADEFFXXX',
            str_contains($name, 'url'),
            str_contains($name, 'link') => 'https://www.example.com',
            str_contains($name, 'color') => '#aabbcc',
            str_contains($name, 'timezone') => 'Europe/Berlin',
            str_contains($name, 'code') => 'example',
            str_contains($name, 'token') => '1f2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d',
            // Labels of options must be unique
            str_contains($name, 'label') => sprintf('Option %d', random_int(1, PHP_INT_MAX)),
            default => 'Example',
        };
    }
}
