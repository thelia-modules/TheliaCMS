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

namespace TheliaCMS\Builder;

use Symfony\Component\Asset\Packages;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * What a front-office theme says about the canvas of the editor.
 *
 * The canvas is an iframe: it only looks like the published page if it loads
 * the stylesheets the theme loads and carries the classes the theme puts
 * around the content. A theme whose CSS hangs on a class of `<html>`, or on
 * the container its page template wraps the content in, cannot be previewed
 * with one stylesheet and the `cms-page-content` wrapper alone. It says so in
 * `config/theliacms.yaml`:
 *
 *     canvas:
 *         stylesheets:
 *             - styles/brand.css            # what the theme passes to asset()
 *             - https://cdn.example.com/fonts.css
 *         html_class: brand
 *         body_class: page
 *         wrapper_class: page__content rich-text
 *
 * Every key is optional. A child theme inherits each key its parents declare
 * and it does not: the nearest declaration of a key wins.
 */
final readonly class ThemeCanvas
{
    public const string FILE_NAME = 'config/theliacms.yaml';

    private const array CLASS_KEYS = ['html_class', 'body_class', 'wrapper_class'];

    /**
     * @param list<string>|null $stylesheets null when no theme of the chain declares any
     */
    public function __construct(
        public ?array $stylesheets = null,
        public string $htmlClass = '',
        public string $bodyClass = '',
        public string $wrapperClass = '',
    ) {
    }

    /**
     * Reads the declarations of a theme and of its parents.
     *
     * @param list<string> $themeDirectories absolute paths, the active theme first, then its parents
     *
     * @throws \InvalidArgumentException when a declaration is there but cannot be read
     */
    public static function declaredIn(array $themeDirectories): self
    {
        $declared = [];

        foreach ($themeDirectories as $directory) {
            $declared += self::readFrom($directory);
        }

        return new self(
            $declared['stylesheets'] ?? null,
            $declared['html_class'] ?? '',
            $declared['body_class'] ?? '',
            $declared['wrapper_class'] ?? '',
        );
    }

    public function declaresStylesheets(): bool
    {
        return null !== $this->stylesheets;
    }

    /**
     * The declared stylesheets as the browser requests them.
     *
     * Resolved the way `asset()` resolves them in the templates of the theme,
     * at every call: a logical path of the asset mapper becomes the name the
     * last build gave it, which changes with each build, and a URL is kept as
     * it is. Written once in the declaration, the canvas and the front cannot
     * drift apart.
     *
     * @return list<string>
     */
    public function stylesheetUrls(Packages $packages): array
    {
        return array_map(
            static fn (string $stylesheet): string => $packages->getUrl($stylesheet),
            $this->stylesheets ?? [],
        );
    }

    /**
     * The document the canvas starts from, when the theme dresses its root.
     *
     * GrapesJS writes this into the iframe before rendering the page into its
     * `<body>`, so the classes are there before the first stylesheet applies.
     * Null keeps the document GrapesJS writes by default.
     */
    public function canvasDocument(): ?string
    {
        if ('' === $this->htmlClass && '' === $this->bodyClass) {
            return null;
        }

        return \sprintf(
            '<!DOCTYPE html><html%s><head></head><body%s></body></html>',
            self::classAttribute($this->htmlClass),
            self::classAttribute($this->bodyClass),
        );
    }

    /**
     * Classes the canvas puts on the element holding the page, next to
     * `cms-page-content`.
     *
     * @return list<string>
     */
    public function wrapperClasses(): array
    {
        return self::classList($this->wrapperClass);
    }

    /**
     * @return array{stylesheets?: list<string>, html_class?: string, body_class?: string, wrapper_class?: string}
     */
    private static function readFrom(string $themeDirectory): array
    {
        $file = rtrim($themeDirectory, '/\\').\DIRECTORY_SEPARATOR.self::FILE_NAME;

        if (!is_file($file)) {
            return [];
        }

        try {
            $content = Yaml::parseFile($file);
        } catch (ParseException $exception) {
            throw new \InvalidArgumentException(\sprintf('%s is not a valid YAML file: %s', $file, $exception->getMessage()), 0, $exception);
        }

        if (!\is_array($content) || !\array_key_exists('canvas', $content)) {
            return [];
        }

        $canvas = $content['canvas'];

        if (!\is_array($canvas)) {
            throw new \InvalidArgumentException(\sprintf('The "canvas" key of %s must hold the keys stylesheets, html_class, body_class or wrapper_class.', $file));
        }

        $unknown = array_diff(array_keys($canvas), ['stylesheets', ...self::CLASS_KEYS]);

        if ([] !== $unknown) {
            throw new \InvalidArgumentException(\sprintf('The "canvas" key of %s does not know "%s". Known keys: stylesheets, html_class, body_class, wrapper_class.', $file, implode('", "', $unknown)));
        }

        $declared = [];

        if (\array_key_exists('stylesheets', $canvas)) {
            $declared['stylesheets'] = self::stylesheetList($canvas['stylesheets'], $file);
        }

        foreach (self::CLASS_KEYS as $key) {
            if (!\array_key_exists($key, $canvas)) {
                continue;
            }

            if (!\is_string($canvas[$key])) {
                throw new \InvalidArgumentException(\sprintf('The "canvas.%s" key of %s must be a string of classes separated by spaces.', $key, $file));
            }

            $declared[$key] = implode(' ', self::classList($canvas[$key]));
        }

        return $declared;
    }

    /**
     * @return list<string>
     */
    private static function stylesheetList(mixed $stylesheets, string $file): array
    {
        if (!\is_array($stylesheets) || !array_is_list($stylesheets)) {
            throw new \InvalidArgumentException(\sprintf('The "canvas.stylesheets" key of %s must be a list.', $file));
        }

        $list = [];

        foreach ($stylesheets as $stylesheet) {
            if (!\is_string($stylesheet) || '' === trim($stylesheet)) {
                throw new \InvalidArgumentException(\sprintf('Every entry of "canvas.stylesheets" in %s must be an asset path or a URL.', $file));
            }

            $list[] = trim($stylesheet);
        }

        return $list;
    }

    /**
     * @return list<string>
     */
    private static function classList(string $classes): array
    {
        return array_values(array_unique(preg_split('/\s+/', $classes, -1, \PREG_SPLIT_NO_EMPTY) ?: []));
    }

    private static function classAttribute(string $classes): string
    {
        return '' === $classes ? '' : ' class="'.htmlspecialchars($classes, \ENT_QUOTES | \ENT_HTML5).'"';
    }
}
