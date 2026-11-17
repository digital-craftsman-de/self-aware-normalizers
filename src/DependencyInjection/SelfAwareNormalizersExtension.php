<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\KnownConstructionNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\ListUnplannedClassesCommand;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\NormalizerChain;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\PlanFactory;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class SelfAwareNormalizersExtension extends Extension
{
    public const string KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID = KnownConstructionNormalizer::class;
    public const string PLAN_FACTORY_SERVICE_ID = PlanFactory::class;
    public const string LIST_UNPLANNED_CLASSES_COMMAND_SERVICE_ID = ListUnplannedClassesCommand::class;

    /**
     * Plans are cached under this version (besides the version of their structure), so a cached plan never outlives the code it was
     * built from. Only known when the container is dumped, so it's read at runtime.
     */
    private const string PLAN_CACHE_VERSION_PARAMETER = 'container.build_id';

    /**
     * @param array<mixed> $configs
     */
    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();

        /**
         * @var array{
         *   implementation_directories: list<string>,
         *   doctrine_type_directories: list<string>,
         *   known_construction_normalizer: array{
         *     enabled: bool,
         *     supported_class_name_pattern: ?non-empty-string,
         *     supported_parent_types: list<class-string>,
         *     accepted_unplanned_classes: array<string, string>,
         *     accepted_context_keys: list<string>,
         *     neutral_normalizers: list<string>,
         *     source_directories: list<string>,
         *     plan_cache_pool: ?string,
         *     fallback_normalizer: string,
         *     priority: int,
         *   },
         * } $config
         */
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter(
            Configuration::IMPLEMENTATION_DIRECTORIES_CONFIGURATION_PARAMETER,
            $config[Configuration::IMPLEMENTATION_DIRECTORIES_KEY],
        );
        $container->setParameter(
            Configuration::DOCTRINE_TYPE_DIRECTORIES_CONFIGURATION_PARAMETER,
            $config[Configuration::DOCTRINE_TYPE_DIRECTORIES_KEY],
        );

        $knownConstructionNormalizerConfig = $config[Configuration::KNOWN_CONSTRUCTION_NORMALIZER_KEY];
        if ($knownConstructionNormalizerConfig[Configuration::KNOWN_CONSTRUCTION_NORMALIZER_ENABLED_KEY]) {
            self::registerKnownConstructionNormalizer($knownConstructionNormalizerConfig, $container);
        }
    }

    /**
     * @param array{
     *   enabled: bool,
     *   supported_class_name_pattern: ?non-empty-string,
     *   supported_parent_types: list<class-string>,
     *   accepted_unplanned_classes: array<string, string>,
     *   accepted_context_keys: list<string>,
     *   neutral_normalizers: list<string>,
     *   source_directories: list<string>,
     *   plan_cache_pool: ?string,
     *   fallback_normalizer: string,
     *   priority: int,
     * } $config
     */
    private static function registerKnownConstructionNormalizer(
        array $config,
        ContainerBuilder $container,
    ): void {
        $planCachePool = $config[Configuration::KNOWN_CONSTRUCTION_NORMALIZER_PLAN_CACHE_POOL_KEY];

        $container->setDefinition(self::PLAN_FACTORY_SERVICE_ID, new Definition(PlanFactory::class, [
            new AbstractArgument('The type extractor of the fallback normalizer, set by the KnownConstructionNormalizerCompilerPass.'),
            $config[Configuration::KNOWN_CONSTRUCTION_NORMALIZER_SUPPORTED_CLASS_NAME_PATTERN_KEY],
            $config[Configuration::KNOWN_CONSTRUCTION_NORMALIZER_SUPPORTED_PARENT_TYPES_KEY],
            new Definition(NormalizerChain::class, [
                new AbstractArgument('The normalizers in front of the known construction normalizer, set by the KnownConstructionNormalizerCompilerPass.'),
                $config[Configuration::KNOWN_CONSTRUCTION_NORMALIZER_NEUTRAL_NORMALIZERS_KEY],
            ]),
        ]));

        // The default context ($defaultContext) isn't set: like for every normalizer, the SerializerPass binds the default context of
        // the serializer to it. The KnownConstructionNormalizerCompilerPass makes sure it's the same as the one of the fallback normalizer.
        $container->setDefinition(self::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID, new Definition(KnownConstructionNormalizer::class, [
            new Reference(self::PLAN_FACTORY_SERVICE_ID),
            new Reference($config[Configuration::KNOWN_CONSTRUCTION_NORMALIZER_FALLBACK_NORMALIZER_KEY]),
            $planCachePool !== null
                ? new Reference($planCachePool, ContainerInterface::NULL_ON_INVALID_REFERENCE)
                : null,
            $planCachePool !== null
                ? new Parameter(self::PLAN_CACHE_VERSION_PARAMETER)
                : 'uncached',
            '$acceptedContextKeys' => $config[Configuration::KNOWN_CONSTRUCTION_NORMALIZER_ACCEPTED_CONTEXT_KEYS_KEY],
        ]))
            ->addTag('serializer.normalizer', [
                'priority' => $config[Configuration::KNOWN_CONSTRUCTION_NORMALIZER_PRIORITY_KEY],
            ]);

        // The command is only available with symfony/console
        if (class_exists(Command::class)) {
            $container->setDefinition(self::LIST_UNPLANNED_CLASSES_COMMAND_SERVICE_ID, new Definition(ListUnplannedClassesCommand::class, [
                new Reference(self::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID),
                $config[Configuration::KNOWN_CONSTRUCTION_NORMALIZER_SOURCE_DIRECTORIES_KEY],
                $config[Configuration::KNOWN_CONSTRUCTION_NORMALIZER_ACCEPTED_UNPLANNED_CLASSES_KEY],
            ]))
                ->addTag('console.command', [
                    'command' => ListUnplannedClassesCommand::NAME,
                ]);
        }
    }
}
