<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\ValueObject;

use BuronDigital\SelfAwareNormalizers\Serializer\NullableStringDenormalizable;
use BuronDigital\SelfAwareNormalizers\Serializer\NullableStringDenormalizableTrait;
use BuronDigital\SelfAwareNormalizers\Serializer\StringNormalizable;

final readonly class SearchTerm implements StringNormalizable, NullableStringDenormalizable
{
    use NullableStringDenormalizableTrait;

    public function __construct(
        public string $search,
    ) {
        if ($this->search === '') {
            throw new \InvalidArgumentException('The search term must not be empty');
        }
        if (str_contains($this->search, '*')) {
            throw new \InvalidArgumentException('The search term must not contain *');
        }
    }

    // -- String normalizable

    #[\Override]
    public static function denormalize(string $data): self
    {
        return new self($data);
    }

    #[\Override]
    public function normalize(): string
    {
        return $this->search;
    }
}
