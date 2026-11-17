<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Chain;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * A normalizer of an application with a higher priority than the KnownConstructionNormalizer, for two classes of its own format.
 */
final class ClaimingNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @return array{claimed: string}
     */
    #[\Override]
    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = [],
    ): array {
        /** @var ClaimedLeaf|ClaimedValue $data */
        return [
            'claimed' => $data instanceof ClaimedLeaf
                ? $data->name
                : $data->value,
        ];
    }

    #[\Override]
    public function supportsNormalization(
        mixed $data,
        ?string $format = null,
        array $context = [],
    ): bool {
        return $data instanceof ClaimedLeaf
            || $data instanceof ClaimedValue;
    }

    #[\Override]
    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): ClaimedLeaf | ClaimedValue {
        /** @var array{claimed: string} $data */
        return $type === ClaimedLeaf::class
            ? new ClaimedLeaf($data['claimed'])
            : new ClaimedValue($data['claimed']);
    }

    #[\Override]
    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): bool {
        return $type === ClaimedLeaf::class
            || $type === ClaimedValue::class;
    }

    /**
     * @return array<string, bool|null>
     */
    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [
            ClaimedLeaf::class => true,
            ClaimedValue::class => true,
        ];
    }
}
