<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;

/**
 * The PlanNormalizer only produces the same results as its fallback when the fallback is a PropertyNormalizer that reads no serializer
 * mapping and resolves the types the same way:
 * - exactly the class PropertyNormalizer (a subclass could change any step),
 * - without class metadata factory, name converter, class discriminator resolver and object class resolver (the plan reads no
 *   mapping, converts no names, resolves no discriminator and uses the class of the object),
 * - with a type extractor, which the PlanFactory gets as well (it's taken from the fallback, so both can't differ),
 * - with the same default context as the PlanNormalizer (both get the default context of the serializer bound),
 * - registered as a normalizer of the serializer with a lower priority than the PlanNormalizer, so the serializer falls back to it for
 *   everything the PlanNormalizer doesn't support.
 *
 * Runs before the services are removed, when the arguments are final (autowired, bound and resolved to positions), so the checks see
 * what's really injected. Fails the container build with an explanation otherwise.
 *
 * @internal
 */
final readonly class PlanNormalizerCompilerPass implements CompilerPassInterface
{
    private const string NORMALIZER_TAG = 'serializer.normalizer';

    /**
     * Arguments of the PropertyNormalizer constructor by position.
     */
    private const int ARGUMENT_CLASS_METADATA_FACTORY = 0;
    private const int ARGUMENT_NAME_CONVERTER = 1;
    private const int ARGUMENT_PROPERTY_TYPE_EXTRACTOR = 2;
    private const int ARGUMENT_CLASS_DISCRIMINATOR_RESOLVER = 3;
    private const int ARGUMENT_OBJECT_CLASS_RESOLVER = 4;
    private const int ARGUMENT_DEFAULT_CONTEXT = 5;

    private const array ARGUMENTS_THAT_MUST_BE_NULL = [
        self::ARGUMENT_CLASS_METADATA_FACTORY => 'class metadata factory ($classMetadataFactory)',
        self::ARGUMENT_NAME_CONVERTER => 'name converter ($nameConverter)',
        self::ARGUMENT_CLASS_DISCRIMINATOR_RESOLVER => 'class discriminator resolver ($classDiscriminatorResolver)',
        self::ARGUMENT_OBJECT_CLASS_RESOLVER => 'object class resolver ($objectClassResolver)',
    ];

    /**
     * Arguments of the PlanNormalizer and PlanFactory constructors by position.
     */
    private const int PLAN_NORMALIZER_ARGUMENT_FALLBACK_NORMALIZER = 1;
    private const int PLAN_NORMALIZER_ARGUMENT_DEFAULT_CONTEXT = 4;
    private const int PLAN_FACTORY_ARGUMENT_PROPERTY_TYPE_EXTRACTOR = 0;

    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(SelfAwareNormalizersExtension::PLAN_NORMALIZER_SERVICE_ID)) {
            return;
        }

        $planNormalizerDefinition = $container->getDefinition(SelfAwareNormalizersExtension::PLAN_NORMALIZER_SERVICE_ID);

        /** @var Reference $fallbackReference Set by the extension */
        $fallbackReference = $planNormalizerDefinition->getArgument(self::PLAN_NORMALIZER_ARGUMENT_FALLBACK_NORMALIZER);
        $fallbackId = (string) $fallbackReference;
        if (!$container->has($fallbackId)) {
            throw self::invalidFallback($fallbackId, 'The service does not exist. Define it (see README) or configure another one in self_aware_normalizers.plan_normalizer.fallback_normalizer.');
        }

        $fallbackDefinition = $container->findDefinition($fallbackId);
        $fallbackClass = $container->getParameterBag()->resolveValue($fallbackDefinition->getClass());
        if ($fallbackClass !== PropertyNormalizer::class) {
            throw self::invalidFallback($fallbackId, sprintf('It must be a %s, but is %s.', PropertyNormalizer::class, is_string($fallbackClass) ? $fallbackClass : get_debug_type($fallbackClass)));
        }
        if ($fallbackDefinition->getFactory() !== null
            || $fallbackDefinition->getMethodCalls() !== []
        ) {
            throw self::invalidFallback($fallbackId, 'It must be built through its constructor only (no factory, no method calls).');
        }

        $fallbackArguments = $fallbackDefinition->getArguments();
        foreach (self::ARGUMENTS_THAT_MUST_BE_NULL as $position => $description) {
            if (($fallbackArguments[$position] ?? null) !== null) {
                throw self::invalidFallback($fallbackId, sprintf('It must be built without %s, as the plan normalizer does not support it. Set the argument to null explicitly (an autowired service otherwise gets it injected).', $description));
            }
        }

        $propertyTypeExtractor = $fallbackArguments[self::ARGUMENT_PROPERTY_TYPE_EXTRACTOR] ?? null;
        if (!$propertyTypeExtractor instanceof Reference
            && !$propertyTypeExtractor instanceof Definition
        ) {
            throw self::invalidFallback($fallbackId, 'It must be built with a property type extractor ($propertyTypeExtractor), which the plan normalizer uses as well.');
        }

        // Same instance (or same definition) as the fallback
        $container->getDefinition(SelfAwareNormalizersExtension::PLAN_FACTORY_SERVICE_ID)
            ->replaceArgument(self::PLAN_FACTORY_ARGUMENT_PROPERTY_TYPE_EXTRACTOR, $propertyTypeExtractor);

        $planNormalizerPriority = self::priority($planNormalizerDefinition);
        $fallbackPriority = $fallbackDefinition->hasTag(self::NORMALIZER_TAG)
            ? self::priority($fallbackDefinition)
            : null;
        if ($fallbackPriority === null) {
            throw self::invalidFallback($fallbackId, sprintf('It must be tagged as "%s", so the serializer falls back to it.', self::NORMALIZER_TAG));
        }
        if ($fallbackPriority >= $planNormalizerPriority) {
            throw self::invalidFallback($fallbackId, sprintf('Its priority (%d) must be lower than the one of the plan normalizer (%d, self_aware_normalizers.plan_normalizer.priority).', $fallbackPriority, $planNormalizerPriority));
        }

        $planNormalizerArguments = $planNormalizerDefinition->getArguments();
        $planNormalizerDefaultContext = $planNormalizerArguments[self::PLAN_NORMALIZER_ARGUMENT_DEFAULT_CONTEXT] ?? [];
        $fallbackDefaultContext = $fallbackArguments[self::ARGUMENT_DEFAULT_CONTEXT] ?? [];
        if ($container->getParameterBag()->resolveValue($planNormalizerDefaultContext) != $container->getParameterBag()->resolveValue($fallbackDefaultContext)) {
            throw self::invalidFallback($fallbackId, 'It must have the same default context as the plan normalizer, which gets the default context of the serializer. Don\'t set $defaultContext on the fallback.');
        }
    }

    /**
     * The priority of the last normalizer tag (0 without priority, like the SerializerPass).
     */
    private static function priority(Definition $definition): int
    {
        $tags = $definition->getTag(self::NORMALIZER_TAG);

        /** @var array{priority?: int} $attributes */
        $attributes = end($tags);

        return $attributes['priority'] ?? 0;
    }

    private static function invalidFallback(
        string $fallbackId,
        string $reason,
    ): InvalidArgumentException {
        return new InvalidArgumentException(sprintf(
            'The fallback normalizer "%s" of the plan normalizer (self_aware_normalizers.plan_normalizer.fallback_normalizer) is not equivalent to it. %s',
            $fallbackId,
            $reason,
        ));
    }
}
