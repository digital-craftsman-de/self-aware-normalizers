<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Union;

use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Leaf;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\Count;

/**
 * Collections that symfony/serializer 8.2 denormalizes differently than 8.1 (element unions, arrays that aren't lists for lists).
 */
final readonly class UnionLists
{
    /**
     * @param list<int|string>               $ids
     * @param array<string, float|bool|null> $flagsByName
     * @param list<Leaf|Count>               $leavesOrCounts
     * @param array<int, string>             $namesByPosition
     * @param list<list<int>>                $matrix
     */
    public function __construct(
        public array $ids,
        public array $flagsByName,
        public array $leavesOrCounts,
        public array $namesByPosition,
        public array $matrix,
    ) {
    }
}

interface Element
{
}

final readonly class TextElement implements Element
{
    public function __construct(
        public string $text,
    ) {
    }
}

final readonly class ImageElement implements Element
{
    public function __construct(
        public string $url,
        public ?int $width,
    ) {
    }
}

/**
 * Collections of unions and of an interface, normalized by the runtime class of every element.
 */
final readonly class UnionElements
{
    /**
     * @param list<TextElement|ImageElement>       $items
     * @param array<string, TextElement|int|null>  $mixed
     * @param list<Element>                        $elements
     * @param list<list<TextElement|ImageElement>> $nested
     * @param list<TextElement|\DateTimeImmutable> $withUnplanned
     */
    public function __construct(
        public array $items,
        public array $mixed,
        public array $elements,
        public array $nested,
        public array $withUnplanned,
    ) {
    }
}

/**
 * Native union parameters.
 */
final readonly class NativeUnions
{
    public function __construct(
        public TextElement | ImageElement $item,
        public int | string $id,
        public TextElement | ImageElement | null $optional,
        public int | string | null $optionalId = null,
    ) {
    }
}

/**
 * Native unions of scalars only, which the plan denormalizes itself.
 */
final readonly class ScalarUnions
{
    public function __construct(
        public int | string $id,
        public int | float $amount,
        public bool | string | null $flag = null,
    ) {
    }
}
