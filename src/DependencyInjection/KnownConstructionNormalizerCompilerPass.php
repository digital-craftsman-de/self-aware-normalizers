<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;

/**
 * The KnownConstructionNormalizer only produces the same results as its fallback when the fallback is a PropertyNormalizer that reads no serializer
 * mapping and resolves the types the same way:
 * - exactly the class PropertyNormalizer (a subclass could change any step),
 * - without class metadata factory, name converter, class discriminator resolver and object class resolver (the plan reads no
 *   mapping, converts no names, resolves no discriminator and uses the class of the object),
 * - with a type extractor, which the PlanFactory gets as well (it's taken from the fallback, so both can't differ),
 * - with the same default context as the KnownConstructionNormalizer (both get the default context of the serializer bound, its options are checked
 *   at runtime against the allowlist of the KnownConstructionNormalizer),
 * - with every other argument of the constructor in its default value: the arguments are an allowlist as well, so an argument added by
 *   a later version of the serializer fails the build when it's configured, instead of silently changing the result of the fallback,
 * - registered as a normalizer of the serializer with a lower priority than the KnownConstructionNormalizer, so the serializer falls back to it for
 *   everything the KnownConstructionNormalizer doesn't support.
 *
 * It also gives the PlanFactory the normalizers the serializer asks before the KnownConstructionNormalizer (see NormalizerChain).
 *
 * Runs before the services are removed, when the arguments are final (autowired, bound and resolved to positions), so the checks see
 * what's really injected. Fails the container build with an explanation otherwise.
 *
 * @internal
 */
final readonly class KnownConstructionNormalizerCompilerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private const string NORMALIZER_TAG = 'serializer.normalizer';

    /**
     * Parameters of the PropertyNormalizer constructor (by name) the pass knows. Any other parameter must keep its default value.
     */
    private const string PARAMETER_PROPERTY_TYPE_EXTRACTOR = 'propertyTypeExtractor';
    private const string PARAMETER_DEFAULT_CONTEXT = 'defaultContext';

    private const array PARAMETERS_THAT_MUST_BE_NULL = [
        'classMetadataFactory' => 'class metadata factory ($classMetadataFactory)',
        'nameConverter' => 'name converter ($nameConverter)',
        'classDiscriminatorResolver' => 'class discriminator resolver ($classDiscriminatorResolver)',
        'objectClassResolver' => 'object class resolver ($objectClassResolver)',
    ];

    /**
     * Arguments of the KnownConstructionNormalizer and PlanFactory constructors by position.
     */
    private const int KNOWN_CONSTRUCTION_NORMALIZER_ARGUMENT_FALLBACK_NORMALIZER = 1;
    private const int KNOWN_CONSTRUCTION_NORMALIZER_ARGUMENT_DEFAULT_CONTEXT = 4;
    private const int DATA_COLLECTOR_ARGUMENT_CONFIGURATION = 2;
    private const int PLAN_FACTORY_ARGUMENT_PROPERTY_TYPE_EXTRACTOR = 0;
    private const int PLAN_FACTORY_ARGUMENT_NORMALIZER_CHAIN = 1;
    private const int NORMALIZER_CHAIN_ARGUMENT_PRECEDING_NORMALIZERS = 0;
    private const int NORMALIZER_CHAIN_ARGUMENT_NEUTRAL_NORMALIZERS = 1;

    /**
     * Tag of the normalizers of the default serializer, added by the SerializerPass.
     */
    private const string DEFAULT_SERIALIZER_NORMALIZER_TAG = 'serializer.normalizer.default';

    /**
     * @param class-string $fallbackClass The class the fallback must be, PropertyNormalizer except in tests (e.g. of a constructor with
     *                                    parameters the pass doesn't know)
     */
    public function __construct(
        private string $fallbackClass = PropertyNormalizer::class,
    ) {
    }

    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(SelfAwareNormalizersExtension::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID)) {
            return;
        }

        $knownConstructionNormalizerDefinition = $container->getDefinition(SelfAwareNormalizersExtension::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID);

        /** @var Reference $fallbackReference Set by the extension */
        $fallbackReference = $knownConstructionNormalizerDefinition->getArgument(self::KNOWN_CONSTRUCTION_NORMALIZER_ARGUMENT_FALLBACK_NORMALIZER);
        $fallbackId = (string) $fallbackReference;
        if (!$container->has($fallbackId)) {
            throw self::invalidFallback($fallbackId, 'The service does not exist. Define it (see README) or configure another one in self_aware_normalizers.known_construction_normalizer.fallback_normalizer.');
        }

        $fallbackDefinition = $container->findDefinition($fallbackId);
        $fallbackClass = $container->getParameterBag()->resolveValue($fallbackDefinition->getClass());
        if ($fallbackClass !== $this->fallbackClass) {
            throw self::invalidFallback($fallbackId, sprintf('It must be a %s (not a subclass), but is %s.', $this->fallbackClass, is_string($fallbackClass) ? $fallbackClass : get_debug_type($fallbackClass)));
        }
        if ($fallbackDefinition->getFactory() !== null
            || $fallbackDefinition->getMethodCalls() !== []
        ) {
            throw self::invalidFallback($fallbackId, 'It must be built through its constructor only (no factory, no method calls).');
        }

        $fallbackArguments = self::argumentsByParameterName($fallbackId, $this->fallbackClass, $fallbackDefinition->getArguments());
        foreach (self::PARAMETERS_THAT_MUST_BE_NULL as $parameterName => $description) {
            if (($fallbackArguments[$parameterName] ?? null) !== null) {
                throw self::invalidFallback($fallbackId, sprintf('It must be built without %s, as the known construction normalizer does not support it. Set the argument to null explicitly (an autowired service otherwise gets it injected).', $description));
            }
        }

        self::validateUnknownArguments($fallbackId, $this->fallbackClass, $fallbackArguments, $container);

        $propertyTypeExtractor = $fallbackArguments[self::PARAMETER_PROPERTY_TYPE_EXTRACTOR] ?? null;
        if (!$propertyTypeExtractor instanceof Reference
            && !$propertyTypeExtractor instanceof Definition
        ) {
            throw self::invalidFallback($fallbackId, 'It must be built with a property type extractor ($propertyTypeExtractor), which the known construction normalizer uses as well.');
        }

        // Same instance (or same definition) as the fallback
        $container->getDefinition(SelfAwareNormalizersExtension::PLAN_FACTORY_SERVICE_ID)
            ->replaceArgument(self::PLAN_FACTORY_ARGUMENT_PROPERTY_TYPE_EXTRACTOR, $propertyTypeExtractor);

        $knownConstructionNormalizerPriority = self::priority($knownConstructionNormalizerDefinition);
        $fallbackPriority = $fallbackDefinition->hasTag(self::NORMALIZER_TAG)
            ? self::priority($fallbackDefinition)
            : null;
        if ($fallbackPriority === null) {
            throw self::invalidFallback($fallbackId, sprintf('It must be tagged as "%s", so the serializer falls back to it.', self::NORMALIZER_TAG));
        }
        if ($fallbackPriority >= $knownConstructionNormalizerPriority) {
            throw self::invalidFallback($fallbackId, sprintf('Its priority (%d) must be lower than the one of the known construction normalizer (%d, self_aware_normalizers.known_construction_normalizer.priority).', $fallbackPriority, $knownConstructionNormalizerPriority));
        }

        $this->configureNormalizerChain($container);
        $this->configureDataCollector($container, $fallbackId, $fallbackPriority, $knownConstructionNormalizerPriority);

        $knownConstructionNormalizerArguments = $knownConstructionNormalizerDefinition->getArguments();
        $knownConstructionNormalizerDefaultContext = $knownConstructionNormalizerArguments[self::KNOWN_CONSTRUCTION_NORMALIZER_ARGUMENT_DEFAULT_CONTEXT] ?? [];
        $fallbackDefaultContext = $fallbackArguments[self::PARAMETER_DEFAULT_CONTEXT] ?? [];
        if ($container->getParameterBag()->resolveValue($knownConstructionNormalizerDefaultContext) != $container->getParameterBag()->resolveValue($fallbackDefaultContext)) {
            throw self::invalidFallback($fallbackId, 'It must have the same default context as the known construction normalizer, which gets the default context of the serializer. Don\'t set $defaultContext on the fallback.');
        }
    }

    /**
     * Gives the data collector of the profiler (only registered in debug mode with the profiler) the configuration it shows: the fallback,
     * the cache pool and the normalizers of the default serializer in their order.
     */
    private function configureDataCollector(
        ContainerBuilder $container,
        string $fallbackId,
        int $fallbackPriority,
        int $knownConstructionNormalizerPriority,
    ): void {
        if (!$container->hasDefinition(KnownConstructionNormalizerProfilerPass::DATA_COLLECTOR_SERVICE_ID)) {
            return;
        }

        /** @var Definition $normalizerChainDefinition Set by the extension */
        $normalizerChainDefinition = $container->getDefinition(SelfAwareNormalizersExtension::PLAN_FACTORY_SERVICE_ID)
            ->getArgument(self::PLAN_FACTORY_ARGUMENT_NORMALIZER_CHAIN);
        /** @var list<string> $neutralNormalizerIds Set by the extension */
        $neutralNormalizerIds = $normalizerChainDefinition->getArgument(self::NORMALIZER_CHAIN_ARGUMENT_NEUTRAL_NORMALIZERS);

        $normalizers = [];
        $position = 'before';
        foreach ($this->findAndSortTaggedServices(self::DEFAULT_SERIALIZER_NORMALIZER_TAG, $container) as $reference) {
            $id = (string) $reference;
            if ($id === SelfAwareNormalizersExtension::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID) {
                $position = 'after';
            }

            /** @var array{priority?: int} $attributes */
            $attributes = $container->getDefinition($id)->getTag(self::DEFAULT_SERIALIZER_NORMALIZER_TAG)[0] ?? [];
            $normalizers[] = [
                'id' => $id,
                'priority' => $attributes['priority'] ?? 0,
                'position' => $id === SelfAwareNormalizersExtension::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID
                    ? 'self'
                    : $position,
                'isNeutral' => in_array($id, $neutralNormalizerIds, true),
            ];
        }

        $dataCollectorDefinition = $container->getDefinition(KnownConstructionNormalizerProfilerPass::DATA_COLLECTOR_SERVICE_ID);
        /**
         * @var array{planCachePool: ?string} $configurationOfExtension Set by the extension (the reference to a pool that doesn't exist
         *                                    is already removed from the KnownConstructionNormalizer here)
         */
        $configurationOfExtension = $dataCollectorDefinition->getArgument(self::DATA_COLLECTOR_ARGUMENT_CONFIGURATION);
        $planCachePool = $configurationOfExtension['planCachePool'];

        $dataCollectorDefinition->replaceArgument(self::DATA_COLLECTOR_ARGUMENT_CONFIGURATION, [
            'fallbackNormalizer' => $fallbackId,
            'fallbackPriority' => $fallbackPriority,
            'priority' => $knownConstructionNormalizerPriority,
            'planCachePool' => $planCachePool,
            'isPlanCachePoolDefined' => $planCachePool !== null
                && $container->has($planCachePool),
            'normalizers' => $normalizers,
        ]);
    }

    /**
     * Gives the NormalizerChain of the PlanFactory the normalizers the default serializer asks before the known construction normalizer,
     * in its order (sorted exactly like the SerializerPass sorts them).
     */
    private function configureNormalizerChain(ContainerBuilder $container): void
    {
        $normalizerIds = array_map(
            static fn (Reference $reference): string => (string) $reference,
            $this->findAndSortTaggedServices(self::DEFAULT_SERIALIZER_NORMALIZER_TAG, $container),
        );
        $position = array_search(SelfAwareNormalizersExtension::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID, $normalizerIds, true);
        if ($position === false) {
            throw new InvalidArgumentException('The known construction normalizer is not a normalizer of the default serializer. Don\'t remove its tag "serializer.normalizer" or restrict it to named serializers.');
        }
        $precedingIds = array_slice($normalizerIds, 0, $position);

        /** @var Definition $normalizerChainDefinition Set by the extension */
        $normalizerChainDefinition = $container->getDefinition(SelfAwareNormalizersExtension::PLAN_FACTORY_SERVICE_ID)
            ->getArgument(self::PLAN_FACTORY_ARGUMENT_NORMALIZER_CHAIN);

        /** @var list<string> $neutralNormalizerIds Set by the extension */
        $neutralNormalizerIds = $normalizerChainDefinition->getArgument(self::NORMALIZER_CHAIN_ARGUMENT_NEUTRAL_NORMALIZERS);
        foreach ($neutralNormalizerIds as $neutralNormalizerId) {
            if (!in_array($neutralNormalizerId, $precedingIds, true)) {
                throw new InvalidArgumentException(sprintf('The neutral normalizer "%s" (self_aware_normalizers.known_construction_normalizer.neutral_normalizers) is not a normalizer the serializer asks before the known construction normalizer. Remove it from the neutral normalizers.', $neutralNormalizerId));
            }
        }

        $precedingNormalizers = [];
        foreach ($precedingIds as $precedingId) {
            $precedingNormalizers[$precedingId] = new Reference($precedingId);
        }
        $normalizerChainDefinition->replaceArgument(self::NORMALIZER_CHAIN_ARGUMENT_PRECEDING_NORMALIZERS, new IteratorArgument($precedingNormalizers));
    }

    /**
     * The configured arguments of the fallback by the name of their constructor parameter. The arguments are resolved to positions at
     * this point (named arguments are resolved by the ResolveNamedArgumentsPass), names are supported as well.
     *
     * @param class-string            $fallbackClass
     * @param array<array-key, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private static function argumentsByParameterName(
        string $fallbackId,
        string $fallbackClass,
        array $arguments,
    ): array {
        $parameterNames = array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            new \ReflectionMethod($fallbackClass, '__construct')->getParameters(),
        );

        $argumentsByParameterName = [];
        foreach ($arguments as $key => $argument) {
            $parameterName = is_int($key)
                ? $parameterNames[$key] ?? null
                : ltrim($key, '$');
            if ($parameterName === null
                || !in_array($parameterName, $parameterNames, true)
            ) {
                throw self::invalidFallback($fallbackId, sprintf('Its constructor has no parameter for the argument %s.', is_int($key) ? sprintf('at position %d', $key) : $key));
            }

            /** @psalm-suppress MixedAssignment */
            $argumentsByParameterName[$parameterName] = $argument;
        }

        return $argumentsByParameterName;
    }

    /**
     * Parameters of the constructor the pass doesn't know (e.g. added by a later version of the serializer) must keep their default
     * value, as they could change the result of the fallback in a way the plan doesn't implement.
     *
     * @param class-string         $fallbackClass
     * @param array<string, mixed> $arguments
     */
    private static function validateUnknownArguments(
        string $fallbackId,
        string $fallbackClass,
        array $arguments,
        ContainerBuilder $container,
    ): void {
        foreach (new \ReflectionMethod($fallbackClass, '__construct')->getParameters() as $parameter) {
            $parameterName = $parameter->getName();
            if (isset(self::PARAMETERS_THAT_MUST_BE_NULL[$parameterName])
                || $parameterName === self::PARAMETER_PROPERTY_TYPE_EXTRACTOR
                || $parameterName === self::PARAMETER_DEFAULT_CONTEXT
                || !array_key_exists($parameterName, $arguments)
            ) {
                continue;
            }

            if (!$parameter->isDefaultValueAvailable()
                || $container->getParameterBag()->resolveValue($arguments[$parameterName]) !== $parameter->getDefaultValue()
            ) {
                throw self::invalidFallback($fallbackId, sprintf('The argument $%s of its constructor is not known to the known construction normalizer, so it must keep its default value. Remove the argument or disable the known construction normalizer.', $parameterName));
            }
        }
    }

    /**
     * The priority the serializer orders the normalizer by: like PriorityTaggedServiceTrait (used by the SerializerPass) for a tag
     * without index, the priority of the first tag counts, 0 without priority. Further tags (e.g. added through autoconfiguration
     * without priority) are ignored.
     */
    private static function priority(Definition $definition): int
    {
        /** @var array{priority?: int} $attributes */
        $attributes = $definition->getTag(self::NORMALIZER_TAG)[0] ?? [];

        return $attributes['priority'] ?? 0;
    }

    private static function invalidFallback(
        string $fallbackId,
        string $reason,
    ): InvalidArgumentException {
        return new InvalidArgumentException(sprintf(
            'The fallback normalizer "%s" of the known construction normalizer (self_aware_normalizers.known_construction_normalizer.fallback_normalizer) is not equivalent to it. %s',
            $fallbackId,
            $reason,
        ));
    }
}
