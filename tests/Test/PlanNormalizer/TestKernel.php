<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer;

use DigitalCraftsman\SelfAwareNormalizers\SelfAwareNormalizersBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;

/**
 * Kernel of an app using the plan normalizer like the README describes it: the PropertyNormalizer without serializer mapping as fallback
 * (priority -50), the BackedEnumNormalizer in front of it (priority -40) and the fixture classes in a ReadModel namespace as scope.
 *
 * Variants of the configuration (e.g. an invalid fallback) are applied through $configure, each with an environment name of its own,
 * as the compiled container is cached per environment.
 */
final class TestKernel extends Kernel
{
    public const string FALLBACK_NORMALIZER_ID = 'serializer.normalizer.property_normalizer';
    public const string TYPE_EXTRACTOR_ID = 'app.property_normalizer_type_extractor';
    public const string PLAN_CACHE_POOL_ID = 'cache.plan_normalizer_plans';
    public const string SUPPORTED_CLASS_NAME_PATTERN = '/\\\\Fixture\\\\ReadModel\\\\/';

    /**
     * Unique per test run, so a container of an earlier run (with other code) is never reused.
     */
    private static ?string $runDirectory = null;

    /**
     * @param \Closure(ContainerBuilder): void|null $configure Changes of the configuration (the extension configs are loaded before)
     */
    public function __construct(
        string $environment = 'test',
        bool $debug = true,
        private readonly ?\Closure $configure = null,
        /**
         * @var array<string, mixed>
         */
        private readonly array $planNormalizerConfig = [],
    ) {
        parent::__construct($environment, $debug);
    }

    /**
     * @return iterable<FrameworkBundle|SelfAwareNormalizersBundle>
     */
    #[\Override]
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new SelfAwareNormalizersBundle(),
        ];
    }

    #[\Override]
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'test' => true,
                'secret' => 'test',
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => [
                    'log' => true,
                ],
                'serializer' => [
                    'enabled' => true,
                    'default_context' => [
                        'skip_null_values' => false,
                        'preserve_empty_objects' => true,
                    ],
                ],
                'property_info' => [
                    'enabled' => true,
                ],
                'cache' => [
                    'pools' => [
                        self::PLAN_CACHE_POOL_ID => [
                            'adapter' => 'cache.adapter.array',
                        ],
                    ],
                ],
            ]);

            $container->loadFromExtension('self_aware_normalizers', [
                'plan_normalizer' => $this->planNormalizerConfig + [
                    'enabled' => true,
                    'supported_class_name_pattern' => self::SUPPORTED_CLASS_NAME_PATTERN,
                    'source_directories' => [
                        __DIR__.'/Fixture',
                    ],
                ],
            ]);

            // Normally set by the DoctrineBundle
            $container->setParameter('doctrine.dbal.connection_factory.types', []);

            $container->setDefinition(PhpDocExtractor::class, new Definition(PhpDocExtractor::class));
            $container->setDefinition(self::TYPE_EXTRACTOR_ID, new Definition(PropertyInfoExtractor::class, [
                '$typeExtractors' => [
                    new Reference(PhpDocExtractor::class),
                    new Reference('property_info.reflection_extractor'),
                ],
            ]));

            $container->setDefinition(self::FALLBACK_NORMALIZER_ID, new Definition(PropertyNormalizer::class, [
                '$classMetadataFactory' => null,
                '$nameConverter' => null,
                '$propertyTypeExtractor' => new Reference(self::TYPE_EXTRACTOR_ID),
                '$classDiscriminatorResolver' => null,
            ]))
                ->setAutowired(true)
                ->addTag('serializer.normalizer', [
                    'priority' => -50,
                ]);

            $container->setDefinition('serializer.normalizer.backed_enum', new Definition(BackedEnumNormalizer::class))
                ->addTag('serializer.normalizer', [
                    'priority' => -40,
                ]);

            if ($this->configure !== null) {
                ($this->configure)($container);
            }
        });
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return dirname(__DIR__, 3);
    }

    #[\Override]
    public function getCacheDir(): string
    {
        return sprintf('%s/cache/%s', self::runDirectory(), $this->environment);
    }

    #[\Override]
    public function getLogDir(): string
    {
        return sprintf('%s/log', self::runDirectory());
    }

    private static function runDirectory(): string
    {
        return self::$runDirectory ??= sprintf('%s/self-aware-normalizers-%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));
    }
}
