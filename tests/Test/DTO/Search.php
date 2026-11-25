<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\DTO;

use BuronDigital\SelfAwareNormalizers\Serializer\ArrayNormalizable;
use BuronDigital\SelfAwareNormalizers\Serializer\NullableArrayDenormalizable;
use BuronDigital\SelfAwareNormalizers\Serializer\NullableArrayDenormalizableTrait;
use BuronDigital\SelfAwareNormalizers\Test\ValueObject\Limit;
use BuronDigital\SelfAwareNormalizers\Test\ValueObject\SearchTerm;

/**
 * @psalm-type NormalizedSearch = array{
 *     searchTerm: string | null,
 *     limit: int,
 * }
 */
final readonly class Search implements ArrayNormalizable, NullableArrayDenormalizable
{
    use NullableArrayDenormalizableTrait;

    public function __construct(
        public ?SearchTerm $searchTerm,
        public Limit $limit,
    ) {
    }

    // -- Array normalizable

    /**
     * @param NormalizedSearch $data
     */
    #[\Override]
    public static function denormalize(array $data): self
    {
        return new self(
            searchTerm: SearchTerm::denormalizeWhenNotNull($data['searchTerm']),
            limit: Limit::denormalize($data['limit']),
        );
    }

    /**
     * @return NormalizedSearch
     */
    #[\Override]
    public function normalize(): array
    {
        return [
            'searchTerm' => $this->searchTerm?->normalize(),
            'limit' => $this->limit->normalize(),
        ];
    }
}
