<?php

declare(strict_types=1);

/**
 * Fixture classes of KnownConstructionNormalizerTest, ListUnplannedClassesCommandTest and KnownConstructionNormalizerEquivalenceTest, one per shape the
 * KnownConstructionNormalizer supports or doesn't support. They are in a ReadModel namespace, so the KnownConstructionNormalizer of the TestKernel is responsible
 * for them. One file, loaded by the tests, as the classes are only meaningful together.
 */

namespace BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject {
    use BuronDigital\SelfAwareNormalizers\Serializer\ArrayNormalizable;
    use BuronDigital\SelfAwareNormalizers\Serializer\BoolNormalizable;
    use BuronDigital\SelfAwareNormalizers\Serializer\FloatNormalizable;
    use BuronDigital\SelfAwareNormalizers\Serializer\IntNormalizable;
    use BuronDigital\SelfAwareNormalizers\Serializer\StringNormalizable;
    use Symfony\Component\Serializer\Exception\ExtraAttributesException;
    use Symfony\Component\Serializer\Exception\InvalidArgumentException;
    use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
    use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
    use Symfony\Component\Serializer\Exception\UnsupportedException;

    final readonly class Count implements IntNormalizable
    {
        public function __construct(
            public int $value,
        ) {
            if ($value < 0) {
                throw new \InvalidArgumentException('A count must not be negative.');
            }
        }

        #[\Override]
        public static function denormalize(int $data): self
        {
            return new self($data);
        }

        #[\Override]
        public function normalize(): int
        {
            return $this->value;
        }
    }

    final readonly class Weight implements FloatNormalizable
    {
        public function __construct(
            public float $value,
        ) {
        }

        #[\Override]
        public static function denormalize(float $data): self
        {
            return new self($data);
        }

        #[\Override]
        public function normalize(): float
        {
            return $this->value;
        }
    }

    final readonly class Flag implements BoolNormalizable
    {
        public function __construct(
            public bool $value,
        ) {
        }

        #[\Override]
        public static function denormalize(bool $data): self
        {
            return new self($data);
        }

        #[\Override]
        public function normalize(): bool
        {
            return $this->value;
        }
    }

    /**
     * Throws exceptions of the serializer, which the PropertyNormalizer handles differently for a nullable (union) type.
     */
    final readonly class StrictName implements StringNormalizable
    {
        public function __construct(
            public string $value,
        ) {
        }

        #[\Override]
        public static function denormalize(string $data): self
        {
            return match ($data) {
                'unsupported' => throw new UnsupportedException('The name is not supported.'),
                'not normalizable' => throw new NotNormalizableValueException('The name is not normalizable.'),
                'invalid argument' => throw new InvalidArgumentException('The name is an invalid argument.'),
                'missing arguments' => throw new MissingConstructorArgumentsException('The name misses arguments.'),
                'extra attributes' => throw new ExtraAttributesException(['extra']),
                default => new self($data),
            };
        }

        #[\Override]
        public function normalize(): string
        {
            return $this->value;
        }
    }

    /**
     * @psalm-type NormalizedPoint = array{
     *   x: int,
     *   y: int,
     * }
     */
    final readonly class Point implements ArrayNormalizable
    {
        public function __construct(
            public int $x,
            public int $y,
        ) {
        }

        /**
         * @param NormalizedPoint $data
         */
        #[\Override]
        public static function denormalize(array $data): self
        {
            return new self($data['x'], $data['y']);
        }

        /**
         * @return NormalizedPoint
         */
        #[\Override]
        public function normalize(): array
        {
            return [
                'x' => $this->x,
                'y' => $this->y,
            ];
        }
    }

    enum PlainStatus: string
    {
        case ACTIVE = 'ACTIVE';
        case ARCHIVED = 'ARCHIVED';
    }
}

namespace BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel {
    use BuronDigital\SelfAwareNormalizers\Serializer\StringNormalizable;
    use BuronDigital\SelfAwareNormalizers\Serializer\StringNormalizableEnumTrait;
    use BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\Other\Settings;
    use BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\Count;
    use BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\Flag;
    use BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\PlainStatus;
    use BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\Point;
    use BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\StrictName;
    use BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\Weight;

    // -- Supported

    final readonly class Scalars
    {
        /**
         * @param array<string, mixed> $map
         * @param list<int|string>     $mixedList
         */
        public function __construct(
            public string $name,
            public int $count,
            public float $amount,
            public bool $isActive,
            public mixed $anything,
            public ?string $comment,
            public array $map,
            public array $mixedList,
            public PlainStatus $status,
            public ?PlainStatus $optionalStatus,
            public \DateTimeImmutable $createdAt,
            public PlainStatus $defaultStatus = PlainStatus::ACTIVE,
            public ?string $withDefault = 'Example',
        ) {
        }
    }

    final readonly class ScalarLists
    {
        /**
         * @param list<bool>         $flags
         * @param list<list<int>>    $matrix
         * @param list<?list<float>> $amounts
         * @param list<?string>      $optionalNames
         */
        public function __construct(
            public array $flags,
            public array $matrix,
            public array $amounts,
            public array $optionalNames,
        ) {
        }
    }

    final readonly class ValueObjects
    {
        /**
         * @param list<Count>               $counts
         * @param array<string, Flag>       $flagsByKey
         * @param array<int|string, Weight> $weightsByAnyKey
         */
        public function __construct(
            public Count $count,
            public Weight $weight,
            public Flag $flag,
            public ?Weight $optionalWeight,
            public array $counts,
            public array $flagsByKey,
            public array $weightsByAnyKey,
        ) {
        }
    }

    final readonly class Leaf
    {
        public function __construct(
            public string $name,
        ) {
        }
    }

    /**
     * References itself, so it isn't planned.
     */
    final readonly class Tree
    {
        /**
         * @param list<Tree>              $children
         * @param array<string, Leaf>     $leavesByKey
         * @param array<int|string, Leaf> $leavesByAnyKey
         */
        public function __construct(
            public string $name,
            public ?self $parent,
            public Leaf $leaf,
            public array $children,
            public array $leavesByKey,
            public array $leavesByAnyKey,
        ) {
        }
    }

    /**
     * Lists of planned classes, and a class that isn't planned as it references itself.
     */
    final readonly class Grove
    {
        /**
         * @param list<Leaf>              $leaves
         * @param array<string, Leaf>     $leavesByKey
         * @param array<int|string, Leaf> $leavesByAnyKey
         */
        public function __construct(
            public string $name,
            public Leaf $leaf,
            public array $leaves,
            public array $leavesByKey,
            public array $leavesByAnyKey,
            public ?Tree $tree,
        ) {
        }
    }

    /**
     * Nested collections of objects: of a planned class built directly level by level (with the key checks the ArrayDenormalizer applies
     * per level, also behind a nullable inner level, and of a generic class), of value objects and of other classes through the
     * serializer. Nested collections of anything else are only checked to be an array, like by the PropertyNormalizer.
     */
    final readonly class NestedObjectCollections
    {
        /**
         * @param list<list<Leaf>>                 $leaves
         * @param array<string, list<Leaf>>        $leavesByKey
         * @param list<array<string, Leaf>>        $keyedLeaves
         * @param list<array<int|string, Leaf>>    $leavesByAnyKey
         * @param list<list<list<Leaf>>>           $cube
         * @param list<list<?Leaf>>                $leavesWithGaps
         * @param list<?list<Leaf>>                $optionalLeaves
         * @param array<string, ?list<Leaf>>       $optionalLeavesByKey
         * @param list<?array<string, list<Leaf>>> $optionalCube
         * @param list<?list<array<string, Leaf>>> $optionalKeyedCube
         * @param list<list<Count>>                $counts
         * @param list<list<\DateTimeImmutable>>   $moments
         * @param list<list<Generic<Leaf>>>        $generics
         * @param list<list<mixed>>                $anythings
         */
        public function __construct(
            public array $leaves,
            public array $leavesByKey,
            public array $keyedLeaves,
            public array $leavesByAnyKey,
            public array $cube,
            public array $leavesWithGaps,
            public array $optionalLeaves,
            public array $optionalLeavesByKey,
            public array $optionalCube,
            public array $optionalKeyedCube,
            public array $counts,
            public array $moments,
            public array $generics,
            public array $anythings,
        ) {
        }
    }

    /**
     * Planned, with a nested collection of self-aware value objects, which goes through the serializer: listed as delegated parameter by
     * self-aware-normalizers:known-construction-normalizer:list-unplanned, although the value object itself is never listed as class.
     */
    final readonly class NestedValueObjects
    {
        /**
         * @param list<list<NameValue>> $names
         */
        public function __construct(
            public array $names,
        ) {
        }
    }

    /**
     * References itself through CycleBack, so neither is planned.
     */
    final readonly class Cycle
    {
        public function __construct(
            public string $name,
            public ?CycleBack $back,
        ) {
        }
    }

    final readonly class CycleBack
    {
        public function __construct(
            public ?Cycle $cycle,
        ) {
        }
    }

    final readonly class WithStrictName
    {
        public function __construct(
            public ?StrictName $name,
            public StrictName $otherName,
        ) {
        }
    }

    final readonly class Objects
    {
        /**
         * @param list<\DateTimeImmutable> $moments
         */
        public function __construct(
            public \Countable $countable,
            public array $moments,
        ) {
        }
    }

    /**
     * Objects the serializer denormalizes, keyed: the keys are checked by the ArrayDenormalizer.
     */
    final readonly class ObjectsByKey
    {
        /**
         * @param array<string, \DateTimeImmutable> $momentsByKey
         */
        public function __construct(
            public array $momentsByKey,
        ) {
        }
    }

    /**
     * The PHPDoc type doesn't match the native type, the native type wins (like for the PropertyNormalizer).
     */
    final readonly class TypeMismatch
    {
        /**
         * @param int $amount
         */
        public function __construct(
            public float $amount,
        ) {
        }
    }

    /**
     * Not read only, so it's only denormalized through the plan.
     */
    final class Mutable
    {
        public function __construct(
            public string $name,
        ) {
        }
    }

    /**
     * Traversable, so the serializer normalizes it element by element (as a list) instead of through an object normalizer. Only
     * denormalized through the plan.
     *
     * @implements \IteratorAggregate<int, Leaf>
     */
    final readonly class Signatories implements \IteratorAggregate, \Countable
    {
        /**
         * @param list<Leaf> $signatories
         */
        public function __construct(
            public array $signatories,
        ) {
        }

        #[\Override]
        public function getIterator(): \Iterator
        {
            return new \ArrayIterator($this->signatories);
        }

        #[\Override]
        public function count(): int
        {
            return count($this->signatories);
        }
    }

    /**
     * Traversable classes nested in a planned class.
     */
    final readonly class SignedContract
    {
        /**
         * @param list<Signatories> $signatoriesOfAmendments
         */
        public function __construct(
            public string $name,
            public Signatories $signatories,
            public ?Signatories $optionalSignatories,
            public array $signatoriesOfAmendments,
        ) {
        }
    }

    final readonly class Optional
    {
        public function __construct(
            public ?string $comment,
            public mixed $anything,
        ) {
        }
    }

    /**
     * The constructor throws a TypeError for a string, which the plan accepts as mixed.
     */
    final readonly class ConstructorTypeError
    {
        public function __construct(
            public mixed $value,
        ) {
            if (is_string($this->value)) {
                /** @psalm-suppress InvalidArgument On purpose */
                (static fn (int $number): int => $number)($this->value);
            }
        }
    }

    /**
     * @template T of Leaf
     */
    final readonly class Generic
    {
        /**
         * @param T $leaf
         */
        public function __construct(
            public Leaf $leaf,
        ) {
        }
    }

    /**
     * @template T
     */
    final readonly class Box
    {
        /**
         * @param T $content
         */
        public function __construct(
            public mixed $content,
        ) {
        }
    }

    final readonly class Boxed
    {
        /**
         * @param Box<Leaf> $box
         */
        public function __construct(
            public Box $box,
        ) {
        }
    }

    /**
     * Value objects normalized to an array, and lists of value objects normalized to arrays and strings.
     */
    final readonly class Located
    {
        /**
         * @param list<Point>      $points
         * @param list<StrictName> $names
         */
        public function __construct(
            public Point $point,
            public ?Point $optionalPoint,
            public array $points,
            public array $names,
        ) {
        }
    }

    /**
     * A nullable class out of the scope, which an object normalizer would build from a scalar, unless it's denormalized as member of
     * a union type (like the PropertyNormalizer does for a nullable type).
     */
    final readonly class WithSettings
    {
        /**
         * The tree is a class that could be put on the fast path, after a class that never gets a plan (both are listed as delegated
         * parameters by the list command).
         */
        public function __construct(
            public ?Settings $settings,
            public ?Tree $tree,
        ) {
        }
    }

    /**
     * Collections whose inner key type differs from the outer one, and keys in another order than int|string.
     */
    final readonly class KeyedCollections
    {
        /**
         * @param list<array<string, Count>> $countsByKey
         * @param array<string|int, Leaf>    $leavesByAnyKey
         */
        public function __construct(
            public array $countsByKey,
            public array $leavesByAnyKey,
        ) {
        }
    }

    /**
     * The native type array overrules the PHPDoc type, like for the PropertyNormalizer.
     */
    final readonly class ArrayTypeMismatch
    {
        /**
         * @param string $values
         *
         * @psalm-suppress MismatchingDocblockParamType On purpose
         */
        public function __construct(
            public array $values,
        ) {
        }
    }

    /**
     * Discriminated read model: the constructor requires one case of the enum, which is neither the first nor the last one, so the
     * SampleFactory needs a SampleProvider that picks it by the class of the parameter.
     */
    final readonly class ImageElement
    {
        public function __construct(
            public string $url,
            public ElementType $type,
        ) {
            if ($type !== ElementType::IMAGE) {
                throw new \InvalidArgumentException('An image element must be of type IMAGE.');
            }
        }
    }

    final readonly class Page
    {
        /**
         * @param list<ImageElement> $elements
         */
        public function __construct(
            public array $elements,
            public ?ImageElement $cover,
        ) {
        }
    }

    enum ElementType: string implements StringNormalizable
    {
        use StringNormalizableEnumTrait;

        case TEXT = 'TEXT';
        case IMAGE = 'IMAGE';
        case VIDEO = 'VIDEO';
    }

    // -- Not supported

    final readonly class ObjectShape
    {
        /**
         * @param object{name: string} $value
         */
        public function __construct(
            public object $value,
        ) {
        }
    }

    final readonly class PrivateConstructor
    {
        private function __construct(
            public string $name,
        ) {
        }

        public static function create(): self
        {
            return new self('Example');
        }
    }

    final readonly class NonPublicProperty
    {
        public function __construct(
            public string $name,
            private string $secret,
        ) {
        }

        public function secret(): string
        {
            return $this->secret;
        }
    }

    abstract readonly class Base
    {
        public function __construct(
            public string $name,
        ) {
        }
    }

    final readonly class Inherited extends Base
    {
    }

    abstract class SecretHolder
    {
        private string $secret = 'Secret';

        public function secret(): string
        {
            return $this->secret;
        }
    }

    abstract class SecretHolderWithCounter extends SecretHolder
    {
        private static int $counter = 0;

        public static function counter(): int
        {
            return self::$counter;
        }
    }

    /**
     * The PropertyNormalizer reads and writes the private property of the parent of the parent class as well, which isn't part of the
     * properties of the class itself.
     */
    final class WithInheritedSecret extends SecretHolderWithCounter
    {
        public function __construct(
            public string $name,
        ) {
        }
    }

    final readonly class ParameterWithoutProperty
    {
        public string $upperCaseName;

        public function __construct(
            string $name,
        ) {
            $this->upperCaseName = strtoupper($name);
        }
    }

    final readonly class AdditionalParameter
    {
        public function __construct(
            public string $name,
            ?string $unused = null,
        ) {
        }
    }

    final readonly class UnionParameter
    {
        public function __construct(
            public int | string $value,
        ) {
        }
    }

    final readonly class IntersectionParameter
    {
        /**
         * @param \Countable&\Traversable<int, string> $value
         */
        public function __construct(
            public \Countable & \Traversable $value,
        ) {
        }
    }

    final readonly class DefaultObject
    {
        public function __construct(
            public \DateTimeImmutable $createdAt = new \DateTimeImmutable('2026-11-15 10:30:00'),
        ) {
        }
    }

    final readonly class NoProperties
    {
        public function __construct()
        {
        }
    }

    final readonly class ObjectParameter
    {
        public function __construct(
            public object $value,
        ) {
        }
    }

    final readonly class IterableParameter
    {
        /**
         * @param iterable<string> $values
         */
        public function __construct(
            public iterable $values,
        ) {
        }
    }

    final readonly class CollectionClass
    {
        /**
         * @param \ArrayObject<int, string> $names
         */
        public function __construct(
            public \ArrayObject $names,
        ) {
        }
    }

    final class Hooked
    {
        public function __construct(
            public string $name {
                set => trim($value);
            },
        ) {
        }
    }

    final readonly class NameValue implements StringNormalizable
    {
        public function __construct(
            public string $value,
        ) {
        }

        #[\Override]
        public static function denormalize(string $data): self
        {
            return new self($data);
        }

        #[\Override]
        public function normalize(): string
        {
            return $this->value;
        }
    }

    enum Kind: string
    {
        case A = 'A';
    }

    /**
     * Reported as self-aware value object, not as enum, so left out of self-aware-normalizers:known-construction-normalizer:list-unplanned.
     */
    enum SelfAwareKind: string implements StringNormalizable
    {
        use StringNormalizableEnumTrait;

        case B = 'B';
    }

    /**
     * Reported as self-aware value object, not as abstract class, so left out of self-aware-normalizers:known-construction-normalizer:list-unplanned.
     */
    abstract readonly class SelfAwareBase implements StringNormalizable
    {
    }

    // -- Not supported, only diagnosed (ListUnplannedClassesCommandTest)

    final readonly class NoConstructor
    {
    }

    final readonly class VariadicParameter
    {
        public function __construct(
            string ...$names,
        ) {
        }
    }

    final readonly class ReferenceParameter
    {
        public function __construct(
            string &$name,
        ) {
        }
    }

    final class UntypedParameter
    {
        /**
         * @psalm-suppress MissingParamType On purpose
         */
        public function __construct(
            public $name,
        ) {
        }
    }

    /**
     * Not in scope, although in a ReadModel namespace: exceptions are never (de)normalized as read models.
     */
    final class ReadModelFailed extends \RuntimeException
    {
    }

    /**
     * Not in scope (only classes are), only diagnosed through the PlanFactory directly.
     */
    interface Described
    {
    }
}

namespace BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\Sample {
    use BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ImageElement;

    /**
     * Not in the scope of the KnownConstructionNormalizer, only built by the SampleFactory (SampleFactoryTest): the implementation of the interface is
     * built for the parameter.
     */
    interface Element
    {
    }

    final readonly class ElementImplementation implements Element
    {
        public function __construct(
            public ImageElement $image,
        ) {
        }
    }

    /**
     * Native type array with PHPDoc types that do or don't overrule it (PlanFactoryTest).
     */
    final readonly class ArrayWithIterableDoc
    {
        /**
         * @param iterable<string> $values
         *
         * @psalm-suppress MismatchingDocblockParamType On purpose
         */
        public function __construct(
            public array $values,
        ) {
        }
    }

    final readonly class ArrayWithCollectionClassDoc
    {
        /**
         * @param \ArrayObject<int, string> $values
         *
         * @psalm-suppress MismatchingDocblockParamType On purpose
         */
        public function __construct(
            public array $values,
        ) {
        }
    }

    final readonly class ArrayWithIntOrIterableDoc
    {
        /**
         * @param int|iterable $values
         *
         * @psalm-suppress MismatchingDocblockParamType On purpose
         */
        public function __construct(
            public array $values,
        ) {
        }
    }

    final readonly class Gallery
    {
        /**
         * @param list<Element> $elements
         */
        public function __construct(
            public array $elements,
        ) {
        }
    }
}

namespace BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\Other {
    /**
     * Out of the scope of the KnownConstructionNormalizer, (de)normalized by the PropertyNormalizer.
     */
    final class Settings
    {
        public string $mode = 'auto';
    }
}
