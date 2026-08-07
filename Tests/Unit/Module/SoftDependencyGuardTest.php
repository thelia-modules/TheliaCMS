<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TheliaCMS\Tests\Unit\Module;

use PHPUnit\Framework\TestCase;

/**
 * The module registers a few services only when the module they lean on is
 * installed, behind `class_exists()` / `interface_exists()`.
 *
 * A guard written without importing the class it names silently tests
 * `TheliaCMS\Whatever`, which never exists: the service is then never
 * registered, on any site, and nothing says so. This reads the source rather
 * than the runtime because the whole point is a name that resolves to the
 * wrong namespace at compile time, on a machine where the optional module is
 * absent.
 */
final class SoftDependencyGuardTest extends TestCase
{
    private const string MODULE_FILE = __DIR__.'/../../../TheliaCMS.php';
    private const string TNT_INDEX_FILE = __DIR__.'/../../../Search/Tnt/CmsPageIndex.php';

    public function testEveryGuardNamesAClassOutsideTheModule(): void
    {
        $guards = self::guardedClassesOf(self::MODULE_FILE);

        self::assertNotEmpty($guards, 'The module is expected to guard its optional dependencies.');

        foreach ($guards as $guarded) {
            self::assertStringStartsNotWith(
                'TheliaCMS\\',
                $guarded,
                \sprintf('The guard on "%s" resolves inside the module, so it is always false: import the class it means.', $guarded),
            );
        }
    }

    public function testTheTntSearchIndexIsGuardedOnTheClassItExtends(): void
    {
        self::assertContains(
            self::parentClassOf(self::TNT_INDEX_FILE),
            self::guardedClassesOf(self::MODULE_FILE),
            'The optional TntSearch index has to be guarded on its own parent, the class that is missing without the module.',
        );
    }

    /**
     * @return list<string> fully qualified names passed to class_exists() and
     *                      interface_exists(), resolved through the imports of the file
     */
    private static function guardedClassesOf(string $file): array
    {
        $tokens = self::significantTokens($file);
        $imports = self::importsOf($tokens);
        $namespace = self::namespaceOf($tokens);

        $guarded = [];

        foreach ($tokens as $index => $token) {
            if (!self::isCall($token, ['class_exists', 'interface_exists'])) {
                continue;
            }

            $name = self::nameArgumentAt($tokens, $index);

            if (null !== $name) {
                $guarded[] = self::resolve($name, $imports, $namespace);
            }
        }

        return $guarded;
    }

    private static function parentClassOf(string $file): string
    {
        $tokens = self::significantTokens($file);

        foreach ($tokens as $index => $token) {
            if (\T_EXTENDS !== ($token[0] ?? null)) {
                continue;
            }

            return self::resolve(
                self::nameAt($tokens, $index + 1),
                self::importsOf($tokens),
                self::namespaceOf($tokens),
            );
        }

        self::fail(\sprintf('No parent class found in %s.', $file));
    }

    /** @return list<array{int, string}|string> */
    private static function significantTokens(string $file): array
    {
        $source = file_get_contents($file);
        self::assertIsString($source, \sprintf('Cannot read %s.', $file));

        return array_values(array_filter(
            token_get_all($source),
            static fn ($token) => !\is_array($token)
                || !\in_array($token[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true),
        ));
    }

    /**
     * @param list<array{int, string}|string> $tokens
     *
     * @return array<string, string> alias => fully qualified name
     */
    private static function importsOf(array $tokens): array
    {
        $imports = [];

        foreach ($tokens as $index => $token) {
            if (\T_USE !== ($token[0] ?? null)) {
                continue;
            }

            $name = self::nameAt($tokens, $index + 1);

            if ('' === $name) {
                continue;
            }

            $alias = \T_AS === ($tokens[$index + 2][0] ?? null)
                ? self::nameAt($tokens, $index + 3)
                : substr($name, (int) strrpos('\\'.$name, '\\'));

            $imports[ltrim($alias, '\\')] = ltrim($name, '\\');
        }

        return $imports;
    }

    /** @param list<array{int, string}|string> $tokens */
    private static function namespaceOf(array $tokens): string
    {
        foreach ($tokens as $index => $token) {
            if (\T_NAMESPACE === ($token[0] ?? null)) {
                return self::nameAt($tokens, $index + 1);
            }
        }

        return '';
    }

    /**
     * @param list<string> $functions
     */
    private static function isCall(array|string $token, array $functions): bool
    {
        return \is_array($token)
            && \T_STRING === $token[0]
            && \in_array($token[1], $functions, true);
    }

    /**
     * Reads `class_exists(Some\Name::class)`, and nothing else: a guard written
     * on a string literal or a variable is out of the scope of this check.
     *
     * @param list<array{int, string}|string> $tokens
     */
    private static function nameArgumentAt(array $tokens, int $index): ?string
    {
        if ('(' !== ($tokens[$index + 1] ?? null)) {
            return null;
        }

        $name = self::nameAt($tokens, $index + 2);

        if ('' === $name) {
            return null;
        }

        $after = $index + 3;

        return \T_DOUBLE_COLON === ($tokens[$after][0] ?? null)
            && \T_CLASS === ($tokens[$after + 1][0] ?? null)
                ? $name
                : null;
    }

    /**
     * PHP 8 tokenises a namespaced name as a single token, so a name is at most
     * one token wide here.
     *
     * @param list<array{int, string}|string> $tokens
     */
    private static function nameAt(array $tokens, int $index): string
    {
        $token = $tokens[$index] ?? null;

        return \is_array($token) && \in_array($token[0], [\T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED], true)
            ? $token[1]
            : '';
    }

    /** @param array<string, string> $imports */
    private static function resolve(string $name, array $imports, string $namespace): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $head = strstr($name, '\\', true) ?: $name;

        if (isset($imports[$head])) {
            return $imports[$head].substr($name, \strlen($head));
        }

        return '' === $namespace ? $name : $namespace.'\\'.$name;
    }
}
