<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer\AuditedVersions;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer\ListUnplannedClassesCommand;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer\PlanFactory;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer\PlanNormalizer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
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
    public const string PLAN_NORMALIZER_SERVICE_ID = PlanNormalizer::class;
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
         *   plan_normalizer: array{
         *     enabled: bool,
         *     supported_class_name_pattern: ?non-empty-string,
         *     supported_parent_types: list<class-string>,
         *     accepted_unplanned_classes: array<string, string>,
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

        $planNormalizerConfig = $config[Configuration::PLAN_NORMALIZER_KEY];
        if ($planNormalizerConfig[Configuration::PLAN_NORMALIZER_ENABLED_KEY]) {
            self::registerPlanNormalizer($planNormalizerConfig, $container);
        }
    }

    /**
     * @param array{
     *   enabled: bool,
     *   supported_class_name_pattern: ?non-empty-string,
     *   supported_parent_types: list<class-string>,
     *   accepted_unplanned_classes: array<string, string>,
     *   source_directories: list<string>,
     *   plan_cache_pool: ?string,
     *   fallback_normalizer: string,
     *   priority: int,
     * } $config
     */
    private static function registerPlanNormalizer(
        array $config,
        ContainerBuilder $container,
    ): void {
        $violations = AuditedVersions::violations();
        if ($violations !== []) {
            throw new LogicException(sprintf(
                'The plan normalizer of digital-craftsman/self-aware-normalizers (self_aware_normalizers.plan_normalizer) mirrors internals of the serializer and can only be enabled with the versions it was audited against: %s. Disable it or update digital-craftsman/self-aware-normalizers.',
                implode('; ', $violations),
            ));
        }

        $planCachePool = $config[Configuration::PLAN_NORMALIZER_PLAN_CACHE_POOL_KEY];

        $container->setDefinition(self::PLAN_FACTORY_SERVICE_ID, new Definition(PlanFactory::class, [
            new AbstractArgument('The type extractor of the fallback normalizer, set by the PlanNormalizerCompilerPass.'),
            $config[Configuration::PLAN_NORMALIZER_SUPPORTED_CLASS_NAME_PATTERN_KEY],
            $config[Configuration::PLAN_NORMALIZER_SUPPORTED_PARENT_TYPES_KEY],
        ]));

        // The default context ($defaultContext) isn't set: like for every normalizer, the SerializerPass binds the default context of
        // the serializer to it. The PlanNormalizerCompilerPass makes sure it's the same as the one of the fallback normalizer.
        $container->setDefinition(self::PLAN_NORMALIZER_SERVICE_ID, new Definition(PlanNormalizer::class, [
            new Reference(self::PLAN_FACTORY_SERVICE_ID),
            new Reference($config[Configuration::PLAN_NORMALIZER_FALLBACK_NORMALIZER_KEY]),
            $planCachePool !== null
                ? new Reference($planCachePool, ContainerInterface::NULL_ON_INVALID_REFERENCE)
                : null,
            $planCachePool !== null
                ? new Parameter(self::PLAN_CACHE_VERSION_PARAMETER)
                : 'uncached',
        ]))
            ->addTag('serializer.normalizer', [
                'priority' => $config[Configuration::PLAN_NORMALIZER_PRIORITY_KEY],
            ]);

        // The command is only available with symfony/console
        if (class_exists(Command::class)) {
            $container->setDefinition(self::LIST_UNPLANNED_CLASSES_COMMAND_SERVICE_ID, new Definition(ListUnplannedClassesCommand::class, [
                new Reference(self::PLAN_NORMALIZER_SERVICE_ID),
                $config[Configuration::PLAN_NORMALIZER_SOURCE_DIRECTORIES_KEY],
                $config[Configuration::PLAN_NORMALIZER_ACCEPTED_UNPLANNED_CLASSES_KEY],
            ]))
                ->addTag('console.command', [
                    'command' => ListUnplannedClassesCommand::NAME,
                ]);
        }
    }
}
