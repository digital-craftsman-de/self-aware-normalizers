<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\DTO;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\ArrayNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\NullableArrayDenormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\NullableArrayDenormalizableTrait;

/**
 * @psalm-import-type NormalizedSearch from Search
 *
 * @psalm-type NormalizedGetUsersQuery = array{
 *     userId: int,
 *     search: NormalizedSearch | null,
 * }
 */
final readonly class GetUsersQuery implements ArrayNormalizable, NullableArrayDenormalizable
{
    use NullableArrayDenormalizableTrait;

    public function __construct(
        public int $userId,
        public ?Search $search,
    ) {
    }

    // -- Array normalizable

    /**
     * @param NormalizedGetUsersQuery $data
     */
    #[\Override]
    public static function denormalize(array $data): self
    {
        return new self(
            userId: $data['userId'],
            search: Search::denormalizeWhenNotNull($data['search']),
        );
    }

    /**
     * @return NormalizedGetUsersQuery
     */
    #[\Override]
    public function normalize(): array
    {
        return [
            'userId' => $this->userId,
            'search' => $this->search?->normalize(),
        ];
    }
}
