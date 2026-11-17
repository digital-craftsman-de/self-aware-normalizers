<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\KnownConstructionNormalizer;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class Configuration implements ConfigurationInterface
{
    public const string IMPLEMENTATION_DIRECTORIES_CONFIGURATION_PARAMETER = 'self_aware_normalizers.implementation_directories';
    public const string DOCTRINE_TYPE_DIRECTORIES_CONFIGURATION_PARAMETER = 'self_aware_normalizers.doctrine_type_directories';

    public const string IMPLEMENTATION_DIRECTORIES_KEY = 'implementation_directories';
    public const string DOCTRINE_TYPE_DIRECTORIES_KEY = 'doctrine_type_directories';

    public const string KNOWN_CONSTRUCTION_NORMALIZER_KEY = 'known_construction_normalizer';
    public const string KNOWN_CONSTRUCTION_NORMALIZER_ENABLED_KEY = 'enabled';
    public const string KNOWN_CONSTRUCTION_NORMALIZER_ACCEPTED_CONTEXT_KEYS_KEY = 'accepted_context_keys';
    public const string KNOWN_CONSTRUCTION_NORMALIZER_NEUTRAL_NORMALIZERS_KEY = 'neutral_normalizers';
    public const string KNOWN_CONSTRUCTION_NORMALIZER_PLAN_CACHE_POOL_KEY = 'plan_cache_pool';
    public const string KNOWN_CONSTRUCTION_NORMALIZER_FALLBACK_NORMALIZER_KEY = 'fallback_normalizer';
    public const string KNOWN_CONSTRUCTION_NORMALIZER_PRIORITY_KEY = 'priority';

    public const string DEFAULT_FALLBACK_NORMALIZER = 'serializer.normalizer.property_normalizer';
    public const int DEFAULT_KNOWN_CONSTRUCTION_NORMALIZER_PRIORITY = -45;

    /**
     * @psalm-suppress MixedMethodCall
     * @psalm-suppress UndefinedMethod
     */
    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('self_aware_normalizers');
        $rootNode = $treeBuilder->getRootNode();

        /** @psalm-suppress PossiblyUndefinedMethod */
        $rootNode
            ->children()
                ->arrayNode(self::IMPLEMENTATION_DIRECTORIES_KEY)
                    ->info('List of full file paths to directories to scan for implementation of the interfaces to automatically register doctrine types for. Subdirectories are scanned too.')
                    ->beforeNormalization()->castToArray()->end()
                    ->defaultValue([])
                    ->prototype('variable')->end()
                ->end()
                ->arrayNode(self::DOCTRINE_TYPE_DIRECTORIES_KEY)
                    ->info('List of full file paths to directories to scan for Doctrine types. Subdirectories are scanned too.')
                    ->beforeNormalization()->castToArray()->end()
                    ->defaultValue([])
                    ->prototype('variable')->end()
                ->end()
                ->arrayNode(self::KNOWN_CONSTRUCTION_NORMALIZER_KEY)
                    ->info('Fast path in front of the PropertyNormalizer for plain constructor-promoted classes (see README). Disabled by default.')
                    ->canBeEnabled()
                    ->children()
                        ->arrayNode(self::KNOWN_CONSTRUCTION_NORMALIZER_ACCEPTED_CONTEXT_KEYS_KEY)
                            ->info('Further context keys the known construction normalizer is used with, for options of the project that the project guarantees not to change the result of the PropertyNormalizer (e.g. options of its own normalizers). A context with any other key that is not built in falls back to the PropertyNormalizer.')
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                            ->validate()
                                ->ifTrue(static fn (array $keys): bool => array_filter(
                                    $keys,
                                    static fn (mixed $key): bool => !is_string($key)
                                        || !KnownConstructionNormalizer::isContextOptionAcceptable($key),
                                ) !== [])
                                ->thenInvalid('The accepted context keys must not contain options the known construction normalizer does not implement or only accepts with a specific value (see KnownConstructionNormalizer::NOT_IMPLEMENTED_CONTEXT_OPTIONS), %s given.')
                            ->end()
                        ->end()
                        ->arrayNode(self::KNOWN_CONSTRUCTION_NORMALIZER_NEUTRAL_NORMALIZERS_KEY)
                            ->info('Service ids of normalizers with a higher priority than the known construction normalizer that never handle the classes it plans or their values, although their getSupportedTypes() may claim them (e.g. "object" => false). Any other normalizer that may claim a class keeps that class from being handled inline.')
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode(self::KNOWN_CONSTRUCTION_NORMALIZER_PLAN_CACHE_POOL_KEY)
                            ->info('Service id of a PSR-6 cache pool the plans are cached in across requests. The plans are only kept per instance when it is null or the service does not exist (e.g. when the pool is only defined in prod).')
                            ->defaultNull()
                        ->end()
                        ->scalarNode(self::KNOWN_CONSTRUCTION_NORMALIZER_FALLBACK_NORMALIZER_KEY)
                            ->info('Service id of the PropertyNormalizer the known construction normalizer falls back to. Must be built without class metadata factory, name converter, class discriminator resolver and object class resolver, and with every other constructor argument but the type extractor and the default context in its default value.')
                            ->defaultValue(self::DEFAULT_FALLBACK_NORMALIZER)
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode(self::KNOWN_CONSTRUCTION_NORMALIZER_PRIORITY_KEY)
                            ->info('Priority of the known construction normalizer in the serializer. Must be lower than the one of the BackedEnumNormalizer and higher than the one of the fallback normalizer.')
                            ->defaultValue(self::DEFAULT_KNOWN_CONSTRUCTION_NORMALIZER_PRIORITY)
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
