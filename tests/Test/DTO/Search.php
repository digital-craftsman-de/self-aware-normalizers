<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\DTO;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\ArrayNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Limit;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\SearchTerm;

/**
 * @psalm-type NormalizedSearch = array{
 *     searchTerm: string | null,
 *     limit: int,
 * }
 */
final readonly class Search implements ArrayNormalizable
{
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
