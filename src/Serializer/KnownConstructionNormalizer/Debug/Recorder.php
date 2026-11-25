<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Debug;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Records how the KnownConstructionNormalizer handled the classes of a request, for the profiler (KnownConstructionNormalizerDataCollector).
 * Only registered in debug mode with the profiler: without it, the KnownConstructionNormalizer has no recorder and does no additional work
 * besides checking for it. Only the top level classes are recorded (nested classes are part of their plan), and only names and reasons,
 * the collector derives everything else from the plans when it collects.
 */
final class Recorder implements ResetInterface
{
    public const string NORMALIZE = 'normalize';
    public const string DENORMALIZE = 'denormalize';

    /**
     * Reason of a class without plan, which the collector diagnoses.
     */
    public const string REASON_NO_PLAN = 'no plan';

    /**
     * Direction => class => number of objects handled by the plan.
     *
     * @var array<string, array<string, int>>
     */
    private array $handledByPlan = [];

    /**
     * Direction => class => reason => number of objects the plan was tried for, but that fell back to the fallback normalizer.
     *
     * @var array<string, array<string, array<string, int>>>
     */
    private array $fallbacks = [];

    /**
     * Direction => class => reason => number of supports*() calls the KnownConstructionNormalizer declined.
     *
     * @var array<string, array<string, array<string, int>>>
     */
    private array $notSupported = [];

    /**
     * Class => milliseconds it took to build the plan, for the plans built in the request.
     *
     * @var array<string, float>
     */
    private array $builtPlans = [];

    /**
     * Class => class, for the plans loaded from the pool in the request.
     *
     * @var array<string, string>
     */
    private array $plansLoadedFromPool = [];

    public function handledByPlan(
        string $direction,
        string $class,
    ): void {
        $this->handledByPlan[$direction][$class] = ($this->handledByPlan[$direction][$class] ?? 0) + 1;
    }

    public function fellBack(
        string $direction,
        string $class,
        string $reason,
    ): void {
        $this->fallbacks[$direction][$class][$reason] = ($this->fallbacks[$direction][$class][$reason] ?? 0) + 1;
    }

    public function notSupported(
        string $direction,
        string $class,
        string $reason,
    ): void {
        $this->notSupported[$direction][$class][$reason] = ($this->notSupported[$direction][$class][$reason] ?? 0) + 1;
    }

    public function planBuilt(
        string $class,
        float $milliseconds,
    ): void {
        $this->builtPlans[$class] = $milliseconds;
    }

    public function planLoadedFromPool(string $class): void
    {
        $this->plansLoadedFromPool[$class] = $class;
    }

    /**
     * @return array{
     *   handledByPlan: array<string, array<string, int>>,
     *   fallbacks: array<string, array<string, array<string, int>>>,
     *   notSupported: array<string, array<string, array<string, int>>>,
     *   builtPlans: array<string, float>,
     *   plansLoadedFromPool: list<string>,
     * }
     */
    public function recorded(): array
    {
        return [
            'handledByPlan' => $this->handledByPlan,
            'fallbacks' => $this->fallbacks,
            'notSupported' => $this->notSupported,
            'builtPlans' => $this->builtPlans,
            'plansLoadedFromPool' => array_values($this->plansLoadedFromPool),
        ];
    }

    #[\Override]
    public function reset(): void
    {
        $this->handledByPlan = [];
        $this->fallbacks = [];
        $this->notSupported = [];
        $this->builtPlans = [];
        $this->plansLoadedFromPool = [];
    }
}
