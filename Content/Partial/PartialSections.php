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

namespace TheliaCMS\Content\Partial;

use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Content\SectionProviderInterface;
use TheliaCMS\Partial\PartialRegistry;

/**
 * Every partial of the site, as a section: the ones of the module and the
 * ones other modules register.
 */
final readonly class PartialSections implements SectionProviderInterface
{
    public function __construct(
        private PartialRegistry $partials,
        private TranslatorInterface $translator,
    ) {
    }

    public function sections(): iterable
    {
        foreach ($this->partials->all() as $partial) {
            yield new PartialSection($partial, $this->translator);
        }
    }
}
