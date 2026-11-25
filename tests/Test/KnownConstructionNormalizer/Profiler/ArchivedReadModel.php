<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Profiler;

use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * A read model with serializer mapping and an attribute that isn't one of the serializer.
 */
final readonly class ArchivedReadModel
{
    public function __construct(
        #[SerializedName('title')]
        public string $name,
    ) {
    }

    #[\Deprecated('Use $name instead.')]
    public function title(): string
    {
        return $this->name;
    }
}
