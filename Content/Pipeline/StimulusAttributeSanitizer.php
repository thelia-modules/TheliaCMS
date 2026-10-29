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
 * Keeps, in `data-controller` and `data-action`, the controllers the theme
 * names and nothing else: a component of the theme keeps its behaviour, and
 * written text that names another controller loses the attribute.
 */
final readonly class StimulusAttributeSanitizer implements AttributeSanitizerInterface
{
    /**
     * An action reads `[event->]identifier#method[:option…]`.
     */
    private const string ACTION = '/^(?:\S+->)?([A-Za-z0-9-]+)#[A-Za-z0-9_$]+(?::!?[A-Za-z]+)*$/';

    /**
     * @param list<string> $controllers the identifiers the theme names
     */
    public function __construct(
        private array $controllers,
    ) {
    }

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): array
    {
        return ['data-controller', 'data-action'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $kept = array_filter(
            preg_split('/\s+/', trim($value), -1, \PREG_SPLIT_NO_EMPTY) ?: [],
            fn (string $token): bool => \in_array('data-controller' === $attribute ? $token : $this->controllerOf($token), $this->controllers, true),
        );

        return [] === $kept ? null : implode(' ', $kept);
    }

    private function controllerOf(string $action): ?string
    {
        return 1 === preg_match(self::ACTION, $action, $match) ? $match[1] : null;
    }
}
