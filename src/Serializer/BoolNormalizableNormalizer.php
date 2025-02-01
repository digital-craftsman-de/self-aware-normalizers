<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class BoolNormalizableNormalizer implements NormalizerInterface, DenormalizerInterface
{
    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof BoolNormalizable;
    }

    /**
     * @param class-string $type
     */
    #[\Override]
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return class_exists($type)
            && is_subclass_of($type, BoolNormalizable::class);
    }

    /**
     * @param BoolNormalizable|null $data
     */
    #[\Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): ?bool
    {
        if ($data === null) {
            return null;
        }

        return $data->normalize();
    }

    /**
     * @param bool|null                      $data
     * @param class-string<BoolNormalizable> $type
     */
    #[\Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ?BoolNormalizable
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
            BoolNormalizable::class => true,
        ];
    }
}
