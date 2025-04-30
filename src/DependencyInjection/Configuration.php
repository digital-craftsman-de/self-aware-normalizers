<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const string DOCTRINE_TYPE_DIRECTORIES_CONFIGURATION_PARAMETER = 'self_aware_normalizers.doctrine_type_directories';
    public const string DOCTRINE_TYPE_DIRECTORIES_KEY = 'doctrine_type_directories';

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
                ->arrayNode(self::DOCTRINE_TYPE_DIRECTORIES_KEY)
                    ->info('List of full file paths to directories to scan for Doctrine types')
                    ->beforeNormalization()->castToArray()->end()
                    ->defaultValue([])
                    ->prototype('variable')->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
