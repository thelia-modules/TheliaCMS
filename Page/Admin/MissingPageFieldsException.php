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

namespace TheliaCMS\Page\Admin;

/**
 * A page left without a value its type requires: online, it would be the one
 * recipe a list sorted by cooking time cannot place.
 */
final class MissingPageFieldsException extends \RuntimeException
{
    /**
     * @param list<string> $labels the labels of the fields left empty
     */
    private function __construct(
        string $message,
        public readonly array $labels,
    ) {
        parent::__construct($message);
    }

    /**
     * @param list<string> $labels
     */
    public static function for(int $pageId, string $locale, array $labels): self
    {
        return new self(\sprintf('CMS page #%d leaves required fields empty in %s: %s.', $pageId, $locale, implode(', ', $labels)), $labels);
    }
}
