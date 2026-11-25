<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Profiler;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * A read model with serializer mapping, which the KnownConstructionNormalizer and its fallback ignore.
 */
#[Context(['skip_null_values' => true])]
final readonly class MappedReadModel
{
    public function __construct(
        #[Groups(['list'])]
        #[SerializedName('title')]
        public string $name,
        public int $count,
    ) {
    }

    #[Ignore]
    public function label(): string
    {
        return sprintf('%s (%d)', $this->name, $this->count);
    }
}
