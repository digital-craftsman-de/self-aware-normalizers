<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Chain;

use BuronDigital\SelfAwareNormalizers\Serializer\StringNormalizable;

/**
 * Classes for the tests of the chain of normalizers in front of the KnownConstructionNormalizer (see NormalizerChain): ClaimingNormalizer
 * (de)normalizes ClaimedLeaf and ClaimedValue in a format of its own, so a plan that handled them inline would produce another result.
 */
final readonly class ClaimedLeaf
{
    public function __construct(
        public string $name,
    ) {
    }
}

final readonly class ClaimedValue implements StringNormalizable
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

final readonly class WithClaimedClasses
{
    /**
     * @param list<ClaimedLeaf>  $leaves
     * @param list<ClaimedValue> $values
     */
    public function __construct(
        public ClaimedLeaf $leaf,
        public array $leaves,
        public ClaimedValue $value,
        public array $values,
        public string $name,
    ) {
    }
}

final readonly class JsonSerializableReadModel implements \JsonSerializable
{
    public function __construct(
        public string $name,
    ) {
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'serializedName' => $this->name,
        ];
    }
}

final readonly class WithClaimedUnion
{
    /**
     * @param list<ClaimedLeaf|WithClaimedClasses|string> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
