<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Debug;

use BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Diagnosis\PlanLimitation;
use BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Diagnosis\PlanLimitationReason;
use BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\KnownConstructionNormalizer;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Profiler panel "Self-Aware Normalizers": which classes of the request took the fast path of the KnownConstructionNormalizer and why the
 * others didn't. The Recorder only records class names and reasons while (de)normalizing, everything else is derived here from the plans
 * (through KnownConstructionNormalizer::diagnose()) when the profile is collected.
 *
 * Only registered in debug mode with the profiler (see KnownConstructionNormalizerProfilerPass).
 *
 * @psalm-type MissedRow = array{
 *   class: string,
 *   direction: string,
 *   count: int,
 *   kind: string,
 *   reason: string,
 *   hint: ?string,
 * }
 * @psalm-type Configuration = array{
 *   fallbackNormalizer?: string,
 *   fallbackPriority?: int,
 *   priority?: int,
 *   planCachePool?: ?string,
 *   isPlanCachePoolDefined?: bool,
 *   normalizers?: list<array{id: string, priority: int, position: string, isNeutral: bool}>,
 * }
 */
final class KnownConstructionNormalizerDataCollector extends AbstractDataCollector
{
    public const string NAME = 'self_aware_normalizers';
    public const string TEMPLATE = '@SelfAwareNormalizers/Collector/known_construction_normalizer.html.twig';

    /**
     * Namespaces of the mapping attributes of the serializer (Annotation is the one before Symfony 7).
     */
    private const array SERIALIZER_MAPPING_NAMESPACES = [
        'Symfony\\Component\\Serializer\\Attribute\\',
        'Symfony\\Component\\Serializer\\Annotation\\',
    ];

    private const array NO_PLANS = [
        'built' => [],
        'loadedFromPool' => [],
        'fromMemory' => [],
    ];

    public const string KIND_RUNTIME_FALLBACK = 'runtime fallback';
    public const string KIND_NO_PLAN = 'no plan';
    public const string KIND_CLAIMED = 'claimed by another normalizer';
    public const string KIND_NOT_SUPPORTED = 'not supported';

    /**
     * @param Configuration $configuration Set by the KnownConstructionNormalizerCompilerPass
     */
    public function __construct(
        private readonly Recorder $recorder,
        private readonly KnownConstructionNormalizer $knownConstructionNormalizer,
        private readonly array $configuration = [],
    ) {
    }

    #[\Override]
    public function collect(
        Request $request,
        Response $response,
        ?\Throwable $exception = null,
    ): void {
        $recorded = $this->recorder->recorded();

        $summary = [];
        foreach ([Recorder::NORMALIZE, Recorder::DENORMALIZE] as $direction) {
            $summary[$direction] = [
                'plan' => array_sum($recorded['handledByPlan'][$direction] ?? []),
                'fallback' => array_sum(array_map(array_sum(...), $recorded['fallbacks'][$direction] ?? [])),
                'notHandled' => array_sum(array_map(array_sum(...), $recorded['notSupported'][$direction] ?? [])),
            ];
        }

        $missed = [];
        $byDesign = [];
        foreach ($recorded['fallbacks'] as $direction => $classes) {
            foreach ($classes as $class => $reasons) {
                foreach ($reasons as $reason => $count) {
                    $missed[] = self::row($class, $direction, $count, self::KIND_RUNTIME_FALLBACK, $reason, null);
                }
            }
        }
        foreach ($recorded['notSupported'] as $direction => $classes) {
            foreach ($classes as $class => $reasons) {
                foreach ($reasons as $reason => $count) {
                    if ($reason !== Recorder::REASON_NO_PLAN) {
                        $missed[] = self::row($class, $direction, $count, self::KIND_NOT_SUPPORTED, $reason, null);
                        continue;
                    }

                    $diagnosis = $this->knownConstructionNormalizer->diagnose($class);
                    /**
                     * @var PlanLimitation $limitation Recorded as without plan, so there is a limitation (for the normalization at least)
                     */
                    $limitation = $diagnosis->limitation ?? $diagnosis->normalizationLimitation;

                    $isClaimed = $limitation->reason === PlanLimitationReason::CLAIMED_BY_OTHER_NORMALIZER;
                    $row = self::row(
                        $class,
                        $direction,
                        $count,
                        $isClaimed
                            ? self::KIND_CLAIMED
                            : self::KIND_NO_PLAN,
                        $limitation->describe(),
                        $limitation->reason->fixHint(),
                    );
                    if (!$isClaimed
                        && $limitation->reason->isByDesign()
                    ) {
                        $byDesign[] = $row;
                    } else {
                        $missed[] = $row;
                    }
                }
            }
        }

        $plannedClasses = array_unique(array_merge(
            array_keys($recorded['handledByPlan'][Recorder::NORMALIZE] ?? []),
            array_keys($recorded['handledByPlan'][Recorder::DENORMALIZE] ?? []),
        ));
        sort($plannedClasses);

        $delegated = [];
        $valueObjectClasses = [];
        foreach ($plannedClasses as $class) {
            $diagnosis = $this->knownConstructionNormalizer->diagnose($class);
            foreach ($diagnosis->delegatedParameters as $parameter) {
                /**
                 * @var PlanLimitation $limitationOfTargetClass Delegated, as the target class has no plan (a class of a recursion has none either)
                 */
                $limitationOfTargetClass = $this->knownConstructionNormalizer->diagnose($parameter->targetClass)->limitation;
                $delegated[] = [
                    'class' => $class,
                    'parameter' => $parameter->name,
                    'type' => $parameter->targetClass.$parameter->typeSuffix,
                    'reason' => $limitationOfTargetClass->describe(),
                ];
            }
            foreach ($diagnosis->fallbackParameters as $parameter => $reason) {
                $delegated[] = [
                    'class' => $class,
                    'parameter' => $parameter,
                    'type' => 'union',
                    'reason' => $reason,
                ];
            }
            foreach ($diagnosis->valueObjectParameters as $valueObjectClass) {
                $valueObjectClasses[$valueObjectClass] = $valueObjectClass;
            }
        }
        sort($valueObjectClasses);

        $plansFromMemory = array_values(array_diff($plannedClasses, array_keys($recorded['builtPlans']), $recorded['plansLoadedFromPool']));

        $recordedClasses = array_unique(array_merge(
            ...array_map(array_keys(...), array_values($recorded['handledByPlan'])),
            ...array_map(array_keys(...), array_values($recorded['fallbacks'])),
            ...array_map(array_keys(...), array_values($recorded['notSupported'])),
        ));
        sort($recordedClasses);

        $this->data = [
            'ignoredMapping' => self::serializerMapping($recordedClasses),
            'summary' => $summary,
            'missed' => $missed,
            'byDesign' => $byDesign,
            'delegated' => $delegated,
            'valueObjectClasses' => $valueObjectClasses,
            'plans' => [
                'built' => $recorded['builtPlans'],
                'loadedFromPool' => $recorded['plansLoadedFromPool'],
                'fromMemory' => $plansFromMemory,
            ],
            'configuration' => $this->configuration,
            'acceptedContextOptions' => array_map(
                static fn (?bool $acceptedValue): string => $acceptedValue === null
                    ? 'any value'
                    : var_export($acceptedValue, true),
                $this->knownConstructionNormalizer->acceptedContextOptions(),
            ),
        ];
    }

    /**
     * The serializer mapping attributes on the classes, their properties and methods: the fallback PropertyNormalizer is built without
     * class metadata factory (and the KnownConstructionNormalizer reads no mapping either), so they are ignored.
     *
     * @param list<string> $classes
     *
     * @return list<array{class: string, member: string, attribute: string}>
     */
    private static function serializerMapping(array $classes): array
    {
        $mapping = [];
        // The serializer only asks the KnownConstructionNormalizer for existing classes and interfaces
        /** @var class-string $class */
        foreach ($classes as $class) {
            $reflectionClass = new \ReflectionClass($class);
            $members = [
                'class' => $reflectionClass,
            ];
            foreach ($reflectionClass->getProperties() as $property) {
                $members[sprintf('$%s', $property->name)] = $property;
            }
            foreach ($reflectionClass->getMethods() as $method) {
                $members[sprintf('%s()', $method->name)] = $method;
            }

            foreach ($members as $member => $reflection) {
                foreach ($reflection->getAttributes() as $attribute) {
                    if (self::isSerializerMappingAttribute($attribute->getName())) {
                        $mapping[] = [
                            'class' => $class,
                            'member' => $member,
                            'attribute' => sprintf('#[%s]', $attribute->getName()),
                        ];
                    }
                }
            }
        }

        return $mapping;
    }

    private static function isSerializerMappingAttribute(string $attribute): bool
    {
        foreach (self::SERIALIZER_MAPPING_NAMESPACES as $namespace) {
            if (str_starts_with($attribute, $namespace)) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function reset(): void
    {
        parent::reset();
        $this->recorder->reset();
    }

    #[\Override]
    public function getName(): string
    {
        return self::NAME;
    }

    #[\Override]
    public static function getTemplate(): string
    {
        return self::TEMPLATE;
    }

    /**
     * Objects (de)normalized through a plan.
     */
    public function getFastCount(): int
    {
        return array_sum(array_column($this->summary(), 'plan'));
    }

    /**
     * Objects that missed the fast path although they could take it (runtime fallbacks, classes without plan that could get one,
     * unsupported contexts and formats). Classes without plan by design and classes of other normalizers aren't counted.
     */
    public function getFallbackCount(): int
    {
        return array_sum(array_map(
            static fn (array $row): int => $row['kind'] === self::KIND_CLAIMED
                ? 0
                : $row['count'],
            $this->getMissed(),
        ));
    }

    /**
     * @return list<array{class: string, member: string, attribute: string}>
     */
    public function getIgnoredMapping(): array
    {
        /** @var list<array{class: string, member: string, attribute: string}> */
        return $this->data['ignoredMapping'] ?? [];
    }

    /**
     * Whether the toolbar and the menu show a warning: objects that missed the fast path or ignored serializer mapping.
     */
    public function hasWarning(): bool
    {
        return $this->getFallbackCount() > 0
            || $this->getIgnoredMapping() !== [];
    }

    /**
     * @return array<string, array{plan: int, fallback: int, notHandled: int}>
     */
    public function getSummary(): array
    {
        return $this->summary();
    }

    /**
     * @return list<MissedRow>
     */
    public function getMissed(): array
    {
        /** @var list<MissedRow> */
        return $this->data['missed'] ?? [];
    }

    /**
     * @return list<MissedRow>
     */
    public function getByDesign(): array
    {
        /** @var list<MissedRow> */
        return $this->data['byDesign'] ?? [];
    }

    /**
     * @return list<array{class: string, parameter: string, type: string, reason: string}>
     */
    public function getDelegated(): array
    {
        /** @var list<array{class: string, parameter: string, type: string, reason: string}> */
        return $this->data['delegated'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getValueObjectClasses(): array
    {
        /** @var list<string> */
        return $this->data['valueObjectClasses'] ?? [];
    }

    /**
     * @return array{built: array<string, float>, loadedFromPool: list<string>, fromMemory: list<string>}
     */
    public function getPlans(): array
    {
        /** @var array{built: array<string, float>, loadedFromPool: list<string>, fromMemory: list<string>} */
        return $this->data['plans'] ?? self::NO_PLANS;
    }

    /**
     * @return Configuration
     */
    public function getConfiguration(): array
    {
        /** @var Configuration */
        return $this->data['configuration'] ?? [];
    }

    /**
     * @return array<string, string> Option => accepted value
     */
    public function getAcceptedContextOptions(): array
    {
        /** @var array<string, string> */
        return $this->data['acceptedContextOptions'] ?? [];
    }

    /**
     * @return array<string, array{plan: int, fallback: int, notHandled: int}>
     */
    private function summary(): array
    {
        /** @var array<string, array{plan: int, fallback: int, notHandled: int}> */
        return $this->data['summary'] ?? [];
    }

    /**
     * @return MissedRow
     */
    private static function row(
        string $class,
        string $direction,
        int $count,
        string $kind,
        string $reason,
        ?string $hint,
    ): array {
        return [
            'class' => $class,
            'direction' => $direction,
            'count' => $count,
            'kind' => $kind,
            'reason' => $reason,
            'hint' => $hint,
        ];
    }
}
