<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Chain;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * A normalizer of an application with a higher priority than the KnownConstructionNormalizer that decides per call for every object, so
 * the plan can't rule out that it handles a class. It never does.
 */
final class PerCallNormalizer implements NormalizerInterface, DenormalizerInterface
{
    #[\Override]
    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = [],
    ): never {
        throw new \LogicException('Never supported.');
    }

    #[\Override]
    public function supportsNormalization(
        mixed $data,
        ?string $format = null,
        array $context = [],
    ): bool {
        return false;
    }

    #[\Override]
    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): never {
        throw new \LogicException('Never supported.');
    }

    #[\Override]
    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): bool {
        return false;
    }

    /**
     * @return array<string, bool|null>
     */
    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [
            'object' => false,
        ];
    }
}
