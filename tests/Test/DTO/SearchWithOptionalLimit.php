<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\DTO;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\ArrayNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Limit;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\SearchTerm;

/**
 * @psalm-type NormalizedSearchWithOptionalLimit = array{
 *     searchTerm: string,
 *     limit: int | null,
 * }
 */
final readonly class SearchWithOptionalLimit implements ArrayNormalizable
{
    public function __construct(
        public SearchTerm $searchTerm,
        public ?Limit $limit,
    ) {
    }

    // -- Array normalizable

    /**
     * @param NormalizedSearchWithOptionalLimit $data
     */
    #[\Override]
    public static function denormalize(array $data): self
    {
        return new self(
            searchTerm: SearchTerm::denormalize($data['searchTerm']),
            limit: Limit::denormalizeWhenNotNull($data['limit']),
        );
    }

    /**
     * @return NormalizedSearchWithOptionalLimit
     */
    #[\Override]
    public function normalize(): array
    {
        return [
            'searchTerm' => $this->searchTerm->normalize(),
            'limit' => $this->limit?->normalize(),
        ];
    }
}
