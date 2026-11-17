<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Debug;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Diagnosis\PlanLimitationReason;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\KnownConstructionNormalizer;
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
                    $limitation = $diagnosis->limitation ?? $diagnosis->normalizationLimitation;
                    if ($limitation === null) {
                        continue;
                    }

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
                $delegated[] = [
                    'class' => $class,
                    'parameter' => $parameter->name,
                    'type' => $parameter->targetClass.$parameter->typeSuffix,
                    'reason' => $this->knownConstructionNormalizer->diagnose($parameter->targetClass)->limitation?->describe() ?? 'planned, but referencing the class itself',
                ];
            }
            foreach ($diagnosis->valueObjectParameters as $valueObjectClass) {
                $valueObjectClasses[$valueObjectClass] = $valueObjectClass;
            }
        }
        sort($valueObjectClasses);

        $plansFromMemory = array_values(array_diff($plannedClasses, array_keys($recorded['builtPlans']), $recorded['plansLoadedFromPool']));

        $this->data = [
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
        return $this->data['plans'] ?? [
            'built' => [],
            'loadedFromPool' => [],
            'fromMemory' => [],
        ];
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
