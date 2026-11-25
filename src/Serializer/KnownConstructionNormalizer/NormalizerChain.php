<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer;

use BuronDigital\SelfAwareNormalizers\Serializer\ArrayNormalizableNormalizer;
use BuronDigital\SelfAwareNormalizers\Serializer\BoolNormalizableNormalizer;
use BuronDigital\SelfAwareNormalizers\Serializer\FloatNormalizableNormalizer;
use BuronDigital\SelfAwareNormalizers\Serializer\IntNormalizableNormalizer;
use BuronDigital\SelfAwareNormalizers\Serializer\StringNormalizableNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;

/**
 * The normalizers the serializer asks before the KnownConstructionNormalizer (the ones with a higher priority, in the order of the
 * serializer). The plan builds nested planned classes and self-aware value objects inline, without asking the serializer which normalizer
 * handles them. That's only equivalent when the serializer would select the KnownConstructionNormalizer (or the self-aware normalizer of the
 * value object) for them as well, so a class one of these normalizers claims is never handled inline: it isn't planned, and as a value of
 * a planned class it's (de)normalized through the serializer.
 *
 * A normalizer claims a class like the serializer selects it (Serializer::getNormalizer() and getDenormalizer()): through
 * getSupportedTypes() with the class, a parent class or interface of it, or else "object" or "*", for the formats the
 * KnownConstructionNormalizer supports (null and json). Whether the support is cacheable doesn't matter: the plan can't call supports*()
 * without the data, so any possible support is a claim. Exceptions:
 * - the self-aware normalizers of this bundle: the plan mirrors them for value objects, so a class they claim is handled by them either way,
 * - neutral normalizers: built-in ones whose support is gated by something the KnownConstructionNormalizer doesn't support anyway (see
 *   NEUTRAL_BUILT_IN_NORMALIZERS), and the ones the application declares (self_aware_normalizers.known_construction_normalizer.neutral_normalizers)
 *   because they never handle the classes of a plan or their values.
 *
 * Determined once per class while the plan is built, so the result is part of the cached plan.
 */
final class NormalizerChain
{
    /**
     * Self-aware normalizers whose (de)normalization the plan mirrors for value objects.
     */
    private const array MIRRORED_NORMALIZERS = [
        StringNormalizableNormalizer::class => true,
        IntNormalizableNormalizer::class => true,
        FloatNormalizableNormalizer::class => true,
        BoolNormalizableNormalizer::class => true,
        ArrayNormalizableNormalizer::class => true,
    ];

    /**
     * Built-in normalizers of Symfony (8.1) that may claim the classes of a plan, but never handle them in a context the
     * KnownConstructionNormalizer supports. Class => reason.
     *
     * The other built-in normalizers of the FrameworkBundle only claim their own classes (e.g. \DateTimeInterface, \BackedEnum,
     * \JsonSerializable or NormalizableInterface, which are rightly not inlined then), claim every object (ObjectNormalizer,
     * PropertyNormalizer, GetSetMethodNormalizer, which then rightly prevent all inlining) or never claim a class (ArrayDenormalizer, which
     * only supports "X[]").
     */
    public const array NEUTRAL_BUILT_IN_NORMALIZERS = [
        UnwrappingDenormalizer::class => 'only supports a context with unwrap_path, which the known construction normalizer does not accept',
    ];

    /**
     * The formats the KnownConstructionNormalizer supports.
     */
    private const array FORMATS = [
        null,
        'json',
    ];

    /**
     * Class => description of the claiming normalizer (null when none claims it).
     *
     * @var array<string, ?string>
     */
    private array $claims = [];

    /**
     * @param iterable<string, object> $precedingNormalizers Service id => normalizer, in the order of the serializer
     * @param list<string>             $neutralNormalizers   Service ids of normalizers that never handle the classes of a plan
     */
    public function __construct(
        private readonly iterable $precedingNormalizers = [],
        private readonly array $neutralNormalizers = [],
    ) {
    }

    /**
     * Description of the normalizer the serializer would (or might) select for the class instead of the KnownConstructionNormalizer or the
     * self-aware normalizer of a value object, null when there is none.
     */
    public function claimOf(string $class): ?string
    {
        if (!array_key_exists($class, $this->claims)) {
            $this->claims[$class] = $this->determineClaim($class);
        }

        return $this->claims[$class];
    }

    private function determineClaim(string $class): ?string
    {
        foreach ($this->precedingNormalizers as $id => $normalizer) {
            $isCacheable = self::support($normalizer, $class);
            if ($isCacheable === null) {
                continue;
            }

            if (isset(self::MIRRORED_NORMALIZERS[$normalizer::class])) {
                return null;
            }

            if (isset(self::NEUTRAL_BUILT_IN_NORMALIZERS[$normalizer::class])
                || in_array($id, $this->neutralNormalizers, true)
            ) {
                continue;
            }

            return $isCacheable
                ? sprintf('claimed by the normalizer "%s"', $id)
                : sprintf('possibly claimed by the normalizer "%s", which decides per call (declare it as neutral normalizer if it never handles the class)', $id);
        }

        return null;
    }

    /**
     * Whether the normalizer may support the class with one of the formats (null when it doesn't), and whether that support is cacheable
     * for all of them.
     */
    private static function support(
        object $normalizer,
        string $class,
    ): ?bool {
        if (!$normalizer instanceof NormalizerInterface
            && !$normalizer instanceof DenormalizerInterface
        ) {
            return null;
        }

        $support = null;
        foreach (self::FORMATS as $format) {
            $isCacheable = self::supportOfSupportedTypes($normalizer->getSupportedTypes($format), $class);
            if ($isCacheable !== null) {
                $support = ($support ?? true) && $isCacheable;
            }
        }

        return $support;
    }

    /**
     * Same lookup as Serializer::getNormalizer() and getDenormalizer() for an object or class: the first supported type that is the class or
     * a parent of it decides (null: not supported), otherwise "object", otherwise "*".
     *
     * @param array<string, bool|null> $supportedTypes
     */
    private static function supportOfSupportedTypes(
        array $supportedTypes,
        string $class,
    ): ?bool {
        foreach ($supportedTypes as $supportedType => $isCacheable) {
            /** @var class-string|'*'|'object' $supportedType */
            if ($supportedType === '*'
                || $supportedType === 'object'
                || (
                    $class !== $supportedType
                    && !is_subclass_of($class, $supportedType)
                )
            ) {
                continue;
            }

            return $isCacheable;
        }

        return $supportedTypes[array_key_exists('object', $supportedTypes) ? 'object' : '*'] ?? null;
    }
}
