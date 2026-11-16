<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

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

    public const string PLAN_NORMALIZER_KEY = 'plan_normalizer';
    public const string PLAN_NORMALIZER_ENABLED_KEY = 'enabled';
    public const string PLAN_NORMALIZER_SUPPORTED_CLASS_NAME_PATTERN_KEY = 'supported_class_name_pattern';
    public const string PLAN_NORMALIZER_SUPPORTED_PARENT_TYPES_KEY = 'supported_parent_types';
    public const string PLAN_NORMALIZER_ACCEPTED_UNPLANNED_CLASSES_KEY = 'accepted_unplanned_classes';
    public const string PLAN_NORMALIZER_SOURCE_DIRECTORIES_KEY = 'source_directories';
    public const string PLAN_NORMALIZER_PLAN_CACHE_POOL_KEY = 'plan_cache_pool';
    public const string PLAN_NORMALIZER_FALLBACK_NORMALIZER_KEY = 'fallback_normalizer';
    public const string PLAN_NORMALIZER_PRIORITY_KEY = 'priority';

    public const string DEFAULT_FALLBACK_NORMALIZER = 'serializer.normalizer.property_normalizer';
    public const int DEFAULT_PLAN_NORMALIZER_PRIORITY = -45;

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
                ->arrayNode(self::PLAN_NORMALIZER_KEY)
                    ->info('Fast path in front of the PropertyNormalizer for plain constructor-promoted classes (see README). Disabled by default.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode(self::PLAN_NORMALIZER_SUPPORTED_CLASS_NAME_PATTERN_KEY)
                            ->info('Regular expression matched against the fully qualified class name of the classes the plan normalizer is responsible for (e.g. all classes in a ReadModel or DTO namespace).')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $pattern): bool => $pattern !== null
                                    && (
                                        !is_string($pattern)
                                        || $pattern === ''
                                        || @preg_match($pattern, '') === false
                                    ))
                                ->thenInvalid('The supported class name pattern must be a valid regular expression, %s given.')
                            ->end()
                        ->end()
                        ->arrayNode(self::PLAN_NORMALIZER_SUPPORTED_PARENT_TYPES_KEY)
                            ->info('Interfaces or parent classes of the classes the plan normalizer is responsible for, e.g. the base classes of commands and queries.')
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode(self::PLAN_NORMALIZER_ACCEPTED_UNPLANNED_CLASSES_KEY)
                            ->info('Classes in the scope of the plan normalizer that fall back to the PropertyNormalizer by design, with the reason (class => reason). Used by the list command with --fail-on-unplanned.')
                            ->useAttributeAsKey('class')
                            ->normalizeKeys(false)
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode(self::PLAN_NORMALIZER_SOURCE_DIRECTORIES_KEY)
                            ->info('Directories whose classes are listed by the list command (recursively), e.g. "%kernel.project_dir%/src".')
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode(self::PLAN_NORMALIZER_PLAN_CACHE_POOL_KEY)
                            ->info('Service id of a PSR-6 cache pool the plans are cached in across requests. The plans are only kept per instance when it is null or the service does not exist (e.g. when the pool is only defined in prod).')
                            ->defaultNull()
                        ->end()
                        ->scalarNode(self::PLAN_NORMALIZER_FALLBACK_NORMALIZER_KEY)
                            ->info('Service id of the PropertyNormalizer the plan normalizer falls back to. Must be built without class metadata factory, name converter, class discriminator resolver and object class resolver.')
                            ->defaultValue(self::DEFAULT_FALLBACK_NORMALIZER)
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode(self::PLAN_NORMALIZER_PRIORITY_KEY)
                            ->info('Priority of the plan normalizer in the serializer. Must be lower than the one of the BackedEnumNormalizer and higher than the one of the fallback normalizer.')
                            ->defaultValue(self::DEFAULT_PLAN_NORMALIZER_PRIORITY)
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $config): bool => $config[self::PLAN_NORMALIZER_ENABLED_KEY] === true
                            && $config[self::PLAN_NORMALIZER_SUPPORTED_CLASS_NAME_PATTERN_KEY] === null
                            && $config[self::PLAN_NORMALIZER_SUPPORTED_PARENT_TYPES_KEY] === [])
                        ->thenInvalid('The plan normalizer needs a scope: configure the supported class name pattern or supported parent types.')
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
