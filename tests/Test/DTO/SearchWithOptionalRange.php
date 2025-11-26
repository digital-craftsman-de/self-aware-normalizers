<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\DTO;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\ArrayNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Range;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\SearchTerm;

/**
 * @psalm-type NormalizedSearchWithOptionalRange = array{
 *     searchTerm: string,
 *     range: float | null,
 * }
 */
final readonly class SearchWithOptionalRange implements ArrayNormalizable
{
    public function __construct(
        public SearchTerm $searchTerm,
        public ?Range $range,
    ) {
    }

    // -- Array normalizable

    /**
     * @param NormalizedSearchWithOptionalRange $data
     */
    #[\Override]
    public static function denormalize(array $data): self
    {
        return new self(
            searchTerm: SearchTerm::denormalize($data['searchTerm']),
            range: Range::denormalizeWhenNotNull($data['range']),
        );
    }

    /**
     * @return NormalizedSearchWithOptionalRange
     */
    #[\Override]
    public function normalize(): array
    {
        return [
            'searchTerm' => $this->searchTerm->normalize(),
            'range' => $this->range?->normalize(),
        ];
    }
}
