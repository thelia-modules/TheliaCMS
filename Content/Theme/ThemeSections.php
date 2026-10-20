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

namespace TheliaCMS\Content\Theme;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * What a front-office theme says about the components a page may be made of.
 *
 * A theme opens its components to the pages of the CMS by naming them in
 * `config/theliacms.yaml`, the way a page would write them:
 *
 *     sections:
 *         components:
 *             - Molecules:Reinsurance:Base
 *             - Organisms:ArticleCard:Base
 *         hidden:
 *             - Partial:latest-contents
 *
 * `hidden` names the sections of the module the theme puts away, because it
 * opens a component of its own for the same thing.
 *
 * Nothing is opened by default: a component of a shop — a cart line, a
 * checkout step — makes no sense on a page, and the theme is the one that
 * knows which ones do. A child theme inherits the list of its parent unless it
 * declares its own; the nearest declaration wins whole.
 */
final readonly class ThemeSections
{
    public const string FILE_NAME = 'config/theliacms.yaml';

    /**
     * @param list<string> $components
     * @param list<string> $hidden
     */
    public function __construct(
        public array $components = [],
        public array $hidden = [],
    ) {
    }

    /**
     * @param list<string> $themeDirectories absolute paths, the active theme first, then its parents
     *
     * @throws \InvalidArgumentException when a declaration is there but cannot be read
     */
    public static function declaredIn(array $themeDirectories): self
    {
        foreach ($themeDirectories as $directory) {
            $declared = self::readFrom($directory);

            if (null !== $declared) {
                return new self($declared['components'], $declared['hidden']);
            }
        }

        return new self();
    }

    /**
     * @return array{components: list<string>, hidden: list<string>}|null null when the theme declares nothing
     */
    private static function readFrom(string $themeDirectory): ?array
    {
        $file = rtrim($themeDirectory, '/\\').\DIRECTORY_SEPARATOR.self::FILE_NAME;

        if (!is_file($file)) {
            return null;
        }

        try {
            $content = Yaml::parseFile($file);
        } catch (ParseException $exception) {
            throw new \InvalidArgumentException(\sprintf('%s is not a valid YAML file: %s', $file, $exception->getMessage()), 0, $exception);
        }

        if (!\is_array($content) || !\array_key_exists('sections', $content)) {
            return null;
        }

        $sections = $content['sections'];

        if (!\is_array($sections) || !\array_key_exists('components', $sections)) {
            throw new \InvalidArgumentException(\sprintf('The "sections" key of %s must hold a "components" list.', $file));
        }

        return [
            'components' => self::names($sections['components'] ?? [], 'sections.components', $file),
            'hidden' => self::names($sections['hidden'] ?? [], 'sections.hidden', $file),
        ];
    }

    /**
     * @return list<string>
     */
    private static function names(mixed $names, string $key, string $file): array
    {
        if (null === $names) {
            return [];
        }

        if (!\is_array($names) || !array_is_list($names)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" key of %s must be a list of component names.', $key, $file));
        }

        foreach ($names as $name) {
            if (!\is_string($name) || 1 !== preg_match('/^[A-Za-z0-9_]+(:[A-Za-z0-9_-]+)+$/', $name)) {
                throw new \InvalidArgumentException(\sprintf('"%s" in %s is not a component name such as Molecules:Button:Base.', \is_scalar($name) ? (string) $name : \gettype($name), $file));
            }
        }

        return array_values(array_unique($names));
    }
}
