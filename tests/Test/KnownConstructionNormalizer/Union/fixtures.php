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
