<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

/**
 * Finds the fully qualified names of classes and enums declared in PHP files of a directory (recursively).
 * The files are parsed through tokens, so they aren't loaded.
 *
 * @internal
 */
final readonly class ConstructFinder
{
    /**
     * @return list<class-string>
     */
    public static function findClassNames(string $directory): array
    {
        return self::findNamesOfConstruct($directory, T_CLASS);
    }

    /**
     * @return list<class-string>
     */
    public static function findEnumNames(string $directory): array
    {
        return self::findNamesOfConstruct($directory, T_ENUM);
    }

    /**
     * @return list<class-string>
     */
    private static function findNamesOfConstruct(string $directory, int $constructTokenId): array
    {
        $names = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            foreach (self::findNamesOfConstructInFile($file->getPathname(), $constructTokenId) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return list<class-string>
     */
    private static function findNamesOfConstructInFile(string $path, int $constructTokenId): array
    {
        $tokens = array_values(array_filter(
            \PhpToken::tokenize((string) file_get_contents($path), TOKEN_PARSE),
            static fn (\PhpToken $token): bool => !$token->isIgnorable(),
        ));

        $names = [];
        $namespace = '';

        // In valid PHP, the keywords namespace, class and enum are always followed by another token.
        foreach ($tokens as $index => $token) {
            if ($token->id === T_NAMESPACE) {
                $nextToken = $tokens[$index + 1];

                // A namespace without name (namespace { ... }) is the global namespace.
                $namespace = $nextToken->is([T_STRING, T_NAME_QUALIFIED])
                    ? $nextToken->text.'\\'
                    : '';

                continue;
            }

            if ($token->id !== $constructTokenId) {
                continue;
            }

            // Only declarations are followed directly by a name. This excludes anonymous classes (new class,
            // new readonly class) and class name resolution (Foo::class).
            $nextToken = $tokens[$index + 1];
            if ($nextToken->id === T_STRING) {
                /** @var class-string $name */
                $name = $namespace.$nextToken->text;
                $names[] = $name;
            }
        }

        return $names;
    }
}
