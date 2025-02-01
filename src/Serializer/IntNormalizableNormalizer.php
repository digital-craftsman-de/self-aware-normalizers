<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class IntNormalizableNormalizer implements NormalizerInterface, DenormalizerInterface
{
    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof IntNormalizable;
    }

    /**
     * @param class-string $type
     */
    #[\Override]
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return class_exists($type)
            && is_subclass_of($type, IntNormalizable::class);
    }

    /**
     * @param IntNormalizable|null $data
     */
    #[\Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): ?int
    {
        if ($data === null) {
            return null;
        }

        return $data->normalize();
    }

    /**
     * @param int|null                      $data
     * @param class-string<IntNormalizable> $type
     */
    #[\Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ?IntNormalizable
    {
        if ($data === null) {
            return null;
        }

        return $type::denormalize($data);
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, bool|null>
     */
    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [
            IntNormalizable::class => true,
        ];
    }
}
