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

namespace TheliaCMS\Content\Pipeline;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Holds the paint and the namespace of an icon to what draws one: a colour,
 * `none` or `currentColor`, never a `url(…)` that would load a gradient or a
 * filter from another host, and the one SVG namespace.
 */
final readonly class SvgAttributeSanitizer implements AttributeSanitizerInterface
{
    public const string NAMESPACE = 'http://www.w3.org/2000/svg';

    private const string PAINT = '/^(?:[a-z]+|#[0-9a-f]{3,8}|(?:rgb|rgba|hsl|hsla)\(\s*[0-9.,%\s\/]+\))$/i';

    private const string TRANSFORM = '/^(?:(?:matrix|translate|scale|rotate|skewX|skewY)\([0-9eE.,+\-\s]*\)\s*,?\s*)+$/';

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): array
    {
        return ['fill', 'stroke', 'transform', 'xmlns'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $value = trim($value);

        return match ($attribute) {
            'xmlns' => self::NAMESPACE === $value ? $value : null,
            'transform' => 1 === preg_match(self::TRANSFORM, $value) ? $value : null,
            default => 1 === preg_match(self::PAINT, $value) ? $value : null,
        };
    }
}
