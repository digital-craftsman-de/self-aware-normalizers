<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Finder\Finder;

/**
 * Lists the classes of the source directories that are in the scope of the KnownConstructionNormalizer but aren't (fully) on its fast path, with the
 * reason the PlanFactory reports (always the first limitation of a class):
 * 1. Not relevant: interfaces, enums, abstract classes and internal classes, which never reach the PropertyNormalizer as such.
 * 2. Falling back: classes that are (de)normalized by the PropertyNormalizer, and planned classes that are only denormalized through
 *    their plan (mutable property).
 * 3. Delegated parameters: parameters of planned classes whose values are (de)normalized through the serializer, as their class has no plan.
 *
 * The text output (--format=text, the default) is made for terminals of a normal width: the first group is grouped by reason, the
 * second has one block per class with the labeled values, the third is grouped by the class of the value with its reason. Classes are
 * written with their full name (the terminal of the IDE links them), class names within the reasons with their short name; long texts
 * are wrapped to the width of the terminal. --format=json lists the same data unchanged, with full class names.
 *
 * "In scope" (summary and JSON) means the classes that reach the KnownConstructionNormalizer. Self-aware value objects (classes, enums and abstract
 * classes implementing an interface of digital-craftsman/self-aware-normalizers) are always (de)normalized by their own normalizers, so
 * like the classes outside of the scope of the KnownConstructionNormalizer they are never listed as classes (first and second group) nor counted.
 * They can still be the class of the value of a delegated parameter in the third group (e.g. a nested list of them), as those values
 * really go through the serializer.
 *
 * --only-relevant hides the first group and the delegated parameters whose class never gets a plan by design (e.g. uploaded files,
 * entities, interfaces).
 *
 * --fail-on-unplanned exits with a failure when a class of the second group isn't one of the accepted unplanned classes (class
 * => why it falls back by design), and when an accepted class doesn't fall back anymore (planned or removed), so the list doesn't go
 * stale. Delegated parameters never fail: their classes are either out of scope by design or listed in the second group themselves.
 *
 * The classes are discovered from the PHP files of the source directories (every class, interface and enum declaration, also several
 * per file). A class that can't be loaded (e.g. a Rector rule, whose parent class only exists with the dev dependencies) is listed as not
 * loadable and skipped.
 *
 * @psalm-type NotRelevantClass = array{
 *   class: string,
 *   reason: string,
 *   description: string,
 * }
 * @psalm-type FallingBackClass = array{
 *   class: string,
 *   fallsBackFor: string,
 *   reason: string,
 *   member: ?string,
 *   detail: ?string,
 *   description: string,
 *   acceptedBecause: ?string,
 * }
 * @psalm-type NotLoadableClass = array{
 *   class: string,
 *   error: string,
 * }
 * @psalm-type DelegatedParameterOfClass = array{
 *   class: string,
 *   parameter: string,
 *   targetClass: string,
 *   typeSuffix: string,
 *   targetReason: ?string,
 *   targetDescription: ?string,
 * }
 */
#[AsCommand(
    name: self::NAME,
    description: 'Lists the classes in the scope of the KnownConstructionNormalizer that are not (fully) on its fast path, with the reason',
)]
final class ListUnplannedClassesCommand extends Command
{
    public const string NAME = 'self-aware-normalizers:known-construction-normalizer:list-unplanned';

    private const string OPTION_ONLY_RELEVANT = 'only-relevant';
    private const string OPTION_FORMAT = 'format';
    private const string OPTION_FAIL_ON_UNPLANNED = 'fail-on-unplanned';

    private const string FORMAT_TEXT = 'text';
    private const string FORMAT_JSON = 'json';

    private const string FALLS_BACK_FOR_DENORMALIZATION_AND_NORMALIZATION = 'denormalization and normalization';
    private const string FALLS_BACK_FOR_NORMALIZATION = 'normalization';

    private const int MINIMUM_WIDTH_OF_WRAPPED_TEXT = 40;

    /**
     * @param list<string>          $sourceDirectories        Directories whose classes are listed (recursively)
     * @param array<string, string> $acceptedUnplannedClasses Class => why it falls back by design
     */
    public function __construct(
        private readonly KnownConstructionNormalizer $knownConstructionNormalizer,
        private readonly array $sourceDirectories,
        private readonly array $acceptedUnplannedClasses,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption(self::OPTION_ONLY_RELEVANT, null, InputOption::VALUE_NONE, 'Hide the classes that never get a plan by design (interfaces, enums, abstract classes, internal classes) and the delegated parameters whose class never gets a plan by design')
            ->addOption(self::OPTION_FORMAT, null, InputOption::VALUE_REQUIRED, 'Output format (text or json)', self::FORMAT_TEXT)
            ->addOption(self::OPTION_FAIL_ON_UNPLANNED, null, InputOption::VALUE_NONE, 'Exit with a failure when a class that is not accepted falls back to the PropertyNormalizer, or an accepted class does not fall back anymore');
    }

    #[\Override]
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        $format = $input->getOption(self::OPTION_FORMAT);
        if ($format !== self::FORMAT_TEXT
            && $format !== self::FORMAT_JSON
        ) {
            $io->getErrorStyle()->error(sprintf('The format must be "%s" or "%s".', self::FORMAT_TEXT, self::FORMAT_JSON));

            return self::INVALID;
        }

        $isOnlyRelevantShown = (bool) $input->getOption(self::OPTION_ONLY_RELEVANT);

        $numberOfClassesInScope = 0;
        $numberOfPlannedClasses = 0;
        $notLoadableClasses = [];
        $notRelevantClasses = [];
        $fallingBackClasses = [];
        $delegatedParameters = [];
        foreach (self::classesDeclaredInDirectories($this->sourceDirectories) as $class) {
            try {
                $isLoaded = class_exists($class)
                    || interface_exists($class);
            } catch (\Error $error) {
                $notLoadableClasses[] = [
                    'class' => $class,
                    'error' => $error->getMessage(),
                ];

                continue;
            }
            if (!$isLoaded) {
                continue;
            }

            $diagnosis = $this->knownConstructionNormalizer->diagnose($class);
            $limitation = $diagnosis->limitation;
            // Never (de)normalized by the KnownConstructionNormalizer: classes outside of its scope, and self-aware value objects, which are always
            // (de)normalized by their own normalizers. Checked on the reason the PlanFactory decided on, so an enum or abstract class
            // implementing an interface of the self-aware normalizers is left out as well.
            $reason = $limitation?->reason;
            if ($reason === Diagnosis\PlanLimitationReason::NOT_IN_SCOPE
                || $reason === Diagnosis\PlanLimitationReason::SELF_AWARE_VALUE_OBJECT
            ) {
                continue;
            }

            ++$numberOfClassesInScope;

            if ($limitation !== null) {
                if ($limitation->reason->isRelevant()) {
                    $fallingBackClasses[] = $this->fallingBackClass($class, self::FALLS_BACK_FOR_DENORMALIZATION_AND_NORMALIZATION, $limitation);
                } else {
                    $notRelevantClasses[] = [
                        'class' => $class,
                        'reason' => $limitation->reason->value,
                        'description' => $limitation->describe(),
                    ];
                }

                continue;
            }

            ++$numberOfPlannedClasses;

            if ($diagnosis->normalizationLimitation !== null) {
                $fallingBackClasses[] = $this->fallingBackClass($class, self::FALLS_BACK_FOR_NORMALIZATION, $diagnosis->normalizationLimitation);
            }

            foreach ($diagnosis->delegatedParameters as $delegatedParameter) {
                $targetLimitation = $this->knownConstructionNormalizer->diagnose($delegatedParameter->targetClass)->limitation;
                // E.g. an uploaded file or an entity, which never gets a plan
                if ($isOnlyRelevantShown
                    && $targetLimitation?->reason->isRelevant() === false
                ) {
                    continue;
                }

                $delegatedParameters[] = [
                    'class' => $class,
                    'parameter' => $delegatedParameter->name,
                    'targetClass' => $delegatedParameter->targetClass,
                    'typeSuffix' => $delegatedParameter->typeSuffix,
                    'targetReason' => $targetLimitation?->reason->value,
                    'targetDescription' => $targetLimitation?->describe(),
                ];
            }
        }

        $numberOfNotRelevantClasses = count($notRelevantClasses);
        if ($isOnlyRelevantShown) {
            $notRelevantClasses = [];
        }

        // Only iterated and merged, so the keys don't matter
        $notAcceptedFallingBackClasses = array_map(
            static fn (array $fallingBackClass): string => $fallingBackClass['class'],
            array_filter(
                $fallingBackClasses,
                static fn (array $fallingBackClass): bool => $fallingBackClass['acceptedBecause'] === null,
            ),
        );
        $staleAcceptedClasses = array_values(array_diff(
            array_keys($this->acceptedUnplannedClasses),
            array_column($fallingBackClasses, 'class'),
        ));

        if ($format === self::FORMAT_JSON) {
            $output->writeln(json_encode(
                [
                    'numberOfClassesInScope' => $numberOfClassesInScope,
                    'numberOfNotRelevantClasses' => $numberOfNotRelevantClasses,
                    'numberOfPlannedClasses' => $numberOfPlannedClasses,
                    'notLoadableClasses' => $notLoadableClasses,
                    'notRelevantClasses' => $notRelevantClasses,
                    'fallingBackClasses' => $fallingBackClasses,
                    'delegatedParameters' => $delegatedParameters,
                    'staleAcceptedClasses' => $staleAcceptedClasses,
                ],
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            self::writeText(
                $io,
                $numberOfClassesInScope,
                $numberOfNotRelevantClasses,
                $numberOfPlannedClasses,
                $isOnlyRelevantShown,
                $notLoadableClasses,
                $notRelevantClasses,
                $fallingBackClasses,
                $delegatedParameters,
            );
        }

        $isFailure = (bool) $input->getOption(self::OPTION_FAIL_ON_UNPLANNED)
            && (
                $notAcceptedFallingBackClasses !== []
                || $staleAcceptedClasses !== []
            );
        if ($isFailure) {
            self::writeErrors($io, array_merge(
                array_map(
                    static fn (string $class): string => sprintf('%s falls back to the PropertyNormalizer. Put it on the fast path, or add it with the reason to the accepted unplanned classes (self_aware_normalizers.known_construction_normalizer.accepted_unplanned_classes) when it falls back by design.', $class),
                    $notAcceptedFallingBackClasses,
                ),
                array_map(
                    static fn (string $class): string => sprintf('%s is an accepted unplanned class, but does not fall back anymore (planned or removed). Remove it from the accepted unplanned classes (self_aware_normalizers.known_construction_normalizer.accepted_unplanned_classes).', $class),
                    $staleAcceptedClasses,
                ),
            ));
        }

        return $isFailure
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * Like SymfonyStyle::error(), but without wrapping: the block wraps long class names within the name, and a line that ends with a
     * backslash of the namespace escapes the closing tag of the style, which is then printed as "</>".
     *
     * @param list<string> $messages
     */
    private static function writeErrors(
        SymfonyStyle $io,
        array $messages,
    ): void {
        $io->getErrorStyle()->writeln([
            '<error> [ERROR] </error>',
            ...array_map(
                static fn (string $message): string => sprintf(' %s', OutputFormatter::escape($message)),
                $messages,
            ),
            '',
        ]);
    }

    /**
     * @return FallingBackClass
     */
    private function fallingBackClass(
        string $class,
        string $fallsBackFor,
        Diagnosis\PlanLimitation $limitation,
    ): array {
        return [
            'class' => $class,
            'fallsBackFor' => $fallsBackFor,
            'reason' => $limitation->reason->value,
            'member' => $limitation->member,
            'detail' => $limitation->detail,
            'description' => $limitation->describe(),
            'acceptedBecause' => $this->acceptedUnplannedClasses[$class] ?? null,
        ];
    }

    /**
     * @param list<NotLoadableClass>          $notLoadableClasses
     * @param list<NotRelevantClass>          $notRelevantClasses
     * @param list<FallingBackClass>          $fallingBackClasses
     * @param list<DelegatedParameterOfClass> $delegatedParameters
     */
    private static function writeText(
        SymfonyStyle $io,
        int $numberOfClassesInScope,
        int $numberOfNotRelevantClasses,
        int $numberOfPlannedClasses,
        bool $isOnlyRelevantShown,
        array $notLoadableClasses,
        array $notRelevantClasses,
        array $fallingBackClasses,
        array $delegatedParameters,
    ): void {
        $terminalWidth = new Terminal()->getWidth();

        self::writeSummary($io, $numberOfClassesInScope, $numberOfNotRelevantClasses, $numberOfPlannedClasses);
        self::writeNotLoadableClasses($io, $terminalWidth, $notLoadableClasses);
        if (!$isOnlyRelevantShown) {
            self::writeNotRelevantClasses($io, $notRelevantClasses);
        }
        self::writeFallingBackClasses($io, $terminalWidth, $fallingBackClasses);
        self::writeDelegatedParameters($io, $terminalWidth, $delegatedParameters);
    }

    private static function writeSummary(
        SymfonyStyle $io,
        int $numberOfClassesInScope,
        int $numberOfNotRelevantClasses,
        int $numberOfPlannedClasses,
    ): void {
        $io->title('Classes in the scope of the KnownConstructionNormalizer that are not (fully) on its fast path');
        $io->text(sprintf(
            '%d classes in scope (incl. %d not relevant), %d planned.',
            $numberOfClassesInScope,
            $numberOfNotRelevantClasses,
            $numberOfPlannedClasses,
        ));
    }

    /**
     * @param list<NotLoadableClass> $notLoadableClasses
     */
    private static function writeNotLoadableClasses(
        SymfonyStyle $io,
        int $terminalWidth,
        array $notLoadableClasses,
    ): void {
        $io->section('Skipped, as they can not be loaded');
        if ($notLoadableClasses === []) {
            $io->text('None.');
        }
        foreach ($notLoadableClasses as $notLoadableClass) {
            self::writeBlock($io, $terminalWidth, $notLoadableClass['class'], [
                'Error:' => $notLoadableClass['error'],
            ]);
        }
    }

    /**
     * Grouped by reason, in the order of the reasons.
     *
     * @param list<NotRelevantClass> $notRelevantClasses
     */
    private static function writeNotRelevantClasses(
        SymfonyStyle $io,
        array $notRelevantClasses,
    ): void {
        $io->section('1. Not relevant: never get a plan by design');
        if ($notRelevantClasses === []) {
            $io->text('None.');
        }
        foreach (Diagnosis\PlanLimitationReason::cases() as $reason) {
            $classesOfReason = array_filter(
                $notRelevantClasses,
                static fn (array $notRelevantClass): bool => $notRelevantClass['reason'] === $reason->value,
            );
            if ($classesOfReason !== []) {
                $io->writeln(sprintf(' <comment>%s</comment> (%d)', OutputFormatter::escape($reason->description()), count($classesOfReason)));
                foreach ($classesOfReason as $notRelevantClass) {
                    $io->writeln(sprintf('   %s', OutputFormatter::escape($notRelevantClass['class'])));
                }
                $io->newLine();
            }
        }
    }

    /**
     * One block per class.
     *
     * @param list<FallingBackClass> $fallingBackClasses
     */
    private static function writeFallingBackClasses(
        SymfonyStyle $io,
        int $terminalWidth,
        array $fallingBackClasses,
    ): void {
        $io->section('2. Falling back to the PropertyNormalizer');
        if ($fallingBackClasses === []) {
            $io->text('None.');
        }
        foreach ($fallingBackClasses as $fallingBackClass) {
            $values = [
                'Falls back for:' => $fallingBackClass['fallsBackFor'],
                'Reason:' => self::reasonForDisplay($fallingBackClass['reason'], $fallingBackClass['description']),
            ];
            if ($fallingBackClass['acceptedBecause'] !== null) {
                $values['Accepted:'] = $fallingBackClass['acceptedBecause'];
            }
            self::writeBlock($io, $terminalWidth, $fallingBackClass['class'], $values);
        }
    }

    /**
     * Grouped by the class of the value, as many parameters share it (e.g. uploaded files).
     *
     * @param list<DelegatedParameterOfClass> $delegatedParameters
     */
    private static function writeDelegatedParameters(
        SymfonyStyle $io,
        int $terminalWidth,
        array $delegatedParameters,
    ): void {
        $io->section('3. Planned, with parameters (de)normalized through the serializer');
        if ($delegatedParameters === []) {
            $io->text('None.');
        }
        $delegatedParametersByTargetClass = [];
        foreach ($delegatedParameters as $delegatedParameter) {
            $delegatedParametersByTargetClass[$delegatedParameter['targetClass']][] = $delegatedParameter;
        }
        ksort($delegatedParametersByTargetClass);
        foreach ($delegatedParametersByTargetClass as $targetClass => $delegatedParametersOfTargetClass) {
            $io->writeln(sprintf(' <comment>%s</comment>', OutputFormatter::escape($targetClass)));
            // The reason is the one of the class of the value, so the same for every parameter
            $firstDelegatedParameter = $delegatedParametersOfTargetClass[0];
            if ($firstDelegatedParameter['targetReason'] !== null
                && $firstDelegatedParameter['targetDescription'] !== null
            ) {
                self::writeWrapped($io, $terminalWidth, ' ', sprintf(
                    '(%s)',
                    self::reasonForDisplay($firstDelegatedParameter['targetReason'], $firstDelegatedParameter['targetDescription']),
                ));
            }
            foreach ($delegatedParametersOfTargetClass as $delegatedParameter) {
                // The full name of the class, so the terminal of the IDE links it
                $io->writeln(OutputFormatter::escape(sprintf(
                    '   %s::$%s%s',
                    $delegatedParameter['class'],
                    $delegatedParameter['parameter'],
                    $delegatedParameter['typeSuffix'] !== ''
                        ? sprintf(' (%s%s)', self::withShortClassNames($targetClass), $delegatedParameter['typeSuffix'])
                        : '',
                )));
            }
            $io->newLine();
        }
    }

    /**
     * The class with its full name (so the terminal of the IDE links it), followed by the labeled values, each wrapped to the width of
     * the terminal and aligned to the column of the values.
     *
     * @param non-empty-array<string, string> $values Label => value
     */
    private static function writeBlock(
        SymfonyStyle $io,
        int $terminalWidth,
        string $class,
        array $values,
    ): void {
        $io->writeln(sprintf(' <info>%s</info>', OutputFormatter::escape($class)));

        $labelWidth = max(array_map(strlen(...), array_keys($values))) + 2;
        foreach ($values as $label => $value) {
            self::writeWrapped($io, $terminalWidth, sprintf('   %s', str_pad($label, $labelWidth)), $value);
        }
        $io->newLine();
    }

    /**
     * The text wrapped to the width of the terminal, the first line after the prefix and the following ones indented to the same column.
     * Words longer than a line (e.g. a class name) aren't cut.
     */
    private static function writeWrapped(
        SymfonyStyle $io,
        int $terminalWidth,
        string $prefix,
        string $text,
    ): void {
        $indentation = strlen($prefix);
        // One column less than the terminal, as a line filling it completely is followed by an empty line in some terminals
        $width = max($terminalWidth - $indentation - 1, self::MINIMUM_WIDTH_OF_WRAPPED_TEXT);
        foreach (explode("\n", wordwrap($text, $width)) as $index => $line) {
            $io->writeln(sprintf(
                '%s%s',
                $index === 0
                    ? $prefix
                    : str_repeat(' ', $indentation),
                OutputFormatter::escape($line),
            ));
        }
    }

    /**
     * The description of a limitation with the class names shortened, except for a type that can't be resolved: its detail is the
     * message of the exception, in which e.g. a typo in a namespace must stay visible.
     */
    private static function reasonForDisplay(
        string $reason,
        string $description,
    ): string {
        return $reason === Diagnosis\PlanLimitationReason::TYPE_IS_NOT_RESOLVABLE->value
            ? $description
            : self::withShortClassNames($description);
    }

    /**
     * The class names within a text (e.g. "declared in App\…\BaseReadModel", also fully qualified with a leading backslash) shortened to
     * the short name, to keep the reasons short.
     */
    private static function withShortClassNames(string $text): string
    {
        return (string) preg_replace('/(?:\\\\(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*|\b(?:[A-Za-z_][A-Za-z0-9_]*\\\\)+)([A-Za-z_][A-Za-z0-9_]*)/', '$1', $text);
    }

    /**
     * Every class, interface and enum declared in the PHP files of the directories, sorted. Not loaded yet.
     *
     * @param list<string> $directories
     *
     * @return list<string>
     */
    private static function classesDeclaredInDirectories(array $directories): array
    {
        if ($directories === []) {
            return [];
        }

        $classes = [];
        foreach (new Finder()->files()->in($directories)->name('*.php') as $file) {
            $tokens = array_values(array_filter(
                \PhpToken::tokenize($file->getContents()),
                static fn (\PhpToken $token): bool => !$token->isIgnorable(),
            ));

            $namespace = '';
            foreach ($tokens as $index => $token) {
                $nextToken = $tokens[$index + 1] ?? null;
                if ($token->is(T_NAMESPACE)) {
                    // A block without name (namespace { … }) is the global namespace
                    $namespace = $nextToken !== null
                        && $nextToken->is([T_NAME_QUALIFIED, T_STRING])
                        ? $nextToken->text
                        : '';

                    continue;
                }

                // A declaration is followed by its name, unlike ::class and an anonymous class (new class, new readonly class)
                if ($token->is([T_CLASS, T_INTERFACE, T_ENUM])
                    && $nextToken !== null
                    && $nextToken->is(T_STRING)
                ) {
                    $classes[] = ltrim(sprintf('%s\\%s', $namespace, $nextToken->text), '\\');
                }
            }
        }

        // A class is only declared once, unique is a guard. Sorting renumbers the keys.
        $classes = array_unique($classes);
        sort($classes);

        return $classes;
    }
}
